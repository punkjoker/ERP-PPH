<?php
include 'db_con.php';

// --- Validate Delivery ID ---
$delivery_id = intval($_GET['id'] ?? 0);
if ($delivery_id <= 0) {
    die("Invalid delivery ID.");
}

// --- Fetch Delivery Batch Info ---
$stmt = $conn->prepare("SELECT * FROM order_deliveries_store_b WHERE id = ?");
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$delivery = $stmt->get_result()->fetch_assoc();

if (!$delivery) {
    die("Delivery batch not found.");
}

// --- Fetch Linked Orders and Destinations ---
$query = "
    SELECT 
        odi.destination,
        do.id AS order_id,
        do.invoice_number,
        do.delivery_number,
        do.company_name,
        do.remarks,
        do.original_status
    FROM order_delivery_items_store_b odi
    JOIN delivery_orders_store_b do 
        ON odi.delivery_order_id = do.id
    WHERE odi.delivery_id = ?
";
$stmt2 = $conn->prepare($query);
$stmt2->bind_param('i', $delivery_id);
$stmt2->execute();
$orders = $stmt2->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Store B Delivery</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-8 pt-24">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-3xl font-bold text-gray-800">🚚 Store B Delivery Details</h2>
      <a href="store_b_order_deliveries.php" 
         class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
         ← Back to Deliveries
      </a>
    </div>

    <!-- Delivery Info -->
    <div class="bg-white shadow-lg rounded-2xl p-6 mb-8">
      <h3 class="text-xl font-semibold text-gray-700 mb-4">Batch Information</h3>
      <div class="grid md:grid-cols-3 gap-4">
        <div><strong>Delivery Day:</strong> <?= htmlspecialchars($delivery['delivery_day']) ?></div>
        <div><strong>Delivery Date:</strong> <?= htmlspecialchars($delivery['delivery_date']) ?></div>
        <div>
          <strong>Status:</strong>
          <span class="px-3 py-1 rounded-full text-sm 
            <?= $delivery['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-700' :
               ($delivery['status'] === 'Completed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') ?>">
            <?= htmlspecialchars($delivery['status']) ?>
          </span>
        </div>
      </div>
      <p class="text-gray-500 text-sm mt-2">Created on: <?= htmlspecialchars($delivery['created_at']) ?></p>
    </div>

    <!-- Linked Orders -->
    <div class="bg-white shadow-lg rounded-2xl p-6">
      <h3 class="text-xl font-semibold text-gray-700 mb-4">📦 Linked Orders</h3>

      <?php if ($orders->num_rows > 0): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="py-2 px-4 border-b text-left">#</th>
              <th class="py-2 px-4 border-b text-left">Destination</th>
              <th class="py-2 px-4 border-b text-left">Company</th>
              <th class="py-2 px-4 border-b text-left">Invoice No</th>
              <th class="py-2 px-4 border-b text-left">Delivery No</th>
              <th class="py-2 px-4 border-b text-left">Remarks</th>
              <th class="py-2 px-4 border-b text-left">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php $count = 1; while ($o = $orders->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50">
              <td class="py-2 px-4 border-b"><?= $count++ ?></td>
              <td class="py-2 px-4 border-b"><?= htmlspecialchars($o['destination']) ?></td>
              <td class="py-2 px-4 border-b"><?= htmlspecialchars($o['company_name']) ?></td>
              <td class="py-2 px-4 border-b"><?= htmlspecialchars($o['invoice_number']) ?></td>
              <td class="py-2 px-4 border-b"><?= htmlspecialchars($o['delivery_number']) ?></td>
              <td class="py-2 px-4 border-b"><?= htmlspecialchars($o['remarks'] ?? '-') ?></td>
              <td class="py-2 px-4 border-b">
                <span class="px-3 py-1 rounded-full text-sm 
                  <?= $o['original_status'] === 'Pending' ? 'bg-yellow-100 text-yellow-700' :
                     ($o['original_status'] === 'Completed' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700') ?>">
                  <?= htmlspecialchars($o['original_status']) ?>
                </span>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="text-gray-500 text-center py-4">No orders linked to this delivery batch yet.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
