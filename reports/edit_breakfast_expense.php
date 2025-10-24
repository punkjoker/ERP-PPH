<?php
include 'db_con.php';

$breakfast_id = intval($_GET['id'] ?? 0);

// Fetch breakfast expense
$stmt = $conn->prepare("SELECT * FROM breakfast_expense WHERE breakfast_id=?");
$stmt->bind_param("i", $breakfast_id);
$stmt->execute();
$result = $stmt->get_result();
$expense = $result->fetch_assoc();
$stmt->close();

if (!$expense) {
    die("Breakfast expense not found.");
}

// Decode items JSON
$items = json_decode($expense['items'], true) ?? [];

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date = $_POST['expense_date'];
    $items_arr = $_POST['items'];
    $costs_arr = $_POST['costs'];
    $transport = floatval($_POST['transport_cost']);
    $bought_by = trim($_POST['items_bought_by']);
    $petty_cash_no = trim($_POST['petty_cash_no']);
    $approved_by = trim($_POST['approved_by']);
    $payment_status = $_POST['payment_status'];

    $expense_array = [];
    $total_items = 0;

    for ($i = 0; $i < count($items_arr); $i++) {
        if (trim($items_arr[$i]) !== '' && floatval($costs_arr[$i]) > 0) {
            $expense_array[] = ['item' => trim($items_arr[$i]), 'cost' => floatval($costs_arr[$i])];
            $total_items += floatval($costs_arr[$i]);
        }
    }

    $total_amount = $total_items + $transport;
    $items_json = json_encode($expense_array);

    $stmt = $conn->prepare("UPDATE breakfast_expense 
        SET expense_date=?, items=?, total_amount=?, items_bought_by=?, transport_cost=?, petty_cash_no=?, approved_by=?, payment_status=?
        WHERE breakfast_id=?");
    $stmt->bind_param("ssdsdsssi", $expense_date, $items_json, $total_amount, $bought_by, $transport, $petty_cash_no, $approved_by, $payment_status, $breakfast_id);
    $stmt->execute();
    $stmt->close();

    $success = "Breakfast expense updated successfully!";
    $items = $expense_array;
    $expense['total_amount'] = $total_amount;
    $expense['items_bought_by'] = $bought_by;
    $expense['transport_cost'] = $transport;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Breakfast Expense</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <script>
        let itemCount = <?= count($items) ?>;

        function addNewItem() {
            itemCount++;
            const container = document.getElementById('items-container');
            const div = document.createElement('div');
            div.classList.add('flex', 'gap-2', 'mb-2');
            div.innerHTML = `
                <input type="text" name="items[]" placeholder="Item ${itemCount}" class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required>
                <input type="number" step="0.01" name="costs[]" placeholder="Cost" class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required oninput="calculateTotal()">
            `;
            container.appendChild(div);
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('input[name="costs[]"]').forEach(c => {
                let val = parseFloat(c.value);
                if (!isNaN(val)) total += val;
            });
            let transport = parseFloat(document.getElementById('transport_cost').value) || 0;
            total += transport;
            document.getElementById('total_amount').innerText = total.toFixed(2);
        }
    </script>
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">Edit Breakfast Expense</h1>

    <?php if(isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="date" name="expense_date" value="<?= htmlspecialchars($expense['expense_date']) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300" required>

                <input type="text" name="items_bought_by" value="<?= htmlspecialchars($expense['items_bought_by']) ?>" placeholder="Items Bought By" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">

                <label class="md:col-span-2 font-semibold mt-2">Items & Costs</label>

                <div id="items-container" class="md:col-span-2 flex flex-col gap-2">
                    <?php foreach ($items as $i => $it): ?>
                        <div class="flex gap-2 mb-2">
                            <input type="text" name="items[]" value="<?= htmlspecialchars($it['item']) ?>" class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required>
                            <input type="number" step="0.01" name="costs[]" value="<?= htmlspecialchars($it['cost']) ?>" class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required oninput="calculateTotal()">
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" onclick="addNewItem()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition md:col-span-2">Add New Item</button>

                <input type="number" step="0.01" name="transport_cost" id="transport_cost" value="<?= htmlspecialchars($expense['transport_cost']) ?>" placeholder="Transport Cost" class="border p-2 rounded focus:ring-2 focus:ring-blue-300 md:col-span-1" oninput="calculateTotal()">
            </div>

            <div class="text-right font-bold text-lg mt-2">Total Amount: KES <span id="total_amount"><?= number_format($expense['total_amount'], 2) ?></span></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <input type="text" name="petty_cash_no" value="<?= htmlspecialchars($expense['petty_cash_no'] ?? '') ?>" placeholder="Petty Cash No" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="approved_by" value="<?= htmlspecialchars($expense['approved_by'] ?? '') ?>" placeholder="Approved By" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">

                <select name="payment_status" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                    <option value="Pending" <?= ($expense['payment_status'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Paid" <?= ($expense['payment_status'] ?? '') === 'Paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>

            <div class="flex justify-between mt-6">
                <a href="breakfast_expense.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Back</a>
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">Update Expense</button>
            </div>
        </form>
    </div>
</div>

<script>calculateTotal();</script>
</body>
</html>
