<?php
session_start();
require 'db_con.php';

// ✅ Add missing columns if they don’t exist
$conn->query("ALTER TABLE materials ADD COLUMN IF NOT EXISTS po_number VARCHAR(100) NULL");
$conn->query("ALTER TABLE materials ADD COLUMN IF NOT EXISTS unit VARCHAR(50) NULL");

// ✅ Handle Add Material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_material'])) {
    $material_name = trim($_POST['material_name']);
    $po_number = trim($_POST['po_number']);
    $quantity = floatval($_POST['quantity']);
    $cost = floatval($_POST['cost']);
    $unit = trim($_POST['unit']);

    if ($material_name && $po_number && $quantity > 0 && $cost > 0) {
        $stmt = $conn->prepare("INSERT INTO materials (material_name, po_number, quantity, cost, unit) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdss", $material_name, $po_number, $quantity, $cost, $unit);
        $stmt->execute();
    }
}

// ✅ Handle Edit Material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_material'])) {
    $id = $_POST['id'];
    $material_name = trim($_POST['material_name']);
    $quantity = floatval($_POST['quantity']);
    $cost = floatval($_POST['cost']);
    $stmt = $conn->prepare("UPDATE materials SET material_name=?, quantity=?, cost=? WHERE id=?");
    $stmt->bind_param("sddi", $material_name, $quantity, $cost, $id);
    $stmt->execute();
}

