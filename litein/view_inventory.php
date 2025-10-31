<?php
require 'db_con.php';

// Handle filters
$where = "1=1";
$params = [];
$types = "";

// Date filter
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where .= " AND DATE(stock_date) BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
}

// Stock name search
if (!empty($_GET['search_name'])) {
    $where .= " AND stock_name LIKE ?";
    $params[] = "%" . $_GET['search_name'] . "%";
    $types .= "s";
}

// Fetch inventory
$query = "SELECT * FROM stock_in WHERE $where ORDER BY stock_name ASC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$inventory = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Inventory</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">

  <div class="flex">
    <!-- Navbar fixed left -->
    <div class="fixed top-0 left-0 h-full w-64 bg-white shadow-lg z-10">
      <?php include 'navbar.php'; ?>
    </div>

    <!-- Page Content -->
    <div class="flex-1 ml-64 p-6 mt-16">

      <!-- Header Section -->
      <div class="bg-white p-6 rounded-xl shadow-lg">
        <div class="mb-6 border-b pb-4">
          <h1 class="text-2xl font-bold text-center text-blue-700 mb-2">STOCK CARDS - QF 18</h1>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <p><strong>EFFECTIVE DATE:</strong> 01/11/2024</p>
            <p><strong>ISSUE DATE:</strong> 25/10/2024</p>
            <p><strong>REVIEW DATE:</strong> 10/2027</p>
            <p><strong>ISSUE NO:</strong> 007</p>
            <p><strong>REVISION NO:</strong> 006</p>
            <p><strong>MANUAL NO:</strong> LYNNTECH-QP-22</p>
          </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="flex flex-wrap gap-4 items-end mb-6">
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
      <div>
        <label class="block font-semibold">Search Stock Name</label>
        <input type="text" name="search_name" placeholder="Enter stock name"
               value="<?php echo $_GET['search_name'] ?? ''; ?>"
               class="border p-2 rounded">
      </div>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        Filter
      </button>
    </form>

    <!-- Inventory Table -->
    <div class="overflow-x-auto">
      <table class="w-full border-collapse border text-sm">
        <thead>
          <tr class="bg-blue-100 text-left">
            <th class="border p-2">Stock Code</th>
            <th class="border p-2">Stock Name</th>
            <th class="border p-2">Original Qty</th>
            <th class="border p-2">Remaining Balance</th>
            <th class="border p-2">Unit Cost</th>
            <th class="border p-2">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($inventory->num_rows > 0): ?>
            <?php while ($row = $inventory->fetch_assoc()): ?>
              <tr>
                <td class="border p-2"><?php echo $row['stock_code']; ?></td>
                <td class="border p-2"><?php echo $row['stock_name']; ?></td>
                <td class="border p-2"><?php echo $row['original_quantity']; ?></td>
                <td class="border p-2"><?php echo $row['quantity']; ?></td>
                <td class="border p-2"><?php echo number_format($row['unit_cost'], 2); ?></td>
                <td class="border p-2 text-center">
                  <a href="view_history.php?stock_code=<?php echo urlencode($row['stock_code']); ?>"
                     class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                    View History
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center border p-4 text-gray-500">No inventory found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
