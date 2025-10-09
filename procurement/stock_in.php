<?php
session_start();
require 'db_con.php'; // adjust path if needed

// Handle Add Stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock'])) {
    $stock_name  = trim($_POST['stock_name']);
    $stock_code  = trim($_POST['stock_code']);
    $quantity    = trim($_POST['quantity']);
    $unit        = trim($_POST['unit']);
    $total_cost  = trim($_POST['total_cost']);
    $unit_cost   = trim($_POST['unit_cost']);

    if ($stock_name && $stock_code && $quantity && $unit && $total_cost && $unit_cost) {
        $stmt = $conn->prepare("INSERT INTO stock_in (stock_name, stock_code, quantity, unit, total_cost, unit_cost) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissd", $stock_name, $stock_code, $quantity, $unit, $total_cost, $unit_cost);
        if ($stmt->execute()) {
            $message = "✅ Stock added successfully!";
        } else {
            $message = "❌ Error adding stock: " . $conn->error;
        }
    } else {
        $message = "⚠️ Please fill all fields.";
    }
}

// Handle Edit Stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_stock'])) {
    $id         = $_POST['id'];
    $stock_name = trim($_POST['stock_name']);
    $stock_code = trim($_POST['stock_code']);
    $quantity   = trim($_POST['quantity']);
    $unit       = trim($_POST['unit']);
    $total_cost = trim($_POST['total_cost']);
    $unit_cost  = trim($_POST['unit_cost']);

    $stmt = $conn->prepare("UPDATE stock_in SET stock_name=?, stock_code=?, quantity=?, unit=?, total_cost=?, unit_cost=? WHERE id=?");
    $stmt->bind_param("ssissdi", $stock_name, $stock_code, $quantity, $unit, $total_cost, $unit_cost, $id);
    if ($stmt->execute()) {
        $message = "✅ Stock updated successfully!";
    } else {
        $message = "❌ Error updating stock: " . $conn->error;
    }
}

