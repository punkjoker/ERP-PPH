<?php 
include 'db_con.php';

// Handle adding breakfast expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date = $_POST['expense_date'];
    $items = $_POST['items'];
    $costs = $_POST['costs'];
    $transport_cost = floatval($_POST['transport_cost']);
    $items_bought_by = trim($_POST['items_bought_by']);

    $expense_array = [];
    $total_items = 0;

    for ($i = 0; $i < count($items); $i++) {
        if (trim($items[$i]) != '' && floatval($costs[$i]) > 0) {
            $expense_array[] = ['item' => trim($items[$i]), 'cost' => floatval($costs[$i])];
            $total_items += floatval($costs[$i]);
        }
    }

    $total_amount = $total_items + $transport_cost;
    $items_json = json_encode($expense_array);

    $stmt = $conn->prepare("INSERT INTO breakfast_expense (expense_date, items, total_amount, items_bought_by, transport_cost) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdss", $expense_date, $items_json, $total_amount, $items_bought_by, $transport_cost);
    $stmt->execute();
    $stmt->close();

    $success = "Breakfast expense added successfully!";
}

// Filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Fetch breakfast expenses
$query = "SELECT * FROM breakfast_expense WHERE 1=1";
$params = [];
$types = '';

if ($from_date != '') {
    $query .= " AND expense_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}
if ($to_date != '') {
    $query .= " AND expense_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}
$query .= " ORDER BY expense_date DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$breakfast_expenses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Breakfast Expense</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        let itemCount = 1;
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
            document.getElementsByName('costs[]').forEach(c => {
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

<div class="ml-64 p-6 max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">Daily Breakfast Expense</h1>

    <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Add Breakfast Expense Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="date" name="expense_date" required class="border p-2 rounded focus:ring-2 focus:ring-blue-300">

                <label class="md:col-span-2 font-semibold mt-2">Items & Costs</label>
                <div id="items-container" class="md:col-span-2 flex flex-col gap-2">
                    <div class="flex gap-2 mb-2">
                        <input type="text" name="items[]" placeholder="Item 1" class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required>
                        <input type="number" step="0.01" name="costs[]" placeholder="Cost" class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required oninput="calculateTotal()">
                    </div>
                </div>

                <button type="button" onclick="addNewItem()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition md:col-span-2">Add New Item</button>

                <input type="text" name="items_bought_by" placeholder="Items Bought By" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
                <input type="number" step="0.01" name="transport_cost" id="transport_cost" placeholder="Transport Cost" class="border p-2 rounded focus:ring-2 focus:ring-blue-300" oninput="calculateTotal()">
            </div>

            <div class="text-right font-bold text-lg mt-2">Total Amount: KES <span id="total_amount">0.00</span></div>
            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">Add Breakfast Expense</button>
        </form>
    </div>

    <!-- Breakfast Expenses List -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Breakfast Expenses List</h2>

        <form method="GET" class="flex flex-col md:flex-row gap-4 items-end mb-4">
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Filter</button>
            <a href="breakfast_expense.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Reset</a>
        </form>

        <table class="w-full border border-gray-300 rounded">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border px-3 py-2">#</th>
                    <th class="border px-3 py-2">Expense Date</th>
                    
                    <th class="border px-3 py-2">Items Bought By</th>
                    <th class="border px-3 py-2">Total Amount</th>
                    <th class="border px-3 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($breakfast_expenses)): $count = 1; ?>
                    <?php foreach ($breakfast_expenses as $be): ?>
                        <tr class="<?= ($count % 2 == 0) ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">
                            <td class="border px-3 py-2"><?= $count++ ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($be['expense_date']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($be['items_bought_by']) ?></td>
                            <td class="border px-3 py-2"><?= number_format($be['total_amount'], 2) ?></td>
                            <td class="border px-3 py-2 space-x-2">
                               <!-- Edit Button -->
<a href="edit_breakfast_expense.php?id=<?= $be['breakfast_id'] ?>" 
   class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition">
   Edit
</a>


                                <a href="view_breakfast_expense.php?id=<?= $be['breakfast_id'] ?>" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="border px-3 py-2 text-center text-gray-500">No breakfast expenses found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-lg relative">
    <h2 class="text-xl font-bold mb-4 text-blue-700">Edit Breakfast Expense</h2>
    <form id="editForm" method="POST" action="update_breakfast_expense.php">
      <input type="hidden" name="id" id="edit_id">

      <div class="mb-3">
        <label class="block font-semibold mb-1">Expense Date</label>
        <input type="date" name="expense_date" id="edit_date" required class="border p-2 w-full rounded">
      </div>

      <div class="mb-3">
        <label class="block font-semibold mb-1">Items Bought By</label>
        <input type="text" name="items_bought_by" id="edit_bought_by" required class="border p-2 w-full rounded">
      </div>

      <div class="mb-3">
        <label class="block font-semibold mb-1">Total Amount</label>
        <input type="number" step="0.01" name="total_amount" id="edit_amount" required class="border p-2 w-full rounded">
      </div>

      <div class="flex justify-end space-x-2 mt-4">
        <button type="button" onclick="closeEditModal()" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">Cancel</button>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update</button>
      </div>
    </form>

    <button onclick="closeEditModal()" class="absolute top-2 right-3 text-gray-600 hover:text-black">&times;</button>
  </div>
</div>

<script>
  function openEditModal(id, date, bought_by, total) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_date').value = date;
    document.getElementById('edit_bought_by').value = bought_by;
    document.getElementById('edit_amount').value = total;
    document.getElementById('editModal').classList.remove('hidden');
  }

  function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
  }
</script>

</body>
</html>
