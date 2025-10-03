<?php
require 'db_con.php';

// Handle form submission (stock out)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stock_code'], $_POST['quantity_removed'])) {
    $stock_code = $_POST['stock_code'];
    $quantity_removed = $_POST['quantity_removed'];

    // Get current stock
    $stmt = $conn->prepare("SELECT * FROM stock_in WHERE stock_code = ?");
    $stmt->bind_param("s", $stock_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $stock = $result->fetch_assoc();

    if ($stock && $quantity_removed > 0 && $quantity_removed <= $stock['quantity']) {
        $remaining = $stock['quantity'] - $quantity_removed;

        // Update stock_in
        $update = $conn->prepare("UPDATE stock_in SET quantity = ? WHERE stock_code = ?");
        $update->bind_param("ds", $remaining, $stock_code);
        $update->execute();

        // Insert into stock_out_history
       $stock_date = $_POST['stock_date'];

$insert = $conn->prepare("INSERT INTO stock_out_history 
    (stock_code, stock_name, quantity_removed, unit_cost, remaining_quantity, stock_date) 
    VALUES (?, ?, ?, ?, ?, ?)");
$insert->bind_param(
    "ssddds",
    $stock['stock_code'],
    $stock['stock_name'],
    $quantity_removed,
    $stock['unit_cost'],
    $remaining,
    $stock_date
);

        $insert->execute();

        $message = "Stock updated successfully!";
    } else {
        $message = "Invalid quantity entered!";
    }
}

// Fetch all stock for dropdown
$stocks = [];
$result = $conn->query("SELECT * FROM stock_in ORDER BY stock_name ASC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $stocks[] = $row;
    }
}

// Filter stock out history
$where = "1=1";
$params = [];
$types = "";

if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where .= " AND DATE(stock_date) BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
}

$query = "SELECT * FROM stock_out_history WHERE $where ORDER BY stock_date DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$history = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Stock Out</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function populateStockDetails() {
        let stocks = <?php echo json_encode($stocks); ?>;
        let selectedCode = document.getElementById('stock_code').value;

        let stock = stocks.find(s => s.stock_code === selectedCode);

        if (stock) {
            document.getElementById('stock_name').value = stock.stock_name;
            document.getElementById('unit_cost').value = stock.unit_cost;
            document.getElementById('current_quantity').value = stock.quantity;
        }
    }
  </script>
</head>
<body class="bg-gray-50 flex">
  <?php include 'navbar.php'; ?>

  <div class="flex-1 ml-64 p-10">
    <h1 class="text-2xl font-bold text-blue-700 mb-6">Stock Out</h1>

    <?php if (!empty($message)): ?>
      <p class="mb-4 text-green-600 font-semibold"><?php echo $message; ?></p>
    <?php endif; ?>

   <!-- Stock Out Form -->
<form method="POST" class="bg-white p-6 rounded shadow-md max-w-4xl mb-10">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Column 1 -->
    <div class="mb-4">
      <label class="block font-semibold">Select Stock</label>
      <select id="stock_code" name="stock_code" onchange="populateStockDetails()" required
              class="w-full border p-2 rounded">
        <option value="">-- Select --</option>
        <?php foreach ($stocks as $s): ?>
          <option value="<?php echo $s['stock_code']; ?>">
            <?php echo $s['stock_name']." (".$s['stock_code'].")"; ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-4">
      <label class="block font-semibold">Stock Name</label>
      <input type="text" id="stock_name" readonly class="w-full border p-2 rounded bg-gray-100">
    </div>

    <div class="mb-4">
      <label class="block font-semibold">Unit Cost</label>
      <input type="text" id="unit_cost" readonly class="w-full border p-2 rounded bg-gray-100">
    </div>

    <!-- Column 2 -->
    <div class="mb-4">
      <label class="block font-semibold">Current Quantity</label>
      <input type="text" id="current_quantity" readonly class="w-full border p-2 rounded bg-gray-100">
    </div>

    <div class="mb-4">
      <label class="block font-semibold">Date of Stock Out</label>
      <input type="date" name="stock_date" required class="w-full border p-2 rounded">
    </div>

    <div class="mb-4">
      <label class="block font-semibold">Quantity to Remove</label>
      <input type="number" step="0.01" name="quantity_removed" required class="w-full border p-2 rounded">
    </div>
  </div>

  <div class="mt-6">
    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
      Remove Stock
    </button>
  </div>
</form>

    <!-- Filter Section -->
    <div class="bg-white p-6 rounded shadow-md mb-6">
      <form method="GET" class="flex gap-4 items-end">
        <div>
          <label class="block font-semibold">From Date</label>
          <input type="date" name="from_date" value="<?php echo $_GET['from_date'] ?? ''; ?>"
                 class="border p-2 rounded">
        </div>
        <div>
          <label class="block font-semibold">To Date</label>
          <input type="date" name="to_date" value="<?php echo $_GET['to_date'] ?? ''; ?>"
                 class="border p-2 rounded">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Filter
        </button>
      </form>
    </div>

    <!-- Stock Out History Table -->
    <div class="bg-white p-6 rounded shadow-md">
      <h2 class="text-lg font-bold mb-4 text-blue-700">Stock Out History</h2>
      <table class="w-full border-collapse border">
        <thead>
          <tr class="bg-blue-100">
            <th class="border p-2">Date</th>
            <th class="border p-2">Stock Code</th>
            <th class="border p-2">Stock Name</th>
            <th class="border p-2">Quantity Removed</th>
            <th class="border p-2">Unit Cost</th>
            <th class="border p-2">Remaining Qty</th>
            <th class="border p-2">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $history->fetch_assoc()): ?>
            <tr>
              <td class="border p-2"><?php echo $row['stock_date']; ?></td>
              <td class="border p-2"><?php echo $row['stock_code']; ?></td>
              <td class="border p-2"><?php echo $row['stock_name']; ?></td>
              <td class="border p-2"><?php echo $row['quantity_removed']; ?></td>
              <td class="border p-2"><?php echo number_format($row['unit_cost'], 2); ?></td>
              <td class="border p-2"><?php echo $row['remaining_quantity']; ?></td>
              <td class="border p-2 text-center">
                <a href="view_history.php?stock_code=<?php echo $row['stock_code']; ?>" 
                   class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                  View History
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
