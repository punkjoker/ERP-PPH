<?php
include 'db_con.php';
$order_id = intval($_GET['id'] ?? 0);

// Fetch order info
$order = $conn->query("
  SELECT o.*, d.company_name
  FROM delivery_orders o
  JOIN delivery_details d ON o.delivery_id = d.id
  WHERE o.id = $order_id
")->fetch_assoc();

// Fetch items
$items = $conn->query("
  SELECT * FROM delivery_order_items WHERE order_id = $order_id
");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>View Delivery Items</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-6">
    <?php include 'navbar.php'; ?>
    <div class="p-6 ml-64">
  <div class="bg-white shadow-md rounded-lg p-6">
    <h1 class="text-2xl font-bold mb-4">📋 Delivery Order Details</h1>
    <p><strong>Company:</strong> <?= htmlspecialchars($order['company_name']) ?></p>
    <p><strong>Invoice #:</strong> <?= htmlspecialchars($order['invoice_number']) ?></p>
    <p><strong>Delivery #:</strong> <?= htmlspecialchars($order['delivery_number']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($order['original_status']) ?></p>
    <p><strong>Created At:</strong> <?= htmlspecialchars($order['created_at']) ?></p>

    <h2 class="text-xl font-semibold mt-6 mb-2">Items</h2>
    <table class="w-full border-collapse">
      <thead class="bg-gray-200">
        <tr>
          <th class="py-2 px-3 text-left">Item Name</th>
          <th class="py-2 px-3 text-left">Source</th>
          <th class="py-2 px-3 text-left">Qty Removed</th>
          <th class="py-2 px-3 text-left">Remaining Qty</th>
          <th class="py-2 px-3 text-left">Unit</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($i = $items->fetch_assoc()): ?>
        <tr class="border-b">
          <td class="py-2 px-3"><?= htmlspecialchars($i['item_name']) ?></td>
          <td class="py-2 px-3"><?= htmlspecialchars($i['source_table']) ?></td>
          <td class="py-2 px-3"><?= htmlspecialchars($i['quantity_removed']) ?></td>
          <td class="py-2 px-3"><?= htmlspecialchars($i['remaining_quantity']) ?></td>
          <td class="py-2 px-3"><?= htmlspecialchars($i['unit']) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <a href="create_order_delivery.php" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded">⬅ Back</a>
  </div>
</body>
</html>
