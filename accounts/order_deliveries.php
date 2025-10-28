<?php
include 'db_con.php';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_day = trim($_POST['delivery_day']);
    $delivery_date = $_POST['delivery_date'];
    $status = 'Pending';

    // 1️⃣ Insert delivery batch
    $stmt = $conn->prepare("INSERT INTO order_deliveries (delivery_day, delivery_date, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $delivery_day, $delivery_date, $status);
    $stmt->execute();
    $batch_id = $conn->insert_id;

    // 2️⃣ Link selected orders
    if (!empty($_POST['destination']) && !empty($_POST['delivery_order'])) {
        foreach ($_POST['destination'] as $i => $destination) {
            $delivery_order_id = intval($_POST['delivery_order'][$i] ?? 0);

            if (!empty($destination) && $delivery_order_id > 0) {
                // Link order to delivery
                $stmt2 = $conn->prepare("
                    INSERT INTO order_delivery_items (delivery_id, destination, delivery_order_id)
                    VALUES (?, ?, ?)
                ");
                $stmt2->bind_param("isi", $batch_id, $destination, $delivery_order_id);
                $stmt2->execute();

                // Update original delivery order status
                $conn->query("UPDATE delivery_orders SET original_status = 'Assigned' WHERE id = $delivery_order_id");
            }
        }
    }

    echo "<script>alert('✅ Delivery batch created successfully!'); window.location.href='".$_SERVER['PHP_SELF']."';</script>";
    exit;
}

// --- Fetch existing batches ---
$deliveries = $conn->query("SELECT * FROM order_deliveries ORDER BY created_at DESC");

// --- Fetch pending delivery orders ---
$pending_orders = $conn->query("
  SELECT id, invoice_number, delivery_number
  FROM delivery_orders
  WHERE original_status = 'Pending'
  ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Deliveries</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-8 pt-24">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">🚚 Manage Order Deliveries</h2>

    <!-- Delivery Form -->
    <form method="post" class="bg-white shadow-lg rounded-2xl p-6 mb-10">
      <div class="grid md:grid-cols-2 gap-6 mb-4">
        <div>
          <label class="font-semibold text-gray-700">Delivery Day</label>
          <input type="text" name="delivery_day" required placeholder="e.g. Monday Dispatch"
                 class="w-full border rounded-md p-2 focus:ring focus:ring-blue-300 mt-1">
        </div>

        <div>
          <label class="font-semibold text-gray-700">Delivery Date</label>
          <input type="date" name="delivery_date" required
                 class="w-full border rounded-md p-2 focus:ring focus:ring-blue-300 mt-1">
        </div>
      </div>

      <h3 class="text-lg font-semibold text-gray-700 mb-2">Destinations & Linked Orders</h3>

      <div id="delivery-container">
        <div class="grid md:grid-cols-2 gap-4 mb-3 delivery-row">
          <input type="text" name="destination[]" placeholder="Destination" class="border rounded-md p-2" required>

          <select name="delivery_order[]" class="border rounded-md p-2" required>
            <option value="">-- Select Pending Delivery Order --</option>
            <?php if ($pending_orders && $pending_orders->num_rows > 0): ?>
              <?php while ($o = $pending_orders->fetch_assoc()): ?>
                <option value="<?= $o['id'] ?>">
                  <?= htmlspecialchars("Invoice #{$o['invoice_number']} - Delivery #{$o['delivery_number']}") ?>
                </option>
              <?php endwhile; ?>
            <?php else: ?>
              <option value="">No pending delivery orders available</option>
            <?php endif; ?>
          </select>
        </div>
      </div>

      <button type="button" id="add-delivery" class="bg-green-600 text-white px-3 py-2 rounded-md shadow hover:bg-green-700 transition mb-4">
        + Add More
      </button>

      <div>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md shadow hover:bg-blue-700 transition">
          Save Delivery Batch
        </button>
      </div>
    </form>

    <!-- Delivery List -->
    <div class="bg-white shadow-lg rounded-2xl p-6">
      <h3 class="text-xl font-semibold mb-4 text-gray-700">📦 Delivery Batches</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="py-2 px-4 border-b text-left">#</th>
              <th class="py-2 px-4 border-b text-left">Delivery Day</th>
              <th class="py-2 px-4 border-b text-left">Delivery Date</th>
              <th class="py-2 px-4 border-b text-left">Status</th>
              <th class="py-2 px-4 border-b text-left">Created</th>
              <th class="py-2 px-4 border-b text-left">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            if ($deliveries->num_rows > 0): 
              $count = 1;
              while ($row = $deliveries->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50">
                <td class="py-2 px-4 border-b"><?= $count++ ?></td>
                <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['delivery_day']) ?></td>
                <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['delivery_date']) ?></td>
                <td class="py-2 px-4 border-b">
                  <span class="px-3 py-1 rounded-full text-sm 
                    <?= $row['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' ?>">
                    <?= htmlspecialchars($row['status']) ?>
                  </span>
                </td>
                <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['created_at']) ?></td>
                <td class="py-2 px-4 border-b">
                  <a href="view_order_delivery.php?id=<?= $row['id'] ?>" 
                     class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600">View</a>
                </td>
              </tr>
              <?php endwhile;
            else: ?>
              <tr><td colspan="6" class="text-center py-4 text-gray-500">No deliveries recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
  // Clone new row
  document.getElementById('add-delivery').addEventListener('click', () => {
    const container = document.getElementById('delivery-container');
    const row = document.querySelector('.delivery-row').cloneNode(true);
    row.querySelectorAll('input, select').forEach(el => el.value = '');
    container.appendChild(row);
  });
  </script>

</body>
</html>
