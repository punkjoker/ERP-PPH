<?php
include 'db_con.php';

// --- Handle Date Filter ---
$from_date = $_GET['from'] ?? date('Y-m-01');
$to_date   = $_GET['to'] ?? date('Y-m-t');

// --- Fetch Pending Deliveries ---
$stmt = $conn->prepare("
    SELECT * FROM order_deliveries 
    WHERE status = 'Pending' AND delivery_date BETWEEN ? AND ? 
    ORDER BY delivery_date ASC
");
$stmt->bind_param('ss', $from_date, $to_date);
$stmt->execute();
$deliveries = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pending Deliveries</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-8">
  <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">🚚 Pending Deliveries</h2>

  <!-- Filter Form -->
  <form method="get" class="mb-6 flex flex-wrap gap-4 items-end">
    <div>
      <label class="font-semibold text-gray-700">From</label>
      <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>" 
             class="border rounded-md p-2 focus:ring focus:ring-blue-300">
    </div>
    <div>
      <label class="font-semibold text-gray-700">To</label>
      <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>" 
             class="border rounded-md p-2 focus:ring focus:ring-blue-300">
    </div>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">Filter</button>
  </form>

  <!-- Pending Deliveries Table -->
  <div class="bg-white shadow-lg rounded-2xl p-6 overflow-x-auto">
    <table class="min-w-full border-collapse">
      <thead class="bg-gray-50">
        <tr>
          <th class="py-2 px-4 border-b text-left text-sm font-medium">#</th>
          <th class="py-2 px-4 border-b text-left text-sm font-medium">Delivery Day</th>
          <th class="py-2 px-4 border-b text-left text-sm font-medium">Delivery Date</th>
          <th class="py-2 px-4 border-b text-left text-sm font-medium">Status</th>
          <th class="py-2 px-4 border-b text-left text-sm font-medium">Created</th>
          <th class="py-2 px-4 border-b text-left text-sm font-medium">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if($deliveries->num_rows > 0): 
          $count = 1;
          while($row = $deliveries->fetch_assoc()): ?>
          <tr class="<?= $count % 2 == 0 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-gray-100 text-sm">
            <td class="py-1 px-3 border-b"><?= $count++ ?></td>
            <td class="py-1 px-3 border-b"><?= htmlspecialchars($row['delivery_day']) ?></td>
            <td class="py-1 px-3 border-b"><?= htmlspecialchars($row['delivery_date']) ?></td>
            <td class="py-1 px-3 border-b">
              <span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-700">
                <?= htmlspecialchars($row['status']) ?>
              </span>
            </td>
            <td class="py-1 px-3 border-b"><?= htmlspecialchars($row['created_at']) ?></td>
            <td class="py-1 px-3 border-b flex gap-2">
              <!-- Edit Status Button triggers modal -->
              <button onclick="openModal(<?= $row['id'] ?>, '<?= $row['status'] ?>')"
                      class="bg-green-500 text-white px-2 py-1 rounded-md hover:bg-green-600 text-xs">
                Edit Status
              </button>

              <a href="view_order_delivery.php?id=<?= $row['id'] ?>" 
                 class="bg-blue-500 text-white px-2 py-1 rounded-md hover:bg-blue-600 text-xs">View</a>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="6" class="text-center py-4 text-gray-500">No pending deliveries found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit Status Modal -->
<div id="statusModal" class="fixed inset-0 hidden items-center justify-center bg-black bg-opacity-50 z-50">
  <div class="bg-white rounded-2xl p-6 w-96 shadow-lg">
    <h3 class="text-lg font-semibold mb-4">Update Delivery Status</h3>
    <form id="statusForm" method="post" action="update_delivery_status.php">
      <input type="hidden" name="id" id="modalDeliveryId">
      <label class="font-semibold text-gray-700">Status</label>
      <select name="status" id="modalStatus" class="w-full border rounded-md p-2 mt-1 mb-4">
        <option value="Pending">Pending</option>
        <option value="Completed">Completed</option>
        <option value="Cancelled">Cancelled</option>
      </select>
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-md bg-gray-300 hover:bg-gray-400">Cancel</button>
        <button type="submit" class="px-4 py-2 rounded-md bg-green-600 text-white hover:bg-green-700">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id, status) {
    document.getElementById('modalDeliveryId').value = id;
    document.getElementById('modalStatus').value = status;
    document.getElementById('statusModal').classList.remove('hidden');
    document.getElementById('statusModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('statusModal').classList.add('hidden');
    document.getElementById('statusModal').classList.remove('flex');
}
</script>

</body>
</html>