// ✅ Fetch approved POs (and related materials)
$poQuery = $conn->query("
    SELECT p.po_no, m.material_name, m.unit 
    FROM po_list p
    LEFT JOIN materials m ON m.po_number = p.po_no
    WHERE p.status = 1
    ORDER BY p.created_at DESC
");

// ✅ Filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$search_name = $_GET['search_name'] ?? '';

$query = "SELECT * FROM materials WHERE 1=1";
if (!empty($from_date) && !empty($to_date)) {
    $query .= " AND DATE(created_at) BETWEEN '{$from_date}' AND '{$to_date}'";
}
if (!empty($search_name)) {
    $query .= " AND (material_name LIKE '%{$search_name}%' OR po_number LIKE '%{$search_name}%')";
}
$query .= " ORDER BY created_at DESC";
$materials = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Materials In</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-blue-50 min-h-screen px-4">

<?php include 'navbar.php'; ?>

<div class="max-w-6xl ml-64 mx-auto mt-24 p-6 bg-white rounded-xl shadow-lg">
  <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Materials In</h2>

  <!-- ✅ Add Material Form -->
 <!-- ✅ Add Material Form -->
<form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
  
  <!-- ✅ Material Name (First Field) -->
  <div>
    <label class="block text-sm font-medium text-gray-700">Material Name</label>
    <input list="material_list" name="material_name" id="material_name"
           class="w-full border rounded px-3 py-2"
           placeholder="Type or select material name" required>
    <datalist id="material_list">
  <?php
    // ✅ Pull from chemical_names where main_category is Packaging Materials
    $matList = $conn->query("
      SELECT DISTINCT chemical_name 
      FROM chemical_names 
      WHERE main_category = 'Packaging Materials'
      ORDER BY chemical_name ASC
    ");
    while ($m = $matList->fetch_assoc()):
  ?>
    <option value="<?= htmlspecialchars($m['chemical_name']) ?>"></option>
  <?php endwhile; ?>
</datalist>

  </div>

  <!-- ✅ PO Number (now a free text box) -->
  <div>
    <label class="block text-sm font-medium text-gray-700">PO Number</label>
    <input type="text" name="po_number" id="po_number"
           class="w-full border rounded px-3 py-2"
           placeholder="Enter P.O number manually" required>
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700">Unit</label>
    <input type="text" name="unit" id="unit" class="w-full border rounded px-3 py-2" placeholder="e.g. kg, L, pcs">
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700">Quantity</label>
    <input type="number" step="0.01" name="quantity" id="quantity" class="w-full border rounded px-3 py-2" required>
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700">Unit Cost (KSh)</label>
    <input type="number" step="0.01" name="cost" id="cost" class="w-full border rounded px-3 py-2" required>
  </div>

  <div class="md:col-span-2 flex justify-center mt-4">
    <button type="submit" name="add_material"
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
      Add Material
    </button>
  </div>
</form>


  <!-- ✅ Filter Section -->
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
      <label class="text-sm font-medium text-gray-700">Search</label>
      <input type="text" name="search_name" value="<?= htmlspecialchars($search_name) ?>" class="border rounded px-3 py-2" placeholder="Material or PO">
    </div>
    <div>
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filter</button>
    </div>
  </form>

  <!-- ✅ Materials Table -->
  <div class="overflow-x-auto">
    <table class="w-full border text-sm">
      <thead class="bg-blue-100">
        <tr>
          <th class="border px-2 py-1">Material</th>
          <th class="border px-2 py-1">PO Number</th>
          <th class="border px-2 py-1">Unit</th>
          <th class="border px-2 py-1">Quantity</th>
          <th class="border px-2 py-1">Cost</th>
          <th class="border px-2 py-1">Total</th>
          <th class="border px-2 py-1">Date</th>
          <th class="border px-2 py-1">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($materials->num_rows > 0): ?>
          <?php while ($row = $materials->fetch_assoc()): ?>
          <tr class="hover:bg-blue-50">
            <td class="border px-2 py-1"><?= htmlspecialchars($row['material_name']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['po_number']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['unit']) ?></td>
            <td class="border px-2 py-1"><?= number_format($row['quantity'], 2) ?></td>
            <td class="border px-2 py-1"><?= number_format($row['cost'], 2) ?></td>
            <td class="border px-2 py-1 font-semibold"><?= number_format($row['quantity'] * $row['cost'], 2) ?></td>
            <td class="border px-2 py-1"><?= $row['created_at'] ?></td>
            <td class="border px-2 py-1">
              <button onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['material_name']) ?>', <?= $row['quantity'] ?>, <?= $row['cost'] ?>)" 
                      class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">Edit</button>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="8" class="text-center text-gray-500 py-2">No materials found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ✅ Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
  <div class="bg-white rounded-lg p-6 w-96">
    <h3 class="text-xl font-bold mb-4 text-blue-700">Edit Material</h3>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="id" id="edit_id">
      <div>
        <label class="block text-gray-700">Material Name</label>
        <input type="text" name="material_name" id="edit_material_name" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block text-gray-700">Quantity</label>
        <input type="number" step="0.01" name="quantity" id="edit_quantity" class="w-full border rounded px-3 py-2" required>
      </div>
      <div>
        <label class="block text-gray-700">Cost</label>
        <input type="number" step="0.01" name="cost" id="edit_cost" class="w-full border rounded px-3 py-2" required>
      </div>
      <div class="flex justify-between mt-4">
        <button type="submit" name="edit_material" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save</button>
        <button type="button" onclick="closeEditModal()" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
$(document).ready(function() {
  // ✅ When PO is selected, fetch all its details from order_items
  $('#po_number').on('change', function() {
    const po_number = $(this).val();
    if (po_number) {
      $.getJSON('get_po_item_details.php', { po_number }, function(data) {
        if (data) {
          $('#material_name').val(data.item_name || '');
          $('#quantity').val(data.quantity || '');
          $('#unit').val(data.unit || '');
          $('#cost').val(data.unit_price || '');
        }
      });
    }
  });
});

// ✅ Edit modal functions
function openEditModal(id, name, qty, cost) {
  $('#edit_id').val(id);
  $('#edit_material_name').val(name);
  $('#edit_quantity').val(qty);
  $('#edit_cost').val(cost);
  $('#editModal').removeClass('hidden');
}

function closeEditModal() {
  $('#editModal').addClass('hidden');
}
</script>


</body>
</html>
