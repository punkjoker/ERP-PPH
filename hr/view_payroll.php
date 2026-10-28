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

    <div class="grid grid-cols-2 gap-6">
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
                    $rawValue = $d['amount'];
                    $isPercentage = strpos($rawValue, '%') !== false;
                    $rateDisplay = $isPercentage ? $rawValue : '-';
                    $deductionAmount = $isPercentage 
                        ? (floatval(preg_replace('/[^0-9.\-]/', '', $rawValue)) / 100) * $payroll['base_salary']
                        : floatval(preg_replace('/[^0-9.\-]/', '', $rawValue));
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
