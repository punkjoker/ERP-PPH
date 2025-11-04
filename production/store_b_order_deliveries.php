<?php
include 'db_con.php';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_day = trim($_POST['delivery_day']);
    $delivery_date = $_POST['delivery_date'];
    $status = 'Pending';

    // 1️⃣ Insert delivery batch into order_deliveries_store_b
    $stmt = $conn->prepare("
        INSERT INTO order_deliveries_store_b (delivery_day, delivery_date, status)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("sss", $delivery_day, $delivery_date, $status);
    $stmt->execute();
    $batch_id = $conn->insert_id;

    // 2️⃣ Link selected orders from store_b tables
    if (!empty($_POST['destination']) && !empty($_POST['delivery_order'])) {
        foreach ($_POST['destination'] as $i => $destination) {
            $delivery_order_id = intval($_POST['delivery_order'][$i] ?? 0);

            if (!empty($destination) && $delivery_order_id > 0) {
                // Insert link into order_delivery_items_store_b
                $stmt2 = $conn->prepare("
                    INSERT INTO order_delivery_items_store_b (delivery_id, destination, delivery_order_id)
                    VALUES (?, ?, ?)
                ");
                $stmt2->bind_param("isi", $batch_id, $destination, $delivery_order_id);
                $stmt2->execute();

                // Update original Store B order status to 'Assigned'
                $conn->query("
                    UPDATE delivery_orders_store_b 
                    SET original_status = 'Assigned' 
                    WHERE id = $delivery_order_id
                ");
            }
        }
    }

    echo "<script>alert('✅ Store B Delivery batch created successfully!'); window.location.href='".$_SERVER['PHP_SELF']."';</script>";
    exit;
}

// --- Fetch existing Store B batches ---
$deliveries = $conn->query("
    SELECT * FROM order_deliveries_store_b ORDER BY created_at DESC
");

// --- Fetch pending delivery orders from Store B ---
$pending_orders = $conn->query("
    SELECT id, invoice_number, delivery_number
    FROM delivery_orders_store_b
    WHERE original_status = 'Pending'
    ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Store B Order Deliveries</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-8 pt-24">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">🚚 Manage Store B Order Deliveries</h2>

    <!-- 📝 Delivery Form -->
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

    <!-- 📋 Delivery List -->
    <div class="bg-white shadow-lg rounded-2xl p-6">
        <h3 class="text-xl font-semibold mb-4 text-gray-700">📦 Store B Delivery Batches</h3>
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
  <!-- Edit Button -->
  <button 
      class="bg-yellow-500 text-white px-3 py-1 rounded-md hover:bg-yellow-600 transition edit-btn"
      data-id="<?= $row['id'] ?>"
      data-day="<?= htmlspecialchars($row['delivery_day']) ?>"
      data-date="<?= htmlspecialchars($row['delivery_date']) ?>"
      data-status="<?= htmlspecialchars($row['status']) ?>">
      Edit
  </button>

  <!-- View Button -->
  <a href="view_store_b_delivery.php?id=<?= $row['id'] ?>" 
     class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600 ml-2">View</a>
</td>

                        </tr>
                        <?php endwhile;
                    else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-gray-500">No Store B deliveries recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- 🧩 Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-lg w-96 p-6 relative">
    <h3 class="text-xl font-bold text-gray-800 mb-4">Update Delivery Status</h3>

    <form id="editForm" method="post" action="update_store_b_delivery_status.php">
      <input type="hidden" name="id" id="edit_id">

      <div class="mb-4">
        <label class="block text-gray-700 font-medium mb-1">Delivery Day</label>
        <input type="text" id="edit_day" class="border rounded-md w-full p-2 bg-gray-100" readonly>
      </div>

      <div class="mb-4">
        <label class="block text-gray-700 font-medium mb-1">Delivery Date</label>
        <input type="text" id="edit_date" class="border rounded-md w-full p-2 bg-gray-100" readonly>
      </div>

      <div class="mb-4">
        <label class="block text-gray-700 font-medium mb-1">Status</label>
        <select name="status" id="edit_status" class="border rounded-md w-full p-2">
          <option value="Pending">Pending</option>
          <option value="Delivered">Delivered</option>
          <option value="Cancelled">Cancelled</option>
        </select>
      </div>

      <div class="flex justify-end space-x-2">
        <button type="button" id="cancelEdit" class="bg-gray-400 text-white px-4 py-2 rounded-md hover:bg-gray-500">Cancel</button>
        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Save</button>
      </div>
    </form>
  </div>
</div>

</div>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('edit_id').value = btn.dataset.id;
    document.getElementById('edit_day').value = btn.dataset.day;
    document.getElementById('edit_date').value = btn.dataset.date;
    document.getElementById('edit_status').value = btn.dataset.status;
    document.getElementById('editModal').classList.remove('hidden');
  });
});

document.getElementById('cancelEdit').addEventListener('click', () => {
  document.getElementById('editModal').classList.add('hidden');
});
</script>

<script>
document.getElementById('add-delivery').addEventListener('click', () => {
    const container = document.getElementById('delivery-container');
    const row = document.querySelector('.delivery-row').cloneNode(true);
    row.querySelectorAll('input, select').forEach(el => el.value = '');
    container.appendChild(row);
});
</script>

</body>
</html>
