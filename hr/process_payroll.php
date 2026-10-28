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

<?php
// Read the selected payroll month from the URL.
// Example: process_payroll.php?id=15&month=2026-10

$selectedMonth = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$monthDate = DateTime::createFromFormat('!Y-m', $selectedMonth);
$payrollMonth = $monthDate->format('F');
$payrollYear = (int) $monthDate->format('Y');

// Fetch the saved workflow information for this employee and month.

// Fetch existing payroll for the selected employee and month
$stmt = $conn->prepare("
    SELECT *
    FROM payroll_records
    WHERE user_id = ?
      AND month = ?
      AND year = ?
    LIMIT 1
");

$stmt->bind_param(
    "isi",
    $user_id,
    $payrollMonth,
    $payrollYear
);

$stmt->execute();

$result = $stmt->get_result();
$existingPayroll = $result->fetch_assoc();

$stmt->close();

// Determine whether this is a new or existing payroll
$isExistingPayroll = !empty($existingPayroll);

// Existing payroll values
$payrollRecord = $existingPayroll ?: [
    'payroll_no' => '',
    'prepared_by' => '',
    'date_prepared' => '',
    'preparation_status' => 'Pending',
    'approved_by' => '',
    'approval_date' => '',
    'approval_status' => 'Pending',
    'accountant_name' => '',
    'payment_date' => '',
    'payment_status' => 'Pending Pay'
];

// Load saved salary amounts
if ($isExistingPayroll) {
    $base_salary = (float) $existingPayroll['base_salary'];
    $total_allowances = (float) $existingPayroll['total_allowances'];
    $estimated_deductions = (float) $existingPayroll['total_deductions'];

    $gross_pay = $base_salary + $total_allowances;
    $net_pay = (float) $existingPayroll['net_pay'];
}

// Load saved allowance and deduction details
$savedDetails = [];

if ($isExistingPayroll && !empty($existingPayroll['details'])) {
    $decoded = json_decode($existingPayroll['details'], true);

    if (is_array($decoded)) {
        $savedDetails = $decoded;
    }
}

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
   
<!-- Employee Info -->
<div class="bg-white shadow rounded-lg p-5 mb-6">

    <h2 class="text-lg font-semibold text-gray-700 mb-3">
        Employee Information
    </h2>

    <div class="space-y-2">

        <p>
            <strong>Email:</strong>
            <?= htmlspecialchars($user['email'] ?? '-') ?>
        </p>

        <p>
            <strong>National ID:</strong>
            <?= htmlspecialchars($user['national_id'] ?? '-') ?>
        </p>

        <p>
            <strong>Bank Name:</strong>
            <?= htmlspecialchars($salary['bank_name'] ?? '-') ?>
        </p>

        <p>
            <strong>Bank Account:</strong>
            <?= htmlspecialchars($salary['bank_account'] ?? '-') ?>
        </p>

    </div>

</div>


    <!-- Base Salary -->
    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Base Salary</h2>
        
<!-- Base Salary -->
<div class="bg-white shadow rounded-lg p-5 mb-6">


    <p class="mb-4">
        <strong>Base Salary:</strong>
        Ksh <?= number_format($base_salary, 2) ?>
    </p>

    <div>
        <label class="block text-gray-700 font-medium mb-2">
            Payroll No.
        </label>

        <input
            type="text"
            id="payrollNo"
            maxlength="100"
            value="<?= htmlspecialchars($payrollRecord['payroll_no'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            placeholder="Enter Payroll Number"
            class="border rounded px-3 py-2 w-full md:w-1/2"
        >
    </div>

</div>

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
<?php if ($isExistingPayroll): ?>

    <?php foreach (($savedDetails['allowances'] ?? []) as $a): ?>
        <tr class="border-b">
            <td class="px-4 py-2">
                <?= htmlspecialchars($a['name'] ?? '') ?>
            </td>
            <td class="px-4 py-2">
                <?= number_format((float)($a['amount'] ?? 0), 2) ?>
            </td>
        </tr>
    <?php endforeach; ?>

<?php else: ?>

    <?php while ($a = $allowances->fetch_assoc()): ?>
        <tr class="border-b">
            <td class="px-4 py-2">
                <?= htmlspecialchars($a['allowance_name']) ?>
            </td>
            <td class="px-4 py-2">
                <?= number_format((float)$a['amount'], 2) ?>
            </td>
        </tr>
    <?php endwhile; ?>

<?php endif; ?>
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
<?php if ($isExistingPayroll): ?>

    <?php foreach (($savedDetails['deductions'] ?? []) as $d): ?>
        <tr class="border-b">
            <td class="px-4 py-2">
                <?= htmlspecialchars($d['name'] ?? '') ?>
            </td>
            <td class="px-4 py-2">
                <?= htmlspecialchars((string)($d['amount'] ?? 0)) ?>
            </td>
        </tr>
    <?php endforeach; ?>

<?php else: ?>

    <?php while ($d = $deductions->fetch_assoc()): ?>
        <tr class="border-b">
            <td class="px-4 py-2">
                <?= htmlspecialchars($d['deduction_name']) ?>
            </td>
            <td class="px-4 py-2">
                <?= htmlspecialchars($d['rate']) ?>%
            </td>
        </tr>
    <?php endwhile; ?>

<?php endif; ?>
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
    <label class="block text-gray-600 font-medium mb-1">
        Month
    </label>

    <select id="payrollMonth"
            class="border rounded px-3 py-2 w-full">

        <?php
        $months = [
            "January", "February", "March", "April",
            "May", "June", "July", "August",
            "September", "October", "November", "December"
        ];

        foreach ($months as $m):
        ?>
            <option value="<?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?>"
                <?= $payrollMonth === $m ? 'selected' : '' ?>>
                <?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div>
    <label class="block text-gray-600 font-medium mb-1">
        Year
    </label>

    <input type="number"
           id="payrollYear"
           class="border rounded px-3 py-2 w-full"
           value="<?= $payrollYear ?>"
           readonly>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

    
<!-- 1. Payroll Preparation -->
<div>
    <label class="block mb-1">Prepared By</label>
    <input type="text" id="preparedBy"
           value="<?= htmlspecialchars($payrollRecord['prepared_by'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
           class="border rounded px-3 py-2 w-full"
           placeholder="Enter preparer's name">
</div>

<div>
    <label class="block mb-1">Date Prepared</label>
    <input type="date" id="datePrepared"
           value="<?= htmlspecialchars($payrollRecord['date_prepared'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
           class="border rounded px-3 py-2 w-full">
</div>

<div>
    <label class="block mb-1">Preparation Status</label>
    <select id="preparationStatus"
            class="border rounded px-3 py-2 w-full">
        <option value="Pending"
            <?= ($payrollRecord['preparation_status'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>
            Pending
        </option>
        <option value="Prepared"
            <?= ($payrollRecord['preparation_status'] ?? '') === 'Prepared' ? 'selected' : '' ?>>
            Prepared
        </option>
    </select>
</div>

<!-- 2. Payroll Approval -->
<div>
    <label class="block mb-1">Approved By</label>
    <input type="text" id="approvedBy"
           value="<?= htmlspecialchars($payrollRecord['approved_by'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
           class="border rounded px-3 py-2 w-full"
           placeholder="Enter approver's name">
</div>

<div>
    <label class="block mb-1">Approval Date</label>
    <input type="date" id="approvalDate"
           value="<?= htmlspecialchars($payrollRecord['approval_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
           class="border rounded px-3 py-2 w-full">
</div>

<div>
    <label class="block mb-1">Approval Status</label>
    <select id="approvalStatus"
            class="border rounded px-3 py-2 w-full">
        <option value="Pending"
            <?= ($payrollRecord['approval_status'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>
            Pending
        </option>
        <option value="Approved"
            <?= ($payrollRecord['approval_status'] ?? '') === 'Approved' ? 'selected' : '' ?>>
            Approved
        </option>
    </select>
</div>

<!-- 3. Payroll Payment -->
<div>
    <label class="block mb-1">Accountant Name</label>
    <input type="text" id="accountantName"
           value="<?= htmlspecialchars($payrollRecord['accountant_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
           class="border rounded px-3 py-2 w-full"
           placeholder="Enter accountant's name">
</div>

<div>
    <label class="block mb-1">Payment Date</label>
    <input type="date" id="paymentDate"
           value="<?= htmlspecialchars($payrollRecord['payment_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
           class="border rounded px-3 py-2 w-full">
</div>

<div>
    <label class="block mb-1">Payment Status</label>
    <select id="paymentStatus"
            class="border rounded px-3 py-2 w-full">
        <option value="Pending Pay"
            <?= ($payrollRecord['payment_status'] ?? 'Pending Pay') === 'Pending Pay' ? 'selected' : '' ?>>
            Pending Pay
        </option>
        <option value="Paid"
            <?= ($payrollRecord['payment_status'] ?? '') === 'Paid' ? 'selected' : '' ?>>
            Paid
        </option>
    </select>
</div>

    <p><strong>Gross Pay:</strong> Ksh <span id="grossPay"><?= number_format($gross_pay, 2) ?></span></p>
    <p><strong>Estimated Deductions:</strong> Ksh <span id="totalDeductions"><?= number_format($estimated_deductions, 2) ?></span></p>
    <p class="text-blue-700 font-semibold"><strong>Net Pay:</strong> Ksh <span id="netPay"><?= number_format($net_pay, 2) ?></span></p>

    <button
    id="savePayrollBtn"
    class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded"
>
    <?= $isExistingPayroll ? 'Update Payroll' : 'Save Payroll' ?>
</button>

    <a href="payroll_list.php"
   class="mt-4 inline-block bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded text-center">
    Back to Payroll List
</a>

</div>

</div>


<script>
let grossPay = <?= (float) $gross_pay ?>;
let totalDeductions = <?= (float) $estimated_deductions ?>;
let netPay = <?= (float) $net_pay ?>;
const baseSalary = <?= (float) $base_salary ?>;

// Safely add a table row without inserting user input as HTML.
function addTableRow(tableSelector, name, amount, suffix = "") {
    const row = $("<tr>").addClass("border-b");

    $("<td>")
        .addClass("px-4 py-2")
        .text(name)
        .appendTo(row);

    $("<td>")
        .addClass("px-4 py-2")
        .text(amount.toFixed(2) + suffix)
        .appendTo(row);

    $(tableSelector + " tbody").append(row);
}

// Add Manual Allowance
$("#addAllowanceBtn").on("click", function (event) {
    event.preventDefault();

    const name = $("#manualAllowanceName").val().trim();
    const amount = parseFloat($("#manualAllowanceAmount").val());

    if (!name || !Number.isFinite(amount) || amount <= 0) {
        alert("Enter valid allowance details.");
        return;
    }

    addTableRow("#allowanceTable", name, amount);

    grossPay += amount;
    updateSummary();

    $("#manualAllowanceName").val("");
    $("#manualAllowanceAmount").val("");
});

// Add Manual Deduction
$("#addDeductionBtn").on("click", function (event) {
    event.preventDefault();

    const name = $("#manualDeductionName").val().trim();
    const amount = parseFloat($("#manualDeductionAmount").val());

    if (!name || !Number.isFinite(amount) || amount <= 0) {
        alert("Enter valid deduction details.");
        return;
    }

    // Manual deductions are entered as amounts, not percentages.
    addTableRow("#deductionTable", name, amount);

    totalDeductions += amount;
    updateSummary();

    $("#manualDeductionName").val("");
    $("#manualDeductionAmount").val("");
});

// Update salary summary
function updateSummary() {
    netPay = grossPay - totalDeductions;

    $("#grossPay").text(
        grossPay.toLocaleString("en-KE", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })
    );

    $("#totalDeductions").text(
        totalDeductions.toLocaleString("en-KE", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })
    );

    $("#netPay").text(
        netPay.toLocaleString("en-KE", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })
    );
}

// Save payroll and all three workflow stages
$("#savePayrollBtn").on("click", function (event) {
    event.preventDefault();

    const button = $(this);
    const month = $("#payrollMonth").val();
    const year = $("#payrollYear").val();

    if (!month || !year) {
        alert("Please select a month and year before saving.");
        return;
    }

    // Collect allowances
    const allowances = [];

    $("#allowanceTable tbody tr").each(function () {
        const cols = $(this).find("td");

        if (cols.length >= 2) {
            allowances.push({
                name: $(cols[0]).text().trim(),
                amount: parseFloat(
                    $(cols[1]).text().replace(/,/g, "")
                ) || 0
            });
        }
    });

    // Collect deductions. Existing percentage deductions are retained
    // as percentages; manually added deductions are amounts.
    const deductions = [];

    $("#deductionTable tbody tr").each(function () {
        const cols = $(this).find("td");

        if (cols.length >= 2) {
            const name = $(cols[0]).text().trim();
            const amountText = $(cols[1]).text().trim();

            deductions.push({
                name: name,
                amount: amountText.includes("%")
                    ? amountText
                    : (parseFloat(amountText.replace(/,/g, "")) || 0)
            });
        }
    });

    // Collect preparation, approval and payment information
    const preparedBy = $("#preparedBy").val().trim();
    const datePrepared = $("#datePrepared").val();
    const preparationStatus = $("#preparationStatus").val();

    const approvedBy = $("#approvedBy").val().trim();
    const approvalDate = $("#approvalDate").val();
    const approvalStatus = $("#approvalStatus").val();

    const accountantName = $("#accountantName").val().trim();
    const paymentDate = $("#paymentDate").val();
    const paymentStatus = $("#paymentStatus").val();

    // Client-side checks for completed stages.
    // save_payroll.php must also validate these on the server.
    if (preparationStatus === "Prepared" &&
        (!preparedBy || !datePrepared)) {
        alert("Enter the preparer's name and preparation date.");
        return;
    }

    if (approvalStatus === "Approved" &&
        (!approvedBy || !approvalDate)) {
        alert("Enter the approver's name and approval date.");
        return;
    }

    if (paymentStatus === "Paid" &&
        (!accountantName || !paymentDate)) {
        alert("Enter the accountant's name and payment date.");
        return;
    }

    if (netPay < 0) {
        alert("Total deductions cannot exceed gross pay.");
        return;
    }

    const payload = {
        user_id: <?= (int) $user_id ?>,
        payroll_no: $("#payrollNo").val().trim(),
        full_name: <?= json_encode($user['full_name'] ?? '') ?>,
        base_salary: baseSalary,
        total_allowances: grossPay - baseSalary,
        total_deductions: totalDeductions,
        net_pay: netPay,
        month: month,
        year: year,

        // Preparation
        prepared_by: preparedBy,
        date_prepared: datePrepared,
        preparation_status: preparationStatus,

        // Approval
        approved_by: approvedBy,
        approval_date: approvalDate,
        approval_status: approvalStatus,

        // Payment
        accountant_name: accountantName,
        payment_date: paymentDate,
        payment_status: paymentStatus,

        details: JSON.stringify({
            allowances: allowances,
            deductions: deductions
        })
    };

    button.prop("disabled", true).text("Saving...");

    $.ajax({
        url: "save_payroll.php",
        type: "POST",
        data: payload,
        dataType: "text"
    })
    .done(function (response) {
        alert(response);
    })
    .fail(function (xhr) {
        const message = xhr.responseText
            ? xhr.responseText
            : "Unable to save payroll. Please try again.";

        alert(message);
    })
    .always(function () {
    button.prop("disabled", false).text(
        <?= json_encode($isExistingPayroll ? 'Update Payroll' : 'Save Payroll') ?>
    );
});

});


$("#payrollMonth").on("change", function () {

    const selectedMonth = $(this).val();
    const selectedYear = $("#payrollYear").val();

    const months = [
        "January", "February", "March", "April",
        "May", "June", "July", "August",
        "September", "October", "November", "December"
    ];

    const monthNumber = String(
        months.indexOf(selectedMonth) + 1
    ).padStart(2, "0");

    const userId = <?= (int) $user_id ?>;

    window.location.href =
        "process_payroll.php?id=" + userId +
        "&month=" + selectedYear + "-" + monthNumber;
});

</script>
</body>
</html>
