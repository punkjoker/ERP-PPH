<?php
include 'db_con.php';
session_start();

// ✅ Handle popup update form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
  $id = $_POST['id'];
  $approved_qty = $_POST['approved_qty'];
  $status = $_POST['status'];

  $update = $conn->prepare("UPDATE department_requests SET approved_qty = ?, status = ? WHERE id = ?");
  $update->bind_param("isi", $approved_qty, $status, $id);
  $update->execute();

  echo "<script>alert('Request updated successfully!'); window.location.href='department_requests.php';</script>";
  exit;
}

// ✅ Filters
$status_filter = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$query = "SELECT * FROM department_requests WHERE 1=1";
$params = [];
$types = '';

if ($status_filter) {
  $query .= " AND status = ?";
  $params[] = $status_filter;
  $types .= 's';
}
if ($start_date && $end_date) {
  $query .= " AND DATE(created_at) BETWEEN ? AND ?";
  $params[] = $start_date;
  $params[] = $end_date;
  $types .= 'ss';
}

$query .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$requests = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Department Requests (Procurement)</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.1/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-6" x-data="{ showModal: false, selected: {} }">
    <div class="bg-white shadow-lg rounded-lg p-6">
      <h1 class="text-2xl font-bold text-blue-700 mb-6">All Department Requests</h1>

      <!-- 🔍 Filter Section -->
      <form method="GET" class="flex flex-wrap gap-4 mb-6 items-end">
        <div>
          <label class="block text-sm font-medium text-gray-600">From Date</label>
          <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="border p-2 rounded">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-600">To Date</label>
          <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="border p-2 rounded">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-600">Status</label>
          <select name="status" class="border p-2 rounded">
            <option value="">All</option>
            <option value="Pending Approval" <?= $status_filter === 'Pending Approval' ? 'selected' : '' ?>>Pending Approval</option>
            <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
          </select>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
      </form>

      <!-- 🧾 Requests Table -->
      <table class="min-w-full border-collapse border border-gray-300">
        <thead class="bg-gray-100">
          <tr>
            <th class="border p-2">Requested By</th>
            <th class="border p-2">Department</th>
            <th class="border p-2">Item</th>
            <th class="border p-2">Used By</th>
            <th class="border p-2 text-center">Requested Qty</th>
            <th class="border p-2 text-center">Approved Qty</th>
            <th class="border p-2 text-center">Status</th>
            <th class="border p-2 text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $requests->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50">
              <td class="border p-2"><?= htmlspecialchars($row['requested_by']) ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['department'] ?? '—') ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['item_name']) ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['item_used_by']) ?></td>
              <td class="border p-2 text-center"><?= htmlspecialchars($row['requested_qty']) ?></td>
              <td class="border p-2 text-center"><?= htmlspecialchars($row['approved_qty'] ?? '-') ?></td>
              <td class="border p-2 text-center"><?= htmlspecialchars($row['status']) ?></td>
              <td class="border p-2 text-center">
                <button 
                  class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700"
                  @click="showModal = true; selected = {
                    id: '<?= $row['id'] ?>',
                    name: '<?= addslashes($row['item_name']) ?>',
                    qty: '<?= $row['requested_qty'] ?>',
                    approved: '<?= $row['approved_qty'] ?>',
                    status: '<?= $row['status'] ?>'
                  }">
                  Update
                </button>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- ✅ Popup Modal -->
    <div 
      x-show="showModal" 
      class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50" 
      x-cloak>
      <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
        <h2 class="text-lg font-bold mb-4 text-blue-700">Update Request</h2>
        <form method="POST" class="space-y-4">
          <input type="hidden" name="id" :value="selected.id">
          <div>
            <label class="block text-sm font-medium text-gray-700">Item Name</label>
            <p class="border p-2 rounded bg-gray-100" x-text="selected.name"></p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Requested Quantity</label>
            <p class="border p-2 rounded bg-gray-100" x-text="selected.qty"></p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Approved Quantity</label>
            <input type="number" name="approved_qty" min="0" class="border p-2 w-full rounded" :value="selected.approved">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" class="border p-2 w-full rounded" :value="selected.status">
              <option value="Pending Approval">Pending Approval</option>
              <option value="Approved">Approved</option>
            </select>
          </div>
          <div class="flex justify-end gap-2">
            <button type="button" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500" @click="showModal = false">Cancel</button>
            <button type="submit" name="update_request" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
