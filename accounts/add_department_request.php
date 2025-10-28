<?php
include 'db_con.php';
session_start();

$user_name = $_SESSION['username'] ?? 'TestUser';

// --- Handle new request submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_name'])) {
  $items = $_POST['item_name'];
  $used_by = $_POST['item_used_by'];
  $quantities = $_POST['requested_qty'];
  $requested_by = $_POST['requested_by'];
  $department = $_POST['department'];

  for ($i = 0; $i < count($items); $i++) {
    $stmt = $conn->prepare("INSERT INTO department_requests 
      (item_name, item_used_by, requested_qty, requested_by, department, status, created_at)
      VALUES (?, ?, ?, ?, ?, 'Pending Approval', NOW())");
    $stmt->bind_param("ssiss", $items[$i], $used_by[$i], $quantities[$i], $requested_by, $department);
    $stmt->execute();
  }
  echo "<script>alert('Request submitted successfully!'); window.location.href='add_department_request.php';</script>";
}

// --- Filtering ---
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$status_filter = $_GET['status'] ?? '';
$query = "SELECT * FROM department_requests WHERE 1";

if ($from && $to) $query .= " AND DATE(created_at) BETWEEN '$from' AND '$to'";
if ($status_filter) $query .= " AND status='$status_filter'";
$query .= " ORDER BY created_at DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Department Requests</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex">
  <!-- Include Navbar -->
  <?php include 'navbar.php'; ?>

  <div class="flex-1 p-8 ml-64">
    <div class="max-w-6xl mx-auto bg-white shadow-lg rounded-lg p-6">
      <h1 class="text-2xl font-bold text-blue-700 mb-6">Department Item Requests</h1>

      <!-- Add Request Form -->
      <form method="POST" class="space-y-4" id="requestForm">
        <div id="itemsContainer" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 item-row">
            <input type="text" name="item_name[]" placeholder="Item Name" class="border p-2 rounded" required>
            <input type="text" name="item_used_by[]" placeholder="Item will be used by" class="border p-2 rounded" required>
            <input type="number" name="requested_qty[]" placeholder="Requested Quantity" min="1" class="border p-2 rounded" required>
          </div>
        </div>

        <!-- Requested By and Department -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
          <input type="text" name="requested_by" value="<?= htmlspecialchars($user_name) ?>" placeholder="Requested By" class="border p-2 rounded" required>
          <input type="text" name="department" placeholder="Department" class="border p-2 rounded" required>
        </div>

        <div class="mt-4 flex gap-3">
          <button type="button" onclick="addItemRow()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">+ Add Another Item</button>
          <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700">Submit Request</button>
        </div>
      </form>

      <!-- Filters -->
      <div class="mt-10 bg-gray-100 p-4 rounded-lg">
        <form method="GET" class="flex flex-wrap items-center gap-4">
          <div>
            <label class="text-sm text-gray-600">From:</label>
            <input type="date" name="from" value="<?= $from ?>" class="border p-2 rounded">
          </div>
          <div>
            <label class="text-sm text-gray-600">To:</label>
            <input type="date" name="to" value="<?= $to ?>" class="border p-2 rounded">
          </div>
          <div>
            <label class="text-sm text-gray-600">Status:</label>
            <select name="status" class="border p-2 rounded">
              <option value="">All</option>
              <option value="Pending Approval" <?= $status_filter == 'Pending Approval' ? 'selected' : '' ?>>Pending</option>
              <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
            </select>
          </div>
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
          <a href="add_department_request.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Reset</a>
        </form>
      </div>

      <!-- Existing Requests Table -->
      <div class="mt-8 overflow-x-auto">
        <table class="min-w-full border-collapse border border-gray-300">
          <thead class="bg-gray-100">
            <tr>
              <th class="border p-2 text-left">Item</th>
              <th class="border p-2 text-left">Used By</th>
              <th class="border p-2 text-left">Quantity</th>
              <th class="border p-2 text-left">Requested By</th>
              <th class="border p-2 text-left">Department</th>
              <th class="border p-2 text-left">Date</th>
              <th class="border p-2 text-left">Status</th>
              <th class="border p-2 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50">
                <td class="border p-2"><?= htmlspecialchars($row['item_name']) ?></td>
                <td class="border p-2"><?= htmlspecialchars($row['item_used_by']) ?></td>
                <td class="border p-2"><?= htmlspecialchars($row['requested_qty']) ?></td>
                <td class="border p-2"><?= htmlspecialchars($row['requested_by']) ?></td>
                <td class="border p-2"><?= htmlspecialchars($row['department']) ?></td>
                <td class="border p-2"><?= date("Y-m-d", strtotime($row['created_at'])) ?></td>
                <td class="border p-2">
                  <span class="px-2 py-1 rounded text-sm <?= $row['status'] === 'Pending Approval' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' ?>">
                    <?= htmlspecialchars($row['status']) ?>
                  </span>
                </td>
                <td class="border p-2 flex gap-2">
                  <button onclick="openEditModal(<?= $row['id'] ?>, '<?= addslashes($row['item_name']) ?>', '<?= addslashes($row['item_used_by']) ?>', <?= $row['requested_qty'] ?>)" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600">Edit</button>
                  <button onclick="viewDetails('<?= addslashes($row['item_name']) ?>','<?= addslashes($row['item_used_by']) ?>',<?= $row['requested_qty'] ?>,'<?= $row['status'] ?>')" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">View</button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full">
      <h2 class="text-lg font-bold mb-4 text-gray-800">Edit Request</h2>
      <form id="editForm" method="POST" action="edit_request.php">
        <input type="hidden" name="id" id="editId">
        <div class="space-y-3">
          <input type="text" name="item_name" id="editItemName" class="border p-2 rounded w-full" required>
          <input type="text" name="item_used_by" id="editUsedBy" class="border p-2 rounded w-full" required>
          <input type="number" name="requested_qty" id="editQty" class="border p-2 rounded w-full" min="1" required>
        </div>
        <div class="flex justify-end gap-3 mt-4">
          <button type="button" onclick="closeEditModal()" class="bg-gray-400 text-white px-4 py-2 rounded">Cancel</button>
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function addItemRow() {
      const container = document.getElementById('itemsContainer');
      const row = document.createElement('div');
      row.className = 'grid grid-cols-1 md:grid-cols-3 gap-4 item-row';
      row.innerHTML = `
        <input type="text" name="item_name[]" placeholder="Item Name" class="border p-2 rounded" required>
        <input type="text" name="item_used_by[]" placeholder="Item will be used by" class="border p-2 rounded" required>
        <input type="number" name="requested_qty[]" placeholder="Requested Quantity" min="1" class="border p-2 rounded" required>
      `;
      container.appendChild(row);
    }

    function openEditModal(id, item, usedBy, qty) {
      document.getElementById('editModal').classList.remove('hidden');
      document.getElementById('editId').value = id;
      document.getElementById('editItemName').value = item;
      document.getElementById('editUsedBy').value = usedBy;
      document.getElementById('editQty').value = qty;
    }

    function closeEditModal() {
      document.getElementById('editModal').classList.add('hidden');
    }

    function viewDetails(item, usedBy, qty, status) {
      alert(`Item: ${item}\nUsed By: ${usedBy}\nQuantity: ${qty}\nStatus: ${status}`);
    }
  </script>
</body>
</html>
