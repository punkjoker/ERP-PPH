<?php
session_start();
include 'db_con.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Handle date filter
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

$where_clause = '';
if (!empty($from_date) && !empty($to_date)) {
    $where_clause = "WHERE DATE(p.created_at) BETWEEN '$from_date' AND '$to_date'";
} elseif (!empty($from_date)) {
    $where_clause = "WHERE DATE(p.created_at) >= '$from_date'";
} elseif (!empty($to_date)) {
    $where_clause = "WHERE DATE(p.created_at) <= '$to_date'";
}

// Fetch all purchase orders with supplier names
$sql = "SELECT p.id, p.po_no, s.supplier_name, p.discount_percentage, p.tax_percentage, 
               p.status, p.created_at 
        FROM po_list p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        $where_clause
        ORDER BY p.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchases List</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
.details-row { display: none; }
</style>
</head>
<body class="bg-gray-100 text-gray-800">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-blue-700">Purchases List</h1>
    <a href="new_purchase.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">+ New Purchase</a>
  </div>

  <!-- ✅ Filter Form -->
  <form method="GET" class="bg-white shadow p-4 mb-6 rounded-lg flex flex-wrap items-end gap-4">
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1">From Date</label>
      <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1">To Date</label>
      <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border rounded px-3 py-2">
    </div>
    <div class="flex gap-2">
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 mt-1">Filter</button>
      <a href="purchases_list.php" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 mt-1">Reset</a>
    </div>
  </form>

  <div class="bg-white shadow-md rounded-lg p-6">
    <table class="min-w-full border border-gray-300 text-sm">
      <thead class="bg-gray-200 text-gray-700">
        <tr>
          <th class="border px-3 py-2 text-left">#</th>
          <th class="border px-3 py-2 text-left">PO No</th>
          <th class="border px-3 py-2 text-left">Supplier</th>
          <th class="border px-3 py-2 text-center">Discount (%)</th>
          <th class="border px-3 py-2 text-center">Tax (%)</th>
          <th class="border px-3 py-2 text-center">Status</th>
          <th class="border px-3 py-2 text-center">Date</th>
          <th class="border px-3 py-2 text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php
      if ($result && $result->num_rows > 0):
          $count = 1;
          while ($row = $result->fetch_assoc()):
      ?>
        <tr class="hover:bg-blue-50">
          <td class="border px-3 py-2"><?= $count++ ?></td>
          <td class="border px-3 py-2 font-semibold text-blue-700"><?= htmlspecialchars($row['po_no']) ?></td>
          <td class="border px-3 py-2"><?= htmlspecialchars($row['supplier_name'] ?? 'N/A') ?></td>
          <td class="border px-3 py-2 text-center"><?= htmlspecialchars($row['discount_percentage']) ?>%</td>
          <td class="border px-3 py-2 text-center"><?= htmlspecialchars($row['tax_percentage']) ?>%</td>
          <td class="border px-3 py-2 text-center">
            <?php
            if ($row['status'] == 0) echo "<span class='text-yellow-600 font-semibold'>Pending</span>";
            elseif ($row['status'] == 1) echo "<span class='text-green-600 font-semibold'>Approved</span>";
            else echo "<span class='text-red-600 font-semibold'>Denied</span>";
            ?>
          </td>
          <td class="border px-3 py-2 text-center"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
          <td class="border px-3 py-2 text-center">
            <button class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs"
                    onclick="toggleDetails(<?= $row['id'] ?>)">
              View Items
            </button>
          </td>
        </tr>

        <!-- Order Items Row -->
        <tr id="details-<?= $row['id'] ?>" class="details-row bg-gray-50">
          <td colspan="8" class="p-4">
            <?php
            $po_id = $row['id'];
            $item_sql = "SELECT manual_name, chemical_code, unit, quantity, unit_price, total
                         FROM order_items WHERE po_id = $po_id";
            $item_result = $conn->query($item_sql);
            if ($item_result && $item_result->num_rows > 0):
            ?>
              <table class="w-full border border-gray-300 mt-2 text-sm">
                <thead class="bg-blue-100 text-blue-800">
                  <tr>
                    <th class="border px-3 py-2 text-left">Item Name</th>
                    <th class="border px-3 py-2 text-left">Item Code</th>
                    <th class="border px-3 py-2 text-center">Unit</th>
                    <th class="border px-3 py-2 text-center">Quantity</th>
                    <th class="border px-3 py-2 text-center">Unit Price</th>
                    <th class="border px-3 py-2 text-center">Total Price</th>
                  </tr>
                </thead>
                <tbody>
                <?php while ($item = $item_result->fetch_assoc()): ?>
                  <tr class="hover:bg-blue-50">
                    <td class="border px-3 py-2"><?= htmlspecialchars($item['manual_name'] ?? '-') ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($item['chemical_code'] ?? '-') ?></td>
                    <td class="border px-3 py-2 text-center"><?= htmlspecialchars($item['unit'] ?? '-') ?></td>
                    <td class="border px-3 py-2 text-center"><?= htmlspecialchars($item['quantity']) ?></td>
                    <td class="border px-3 py-2 text-center"><?= number_format($item['unit_price'], 2) ?></td>
                    <td class="border px-3 py-2 text-center font-semibold text-green-700"><?= number_format($item['total'], 2) ?></td>
                  </tr>
                <?php endwhile; ?>
                </tbody>
              </table>
            <?php else: ?>
              <p class="text-gray-500 text-center py-2">No items found for this PO.</p>
            <?php endif; ?>
          </td>
        </tr>
      <?php
          endwhile;
      else:
      ?>
        <tr><td colspan="8" class="text-center text-gray-500 py-3">No purchase orders found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleDetails(id) {
  const row = document.getElementById('details-' + id);
  row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
}
</script>
</body>
</html>
