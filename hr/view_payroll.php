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
/*
|--------------------------------------------------------------------------
| PENSION CONTRIBUTION SUMMARY
|--------------------------------------------------------------------------
| Employee contribution is already included in payroll deductions.
| Employer contribution is displayed separately and does not affect net pay.
*/

$pensionDetails = $details['pension'] ?? [];

$pensionEnabled = !empty($pensionDetails['enabled']);

$employeePension = $pensionEnabled
    ? (float)($pensionDetails['employee_amount'] ?? 0)
    : 0;

// Employer contributes 6% of the same gross pay when employee is enrolled.
$employerPensionRate = 6;

$employerPension = $pensionEnabled
    ? round($grossPay * ($employerPensionRate / 100), 2)
    : 0;

$totalPensionContribution = $employeePension + $employerPension;
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
        <!-- Pension Contribution Summary -->
<section class="bg-white border border-gray-200 rounded-lg shadow-sm mb-6 overflow-hidden">

    <div class="bg-indigo-800 text-white px-5 py-4">
        <h2 class="text-lg font-semibold">
            <i class="fas fa-piggy-bank mr-2"></i>
            Pension Contribution Summary
        </h2>

        <p class="text-sm text-indigo-100">
            Employee and employer pension contributions for <?= htmlspecialchars($monthName) ?>
        </p>
    </div>

    <div class="p-5">

        <?php if ($pensionEnabled): ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <!-- Employee Contribution -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">

                    <p class="text-sm text-blue-700 font-semibold">
                        Employee Contribution (6%)
                    </p>

                    <p class="text-2xl font-bold text-blue-900 mt-2">
                        KSh <?= number_format($employeePension, 2) ?>
                    </p>

                    <p class="text-xs text-blue-600 mt-2">
                        Deducted from employee gross pay
                    </p>

                </div>

                <!-- Employer Contribution -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">

                    <p class="text-sm text-green-700 font-semibold">
                        Employer Contribution (6%)
                    </p>

                    <p class="text-2xl font-bold text-green-900 mt-2">
                        KSh <?= number_format($employerPension, 2) ?>
                    </p>

                    <p class="text-xs text-green-600 mt-2">
                        Additional employer-funded contribution
                    </p>

                </div>

                <!-- Combined Contribution -->
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">

                    <p class="text-sm text-indigo-700 font-semibold">
                        Total Pension Contribution (12%)
                    </p>

                    <p class="text-2xl font-bold text-indigo-900 mt-2">
                        KSh <?= number_format($totalPensionContribution, 2) ?>
                    </p>

                    <p class="text-xs text-indigo-600 mt-2">
                        Combined employee and employer contributions
                    </p>

                </div>

            </div>

            <div class="mt-4 bg-gray-50 border rounded-lg p-4">

                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-600">Pensionable gross pay basis</span>
                    <strong>KSh <?= number_format($grossPay, 2) ?></strong>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Employer contribution rate</span>
                    <strong>6%</strong>
                </div>

            </div>

            <p class="text-xs text-gray-500 mt-3">
                Employer contribution is an additional company expense.
                It is not deducted from employee salary and does not reduce net pay.
            </p>

        <?php else: ?>

            <div class="bg-gray-50 border rounded-lg p-5 text-center">

                <i class="fas fa-info-circle text-gray-400 text-2xl"></i>

                <p class="font-semibold text-gray-700 mt-2">
                    Employee is not enrolled in the pension scheme.
                </p>

                <p class="text-sm text-gray-500 mt-1">
                    No employee or employer pension contribution has been included.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>
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
           <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">

    
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
    
<?php
/*
|--------------------------------------------------------------------------
| SACCO TRACKING FOR SELECTED PAYROLL MONTH
|--------------------------------------------------------------------------
| This is display-only. It does not modify payroll deductions or net pay.
*/

$saccoStart = $year . '-' . $monthNum . '-01';
$saccoEnd = date('Y-m-t', strtotime($saccoStart));

$saccoMemberships = [];
$saccoError = '';

