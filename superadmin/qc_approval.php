<?php
session_start();
require 'db_con.php';

// Filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$search_name = $_GET['search_name'] ?? '';

$query = "SELECT * FROM chemicals_in WHERE 1=1";

if (!empty($from_date) && !empty($to_date)) {
    $query .= " AND DATE(date_added) BETWEEN '" . $conn->real_escape_string($from_date) . "' 
                AND '" . $conn->real_escape_string($to_date) . "'";
}
if (!empty($search_name)) {
    $query .= " AND chemical_name LIKE '%" . $conn->real_escape_string($search_name) . "%'";
}

$query .= " ORDER BY date_added DESC";
$chemicals = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Inspect Chemicals In</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen">

<?php include 'navbar.php'; ?>

<div class="max-w-7xl ml-64 mx-auto mt-24 p-6 bg-white rounded-xl shadow-lg">
  <!-- Page Title -->
  <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Inspect Chemicals In</h2>

  <!-- Filters -->
  <form method="GET" class="flex flex-wrap gap-4 mb-6">
    <div>
      <label class="text-sm">From:</label>
      <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border rounded px-2 py-1">
    </div>
    <div>
      <label class="text-sm">To:</label>
      <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border rounded px-2 py-1">
    </div>
    <div>
      <label class="text-sm">Chemical Name:</label>
      <input type="text" name="search_name" value="<?= htmlspecialchars($search_name) ?>" placeholder="Search..." class="border rounded px-2 py-1">
    </div>
    <div class="flex items-end">
      <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700">Filter</button>
    </div>
  </form>

  <!-- Table -->
  <div class="overflow-x-auto">
    <table class="w-full border text-sm">
      <thead class="bg-blue-100">
        <tr>
          <th class="border px-2 py-1">Chemical Name</th>
          <th class="border px-2 py-1">RM LOT NO</th>
          <th class="border px-2 py-1">Std Qty</th>
          <th class="border px-2 py-1">Remaining Qty</th>
          <th class="border px-2 py-1">Total Cost</th>
          <th class="border px-2 py-1">Unit Price</th>
          <th class="border px-2 py-1">Date Added</th>
          <th class="border px-2 py-1">Status</th>
          <th class="border px-2 py-1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($chemicals->num_rows > 0): ?>
          <?php while ($row = $chemicals->fetch_assoc()): ?>
            <tr>
              <td class="border px-2 py-1"><?= htmlspecialchars($row['chemical_name']) ?></td>
              <td class="border px-2 py-1"><?= htmlspecialchars($row['rm_lot_no']) ?></td>
              <td class="border px-2 py-1"><?= $row['std_quantity'] ?></td>
              <td class="border px-2 py-1 text-green-700 font-semibold"><?= $row['remaining_quantity'] ?></td>
              <td class="border px-2 py-1"><?= number_format($row['total_cost'], 2) ?></td>
              <td class="border px-2 py-1"><?= number_format($row['unit_price'], 2) ?></td>
              <td class="border px-2 py-1"><?= $row['date_added'] ?></td>
              <td class="border px-2 py-1">
                <?php if ($row['status'] == 'Pending'): ?>
                  <span class="bg-yellow-200 text-yellow-800 px-2 py-1 rounded">Pending</span>
                <?php elseif ($row['status'] == 'Approved'): ?>
                  <span class="bg-green-200 text-green-800 px-2 py-1 rounded">Approved</span>
                <?php else: ?>
                  <span class="bg-red-200 text-red-800 px-2 py-1 rounded">Rejected</span>
                <?php endif; ?>
              </td>
              <td class="border px-2 py-1 flex gap-2">
                
                <a href="view_analysis.php?id=<?= $row['id'] ?>" class="bg-gray-600 text-white px-2 py-1 rounded hover:bg-gray-700 text-xs">View Analysis</a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="9" class="text-center text-gray-500 py-2">No chemicals found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
