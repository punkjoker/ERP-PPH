<?php
require 'db_con.php';

$user_id = intval($_GET['id']);
if (!$user_id) die("Invalid Employee ID");

// ✅ Fetch Employee Info
$user = $conn->query("SELECT full_name, email FROM users WHERE user_id = $user_id")->fetch_assoc();

// ✅ Fetch existing salary info
$salary = $conn->query("SELECT * FROM employee_salary WHERE user_id = $user_id")->fetch_assoc();

// ✅ Fetch existing allowances
$allowances = $conn->query("SELECT * FROM employee_allowances WHERE user_id = $user_id");

// ✅ Fetch existing deductions
$employee_deductions = $conn->query("
    SELECT ed.deduction_id, d.deduction_name, d.rate, d.deduction_id
    FROM employee_deductions ed
    JOIN deductions d ON ed.deduction_id = d.deduction_id
    WHERE ed.user_id = $user_id
");

// ✅ Fetch available deductions for dropdown
$deductions = $conn->query("SELECT * FROM deductions ORDER BY deduction_name ASC");

// ✅ Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $base_salary = floatval($_POST['base_salary']);
    $bank_name = trim($_POST['bank_name']);
    $bank_account = trim($_POST['bank_account']);

    // Insert or Update salary record
    $exists = $conn->query("SELECT salary_id FROM employee_salary WHERE user_id = $user_id")->num_rows;
    if ($exists) {
        $stmt = $conn->prepare("UPDATE employee_salary SET base_salary=?, bank_name=?, bank_account=? WHERE user_id=?");
        $stmt->bind_param("dssi", $base_salary, $bank_name, $bank_account, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO employee_salary (base_salary, bank_name, bank_account, user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("dssi", $base_salary, $bank_name, $bank_account, $user_id);
    }
    $stmt->execute();

    // ✅ Remove existing allowances and re-insert
    $conn->query("DELETE FROM employee_allowances WHERE user_id = $user_id");
    if (!empty($_POST['allowance_name'])) {
        foreach ($_POST['allowance_name'] as $index => $name) {
            $amount = floatval($_POST['allowance_amount'][$index]);
            if (!empty($name) && $amount > 0) {
                $stmt = $conn->prepare("INSERT INTO employee_allowances (user_id, allowance_name, amount) VALUES (?, ?, ?)");
                $stmt->bind_param("isd", $user_id, $name, $amount);
                $stmt->execute();
            }
        }
    }

    // ✅ Remove existing deductions and re-insert
    $conn->query("DELETE FROM employee_deductions WHERE user_id = $user_id");
    if (!empty($_POST['deduction_id'])) {
        foreach ($_POST['deduction_id'] as $deduction_id) {
            $stmt = $conn->prepare("INSERT INTO employee_deductions (user_id, deduction_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $user_id, $deduction_id);
            $stmt->execute();
        }
    }

    echo "<script>alert('Salary details updated successfully'); window.location='payroll_details.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Update Salary Details</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
<script>
function addAllowanceRow(name = '', amount = '') {
    const container = document.getElementById('allowances');
    const row = document.createElement('div');
    row.className = "flex gap-2 mb-2";
    row.innerHTML = `
        <input type="text" name="allowance_name[]" value="${name}" placeholder="Allowance Name" class="border px-3 py-2 rounded w-1/2" required>
        <input type="number" name="allowance_amount[]" value="${amount}" placeholder="Amount" step="0.01" class="border px-3 py-2 rounded w-1/2" required>
        <button type="button" class="bg-red-500 text-white px-3 py-1 rounded" onclick="this.parentElement.remove()">Remove</button>
    `;
    container.appendChild(row);
}

function addDeductionRow(selectedId = '') {
    const container = document.getElementById('deductions');
    const row = document.createElement('div');
    row.className = "flex gap-2 mb-2";
    row.innerHTML = `
        <select name="deduction_id[]" class="border px-3 py-2 rounded w-full" required>
            <?php
            $deductions->data_seek(0);
            while ($d = $deductions->fetch_assoc()) {
                echo "<option value='{$d['deduction_id']}'>" . htmlspecialchars($d['deduction_name']) . " ({$d['rate']}%)</option>";
            }
            ?>
        </select>
        <button type="button" class="bg-red-500 text-white px-3 py-1 rounded" onclick="this.parentElement.remove()">Remove</button>
    `;
    container.appendChild(row);
    if (selectedId) row.querySelector("select").value = selectedId;
}

// Prefill existing allowances/deductions on load
window.onload = function() {
    <?php while ($a = $allowances->fetch_assoc()) { ?>
        addAllowanceRow("<?= htmlspecialchars($a['allowance_name']) ?>", "<?= $a['amount'] ?>");
    <?php } ?>

    <?php while ($d = $employee_deductions->fetch_assoc()) { ?>
        addDeductionRow("<?= $d['deduction_id'] ?>");
    <?php } ?>
};
</script>

</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-5xl mx-auto">
    <h1 class="text-xl font-bold mb-4 text-gray-700">Update Salary - <?= htmlspecialchars($user['full_name']) ?></h1>
    <form method="POST">

        <!-- Base Salary -->
        <div class="mb-4">
            <label class="block text-gray-700 font-medium">Base Salary</label>
            <input type="number" step="0.01" name="base_salary" value="<?= $salary['base_salary'] ?? '' ?>" class="border rounded px-3 py-2 w-full" required>
        </div>

        <!-- Bank Info -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-gray-700 font-medium">Bank Name</label>
                <input type="text" name="bank_name" value="<?= htmlspecialchars($salary['bank_name'] ?? '') ?>" class="border rounded px-3 py-2 w-full" required>
            </div>
            <div>
                <label class="block text-gray-700 font-medium">Bank Account Number</label>
                <input type="text" name="bank_account" value="<?= htmlspecialchars($salary['bank_account'] ?? '') ?>" class="border rounded px-3 py-2 w-full" required>
            </div>
        </div>

        <!-- Allowances -->
        <h2 class="text-lg font-semibold mb-2">Allowances</h2>
        <div id="allowances"></div>
        <button type="button" onclick="addAllowanceRow()" class="bg-green-600 text-white px-3 py-1 rounded mb-4">+ Add Allowance</button>

        <!-- Deductions -->
        <h2 class="text-lg font-semibold mb-2">Deductions</h2>
        <div id="deductions"></div>
        <button type="button" onclick="addDeductionRow()" class="bg-red-600 text-white px-3 py-1 rounded mb-4">+ Add Deduction</button>

        <!-- Submit -->
        <div class="mt-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">Save Salary Details</button>
        </div>
    </form>
</div>
</body>
</html>