try {
    // Find every SACCO membership belonging to this payroll user.
    $saccoStmt = $conn->prepare("
        SELECT
            m.sacco_member_id,
            m.sacco_account_no,
            m.membership_date,
            m.status AS membership_status,
            s.sacco_name
        FROM hr_sacco_members m
        INNER JOIN hr_saccos s
            ON s.sacco_id = m.sacco_id
        WHERE m.user_id = ?
        ORDER BY s.sacco_name
    ");

    $saccoStmt->bind_param("i", $user_id);
    $saccoStmt->execute();
    $saccoMemberships = $saccoStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $saccoStmt->close();

    foreach ($saccoMemberships as &$membership) {

        $memberId = (int)$membership['sacco_member_id'];

        /*
         * Opening balance:
         * All contribution-ledger activity before the selected month.
         *
         * Contributions are grouped using contribution_month where
         * available, otherwise transaction_date.
         */
        $openingStmt = $conn->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN transaction_type IN (
                        'opening_balance',
                        'contribution',
                        'adjustment_credit'
                    ) THEN amount
                    ELSE -amount
                END
            ), 0) AS opening_balance
            FROM hr_sacco_transactions
            WHERE sacco_member_id = ?
              AND (
                    CASE
                        WHEN transaction_type = 'contribution'
                             AND contribution_month IS NOT NULL
                        THEN DATE_FORMAT(contribution_month, '%Y-%m-01')
                        ELSE DATE_FORMAT(transaction_date, '%Y-%m-01')
                    END
                  ) < ?
        ");

        $openingStmt->bind_param("is", $memberId, $saccoStart);
        $openingStmt->execute();

        $openingBalance = (float)$openingStmt
            ->get_result()->fetch_assoc()['opening_balance'];

        $openingStmt->close();

        // Contributions recorded for the selected month.
        $contributionStmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS monthly_contribution
            FROM hr_sacco_transactions
            WHERE sacco_member_id = ?
              AND transaction_type = 'contribution'
              AND (
                    (
                        contribution_month IS NOT NULL
                        AND contribution_month = ?
                    )
                    OR
                    (
                        contribution_month IS NULL
                        AND transaction_date BETWEEN ? AND ?
                    )
                  )
        ");

        $contributionStmt->bind_param(
            "isss",
            $memberId,
            $saccoStart,
            $saccoStart,
            $saccoEnd
        );

        $contributionStmt->execute();

        $monthlyContribution = (float)$contributionStmt
            ->get_result()->fetch_assoc()['monthly_contribution'];

        $contributionStmt->close();

        $membership['opening_balance'] = $openingBalance;
        $membership['monthly_contribution'] = $monthlyContribution;
        $membership['balance_after_contribution'] =
            $openingBalance + $monthlyContribution;

        /*
         * Actual ledger balance at the end of the selected month.
         * This accounts for withdrawals and adjustments as well.
         */
        $closingStmt = $conn->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN transaction_type IN (
                        'opening_balance',
                        'contribution',
                        'adjustment_credit'
                    ) THEN amount
                    ELSE -amount
                END
            ), 0) AS closing_balance
            FROM hr_sacco_transactions
            WHERE sacco_member_id = ?
              AND (
                    CASE
                        WHEN transaction_type = 'contribution'
                             AND contribution_month IS NOT NULL
                        THEN DATE_FORMAT(contribution_month, '%Y-%m-01')
                        ELSE DATE_FORMAT(transaction_date, '%Y-%m-01')
                    END
                  ) <= ?
        ");

        $closingStmt->bind_param("is", $memberId, $saccoStart);
        $closingStmt->execute();

        $membership['closing_balance'] = (float)$closingStmt
            ->get_result()->fetch_assoc()['closing_balance'];

        $closingStmt->close();

        // Fetch loans issued by the end of the selected payroll month.
        $loanStmt = $conn->prepare("
            SELECT
                l.loan_id,
                l.loan_reference,
                l.principal_amount,
                l.interest_amount,
                l.issue_date,

                COALESCE((
                    SELECT SUM(r1.amount)
                    FROM hr_sacco_loan_repayments r1
                    WHERE r1.loan_id = l.loan_id
                      AND r1.repayment_date BETWEEN ? AND ?
                ), 0) AS paid_this_month,

                COALESCE((
                    SELECT SUM(r2.amount)
                    FROM hr_sacco_loan_repayments r2
                    WHERE r2.loan_id = l.loan_id
                      AND r2.repayment_date <= ?
                ), 0) AS total_repaid_to_month_end

            FROM hr_sacco_loans l
            WHERE l.sacco_member_id = ?
              AND l.issue_date <= ?
            ORDER BY l.issue_date, l.loan_id
        ");

        $loanStmt->bind_param(
            "sssis",
            $saccoStart,
            $saccoEnd,
            $saccoEnd,
            $memberId,
            $saccoEnd
        );

        $loanStmt->execute();
        $membership['loans'] = $loanStmt
            ->get_result()->fetch_all(MYSQLI_ASSOC);

        $loanStmt->close();

        $membership['total_loan_amount'] = 0;
        $membership['total_loan_paid_month'] = 0;
        $membership['total_loan_pending'] = 0;

        foreach ($membership['loans'] as &$loan) {

            $loanAmount =
                (float)$loan['principal_amount'] +
                (float)$loan['interest_amount'];

            $paidThisMonth = (float)$loan['paid_this_month'];

            $pendingBalance = max(
                0,
                $loanAmount - (float)$loan['total_repaid_to_month_end']
            );

            $loan['total_loan_amount'] = $loanAmount;
            $loan['pending_balance'] = $pendingBalance;

            $membership['total_loan_amount'] += $loanAmount;
            $membership['total_loan_paid_month'] += $paidThisMonth;
            $membership['total_loan_pending'] += $pendingBalance;
        }

        unset($loan);
    }

    unset($membership);

} catch (Throwable $e) {
    $saccoError = $e->getMessage();
}

