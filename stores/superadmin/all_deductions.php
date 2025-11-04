<?php
session_start();
require 'db_con.php';

// ✅ Filters: month & year
$month = $_GET['month'] ?? date('F'); 
$year = $_GET['year'] ?? date('Y');

// ✅ Fetch employees (Staff & Active)
$employees = [];
$empQuery = $conn->query("
    SELECT user_id, full_name 
    FROM users 
    WHERE group_id = (SELECT group_id FROM groups WHERE group_name='Staff') 
      AND status='active'
    ORDER BY full_name ASC
");
while ($e = $empQuery->fetch_assoc()) {
    $employees[] = $e;
}

// ✅ Fetch payroll records for selected month/year
$payrolls = [];
$allowancesList = []; // unique allowances
$deductionsList = []; // unique deductions

$payQuery = $conn->query("SELECT * FROM payroll_records WHERE month='{$month}' AND year={$year}");
while ($p = $payQuery->fetch_assoc()) {
    $payrolls[$p['user_id']] = $p;

    $details = json_decode($p['details'], true);
    if ($details) {
        if (isset($details['allowances'])) {
            foreach ($details['allowances'] as $allow) {
                $allowancesList[$allow['name']] = $allow['name'];
            }
        }
        if (isset($details['deductions'])) {
            foreach ($details['deductions'] as $ded) {
                $deductionsList[$ded['name']] = $ded['name'];
            }
        }
    }
}

// Sort column names
ksort($allowancesList);
ksort($deductionsList);

// ✅ Prepare totals
$totalsAllowances = array_fill_keys(array_keys($allowancesList), 0);
$totalsDeductions = array_fill_keys(array_keys($deductionsList), 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payroll - <?= htmlspecialchars($month . ' ' . $year) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 mt-24 p-6 bg-white rounded shadow max-w-7xl mx-auto">

<h1 class="text-2xl font-bold mb-4">Payroll - <?= htmlspecialchars($month . ' ' . $year) ?></h1>

<!-- Filter Form -->
<form method="GET" class="flex gap-4 mb-6 items-end">
    <div>
        <label class="block text-sm font-medium text-gray-700">Month</label>
        <select name="month" class="border rounded px-3 py-2">
            <?php 
            $months = [
                'January','February','March','April','May','June',
                'July','August','September','October','November','December'
            ];
            foreach ($months as $m):
            ?>
                <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $m ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Year</label>
        <input type="number" name="year" value="<?= htmlspecialchars($year) ?>" class="border rounded px-3 py-2" min="2000" max="2100">
    </div>
    <div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filter</button>
    </div>
</form>

<!-- Payroll Table -->
<div class="overflow-x-auto">
<table class="w-full border text-sm">
    <thead class="bg-blue-100">
        <tr>
            <th class="border px-2 py-1">Employee</th>
            <?php foreach ($allowancesList as $allow): ?>
                <th class="border px-2 py-1"><?= htmlspecialchars($allow) ?></th>
            <?php endforeach; ?>
            <?php foreach ($deductionsList as $ded): ?>
                <th class="border px-2 py-1"><?= htmlspecialchars($ded) ?></th>
            <?php endforeach; ?>
            <th class="border px-2 py-1">Net Pay</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($employees as $emp): ?>
        <tr class="hover:bg-gray-50">
            <td class="border px-2 py-1"><?= htmlspecialchars($emp['full_name']) ?></td>

            <?php
            $details = isset($payrolls[$emp['user_id']]) ? json_decode($payrolls[$emp['user_id']]['details'], true) : null;

            // Allowances
            foreach ($allowancesList as $allow) {
                $amt = 0;
                if ($details && isset($details['allowances'])) {
                    foreach ($details['allowances'] as $a) {
                        if ($a['name'] == $allow) {
                            $amt = floatval($a['amount']);
                            $totalsAllowances[$allow] += $amt;
                        }
                    }
                }
                echo "<td class='border px-2 py-1'>" . number_format($amt, 2) . "</td>";
            }

            // Deductions
            foreach ($deductionsList as $ded) {
                $amt = 0;
                if ($details && isset($details['deductions'])) {
                    foreach ($details['deductions'] as $d) {
                        if ($d['name'] == $ded) {
                            $val = $d['amount'];
                            if (str_ends_with($val, '%')) {
                                $base = $payrolls[$emp['user_id']]['base_salary'] ?? 0;
                                $amt = floatval(str_replace('%','',$val)) * $base / 100;
                            } else {
                                $amt = floatval($val);
                            }
                            $totalsDeductions[$ded] += $amt;
                        }
                    }
                }
                echo "<td class='border px-2 py-1'>" . number_format($amt, 2) . "</td>";
            }

            // Net Pay
            $netPay = $payrolls[$emp['user_id']]['net_pay'] ?? 0;
            echo "<td class='border px-2 py-1 font-semibold'>" . number_format($netPay, 2) . "</td>";
            ?>
        </tr>
        <?php endforeach; ?>
    </tbody>

    <tfoot class="bg-gray-200 font-semibold">
        <tr>
            <td class="border px-2 py-1">Total</td>
            <?php foreach ($totalsAllowances as $t): ?>
                <td class="border px-2 py-1"><?= number_format($t,2) ?></td>
            <?php endforeach; ?>
            <?php foreach ($totalsDeductions as $t): ?>
                <td class="border px-2 py-1"><?= number_format($t,2) ?></td>
            <?php endforeach; ?>
            <td class="border px-2 py-1"></td>
        </tr>
    </tfoot>
</table>
</div>
</body>
</html>
