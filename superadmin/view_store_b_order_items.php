<?php
include 'db_con.php';
$order_id = intval($_GET['id'] ?? 0);

// --- Get order details ---
$order = $conn->query("SELECT * FROM delivery_orders_store_b WHERE id=$order_id")->fetch_assoc();
if (!$order) { die("Order not found"); }

// --- Get order items ---
$items = $conn->query("SELECT * FROM delivery_order_items_store_b WHERE order_id=$order_id");
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>View Delivery Order Items (Store B)</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="p-6 ml-64">
  <a href="create_store_b_order.php" class="text-blue-600 underline mb-4 inline-block">← Back to Orders</a>

  <div class="bg-white p-6 rounded shadow-md mb-6">
    <h2 class="text-2xl font-bold mb-2">🚚 Delivery Order #<?= htmlspecialchars($order['id']) ?></h2>
    <p><strong>Customer:</strong> <?= htmlspecialchars($order['company_name']) ?></p>
    <p><strong>Invoice #:</strong> <?= htmlspecialchars($order['invoice_number']) ?></p>
    <p><strong>Delivery #:</strong> <?= htmlspecialchars($order['delivery_number']) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars($order['original_status']) ?></p>
    <p><strong>Remarks:</strong> <?= nl2br(htmlspecialchars($order['remarks'])) ?></p>
  </div>

  <div class="bg-white p-6 rounded shadow-md">
    <h3 class="text-xl font-semibold mb-3">📦 Items in this Delivery</h3>
    <table class="min-w-full border border-gray-300">
      <thead class="bg-gray-200">
        <tr>
          <th class="py-2 px-3 text-left">Item Name</th>
          
          <th class="py-2 px-3 text-left">Pack Size</th>
          <th class="py-2 px-3 text-left">Quantity Removed</th>
          
          <th class="py-2 px-3 text-left">Unit</th>
    
        </tr>
      </thead>
      <tbody>
        <?php
        if ($items->num_rows === 0) {
            echo "<tr><td colspan='7' class='text-center py-4 text-gray-500'>No items found.</td></tr>";
        } else {
            while ($it = $items->fetch_assoc()) {
                echo "
                <tr class='border-t'>
                  <td class='py-2 px-3'>".htmlspecialchars($it['item_name'])."</td>
                  
                  <td class='py-2 px-3'>".htmlspecialchars($it['pack_size'])."</td>
                  <td class='py-2 px-3'>".htmlspecialchars($it['quantity_removed'])."</td>
                  
                  <td class='py-2 px-3'>".htmlspecialchars($it['unit'])."</td>
                
                </tr>";
            }
        }
        ?>
      </tbody>
    </table>
  </div>
</body>
</html>
