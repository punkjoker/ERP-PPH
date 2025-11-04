<?php 
require 'db_con.php';

// Handle form submission (stock out)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stock_code'], $_POST['quantity_removed'])) {
    $stock_code = $_POST['stock_code'];
    $quantity_removed = $_POST['quantity_removed'];
    $stock_date = $_POST['stock_date'];
    $reason = trim($_POST['reason']);
    $requested_by = trim($_POST['requested_by']);
    $approved_by = trim($_POST['approved_by']);

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

        // Insert into stock_out_history (with reason, requested_by, approved_by)
        $insert = $conn->prepare("INSERT INTO stock_out_history 
            (stock_code, stock_name, quantity_removed, unit_cost, remaining_quantity, stock_date, reason, requested_by, approved_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param(
            "ssdddssss",
            $stock['stock_code'],
            $stock['stock_name'],
            $quantity_removed,
            $stock['unit_cost'],
            $remaining,
            $stock_date,
            $reason,
            $requested_by,
            $approved_by
        );

        if ($insert->execute()) {
            $message = "✅ Stock updated successfully!";
        } else {
            $message = "❌ Error inserting stock out record: " . $conn->error;
        }
    } else {
        $message = "⚠️ Invalid quantity entered!";
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

// Filter stock out history
$where = "1=1";
$params = [];
$types = "";

// Filter by date range
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where .= " AND DATE(stock_date) BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
}

// Filter by stock code
if (!empty($_GET['stock_code'])) {
    $where .= " AND stock_code = ?";
    $params[] = $_GET['stock_code'];
    $types .= "s";
}

// Filter by stock name
if (!empty($_GET['stock_name'])) {
    $where .= " AND stock_name = ?";
    $params[] = $_GET['stock_name'];
    $types .= "s";
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
    <form method="POST" class="bg-white p-6 rounded shadow-md max-w-5xl mb-10">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block font-semibold">Select Stock</label>
          <select id="stock_code" name="stock_code" onchange="populateStockDetails()" required class="w-full border p-2 rounded">
            <option value="">-- Select --</option>
            <?php foreach ($stocks as $s): ?>
              <option value="<?php echo $s['stock_code']; ?>">
                <?php echo $s['stock_name']." (".$s['stock_code'].")"; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block font-semibold">Stock Name</label>
          <input type="text" id="stock_name" readonly class="w-full border p-2 rounded bg-gray-100">
        </div>

        <div>
          <label class="block font-semibold">Unit Cost</label>
          <input type="text" id="unit_cost" readonly class="w-full border p-2 rounded bg-gray-100">
        </div>

        <div>
          <label class="block font-semibold">Current Quantity</label>
          <input type="text" id="current_quantity" readonly class="w-full border p-2 rounded bg-gray-100">
        </div>

        <div>
          <label class="block font-semibold">Date of Stock Out</label>
          <input type="date" name="stock_date" required class="w-full border p-2 rounded">
        </div>

        <div>
          <label class="block font-semibold">Quantity to Remove</label>
          <input type="number" step="0.01" name="quantity_removed" required class="w-full border p-2 rounded">
        </div>

        <div class="col-span-2">
          <label class="block font-semibold">Reason for Stock Removal</label>
          <input type="text" name="reason" placeholder="e.g. Requested by Sales for KWAL" class="w-full border p-2 rounded">
        </div>

        <div>
          <label class="block font-semibold">Requested By</label>
          <input type="text" name="requested_by" placeholder="Name of person/department" class="w-full border p-2 rounded">
        </div>

        <div>
          <label class="block font-semibold">Approved By</label>
          <input type="text" name="approved_by" placeholder="Name of approver" class="w-full border p-2 rounded">
        </div>
      </div>

      <div class="mt-6">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
          Remove Stock
        </button>
      </div>
    </form>

    <!-- Filter Section -->
<div class="bg-white p-4 rounded shadow-md mb-6">
  <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">

    <!-- From Date -->
    <div>
      <label class="block font-semibold text-sm">From Date</label>
      <input type="date" name="from_date" value="<?php echo $_GET['from_date'] ?? ''; ?>"
             class="border p-2 rounded w-full text-sm">
    </div>

    <!-- To Date -->
    <div>
      <label class="block font-semibold text-sm">To Date</label>
      <input type="date" name="to_date" value="<?php echo $_GET['to_date'] ?? ''; ?>"
             class="border p-2 rounded w-full text-sm">
    </div>

    <!-- Filter by Stock Code -->
    <div>
      <label class="block font-semibold text-sm">Stock Code</label>
      <select name="stock_code" class="border p-2 rounded w-full text-sm">
        <option value="">-- All --</option>
        <?php foreach ($stocks as $s): ?>
          <option value="<?php echo $s['stock_code']; ?>"
            <?php echo (isset($_GET['stock_code']) && $_GET['stock_code'] == $s['stock_code']) ? 'selected' : ''; ?>>
            <?php echo $s['stock_code']; ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Filter by Product Name -->
    <div>
      <label class="block font-semibold text-sm">Product Name</label>
      <select name="stock_name" class="border p-2 rounded w-full text-sm">
        <option value="">-- All --</option>
        <?php foreach ($stocks as $s): ?>
          <option value="<?php echo htmlspecialchars($s['stock_name']); ?>"
            <?php echo (isset($_GET['stock_name']) && $_GET['stock_name'] == $s['stock_name']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($s['stock_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Submit -->
    <div>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700 w-full">
        Filter
      </button>
    </div>
  </form>
</div>

    <!-- Stock Out History Table -->
    <div class="bg-white p-4 rounded shadow-md">
  <h2 class="text-lg font-bold mb-3 text-blue-700">Stock Out History</h2>
  <table class="w-full border-collapse border text-xs">
    <thead>
      <tr class="bg-blue-100 text-gray-700">
        <th class="border p-1">#</th>
        <th class="border p-1">Date</th>
        <th class="border p-1">Stock Code</th>
        <th class="border p-1">Stock Name</th>
        <th class="border p-1">Qty Removed</th>
        <th class="border p-1">Unit Cost</th>
        <th class="border p-1">Remaining</th>
        <th class="border p-1">Reason</th>
        <th class="border p-1">Requested By</th>
        <th class="border p-1">Approved By</th>
        <th class="border p-1 text-center">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $count = 1;
      while ($row = $history->fetch_assoc()): ?>
        <tr class="hover:bg-gray-50">
          <td class="border p-1 text-center"><?php echo $count++; ?></td>
          <td class="border p-1"><?php echo $row['stock_date']; ?></td>
          <td class="border p-1"><?php echo $row['stock_code']; ?></td>
          <td class="border p-1"><?php echo $row['stock_name']; ?></td>
          <td class="border p-1 text-center"><?php echo $row['quantity_removed']; ?></td>
          <td class="border p-1 text-right"><?php echo number_format($row['unit_cost'], 2); ?></td>
          <td class="border p-1 text-center"><?php echo $row['remaining_quantity']; ?></td>
          <td class="border p-1"><?php echo htmlspecialchars($row['reason']); ?></td>
          <td class="border p-1"><?php echo htmlspecialchars($row['requested_by']); ?></td>
          <td class="border p-1"><?php echo htmlspecialchars($row['approved_by']); ?></td>
          <td class="border p-1 text-center">
            <a href="view_history.php?stock_code=<?php echo $row['stock_code']; ?>" 
               class="bg-blue-500 text-white px-2 py-0.5 rounded text-xs hover:bg-blue-600 transition">
              View
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
