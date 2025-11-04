<?php
include 'db_con.php';
include 'navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>All Delivery Order Items</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<div class="p-6 ml-64">
  <h1 class="text-3xl font-bold mb-6">📦 All Delivery Order Items</h1>

  <!-- ✅ Date Filter Form -->
  <form method="GET" class="flex flex-wrap items-center gap-4 mb-6 bg-white p-4 rounded-lg shadow">
    <div>
      <label for="start_date" class="block text-sm font-medium text-gray-700">From:</label>
      <input type="date" id="start_date" name="start_date"
             value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>"
             class="border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
    </div>

    <div>
      <label for="end_date" class="block text-sm font-medium text-gray-700">To:</label>
      <input type="date" id="end_date" name="end_date"
             value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>"
             class="border border-gray-300 rounded-md px-3 py-2 focus:ring-blue-500 focus:border-blue-500">
    </div>

    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg mt-5">
      Filter
    </button>
<a href="download_all_delivery_order_items.php?start_date=<?= urlencode($_GET['start_date'] ?? '') ?>&end_date=<?= urlencode($_GET['end_date'] ?? '') ?>"
   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow">
   ⬇ Download Report
</a>

    <a href="all_delivery_order_items.php" class="ml-2 text-blue-600 mt-5 underline">Reset</a>
  </form>

  <?php
  // ✅ Prepare the base SQL
  $sql = "
    SELECT 
      d.company_name,
      o.invoice_number,
      o.delivery_number,
      i.item_name,
      i.source_table,
      i.quantity_removed,
      i.unit,
      i.created_at
    FROM delivery_order_items i
    JOIN delivery_orders o ON i.order_id = o.id
    JOIN delivery_details d ON o.delivery_id = d.id
  ";

  // ✅ Add date filtering logic
  $params = [];
  $conditions = [];

  if (!empty($_GET['start_date'])) {
      $conditions[] = "DATE(i.created_at) >= ?";
      $params[] = $_GET['start_date'];
  }

  if (!empty($_GET['end_date'])) {
      $conditions[] = "DATE(i.created_at) <= ?";
      $params[] = $_GET['end_date'];
  }

  if ($conditions) {
      $sql .= " WHERE " . implode(" AND ", $conditions);
  }

  $sql .= " ORDER BY i.created_at DESC";

  // ✅ Execute the query safely
  $stmt = $conn->prepare($sql);
  if ($params) {
      $types = str_repeat("s", count($params));
      $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  ?>

 <!-- ✅ Display Table -->
<div class="bg-white shadow-md rounded-lg p-6 overflow-x-auto">
  <table class="min-w-full border border-gray-200 text-sm">
    <thead class="bg-blue-600 text-white">
      <tr>
        <th class="px-3 py-1.5 text-left">Company Name</th>
        <th class="px-3 py-1.5 text-left">Invoice No.</th>
        <th class="px-3 py-1.5 text-left">Delivery No.</th>
        <th class="px-3 py-1.5 text-left">Item Name</th>
       
        <th class="px-3 py-1.5 text-right">Quantity</th>
        <th class="px-3 py-1.5 text-left">Unit</th>
        <th class="px-3 py-1.5 text-left">Delivered On</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr class="bg-white hover:bg-gray-50 shadow-sm transition-all duration-150">
            <td class="px-3 py-1"><?= htmlspecialchars($row['company_name']) ?></td>
            <td class="px-3 py-1"><?= htmlspecialchars($row['invoice_number']) ?></td>
            <td class="px-3 py-1"><?= htmlspecialchars($row['delivery_number']) ?></td>
            <td class="px-3 py-1"><?= htmlspecialchars($row['item_name']) ?></td>

            <td class="px-3 py-1 text-right"><?= number_format($row['quantity_removed'], 2) ?></td>
            <td class="px-3 py-1"><?= htmlspecialchars($row['unit']) ?></td>
            <td class="px-3 py-1 text-gray-600"><?= htmlspecialchars($row['created_at']) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="8" class="text-center text-gray-500 py-3">No delivery items found for the selected dates.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div>

</body>
</html>

<?php 
$stmt->close();
$conn->close();
?>
