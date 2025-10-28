<?php 
session_start();
require 'db_con.php';

// Get payroll ID from GET
$payroll_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch payroll record with user info
$stmt = $conn->prepare("
    SELECT pr.*, u.full_name, u.email, u.national_id, u.status
    FROM payroll_records pr
    INNER JOIN users u ON pr.user_id = u.user_id
    WHERE pr.payroll_id = ?
");
$stmt->bind_param("i", $payroll_id);
$stmt->execute();
$result = $stmt->get_result();
$payroll = $result->fetch_assoc();
$stmt->close();

if (!$payroll) {
    die("<div style='margin:100px;text-align:center;color:red;font-weight:bold;'>
        Payroll record not found.
    </div>");
}

// Decode allowances & deductions
$details = json_decode($payroll['details'], true);
$manualAllowances = $details['allowances'] ?? [];
$manualDeductions = $details['deductions'] ?? [];

// Month + year for display
$monthName = $payroll['month'] . ' ' . $payroll['year'];
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

    <div class="grid grid-cols-2 gap-4 mb-6">
        <div>
            <p><strong>Email:</strong> <?= htmlspecialchars($payroll['email']) ?></p>
            <p><strong>National ID:</strong> <?= htmlspecialchars($payroll['national_id']) ?></p>
        </div>
        <div>
            <p><strong>Status:</strong> 
                <span class="px-2 py-1 rounded-full text-sm font-medium <?= $payroll['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($payroll['status']) ?>
                </span>
            </p>
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
                                <td class="px-3 py-2 text-right"><?= number_format(floatval($a['amount']), 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-500 italic">No manual allowances added.</p>
            <?php endif; ?>
        </div>

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
                                ? (floatval(str_replace('%','',$rawValue)) / 100) * $payroll['base_salary']
                                : floatval($rawValue);
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

    <div class="mt-6 flex justify-between">
        <a href="my_payrolls.php" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-lg">
            ← Back to Payroll List
        </a>
        <a href="download_my_payroll_pdf.php?id=<?= $payroll_id ?>&download=1" 
   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
    📄 Download PDF
</a>

    </div>
</div>

</body>
</html>
