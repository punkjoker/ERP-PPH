<?php
require 'db_con.php';

$user_id = intval($_GET['id']);
if (!$user_id) die("Invalid Employee ID");



// Fetch employee basic info
$user = $conn->query("SELECT full_name, email, national_id FROM users WHERE user_id = $user_id")->fetch_assoc();

// Fetch salary info
$salary = $conn->query("SELECT * FROM employee_salary WHERE user_id = $user_id")->fetch_assoc();

// Fetch allowances
$allowances = $conn->query("SELECT allowance_name, amount FROM employee_allowances WHERE user_id = $user_id");

// Fetch deductions (with rates)
$deductions = $conn->query("
    SELECT d.deduction_name, d.rate
    FROM employee_deductions ed
    JOIN deductions d ON ed.deduction_id = d.deduction_id
    WHERE ed.user_id = $user_id
");

$base_salary = $salary['base_salary'] ?? 0;
$total_allowances = 0;
$total_deduction_rate = 0;

if ($allowances->num_rows > 0) {
    while ($a = $allowances->fetch_assoc()) {
        $total_allowances += $a['amount'];
    }
    $allowances->data_seek(0);
}
if ($deductions->num_rows > 0) {
    while ($d = $deductions->fetch_assoc()) {
        $total_deduction_rate += $d['rate'];
    }
    $deductions->data_seek(0);
}

$gross_pay = $base_salary + $total_allowances;
$estimated_deductions = ($gross_pay * $total_deduction_rate) / 100;
$net_pay = $gross_pay - $estimated_deductions;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Process Payroll</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Process Payroll - <?= htmlspecialchars($user['full_name']) ?></h1>

    <!-- Employee Info -->
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Employee Information</h2>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        <p><strong>National ID:</strong> <?= htmlspecialchars($user['national_id']) ?></p>
    </div>

    <!-- Base Salary -->
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Base Salary</h2>
        <p><strong>Base Salary:</strong> Ksh <?= number_format($base_salary, 2) ?></p>
    </div>

    <!-- Allowances -->
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Allowances</h2>
        <table class="min-w-full table-auto border mb-3" id="allowanceTable">
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

        <!-- Manual Add Allowance -->
        <div class="flex items-center gap-2">
            <input type="text" id="manualAllowanceName" placeholder="Allowance Name" class="border rounded px-3 py-2 w-1/2">
            <input type="number" id="manualAllowanceAmount" placeholder="Amount" class="border rounded px-3 py-2 w-1/4">
            <button id="addAllowanceBtn" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">Add</button>
        </div>
    </div>

    <!-- Deductions -->
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Deductions</h2>
        <table class="min-w-full table-auto border mb-3" id="deductionTable">
            <thead class="bg-red-500 text-white">
                <tr>
                    <th class="px-4 py-2 text-left">Deduction Name</th>
                    <th class="px-4 py-2 text-left">Rate / Amount</th>
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

        <!-- Manual Add Deduction -->
        <div class="flex items-center gap-2">
            <input type="text" id="manualDeductionName" placeholder="Deduction Name" class="border rounded px-3 py-2 w-1/2">
            <input type="number" id="manualDeductionAmount" placeholder="Amount" class="border rounded px-3 py-2 w-1/4">
            <button id="addDeductionBtn" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Add</button>
        </div>
    </div>

   <!-- Summary -->
<div class="bg-white shadow rounded-lg p-5">
    <h2 class="text-lg font-semibold text-gray-700 mb-3">Salary Summary</h2>

    <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-gray-600 font-medium mb-1">Month</label>
            <select id="payrollMonth" class="border rounded px-3 py-2 w-full">
                <option value="">Select Month</option>
                <?php 
                $months = [
                    "January","February","March","April","May","June",
                    "July","August","September","October","November","December"
                ];
                foreach ($months as $m): ?>
                    <option value="<?= $m ?>" <?= (date('F') == $m ? 'selected' : '') ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-gray-600 font-medium mb-1">Year</label>
            <input type="number" id="payrollYear" class="border rounded px-3 py-2 w-full" value="<?= date('Y') ?>" readonly>
        </div>
    </div>

    <p><strong>Gross Pay:</strong> Ksh <span id="grossPay"><?= number_format($gross_pay, 2) ?></span></p>
    <p><strong>Estimated Deductions:</strong> Ksh <span id="totalDeductions"><?= number_format($estimated_deductions, 2) ?></span></p>
    <p class="text-blue-700 font-semibold"><strong>Net Pay:</strong> Ksh <span id="netPay"><?= number_format($net_pay, 2) ?></span></p>

    <button id="savePayrollBtn" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">Save Payroll</button>
</div>

</div>

<script>
let grossPay = <?= $gross_pay ?>;
let totalDeductions = <?= $estimated_deductions ?>;
let netPay = <?= $net_pay ?>;

// Add Manual Allowance
$("#addAllowanceBtn").on("click", function() {
    let name = $("#manualAllowanceName").val().trim();
    let amount = parseFloat($("#manualAllowanceAmount").val());
    if (!name || isNaN(amount) || amount <= 0) return alert("Enter valid allowance details.");

    $("#allowanceTable tbody").append(`<tr><td class='px-4 py-2'>${name}</td><td class='px-4 py-2'>${amount.toFixed(2)}</td></tr>`);
    grossPay += amount;
    updateSummary();
    $("#manualAllowanceName").val("");
    $("#manualAllowanceAmount").val("");
});

// Add Manual Deduction
$("#addDeductionBtn").on("click", function() {
    let name = $("#manualDeductionName").val().trim();
    let amount = parseFloat($("#manualDeductionAmount").val());
    if (!name || isNaN(amount) || amount <= 0) return alert("Enter valid deduction details.");

    $("#deductionTable tbody").append(`<tr><td class='px-4 py-2'>${name}</td><td class='px-4 py-2'>${amount.toFixed(2)}</td></tr>`);
    totalDeductions += amount;
    updateSummary();
    $("#manualDeductionName").val("");
    $("#manualDeductionAmount").val("");
});

function updateSummary() {
    netPay = grossPay - totalDeductions;
    $("#grossPay").text(grossPay.toLocaleString());
    $("#totalDeductions").text(totalDeductions.toLocaleString());
    $("#netPay").text(netPay.toLocaleString());
}
</script>
<script>
$("#savePayrollBtn").on("click", function() {
    const month = $("#payrollMonth").val();
const year = $("#payrollYear").val();
if (!month || !year) return alert("Please select a month and year before saving.");


    // Gather data
    let allowances = [];
    $("#allowanceTable tbody tr").each(function() {
        let cols = $(this).find("td");
        allowances.push({
            name: $(cols[0]).text(),
            amount: parseFloat($(cols[1]).text().replace(/,/g, ''))
        });
    });

    let deductions = [];
    $("#deductionTable tbody tr").each(function() {
        let cols = $(this).find("td");
        deductions.push({
            name: $(cols[0]).text(),
            amount: $(cols[1]).text().includes('%') ? $(cols[1]).text() : parseFloat($(cols[1]).text().replace(/,/g, ''))
        });
    });

    const payload = {
        user_id: <?= $user_id ?>,
        full_name: "<?= addslashes($user['full_name']) ?>",
        base_salary: <?= $base_salary ?>,
        total_allowances: grossPay - <?= $base_salary ?>,
        total_deductions: totalDeductions,
        net_pay: netPay,
        month,
        year,
        details: JSON.stringify({allowances, deductions})
    };

    $.post("save_payroll.php", payload, function(response) {
        alert(response);
    });
});
</script>

</body>
</html>