// Fetch all stock
$result = $conn->query("SELECT * FROM stock_in ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock In - Lynntech</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen px-6">

  <!-- Navbar -->
  <?php include 'navbar.php'; ?> 

  <!-- Header Info -->
  <div class="max-w-6xl ml-64 mx-auto mt-24 bg-white rounded-xl shadow-lg p-6 text-center">
      <h1 class="text-3xl font-bold text-blue-700 mb-4">STOCK CARDS - QF 18</h1>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-gray-700 text-sm">
          <div><strong>EFFECTIVE DATE:</strong> 01/11/2024</div>
          <div><strong>ISSUE DATE:</strong> 25/10/2024</div>
          <div><strong>REVIEW DATE:</strong> 10/2027</div>
          <div><strong>ISSUE NO:</strong> 007</div>
          <div><strong>REVISION NO:</strong> 006</div>
          <div><strong>MANUAL NO:</strong> LYNNTECH-QP-22</div>
      </div>
  </div>

  <!-- Message -->
  <?php if (!empty($message)): ?>
      <div class="max-w-4xl ml-64 mx-auto mt-4 text-center text-white px-4 py-2 rounded 
          <?php echo (str_contains($message,'✅')) ? 'bg-green-500' : 'bg-red-500'; ?>">
          <?= $message ?>
      </div>
  <?php endif; ?>

  <!-- Add Stock Form -->
  <div class="max-w-6xl ml-64 mx-auto mt-6 p-6 bg-white rounded-xl shadow-lg">
      <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Add Stock</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
              <label class="block text-gray-700 font-medium mb-1">Stock Name</label>
              <input type="text" name="stock_name" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-400" required>
          </div>
          <div>
              <label class="block text-gray-700 font-medium mb-1">Stock Code</label>
              <input type="text" name="stock_code" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-400" required>
          </div>
          <div>
              <label class="block text-gray-700 font-medium mb-1">Quantity</label>
              <input type="number" name="quantity" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-400" placeholder="e.g. 50" required>
          </div>
          <div>
              <label class="block text-gray-700 font-medium mb-1">Unit (kg/litres)</label>
              <input type="text" name="unit" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-400" placeholder="e.g. kg or litres" required>
          </div>
          <div>
              <label class="block text-gray-700 font-medium mb-1">Total Cost (KSh)</label>
              <input type="number" step="0.01" name="total_cost" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-400" required>
          </div>
          <div>
              <label class="block text-gray-700 font-medium mb-1">Unit Cost (KSh)</label>
              <input type="number" step="0.01" name="unit_cost" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-400" required>
          </div>
          <div class="md:col-span-3 text-center">
              <button type="submit" name="add_stock"
                  class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow-md transition">
                  Add Stock
              </button>
          </div>
      </form>
  </div>

  <!-- Existing Stock Table -->
  <div class="max-w-6xl ml-64 mx-auto mt-8 p-6 bg-white rounded-xl shadow-lg">
      <h3 class="text-2xl font-bold text-blue-700 mb-4 text-center">Existing Stock</h3>
      <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-200 rounded-lg">
              <thead class="bg-blue-600 text-white">
                  <tr>
                      <th class="py-2 px-4 text-left">ID</th>
                      <th class="py-2 px-4 text-left">Stock Name</th>
                      <th class="py-2 px-4 text-left">Stock Code</th>
                      <th class="py-2 px-4 text-left">Quantity</th>
                      <th class="py-2 px-4 text-left">Unit</th>
                      <th class="py-2 px-4 text-left">Total Cost</th>
                      <th class="py-2 px-4 text-left">Unit Cost</th>
                      <th class="py-2 px-4 text-left">Date Added</th>
                      <th class="py-2 px-4 text-left">Actions</th>
                  </tr>
              </thead>
              <tbody>
                  <?php while ($row = $result->fetch_assoc()): ?>
                      <tr class="border-b hover:bg-blue-50">
                          <td class="py-2 px-4"><?= $row['id'] ?></td>
                          <td class="py-2 px-4"><?= htmlspecialchars($row['stock_name']) ?></td>
                          <td class="py-2 px-4"><?= htmlspecialchars($row['stock_code']) ?></td>
                          <td class="py-2 px-4"><?= $row['quantity'] ?></td>
                          <td class="py-2 px-4"><?= $row['unit'] ?></td>
                          <td class="py-2 px-4"><?= number_format($row['total_cost'],2) ?></td>
                          <td class="py-2 px-4"><?= number_format($row['unit_cost'],2) ?></td>
                          <td class="py-2 px-4"><?= $row['created_at'] ?></td>
                          <td class="py-2 px-4">
                              <button onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['stock_name']) ?>', '<?= htmlspecialchars($row['stock_code']) ?>', <?= $row['quantity'] ?>, '<?= $row['unit'] ?>', <?= $row['total_cost'] ?>, <?= $row['unit_cost'] ?>)"
                                  class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">
                                  Edit
                              </button>
                          </td>
                      </tr>
                  <?php endwhile; ?>
              </tbody>
          </table>
      </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
      <div class="bg-white rounded-lg p-6 w-96">
          <h3 class="text-xl font-bold mb-4 text-blue-700">Edit Stock</h3>
          <form method="POST" id="editForm" class="space-y-3">
              <input type="hidden" name="id" id="edit_id">
              <div><label class="block text-gray-700 font-medium">Stock Name</label>
                  <input type="text" name="stock_name" id="edit_stock_name" class="w-full border rounded px-3 py-2" required>
              </div>
              <div><label class="block text-gray-700 font-medium">Stock Code</label>
                  <input type="text" name="stock_code" id="edit_stock_code" class="w-full border rounded px-3 py-2" required>
              </div>
              <div><label class="block text-gray-700 font-medium">Quantity</label>
                  <input type="number" name="quantity" id="edit_quantity" class="w-full border rounded px-3 py-2" required>
              </div>
              <div><label class="block text-gray-700 font-medium">Unit</label>
                  <input type="text" name="unit" id="edit_unit" class="w-full border rounded px-3 py-2" required>
              </div>
              <div><label class="block text-gray-700 font-medium">Total Cost (KSh)</label>
                  <input type="number" step="0.01" name="total_cost" id="edit_total_cost" class="w-full border rounded px-3 py-2" required>
              </div>
              <div><label class="block text-gray-700 font-medium">Unit Cost (KSh)</label>
                  <input type="number" step="0.01" name="unit_cost" id="edit_unit_cost" class="w-full border rounded px-3 py-2" required>
              </div>
              <div class="flex justify-between mt-4">
                  <button type="submit" name="edit_stock" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save</button>
                  <button type="button" onclick="closeEditModal()" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
              </div>
          </form>
      </div>
  </div>

  <script>
    function openEditModal(id, name, code, quantity, unit, total_cost, unit_cost) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_stock_name').value = name;
        document.getElementById('edit_stock_code').value = code;
        document.getElementById('edit_quantity').value = quantity;
        document.getElementById('edit_unit').value = unit;
        document.getElementById('edit_total_cost').value = total_cost;
        document.getElementById('edit_unit_cost').value = unit_cost;
        document.getElementById('editModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
  </script>

</body>
</html>
