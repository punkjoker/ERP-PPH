<?php
session_start();
require 'db_con.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_chemical'])) {
    $name = trim($_POST['chemical_name']);
    $rm_lot_no = trim($_POST['rm_lot_no']);
    $std_qty = floatval($_POST['std_quantity']);
    $remaining_qty = $std_qty; // initially same as std qty
    $total_cost = floatval($_POST['total_cost']);
    $unit_price = floatval($_POST['unit_price']);
    $date_added = $_POST['date_added'];

    // insert with status = Pending
    $stmt = $conn->prepare("INSERT INTO chemicals_in 
        (chemical_name, rm_lot_no, std_quantity, remaining_quantity, total_cost, unit_price, date_added, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("ssiddds", $name, $rm_lot_no, $std_qty, $remaining_qty, $total_cost, $unit_price, $date_added);
    $stmt->execute();
}

// Filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$search_name = $_GET['search_name'] ?? '';

$query = "SELECT * FROM chemicals_in WHERE 1=1";

if (!empty($from_date) && !empty($to_date)) {
    $query .= " AND DATE(date_added) BETWEEN '" . $conn->real_escape_string($from_date) . "' 
                AND '" . $conn->real_escape_string($to_date) . "'";
}
if (!empty($search_name)) {
    $query .= " AND chemical_name LIKE '%" . $conn->real_escape_string($search_name) . "%'";
}

$query .= " ORDER BY date_added DESC";
$chemicals = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Chemicals In</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen px-4">

<?php include 'navbar.php'; ?>

<div class="max-w-6xl ml-64 mx-auto mt-24 p-6 bg-white rounded-xl shadow-lg">
  <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Chemicals In</h2>

  <!-- Form -->
  <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div>
      <label class="block text-sm font-medium text-gray-700">Chemical Name</label>
      <input type="text" name="chemical_name" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">RM LOT NO</label>
      <input type="text" name="rm_lot_no" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">STD Quantity</label>
      <input type="number" step="0.01" name="std_quantity" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Total Cost of Lot</label>
      <input type="number" step="0.01" name="total_cost" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Unit Price (Kg/Litre)</label>
      <input type="number" step="0.01" name="unit_price" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Date Added</label>
      <input type="date" name="date_added" class="w-full border rounded px-3 py-2" required>
    </div>

    <div class="md:col-span-2 flex justify-center mt-4">
      <button type="submit" name="add_chemical" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
        Add Chemical
      </button>
    </div>
  </form>

  <!-- Filters -->
  <form method="GET" class="flex flex-wrap gap-4 mb-4 items-end">
    <div>
      <label class="text-sm font-medium text-gray-700">From Date</label>
      <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border rounded px-3 py-2">
    </div>
    <div>
      <label class="text-sm font-medium text-gray-700">To Date</label>
      <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border rounded px-3 py-2">
    </div>
    <div>
      <label class="text-sm font-medium text-gray-700">Search Chemical</label>
      <input type="text" name="search_name" value="<?= htmlspecialchars($search_name) ?>" class="border rounded px-3 py-2" placeholder="Enter name">
    </div>
    <div>
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filter</button>
    </div>
  </form>

  <!-- Table -->
  <div class="overflow-x-auto">
    <table class="w-full border text-sm">
      <thead class="bg-blue-100">
        <tr>
          <th class="border px-2 py-1">Chemical Name</th>
          <th class="border px-2 py-1">RM LOT NO</th>
          <th class="border px-2 py-1">STD Quantity</th>
          <th class="border px-2 py-1">Remaining Quantity</th>
          <th class="border px-2 py-1">Total Cost</th>
          <th class="border px-2 py-1">Unit Price</th>
          <th class="border px-2 py-1">Date Added</th>
          <th class="border px-2 py-1">Status</th>
          <th class="border px-2 py-1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($chemicals->num_rows > 0): ?>
          <?php while ($row = $chemicals->fetch_assoc()): ?>
            <tr>
              <td class="border px-2 py-1"><?= htmlspecialchars($row['chemical_name']) ?></td>
              <td class="border px-2 py-1"><?= htmlspecialchars($row['rm_lot_no']) ?></td>
              <td class="border px-2 py-1"><?= $row['std_quantity'] ?></td>
              <td class="border px-2 py-1 text-green-700"><?= $row['remaining_quantity'] ?></td>
              <td class="border px-2 py-1"><?= number_format($row['total_cost'], 2) ?></td>
              <td class="border px-2 py-1"><?= number_format($row['unit_price'], 2) ?></td>
              <td class="border px-2 py-1"><?= $row['date_added'] ?></td>
              <td class="border px-2 py-1 font-bold 
                <?= $row['status']=='Pending' ? 'text-yellow-600' : ($row['status']=='Approved' ? 'text-green-600' : 'text-red-600') ?>">
                <?= htmlspecialchars($row['status']) ?>
              </td>
              <td class="border px-2 py-1">
                <button onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['chemical_name']) ?>', '<?= htmlspecialchars($row['rm_lot_no']) ?>', <?= $row['std_quantity'] ?>, <?= $row['remaining_quantity'] ?>, <?= $row['total_cost'] ?>, <?= $row['unit_price'] ?>, '<?= $row['date_added'] ?>')" 
                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-xs">
                  Edit
                </button>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="9" class="text-center text-gray-500 py-2">No chemicals found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
  <div class="bg-white rounded-lg shadow-lg w-96 p-6">
    <h3 class="text-lg font-bold mb-4">Edit Chemical</h3>
    <form method="POST" action="update_chemical.php" class="space-y-3">
      <input type="hidden" name="id" id="edit_id">
      <div>
        <label class="text-sm">Chemical Name</label>
        <input type="text" name="chemical_name" id="edit_name" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="text-sm">RM LOT NO</label>
        <input type="text" name="rm_lot_no" id="edit_lot" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="text-sm">STD Quantity</label>
        <input type="number" step="0.01" name="std_quantity" id="edit_std_qty" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="text-sm">Remaining Quantity</label>
        <input type="number" step="0.01" name="remaining_quantity" id="edit_rem_qty" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="text-sm">Total Cost</label>
        <input type="number" step="0.01" name="total_cost" id="edit_total" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="text-sm">Unit Price</label>
        <input type="number" step="0.01" name="unit_price" id="edit_unit" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="text-sm">Date Added</label>
        <input type="date" name="date_added" id="edit_date" class="w-full border rounded px-3 py-2" required>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="closeEditModal()" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
        <button type="submit" name="update_chemical" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, name, lot, std_qty, rem_qty, total, unit, date) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_lot').value = lot;
  document.getElementById('edit_std_qty').value = std_qty;
  document.getElementById('edit_rem_qty').value = rem_qty;
  document.getElementById('edit_total').value = total;
  document.getElementById('edit_unit').value = unit;
  document.getElementById('edit_date').value = date;
  document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() {
  document.getElementById('editModal').classList.add('hidden');
}
</script>

</body>
</html>
