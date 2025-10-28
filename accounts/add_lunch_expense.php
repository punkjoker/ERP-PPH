<?php
include 'db_con.php';

// Handle adding lunch expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $week_no = date('W', strtotime($start_date)); // get week number
    $items = $_POST['items']; // array of items
    $costs = $_POST['costs']; // array of costs

    $transport_cost = floatval($_POST['transport_cost']);
    $items_bought_by = trim($_POST['items_bought_by']);

    $expense_array = [];
    $total_items = 0;
    for($i=0;$i<count($items);$i++){
        if(trim($items[$i]) != '' && floatval($costs[$i]) > 0){
            $expense_array[] = ['item'=>trim($items[$i]), 'cost'=>floatval($costs[$i])];
            $total_items += floatval($costs[$i]);
        }
    }

    $total_amount = $total_items + $transport_cost;
    $items_json = json_encode($expense_array);

    $stmt = $conn->prepare("INSERT INTO lunch_expense (week_no,start_date,end_date,items,total_amount,items_bought_by,transport_cost) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("isssdss", $week_no, $start_date, $end_date, $items_json, $total_amount, $items_bought_by, $transport_cost);
    $stmt->execute();
    $stmt->close();

    $success = "Lunch expense added successfully!";
}

// Filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Fetch lunch expenses
$query = "SELECT * FROM lunch_expense WHERE 1=1";
$params = [];
$types = '';

if($from_date != ''){
    $query .= " AND start_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}
if($to_date != ''){
    $query .= " AND end_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}
$query .= " ORDER BY start_date DESC";

$stmt = $conn->prepare($query);
if(!empty($params)){
    $stmt->bind_param($types,...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$lunch_expenses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Weekly Lunch Expense</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        let itemCount = 1;
        function addNewItem(){
            itemCount++;
            const container = document.getElementById('items-container');
            const div = document.createElement('div');
            div.classList.add('flex','gap-2','mb-2');
            div.innerHTML = `
                <input type="text" name="items[]" placeholder="Item ${itemCount}" class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required>
                <input type="number" step="0.01" name="costs[]" placeholder="Cost" class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required oninput="calculateTotal()">
            `;
            container.appendChild(div);
        }

        function calculateTotal(){
            let total = 0;
            document.getElementsByName('costs[]').forEach(c => {
                let val = parseFloat(c.value);
                if(!isNaN(val)) total += val;
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
    <h1 class="text-3xl font-bold mb-6 text-blue-700">Add Weekly Lunch Expense</h1>

    <?php if(isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Add Lunch Expense Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="date" name="start_date" id="start_date" class="border p-2 rounded focus:ring-2 focus:ring-blue-300" required>
                <input type="date" name="end_date" id="end_date" class="border p-2 rounded focus:ring-2 focus:ring-blue-300" required>
                <label class="md:col-span-2 font-semibold mt-2">Items & Costs</label>
                <div id="items-container" class="md:col-span-2 flex flex-col gap-2">
                    <div class="flex gap-2 mb-2">
                        <input type="text" name="items[]" placeholder="Item 1" class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required>
                        <input type="number" step="0.01" name="costs[]" placeholder="Cost" class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required oninput="calculateTotal()">
                    </div>
                </div>
                <button type="button" onclick="addNewItem()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition md:col-span-2">Add New Item</button>

                <input type="text" name="items_bought_by" placeholder="Items Bought By" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300 md:col-span-1">
                <input type="number" step="0.01" name="transport_cost" id="transport_cost" placeholder="Transport Cost" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300 md:col-span-1" oninput="calculateTotal()">
            </div>

            <div class="text-right font-bold text-lg mt-2">Total Amount: KES <span id="total_amount">0.00</span></div>

            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">Add Lunch Expense</button>
        </form>
    </div>

    <!-- Lunch Expenses List -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Lunch Expenses List</h2>

        <!-- Filters -->
        <form method="GET" class="flex flex-col md:flex-row gap-4 items-end mb-4">
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Filter</button>
            <a href="add_lunch_expense.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Reset</a>
        </form>

        <table class="w-full border border-gray-300 rounded">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border px-3 py-2">#</th>
                    <th class="border px-3 py-2">Week No</th>
                    <th class="border px-3 py-2">From Date</th>
                    <th class="border px-3 py-2">To Date</th>
                    <th class="border px-3 py-2">Items Bought By</th>
                    <th class="border px-3 py-2">Total Amount</th>
                    <th class="border px-3 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($lunch_expenses)): $count=1; ?>
                    <?php foreach($lunch_expenses as $le): ?>
                        <tr class="<?= ($count % 2 == 0) ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">
                            <td class="border px-3 py-2"><?= $count++ ?></td>
                            <td class="border px-3 py-2"><?= $le['week_no'] ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($le['start_date']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($le['end_date']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($le['items_bought_by']) ?></td>
                            <td class="border px-3 py-2"><?= number_format($le['total_amount'],2) ?></td>
                            <td class="border px-3 py-2 space-x-2">
                                <a href="edit_lunch_expense.php?id=<?= $le['lunch_id'] ?>" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition">Edit</a>
                                <a href="view_lunch_expense.php?id=<?= $le['lunch_id'] ?>" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="border px-3 py-2 text-center text-gray-500">No lunch expenses found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
