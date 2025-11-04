<?php
session_start();
require 'db_con.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_material'])) {
    $material_id = intval($_POST['material_id']);
    $quantity_removed = intval($_POST['quantity_removed']);
    $issued_to = trim($_POST['issued_to']);
    $description = trim($_POST['description']);
    $batch_number = trim($_POST['batch_number']);
    $used = floatval($_POST['used']);
    $wasted = floatval($_POST['wasted']);

    $stmt = $conn->prepare("SELECT * FROM materials WHERE id=? ORDER BY created_at ASC LIMIT 1");
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $material = $result->fetch_assoc();

    if ($material) {
        $remaining = $material['quantity'] - $quantity_removed;
        if ($quantity_removed > 0 && $remaining >= 0) {
            $update = $conn->prepare("UPDATE materials SET quantity=? WHERE id=?");
            $update->bind_param("ii", $remaining, $material_id);
            $update->execute();

           // ✅ Calculate costs
$unit_cost = floatval($material['cost']);
$total_cost = $unit_cost * $quantity_removed;

$insert = $conn->prepare("
    INSERT INTO material_out_history 
    (material_id, material_name, quantity_removed, remaining_quantity, issued_to, description, batch_number, used, wasted, unit_cost, total_cost) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$insert->bind_param(
    "isiisssdddd", 
    $material_id, 
    $material['material_name'], 
    $quantity_removed, 
    $remaining, 
    $issued_to, 
    $description, 
    $batch_number,
    $used, 
    $wasted, 
    $unit_cost, 
    $total_cost
);
$insert->execute();

// ✅ Also insert into label_reconciliation
$insert_label = $conn->prepare("
    INSERT INTO label_reconciliation 
    (material_id, material_name, quantity_removed, remaining_quantity, issued_to, description, batch_number, used, wasted, unit_cost, total_cost) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$insert_label->bind_param(
    "isiisssdddd", 
    $material_id, 
    $material['material_name'], 
    $quantity_removed, 
    $remaining, 
    $issued_to, 
    $description, 
    $batch_number,
    $used, 
    $wasted, 
    $unit_cost, 
    $total_cost
);
$insert_label->execute();


            $message = "✅ {$quantity_removed} units removed from {$material['material_name']}. Remaining: $remaining";
        } else {
            $message = "⚠️ Invalid removal quantity!";
        }
    } else {
        $message = "❌ Material not found.";
    }
}

// Fetch materials
$materials = $conn->query("SELECT * FROM materials ORDER BY created_at ASC");

// Fetch BOM batches for dropdown
$batches = $conn->query("SELECT DISTINCT batch_number FROM bill_of_materials ORDER BY batch_number ASC");

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';
$filter_name = $_GET['filter_name'] ?? '';

$query = "SELECT * FROM material_out_history WHERE 1=1";

if (!empty($from_date) && !empty($to_date)) {
    $query .= " AND DATE(removed_at) BETWEEN '" . $conn->real_escape_string($from_date) . "' 
                AND '" . $conn->real_escape_string($to_date) . "'";
} elseif (!empty($from_date)) {
    $query .= " AND DATE(removed_at) >= '" . $conn->real_escape_string($from_date) . "'";
} elseif (!empty($to_date)) {
    $query .= " AND DATE(removed_at) <= '" . $conn->real_escape_string($to_date) . "'";
}

if (!empty($filter_name)) {
    $query .= " AND material_name LIKE '%" . $conn->real_escape_string($filter_name) . "%'";
}

$query .= " ORDER BY removed_at DESC LIMIT 50";
$removed_items = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Remove Raw Material</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen px-4">

<?php include 'navbar.php'; ?>

<div class="max-w-5xl ml-64 mx-auto mt-24 p-6 bg-white rounded-xl shadow-lg">
  <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Remove Raw Material</h2>

  <!-- Message -->
  <?php if (!empty($message)): ?>
    <div class="mb-4 text-center text-white px-4 py-2 rounded 
        <?= (str_contains($message,'✅')) ? 'bg-green-500' : 'bg-red-500'; ?>">
        <?= $message ?>
    </div>
  <?php endif; ?>

  <!-- Form -->
  <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div>
      <label class="block text-sm text-gray-700 font-medium">Select Material (FIFO)</label>
      <select name="material_id" id="material_id" class="w-full border rounded px-3 py-2" required onchange="updateMaterialInfo()">
        <option value="">-- Select Material --</option>
        <?php while ($row = $materials->fetch_assoc()): ?>
          <option value="<?= $row['id'] ?>" data-original="<?= $row['quantity'] ?>" data-name="<?= htmlspecialchars($row['material_name']) ?>">
            <?= htmlspecialchars($row['material_name']) ?> (Remaining: <?= $row['quantity'] ?>)
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm text-gray-700 font-medium">Original Quantity</label>
      <input type="text" id="original_qty" class="w-full border rounded px-3 py-2 bg-gray-100" readonly>
    </div>

    <div>
      <label class="block text-sm text-gray-700 font-medium">Remaining Quantity</label>
      <input type="text" id="remaining_qty" class="w-full border rounded px-3 py-2 bg-gray-100" readonly>
    </div>
<!-- ✅ Batch Number Dropdown -->
    <div>
      <label class="block text-sm text-gray-700 font-medium">Batch Number</label>
      <select name="batch_number" class="w-full border rounded px-3 py-2" required>
        <option value="">-- Select Batch --</option>
        <?php while ($b = $batches->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($b['batch_number']) ?>"><?= htmlspecialchars($b['batch_number']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm text-gray-700 font-medium">Quantity to Remove</label>
      <input type="number" name="quantity_removed" class="w-full border rounded px-3 py-2" required>
    </div>
    <div>
  <label class="block text-sm text-gray-700 font-medium">Unit Cost</label>
  <input type="text" name="unit_cost" id="unit_cost" class="w-full border rounded px-3 py-2 bg-gray-100" readonly>
</div>

<div>
  <label class="block text-sm text-gray-700 font-medium">Total Cost</label>
  <input type="text" name="total_cost" id="total_cost" class="w-full border rounded px-3 py-2 bg-gray-100" readonly>
</div>

  <!-- ✅ Used and Wasted -->
    <div>
      <label class="block text-sm text-gray-700 font-medium">Used Quantity</label>
      <input type="number" step="0.01" name="used" class="w-full border rounded px-3 py-2">
    </div>

    <div>
      <label class="block text-sm text-gray-700 font-medium">Wasted Quantity</label>
      <input type="number" step="0.01" name="wasted" class="w-full border rounded px-3 py-2">
    </div>

    <div>
      <label class="block text-sm text-gray-700 font-medium">Issued To</label>
      <input type="text" name="issued_to" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
      <label class="block text-sm text-gray-700 font-medium">Description</label>
      <textarea name="description" class="w-full border rounded px-3 py-2"></textarea>
    </div>

    <div class="md:col-span-2 flex justify-between mt-4">
      <button type="submit" name="remove_material" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg">
        Remove Material
      </button>
    </div>
  </form>

  <!-- Filters -->
  <!-- Filters -->
<form method="GET" class="flex flex-wrap gap-4 mb-4 items-end no-print">
  <div>
    <label class="text-sm font-medium text-gray-700">From Date</label>
    <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border rounded px-3 py-2">
  </div>
  <div>
    <label class="text-sm font-medium text-gray-700">To Date</label>
    <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border rounded px-3 py-2">
  </div>
  <div>
    <label class="text-sm font-medium text-gray-700">Search by Material</label>
    <input type="text" name="filter_name" value="<?= htmlspecialchars($filter_name) ?>" class="border rounded px-3 py-2" placeholder="Enter name">
  </div>
  <div class="flex gap-2">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filter</button>
    <button type="button" onclick="printFiltered()" 
  class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Print</button>

  </div>
</form>


  <!-- Recent Removals -->
  <!-- Recent Removals -->
<h3 class="text-xl font-semibold text-blue-700 mb-3">Recent Removals</h3>
<div id="print-section" class="overflow-x-auto">
  <table class="w-full border text-sm">

      <thead class="bg-blue-100">
        <tr>
          <th class="border px-2 py-1">Material</th>
          <th class="border px-2 py-1">Qty Removed</th>
          <th class="border px-2 py-1">Remaining</th>
          <th class="border px-2 py-1">Issued To</th>
          <th class="border px-2 py-1">Batch Issued to</th>
          <th class="border px-2 py-1">Date</th>
          <th class="border px-2 py-1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($removed_items->num_rows > 0): ?>
          <?php while ($row = $removed_items->fetch_assoc()): ?>
            <tr>
              <td class="border px-2 py-1"><?= htmlspecialchars($row['material_name']) ?></td>
              <td class="border px-2 py-1 text-red-600 font-bold"><?= $row['quantity_removed'] ?></td>
              <td class="border px-2 py-1 text-green-700"><?= $row['remaining_quantity'] ?></td>
              <td class="border px-2 py-1"><?= htmlspecialchars($row['issued_to']) ?></td>
              <td class="border px-2 py-1"><?= htmlspecialchars($row['batch_number']) ?></td>
              <td class="border px-2 py-1"><?= $row['removed_at'] ?></td>
              <td class="border px-2 py-1 flex gap-2">
  <a href="view_material_history.php?id=<?= $row['material_id'] ?>" 
     class="bg-blue-500 text-white px-2 py-1 rounded text-xs hover:bg-blue-600">View</a>

  <button onclick="openEditModal(
      <?= $row['id'] ?>, 
      '<?= htmlspecialchars($row['material_name']) ?>', 
      <?= $row['quantity_removed'] ?>, 
      <?= $row['remaining_quantity'] ?>, 
      '<?= htmlspecialchars($row['issued_to']) ?>'
    )" 
    class="bg-yellow-500 text-white px-2 py-1 rounded text-xs hover:bg-yellow-600">
    Edit
  </button>
</td>

            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="text-center text-gray-500 py-2">No removals yet.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-lg shadow-lg w-96 p-6">
    <h3 class="text-xl font-semibold mb-4">Edit Removed Material</h3>
    <form id="editForm" method="POST" action="update_removed_item.php">
      <input type="hidden" name="id" id="edit_id">

      <div class="mb-3">
        <label class="block text-sm font-medium">Material</label>
        <input type="text" id="edit_material" class="w-full border rounded px-3 py-2 bg-gray-100" readonly>
      </div>

      <div class="mb-3">
        <label class="block text-sm font-medium">Qty Removed</label>
        <input type="number" name="quantity_removed" id="edit_qty_removed" class="w-full border rounded px-3 py-2" required>
      </div>

      <div class="mb-3">
        <label class="block text-sm font-medium">Remaining</label>
        <input type="number" id="edit_remaining" class="w-full border rounded px-3 py-2 bg-gray-100" readonly>
      </div>

      <div class="mb-3">
        <label class="block text-sm font-medium">Issued To</label>
        <input type="text" name="issued_to" id="edit_issued_to" class="w-full border rounded px-3 py-2" required>
      </div>

      <div class="flex justify-end gap-2 mt-4">
        <button type="button" onclick="closeEditModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Cancel</button>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function updateMaterialInfo() {
  const select = document.getElementById('material_id');
  const option = select.options[select.selectedIndex];

  if (option.value) {
    const materialId = option.value;
    fetch('get_material_cost.php?id=' + materialId)
      .then(response => response.json())
      .then(data => {
        document.getElementById('original_qty').value = data.quantity;
        document.getElementById('remaining_qty').value = data.quantity;
        document.getElementById('unit_cost').value = data.cost;
      });
  } else {
    document.getElementById('original_qty').value = '';
    document.getElementById('remaining_qty').value = '';
    document.getElementById('unit_cost').value = '';
    document.getElementById('total_cost').value = '';
  }
}

// Auto-calc total cost
document.querySelector('input[name="quantity_removed"]').addEventListener('input', function() {
  const qty = parseFloat(this.value) || 0;
  const unitCost = parseFloat(document.getElementById('unit_cost').value) || 0;
  document.getElementById('total_cost').value = (qty * unitCost).toFixed(2);
});
</script>

<script>
  function printFiltered() {
    const printContent = document.getElementById('print-section').innerHTML;
    const originalContent = document.body.innerHTML;

    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload(); // reload to restore JS/CSS
  }
</script>
<script>
  function openEditModal(id, material, qty_removed, remaining, issued_to) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_material').value = material;
    document.getElementById('edit_qty_removed').value = qty_removed;
    document.getElementById('edit_remaining').value = remaining;
    document.getElementById('edit_issued_to').value = issued_to;

    document.getElementById('editModal').classList.remove('hidden');
  }

  function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
  }
</script>

</body>
</html>