?>

<?php if (!empty($saccoMemberships)): ?>

<section class="mt-6 mb-6 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">

    <div class="bg-indigo-800 text-white px-5 py-4">
        <h2 class="text-lg font-bold">
            <i class="fas fa-piggy-bank mr-2"></i>
            SACCO Contribution & Loan Tracking
        </h2>

        <p class="text-sm text-indigo-100">
            Member financial position for <?= htmlspecialchars($monthName) ?>
            | Independent of payroll deductions
        </p>
    </div>

    <div class="p-5">

        <?php if ($saccoError): ?>

            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">
                Unable to load SACCO information:
                <?= htmlspecialchars($saccoError) ?>
            </div>

        <?php else: ?>

            <?php foreach ($saccoMemberships as $membership): ?>

                <div class="border rounded-lg mb-6 overflow-hidden">

                    <div class="bg-gray-100 px-4 py-3 flex flex-wrap justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-gray-800">
                                <?= htmlspecialchars($membership['sacco_name']) ?>
                            </h3>

                            <p class="text-xs text-gray-500">
                                Account:
                                <?= htmlspecialchars(
                                    $membership['sacco_account_no'] ?: 'Not provided'
                                ) ?>
                            </p>
                        </div>

                        <span class="text-xs px-3 py-1 rounded-full bg-indigo-100 text-indigo-700 font-semibold">
                            <?= htmlspecialchars($membership['membership_status']) ?>
                            Member
                        </span>
                    </div>

                    <!-- CONTRIBUTION TRACKING -->
                    <div class="p-4">

                        <h4 class="font-semibold text-green-700 mb-3">
                            <i class="fas fa-wallet mr-1"></i>
                            Contribution Tracking
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

                            <div class="bg-gray-50 border rounded-lg p-4">
                                <p class="text-xs text-gray-500 uppercase">
                                    Opening Balance
                                </p>

                                <p class="text-xl font-bold text-gray-800 mt-1">
                                    KSh <?= number_format(
                                        $membership['opening_balance'], 2
                                    ) ?>
                                </p>
                            </div>

                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <p class="text-xs text-green-700 uppercase">
                                    Contribution - <?= htmlspecialchars($monthName) ?>
                                </p>

                                <p class="text-xl font-bold text-green-800 mt-1">
                                    KSh <?= number_format(
                                        $membership['monthly_contribution'], 2
                                    ) ?>
                                </p>
                            </div>

                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p class="text-xs text-blue-700 uppercase">
                                    Balance After Contribution
                                </p>

                                <p class="text-xl font-bold text-blue-800 mt-1">
                                    KSh <?= number_format(
                                        $membership['balance_after_contribution'], 2
                                    ) ?>
                                </p>
                            </div>

                        </div>

                        <?php if (
                            abs(
                                $membership['closing_balance'] -
                                $membership['balance_after_contribution']
                            ) > 0.009
                        ): ?>

                            <p class="mt-3 text-xs text-gray-600 bg-gray-50 border rounded p-3">
                                <strong>Actual closing contribution balance:</strong>
                                KSh <?= number_format(
                                    $membership['closing_balance'], 2
                                ) ?>.
                                This includes other ledger entries such as withdrawals
                                and adjustments during the month.
                            </p>

                        <?php endif; ?>

                    </div>

                    <!-- LOAN TRACKING -->
                    <?php if (!empty($membership['loans'])): ?>

                        <div class="border-t p-4">

                            <h4 class="font-semibold text-red-700 mb-3">
                                <i class="fas fa-hand-holding-usd mr-1"></i>
                                SACCO Loan Tracking
                            </h4>

                            <div class="overflow-x-auto">

                                <table class="min-w-full text-sm border-collapse">

                                    <thead class="bg-red-50 text-gray-700">
                                        <tr>
                                            <th class="px-3 py-3 text-left">Loan Reference</th>
                                            <th class="px-3 py-3 text-right">Loan Amount</th>
                                            <th class="px-3 py-3 text-right">Paid This Month</th>
                                            <th class="px-3 py-3 text-right">Pending Balance</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        <?php foreach ($membership['loans'] as $loan): ?>

                                            <tr class="border-b">

                                                <td class="px-3 py-3">
                                                    <?= htmlspecialchars(
                                                        $loan['loan_reference'] ?: 'Loan #' . $loan['loan_id']
                                                    ) ?>
                                                    <p class="text-xs text-gray-500">
                                                        Issued:
                                                        <?= htmlspecialchars($loan['issue_date']) ?>
                                                    </p>
                                                </td>

                                                <td class="px-3 py-3 text-right">
                                                    KSh <?= number_format(
                                                        $loan['total_loan_amount'], 2
                                                    ) ?>
                                                </td>

                                                <td class="px-3 py-3 text-right text-green-700 font-semibold">
                                                    KSh <?= number_format(
                                                        $loan['paid_this_month'], 2
                                                    ) ?>
                                                </td>

                                                <td class="px-3 py-3 text-right font-bold <?= $loan['pending_balance'] > 0 ? 'text-red-700' : 'text-green-700' ?>">
                                                    KSh <?= number_format(
                                                        $loan['pending_balance'], 2
                                                    ) ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                    <tfoot class="bg-gray-100 font-bold">

                                        <tr>
                                            <td class="px-3 py-3">Total Loans</td>

                                            <td class="px-3 py-3 text-right">
                                                KSh <?= number_format(
                                                    $membership['total_loan_amount'], 2
                                                ) ?>
                                            </td>

                                            <td class="px-3 py-3 text-right text-green-700">
                                                KSh <?= number_format(
                                                    $membership['total_loan_paid_month'], 2
                                                ) ?>
                                            </td>

                                            <td class="px-3 py-3 text-right text-red-700">
                                                KSh <?= number_format(
                                                    $membership['total_loan_pending'], 2
                                                ) ?>
                                            </td>
                                        </tr>

                                    </tfoot>

                                </table>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="border-t p-4">
                            <h4 class="font-semibold text-gray-700 mb-1">
                                <i class="fas fa-hand-holding-usd mr-1"></i>
                                SACCO Loan Tracking
                            </h4>

                            <p class="text-sm text-gray-500 italic">
                                No loan has been recorded for this SACCO membership
                                as at <?= htmlspecialchars($monthName) ?>.
                            </p>
                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

            <p class="text-xs text-gray-500 mt-3">
                SACCO balances are displayed for information only.
                They are not included in payroll deductions or net pay.
            </p>

        <?php endif; ?>

    </div>
