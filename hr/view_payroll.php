<?php
require 'db_con.php';

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
list($year, $monthNum) = explode('-', $selectedMonth);
$monthName = date('F Y', strtotime($selectedMonth . '-01'));

// Convert numeric month to full month name like "October"
$monthText = date('F', mktime(0, 0, 0, $monthNum, 10));

// Fetch payroll record

$stmt = $conn->prepare("
    SELECT
        pr.*,
        u.email,
        u.national_id,
        u.status,
        es.bank_name,
        es.bank_account
    FROM payroll_records pr

    INNER JOIN users u
        ON pr.user_id = u.user_id

    LEFT JOIN employee_salary es
        ON pr.user_id = es.user_id

    WHERE pr.user_id = ?
      AND pr.month = ?
      AND pr.year = ?

    LIMIT 1
");

$stmt->bind_param("isi", $user_id, $monthText, $year);
$stmt->execute();
$result = $stmt->get_result();
$payroll = $result->fetch_assoc();

if (!$payroll) {
    die("<div style='margin:100px;text-align:center;color:red;font-weight:bold;'>
        No payroll record found for this user in $monthName.
    </div>");
}

// Decode manual allowances & deductions JSON
$details = json_decode($payroll['details'], true);
$manualAllowances = $details['allowances'] ?? [];
$manualDeductions = $details['deductions'] ?? [];

// Audit values are taken from the saved payroll snapshot, not recalculated.
function auditValue($source, $paths) {
    foreach ($paths as $path) {
        $value = $source;
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                $value = null;
                break;
            }
            $value = $value[$part];
        }
        if (is_numeric($value) && is_scalar($value)) return (float)$value;
    }
    return null;
}
function auditMoney($value) {
    return $value === null ? 'Not recorded' : 'Ksh ' . number_format($value, 2);
}
function auditEsc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$details = is_array($details) ? $details : [];
$grossPay = (float)$payroll['base_salary'] + (float)$payroll['total_allowances'];
// The following fields may vary across calculator versions. Add the exact
// saved JSON key to these lists if your calculator uses another name.
// In the original calculator calculation.taxable_pay is already AFTER pre-tax deductions.
// Gross taxable earnings are the saved gross pay under the calculator's
// documented assumption that all cash allowances are taxable.
$taxablePay = auditValue($details, [
    'audit.taxable_pay', 'audit.gross_taxable_pay',
    'calculation.gross_taxable_pay', 'tax.gross_taxable_pay', 'gross_taxable_pay'
]);
if ($taxablePay === null && (($details['calculation']['rule_set'] ?? '') === 'KE_STANDARD_2026_02_PENSION')) {
    $taxablePay = $grossPay;
}
$netTaxable = auditValue($details, [
    'audit.net_taxable_pay', 'calculation.taxable_pay', 'calculation.net_taxable', 'calculation.net_taxable_pay',
    'calculation.taxable_income', 'calculation.paye_taxable_pay',
    'tax.net_taxable', 'tax.taxable_income', 'net_taxable',
    'net_taxable_pay', 'taxable_income'
]);
$taxCharged = auditValue($details, [
    'audit.tax_charged', 'calculation.paye_before_relief', 'calculation.tax_charged', 'calculation.gross_paye',
    'calculation.tax_before_relief', 'tax.tax_charged',
    'tax.gross_paye', 'tax_charged', 'gross_paye', 'tax_before_relief'
]);
$taxRelief = auditValue($details, [
    'audit.tax_relief', 'calculation.tax_relief', 'calculation.personal_relief',
    'calculation.relief_applied', 'tax.tax_relief',
    'tax.personal_relief', 'tax_relief', 'personal_relief', 'relief_applied'
]);
$taxDeducted = auditValue($details, [
    'audit.paye_deducted', 'calculation.tax_deducted', 'calculation.paye',
    'calculation.paye_deducted', 'tax.tax_deducted',
    'tax.paye', 'tax_deducted', 'paye', 'paye_deducted'
]);
// Use saved line items as a fallback for PAYE only, never as a tax calculation.
if ($taxDeducted === null && is_array($manualDeductions)) {
    foreach ($manualDeductions as $line) {
        if (!is_array($line)) continue;
        $lineName = strtoupper(trim((string)($line['name'] ?? $line['code'] ?? '')));
        if ($lineName === 'PAYE' || $lineName === 'INCOME TAX (PAYE)') {
            $v = $line['calculated_amount'] ?? $line['amount'] ?? null;
            if (is_numeric($v)) { $taxDeducted = (float)$v; break; }
        }
    }
}
// The legacy personal_relief field represents entitlement, not necessarily used relief.
// Derive applied relief only from saved pre-relief tax and saved PAYE.
if ($taxCharged !== null && $taxDeducted !== null) {
    $taxRelief = max(0.0, round($taxCharged - $taxDeducted, 2));
}
$auditFields = [
    ['Taxable Pay', $taxablePay, 'Gross earnings treated as taxable in the saved calculation'],
    ['Net Taxable Pay', $netTaxable, 'Taxable income after qualifying pre-tax deductions'],
    ['Tax Charged', $taxCharged, 'PAYE calculated before tax relief'],
    ['Tax Relief', $taxRelief, 'Tax relief actually applied in the saved calculation'],
    ['Tax Deducted (PAYE)', $taxDeducted, 'Final PAYE withheld after relief'],
    ['Net Pay', (float)$payroll['net_pay'], 'Saved employee take-home pay'],
];
// Explicitly show the deductions bridging gross taxable pay and net taxable pay.
$preTaxLines = [];
foreach ($manualDeductions as $line) {
    if (!is_array($line)) continue;
    $name = strtolower(trim((string)($line['name'] ?? '')));
    $amount = $line['amount'] ?? null;
    if (!is_numeric($amount)) continue;
    if ($name === 'nssf' || $name === 'shif' || $name === 'affordable housing levy' || $name === 'ahl' || $name === 'housing levy') {
        $preTaxLines[] = ['name' => $line['name'], 'amount' => (float)$amount];
    } elseif (strpos($name, 'employee pension') !== false && !empty($details['pension']['tax_qualified'])) {
        $preTaxLines[] = ['name' => 'Qualifying employee pension', 'amount' => (float)$amount];
    }
}
$preTaxTotal = array_sum(array_column($preTaxLines, 'amount'));
$preTaxReconciles = $taxablePay !== null && $netTaxable !== null && abs(($taxablePay - $preTaxTotal) - $netTaxable) <= 0.02;
$hasMissingAudit = $taxablePay === null || $netTaxable === null || $taxCharged === null || $taxRelief === null || $taxDeducted === null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Payroll - <?= htmlspecialchars($payroll['full_name']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-4xl mx-auto bg-white shadow-lg rounded-lg">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">
        Payroll Details - <?= htmlspecialchars($payroll['full_name']) ?> (<?= $monthName ?>)
    </h1>

    
<!-- Employee and Payroll Information -->
<div class="bg-gray-50 rounded-lg p-5 mb-6">

    <h2 class="text-lg font-semibold text-gray-700 mb-4">
        Employee & Payroll Information
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Left Column -->
        <div class="space-y-3">

            <p>
                <strong>Email:</strong>
                <?= htmlspecialchars($payroll['email'] ?? '-') ?>
            </p>

            <p>
                <strong>National ID:</strong>
                <?= htmlspecialchars($payroll['national_id'] ?? '-') ?>
            </p>

            <p>
                <strong>Payroll No:</strong>
                <?= htmlspecialchars($payroll['payroll_no'] ?: '-') ?>
            </p>

        </div>

        <!-- Right Column -->
        <div class="space-y-3">

            <p>
                <strong>Bank Name:</strong>
                <?= htmlspecialchars($payroll['bank_name'] ?: '-') ?>
            </p>

            <p>
                <strong>Bank Account Number:</strong>
                <?= htmlspecialchars($payroll['bank_account'] ?: '-') ?>
            </p>

            <p>
                <strong>Employee Status:</strong>

                <?php
                    $status = $payroll['status'] ?? 'inactive';
                ?>

                <span class="px-2 py-1 rounded-full text-sm font-medium
                    <?= $status === 'active'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-red-100 text-red-700' ?>">

                    <?= htmlspecialchars($status) ?>

                </span>
            </p>

        </div>

    </div>

</div>


    <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <h2 class="text-lg font-semibold mb-2 text-gray-700">Summary</h2>
        <p><strong>Base Salary:</strong> Ksh <?= number_format($payroll['base_salary'], 2) ?></p>
        <p><strong>Total Allowances:</strong> Ksh <?= number_format($payroll['total_allowances'], 2) ?></p>
        <p><strong>Total Deductions:</strong> Ksh <?= number_format($payroll['total_deductions'], 2) ?></p>
        <hr class="my-2">
        <p class="text-xl font-bold text-blue-700">Net Pay: Ksh <?= number_format($payroll['net_pay'], 2) ?></p>
    </div>

    <!-- Payroll Tax & Audit Summary: historical saved values only -->
    <section class="bg-white border border-gray-200 rounded-lg shadow-sm mb-6 overflow-hidden">
        <div class="bg-gray-800 text-white px-5 py-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold">Payroll Tax &amp; Audit Summary</h2>
                <p class="text-xs text-gray-300">Saved payroll snapshot | <?= auditEsc($monthName) ?> | Payroll #<?= auditEsc($payroll['payroll_no'] ?? '-') ?></p>
            </div>
            <span class="text-xs bg-gray-700 rounded px-3 py-1">KES</span>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
                <div class="rounded-lg border bg-gray-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Basic Salary</p>
                    <p class="font-bold text-gray-800 mt-1">Ksh <?= number_format((float)$payroll['base_salary'], 2) ?></p>
                </div>
                <div class="rounded-lg border bg-gray-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Allowances</p>
                    <p class="font-bold text-gray-800 mt-1">Ksh <?= number_format((float)$payroll['total_allowances'], 2) ?></p>
                </div>
                <div class="rounded-lg border bg-blue-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-blue-700">Gross Pay</p>
                    <p class="font-bold text-blue-900 mt-1">Ksh <?= number_format($grossPay, 2) ?></p>
                </div>
            </div>
            <?php if ($preTaxLines && $taxablePay !== null): ?>
            <div class="mb-5 border border-gray-200 rounded-lg overflow-hidden">
                <h3 class="bg-gray-100 px-4 py-3 font-semibold text-gray-800">Qualifying Pre-Tax Deductions</h3>
                <div class="px-4 py-3 text-sm flex justify-between"><span>Gross taxable earnings</span><strong><?= auditEsc(auditMoney($taxablePay)) ?></strong></div>
                <?php foreach ($preTaxLines as $line): ?>
                <div class="px-4 py-2 text-sm flex justify-between border-t"><span>Less: <?= auditEsc($line['name']) ?></span><span>(<?= auditEsc(auditMoney($line['amount'])) ?>)</span></div>
                <?php endforeach; ?>
                <div class="px-4 py-3 text-sm flex justify-between border-t bg-blue-50"><strong>Net taxable pay</strong><strong><?= auditEsc(auditMoney($netTaxable)) ?></strong></div>
                <?php if (!$preTaxReconciles): ?>
                <p class="px-4 py-2 text-xs text-yellow-800 bg-yellow-50">Pre-tax deduction lines do not fully reconcile to saved taxable income. The saved calculation remains authoritative; check pension qualification and deduction caps.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead><tr class="bg-gray-100 text-gray-700">
                        <th class="text-left px-4 py-3">Audit Component</th>
                        <th class="text-right px-4 py-3">Amount (KES)</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($auditFields as $field): ?>
                        <tr class="border-b <?= $field[0] === 'Net Pay' ? 'bg-blue-50' : '' ?>">
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-800"><?= auditEsc($field[0]) ?></span>
                                <p class="text-xs text-gray-500 mt-1"><?= auditEsc($field[2]) ?></p>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap font-semibold <?= $field[1] === null ? 'text-yellow-700' : 'text-gray-900' ?>">
                                <?= auditEsc(auditMoney($field[1])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 text-sm text-gray-700 border-t pt-3 flex flex-wrap justify-between gap-2">
                <span><strong>Total Payroll Deductions:</strong> Ksh <?= number_format((float)$payroll['total_deductions'], 2) ?></span>
                <span><strong>Gross less deductions:</strong> Ksh <?= number_format($grossPay - (float)$payroll['total_deductions'], 2) ?></span>
            </div>
            <?php if (abs(($grossPay - (float)$payroll['total_deductions']) - (float)$payroll['net_pay']) > 0.02): ?>
                <p class="mt-3 rounded bg-red-50 border border-red-200 text-red-800 text-sm p-3">Audit exception: gross pay minus total deductions does not match saved net pay. Review the payroll snapshot.</p>
            <?php endif; ?>
            <?php if ($hasMissingAudit): ?>
                <p class="mt-3 rounded bg-yellow-50 border border-yellow-200 text-yellow-900 text-sm p-3">Some tax audit fields are unavailable in this saved payroll record. They are shown as “Not recorded” rather than estimated. To complete the audit trail, save those figures in the payroll calculator's details JSON when processing future payrolls.</p>
            <?php endif; ?>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Allowances -->
        <div class="bg-green-50 p-4 rounded-lg">
            <h2 class="text-lg font-semibold mb-2 text-green-700">Allowances Breakdown</h2>
            <?php if (!empty($manualAllowances)) : ?>
                <table class="min-w-full table-auto text-sm">
                    <thead class="bg-green-200">
                        <tr>
                            <th class="px-3 py-2 text-left">Allowance</th>
                            <th class="px-3 py-2 text-right">Amount (Ksh)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manualAllowances as $a): ?>
                            <tr class="border-b">
                                <td class="px-3 py-2"><?= htmlspecialchars($a['name']) ?></td>
                                <td class="px-3 py-2 text-right">
    <?= number_format(floatval(preg_replace('/[^0-9.\-]/', '', $a['amount'])), 2) ?>
</td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-500 italic">No manual allowances added.</p>
            <?php endif; ?>
        </div>

        <!-- Deductions -->
        <!-- Deductions -->
<div class="bg-red-50 p-4 rounded-lg">
    <h2 class="text-lg font-semibold mb-2 text-red-700">Deductions Breakdown</h2>
    <?php if (!empty($manualDeductions)) : ?>
        <table class="min-w-full table-auto text-sm">
            <thead class="bg-red-200">
                <tr>
                    <th class="px-3 py-2 text-left">Deduction</th>
                    <th class="px-3 py-2 text-right">Rate</th>
                    <th class="px-3 py-2 text-right">Amount (Ksh)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($manualDeductions as $d):
                    if (!is_array($d)) continue;
                    $basis = (string)($d['basis'] ?? '');
                    $rateDisplay = ($basis === 'percent' && isset($d['value'])) ? number_format((float)$d['value'], 2) . '%' : (isset($d['rate']) && is_numeric($d['rate']) ? number_format((float)$d['rate'], 2) . '%' : '-');
                    $deductionAmount = is_numeric($d['amount'] ?? null) ? (float)$d['amount'] : 0.0;
                ?>
                    <tr class="border-b">
                        <td class="px-3 py-2"><?= htmlspecialchars($d['name']) ?></td>
                        <td class="px-3 py-2 text-right"><?= htmlspecialchars($rateDisplay) ?></td>
                        <td class="px-3 py-2 text-right"><?= number_format($deductionAmount, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-gray-500 italic">No manual deductions added.</p>
    <?php endif; ?>
</div>

    </div>
<!-- Payroll Workflow Details -->
<div class="bg-blue-50 rounded-lg p-4 mb-6">
    <h2 class="text-lg font-semibold mb-4 text-blue-800">
        Payroll Workflow Details
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <!-- Prepared By -->
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <h3 class="font-semibold text-gray-700 mb-3">
                Prepared By
            </h3>

            <p class="mb-2">
                <strong>Name:</strong>
                <?= htmlspecialchars($payroll['prepared_by'] ?? 'Not recorded') ?>
            </p>

            <p class="mb-2">
                <strong>Date:</strong>
                <?= !empty($payroll['date_prepared'])
                    ? htmlspecialchars($payroll['date_prepared'])
                    : 'Not recorded' ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?php $prepStatus = $payroll['preparation_status'] ?? 'Pending'; ?>
                <span class="inline-block px-2 py-1 rounded-full text-sm
                    <?= $prepStatus === 'Prepared'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700' ?>">
                    <?= htmlspecialchars($prepStatus) ?>
                </span>
            </p>
        </div>

        <!-- Approved By -->
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <h3 class="font-semibold text-gray-700 mb-3">
                Approved By
            </h3>

            <p class="mb-2">
                <strong>Name:</strong>
                <?= htmlspecialchars($payroll['approved_by'] ?? 'Not recorded') ?>
            </p>

            <p class="mb-2">
                <strong>Date:</strong>
                <?= !empty($payroll['approval_date'])
                    ? htmlspecialchars($payroll['approval_date'])
                    : 'Not recorded' ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?php $approvalStatus = $payroll['approval_status'] ?? 'Pending'; ?>
                <span class="inline-block px-2 py-1 rounded-full text-sm
                    <?= $approvalStatus === 'Approved'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700' ?>">
                    <?= htmlspecialchars($approvalStatus) ?>
                </span>
            </p>
        </div>

        <!-- Paid By -->
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <h3 class="font-semibold text-gray-700 mb-3">
                Paid By
            </h3>

            <p class="mb-2">
                <strong>Name:</strong>
                <?= htmlspecialchars($payroll['accountant_name'] ?? 'Not recorded') ?>
            </p>

            <p class="mb-2">
                <strong>Payment Date:</strong>
                <?= !empty($payroll['payment_date'])
                    ? htmlspecialchars($payroll['payment_date'])
                    : 'Not recorded' ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?php $paymentStatus = $payroll['payment_status'] ?? 'Pending Pay'; ?>
                <span class="inline-block px-2 py-1 rounded-full text-sm
                    <?= $paymentStatus === 'Paid'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700' ?>">
                    <?= htmlspecialchars($paymentStatus) ?>
                </span>
            </p>
        </div>

    </div>
</div>
    <div class="mt-6 text-right">
        <a href="payroll_list.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-lg">
            ← Back to Payroll List
        </a>
    </div>
    
   <a href="download_payroll_pdf.php?id=<?= $user_id ?>&month=<?= urlencode($selectedMonth) ?>"
   onclick="return openPayrollPDF(this.href);"
   class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
    📄 Download PDF
</a>

<script>
function openPayrollPDF(url) {
    const popup = window.open(
        url,
        'payrollPDF',
        'width=900,height=700,resizable=yes,scrollbars=yes'
    );

    if (!popup) {
        alert('Your browser blocked the popup. Please allow popups for this site.');
        return false;
    }

    return false;
}
</script>

</div>

</body>
</html>
