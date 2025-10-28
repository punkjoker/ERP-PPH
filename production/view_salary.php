<?php
require 'db_con.php';

$user_id = intval($_GET['id']);
if (!$user_id) die("Invalid Employee ID");

// ✅ Fetch employee basic info
$user = $conn->query("SELECT full_name, email, national_id FROM users WHERE user_id = $user_id")->fetch_assoc();

// ✅ Fetch salary info
$salary = $conn->query("SELECT * FROM employee_salary WHERE user_id = $user_id")->fetch_assoc();

// ✅ Fetch allowances
$allowances = $conn->query("SELECT allowance_name, amount FROM employee_allowances WHERE user_id = $user_id");

// ✅ Fetch deductions (join to get names and rates)
$deductions = $conn->query("
    SELECT d.deduction_name, d.rate
    FROM employee_deductions ed
    JOIN deductions d ON ed.deduction_id = d.deduction_id
    WHERE ed.user_id = $user_id
");

// ✅ Calculate totals
$base_salary = $salary['base_salary'] ?? 0;
$total_allowances = 0;
$total_deduction_rate = 0;

if ($allowances->num_rows > 0) {
    while ($a = $allowances->fetch_assoc()) {
        $total_allowances += $a['amount'];
    }
    // rewind pointer to display again
    $allowances->data_seek(0);
}
if ($deductions->num_rows > 0) {
    while ($d = $deductions->fetch_assoc()) {
        $total_deduction_rate += $d['rate'];
    }
    $deductions->data_seek(0);
}

// ✅ Compute pay summary
$gross_pay = $base_salary + $total_allowances;
$estimated_deductions = ($gross_pay * $total_deduction_rate) / 100;
$net_pay = $gross_pay - $estimated_deductions;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Salary Details</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Salary Details - <?= htmlspecialchars($user['full_name']) ?></h1>

    <!-- Employee Info -->
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Employee Information</h2>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        <p><strong>National ID:</strong> <?= htmlspecialchars($user['national_id']) ?></p>
    </div>

    <!-- Salary Info -->
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Salary & Bank Information</h2>
        <p><strong>Base Salary:</strong> Ksh <?= number_format($base_salary, 2) ?></p>
        <p><strong>Bank Name:</strong> <?= htmlspecialchars($salary['bank_name'] ?? '-') ?></p>
        <p><strong>Bank Account:</strong> <?= htmlspecialchars($salary['bank_account'] ?? '-') ?></p>
    </div>

    <!-- Allowances -->
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Allowances</h2>
        <?php if ($allowances->num_rows > 0): ?>
            <table class="min-w-full table-auto border">
                <thead class="bg-green-500 text-white">
                    <tr>
                        <th class="px-4 py-2 text-left">Allowance Name</th>
                        <th class="px-4 py-2 text-left">Amount (Ksh)</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($a = $allowances->fetch_assoc()): ?>
                    <tr class="border-b">
                        <td class="px-4 py-2"><?= htmlspecialchars($a['allowance_name']) ?></td>
                        <td class="px-4 py-2"><?= number_format($a['amount'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-gray-500">No allowances assigned.</p>
        <?php endif; ?>
    </div>

    <!-- Deductions -->
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Deductions</h2>
        <?php if ($deductions->num_rows > 0): ?>
            <table class="min-w-full table-auto border">
                <thead class="bg-red-500 text-white">
                    <tr>
                        <th class="px-4 py-2 text-left">Deduction Name</th>
                        <th class="px-4 py-2 text-left">Rate (%)</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($d = $deductions->fetch_assoc()): ?>
                    <tr class="border-b">
                        <td class="px-4 py-2"><?= htmlspecialchars($d['deduction_name']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($d['rate']) ?>%</td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-gray-500">No deductions assigned.</p>
        <?php endif; ?>
    </div>

    <!-- Summary -->
    <div class="bg-white shadow rounded-lg p-5">
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Salary Summary</h2>
        <p><strong>Gross Pay:</strong> Ksh <?= number_format($gross_pay, 2) ?></p>
        <p><strong>Total Deduction Rate:</strong> <?= number_format($total_deduction_rate, 2) ?>%</p>
        <p><strong>Estimated Deductions:</strong> Ksh <?= number_format($estimated_deductions, 2) ?></p>
        <p class="text-blue-700 font-semibold"><strong>Net Pay (Est.):</strong> Ksh <?= number_format($net_pay, 2) ?></p>
    </div>
</div>
</body>
</html>