</section>

<?php endif; ?>

<?php
/*
|--------------------------------------------------------------------------
| PENSION TRACKING FOR SELECTED PAYROLL MONTH
|--------------------------------------------------------------------------
| Reads the pension ledger.
| Only Paid contributions are included in the accumulated pension balance.
| This section is display-only and does not affect payroll calculations.
*/

$pensionTracking = [
    'opening_balance' => 0,
    'employee_amount' => 0,
    'employer_amount' => 0,
    'total_contribution' => 0,
    'remittance_status' => 'Not Recorded',
    'payment_date' => null,
    'payment_reference' => null,
    'cumulative_paid' => 0
];

$pensionTrackingError = '';

try {
    $selectedYear = (int)$year;
    $selectedMonthStart = date('Y-m-01', strtotime($selectedMonth . '-01'));

    // 1. Fetch employee opening pension balance.
    $openingPensionStmt = $conn->prepare("
        SELECT opening_balance, effective_date
        FROM employee_pension_opening_balances
        WHERE user_id = ?
          AND (
                effective_date IS NULL
                OR effective_date <= ?
          )
        LIMIT 1
    ");

    $openingPensionStmt->bind_param(
        "is",
        $user_id,
        $saccoEnd
    );

    $openingPensionStmt->execute();

    $openingPension = $openingPensionStmt
        ->get_result()
        ->fetch_assoc();

    if ($openingPension) {
        $pensionTracking['opening_balance'] =
            (float)$openingPension['opening_balance'];
    }

    $openingPensionStmt->close();


    // 2. Fetch pension contribution for the selected payroll month.
    $monthlyPensionStmt = $conn->prepare("
        SELECT
            employee_amount,
            employer_amount,
            total_contribution,
            remittance_status,
            payment_date,
            payment_reference
        FROM pension_contributions
        WHERE user_id = ?
          AND month = ?
          AND year = ?
        LIMIT 1
    ");

    $monthlyPensionStmt->bind_param(
        "isi",
        $user_id,
        $monthText,
        $selectedYear
    );

    $monthlyPensionStmt->execute();

    $monthlyPension = $monthlyPensionStmt
        ->get_result()
        ->fetch_assoc();

    if ($monthlyPension) {
        $pensionTracking['employee_amount'] =
            (float)$monthlyPension['employee_amount'];

        $pensionTracking['employer_amount'] =
            (float)$monthlyPension['employer_amount'];

        $pensionTracking['total_contribution'] =
            (float)$monthlyPension['total_contribution'];

        $pensionTracking['remittance_status'] =
            $monthlyPension['remittance_status'];

        $pensionTracking['payment_date'] =
            $monthlyPension['payment_date'];

        $pensionTracking['payment_reference'] =
            $monthlyPension['payment_reference'];
    }

    $monthlyPensionStmt->close();

// 3. Calculate all accrued pension contributions up to selected month.
// Include BOTH Paid and Pending contributions in the accrued balance.
$cumulativePensionStmt = $conn->prepare("
    SELECT
        COALESCE(SUM(total_contribution), 0) AS accrued_contributions,
        COALESCE(SUM(
            CASE
                WHEN LOWER(TRIM(remittance_status)) = 'paid'
                THEN total_contribution
                ELSE 0
            END
        ), 0) AS cumulative_paid
    FROM pension_contributions
    WHERE user_id = ?
      AND STR_TO_DATE(
            CONCAT('01 ', month, ' ', year),
            '%d %M %Y'
          ) <= STR_TO_DATE(
            CONCAT('01 ', ?, ' ', ?),
            '%d %M %Y'
          )
");

$cumulativePensionStmt->bind_param(
    "iss",
    $user_id,
    $monthText,
    $year
);

$cumulativePensionStmt->execute();

$cumulativeResult = $cumulativePensionStmt
    ->get_result()
    ->fetch_assoc();

$pensionTracking['accrued_contributions'] =
    (float)$cumulativeResult['accrued_contributions'];

$pensionTracking['cumulative_paid'] =
    (float)$cumulativeResult['cumulative_paid'];

$cumulativePensionStmt->close();

// Closing balance includes ALL accrued contributions,
// regardless of whether remittance has been paid.
$pensionTracking['closing_balance'] =
    $pensionTracking['opening_balance'] +
    $pensionTracking['accrued_contributions'];

} catch (Throwable $e) {
    $pensionTrackingError = $e->getMessage();
}
?>

<!-- PENSION TRACKING -->
<section class="mt-6 mb-6 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">

    <div class="bg-green-800 text-white px-5 py-4">
        <h2 class="text-lg font-bold">
            <i class="fas fa-piggy-bank mr-2"></i>
            Pension Contribution Tracking
        </h2>

        <p class="text-sm text-green-100">
            Employee and employer pension position for
            <?= htmlspecialchars($monthName) ?>
            | Independent of SACCO tracking
        </p>
    </div>

    <div class="p-5">

        <?php if ($pensionTrackingError): ?>

            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">
                Unable to load pension information:
                <?= htmlspecialchars($pensionTrackingError) ?>
            </div>

        <?php else: ?>

            <!-- Pension Balance Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">

                <div class="bg-gray-50 border rounded-lg p-4">
                    <p class="text-xs text-gray-500 uppercase">
                        Opening Pension Balance
                    </p>

                    <p class="text-xl font-bold text-gray-800 mt-2">
                        KSh <?= number_format(
                            $pensionTracking['opening_balance'], 2
                        ) ?>
                    </p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-xs text-blue-700 uppercase">
                        Contributions Remitted to Date
                    </p>

                    <p class="text-xl font-bold text-blue-900 mt-2">
                        KSh <?= number_format(
                            $pensionTracking['cumulative_paid'], 2
                        ) ?>
                    </p>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-xs text-green-700 uppercase">
                        Closing Pension Balance
                    </p>

                    <p class="text-xl font-bold text-green-900 mt-2">
                        KSh <?= number_format(
                            $pensionTracking['closing_balance'], 2
                        ) ?>
                    </p>
                    <p class="text-xs text-green-700 mt-1">
                        Opening balance + remitted contributions
                    </p>
                </div>

            </div>


            <!-- Monthly Contributions -->
            <h3 class="font-semibold text-gray-800 mb-3">
                Monthly Contribution Breakdown
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-700 font-semibold">
                        Employee Contribution (6%)
                    </p>

                    <p class="text-2xl font-bold text-blue-900 mt-2">
                        KSh <?= number_format(
                            $pensionTracking['employee_amount'], 2
                        ) ?>
                    </p>
                    <p class="text-xs text-blue-600 mt-1">
                        Deducted from employee payroll
                    </p>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-sm text-green-700 font-semibold">
                        Employer Contribution (6%)
                    </p>

                    <p class="text-2xl font-bold text-green-900 mt-2">
                        KSh <?= number_format(
                            $pensionTracking['employer_amount'], 2
                        ) ?>
                    </p>
                    <p class="text-xs text-green-600 mt-1">
                        Additional employer-funded contribution
                    </p>
                </div>

                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                    <p class="text-sm text-indigo-700 font-semibold">
                        Total Monthly Contribution (12%)
                    </p>

                    <p class="text-2xl font-bold text-indigo-900 mt-2">
                        KSh <?= number_format(
                            $pensionTracking['total_contribution'], 2
                        ) ?>
                    </p>
                    <p class="text-xs text-indigo-600 mt-1">
                        Employee + employer
                    </p>
                </div>

            </div>


            <!-- Remittance Information -->
            <div class="border rounded-lg overflow-hidden">

                <div class="bg-gray-100 px-4 py-3">
                    <h3 class="font-semibold text-gray-800">
                        Pension Remittance Information
                    </h3>
                </div>

                <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">

                    <div>
                        <p class="text-xs text-gray-500 uppercase">
                            Remittance Status
                        </p>

                        <?php
                        $remittanceStatus =
                            $pensionTracking['remittance_status'];

                        $statusClass = strtolower(trim($remittanceStatus)) === 'paid'
    ? 'bg-green-100 text-green-700'
    : 'bg-yellow-100 text-yellow-700';
                        ?>

                        <span class="inline-block mt-2 px-3 py-1 rounded-full text-sm font-semibold <?= $statusClass ?>">
                            <?= htmlspecialchars($remittanceStatus) ?>
                        </span>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">
                            Payment Date
                        </p>

                        <p class="font-semibold text-gray-800 mt-2">
                            <?= !empty($pensionTracking['payment_date'])
                                ? htmlspecialchars($pensionTracking['payment_date'])
                                : 'Not recorded' ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500 uppercase">
                            Payment Reference
                        </p>

                        <p class="font-semibold text-gray-800 mt-2">
                            <?= !empty($pensionTracking['payment_reference'])
                                ? htmlspecialchars($pensionTracking['payment_reference'])
                                : 'Not recorded' ?>
                        </p>
                    </div>

                </div>
            </div>

            <?php if (!$monthlyPension): ?>

                <div class="mt-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-3 text-sm">
                    No pension contribution record was found for
                    <?= htmlspecialchars($monthName) ?>.
                    The contribution will appear here once the payroll
                    has been saved with pension enabled.
                </div>

            <?php endif; ?>

            <p class="text-xs text-gray-500 mt-4">
                Pension tracking is for reporting and reconciliation.
                Pending contributions are excluded from the closing
                remitted balance. Employer contributions do not reduce
                employee net pay.
            </p>

        <?php endif; ?>

    </div>
</section>

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
