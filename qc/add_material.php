<?php
session_start();
require 'db_con.php'; // adjust if needed

// Handle form submission (Add Material)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_material'])) {
    $material_name = trim($_POST['material_name']);
    $cost = trim($_POST['cost']);
    $quantity = trim($_POST['quantity']);

    if (!empty($material_name) && !empty($cost) && !empty($quantity)) {
        $stmt = $conn->prepare("INSERT INTO materials (material_name, cost, quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("sdi", $material_name, $cost, $quantity);
        if ($stmt->execute()) {
            $message = "✅ Material added successfully!";
        } else {
            $message = "❌ Error adding material: " . $conn->error;
        }
    } else {
        $message = "⚠️ Please fill all fields.";
    }
}

// Handle Edit Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_material'])) {
    $id = $_POST['id'];
    $material_name = trim($_POST['material_name']);
    $cost = trim($_POST['cost']);
    $quantity = trim($_POST['quantity']);

    $stmt = $conn->prepare("UPDATE materials SET material_name=?, cost=?, quantity=? WHERE id=?");
    $stmt->bind_param("sdii", $material_name, $cost, $quantity, $id);
    if ($stmt->execute()) {
        $message = "✅ Material updated successfully!";
    } else {
        $message = "❌ Error updating material: " . $conn->error;
    }
}

// Fetch all materials
$result = $conn->query("SELECT * FROM materials ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Materials - Lynntech</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen px-4">

    <!-- Navbar -->
    <?php include 'navbar.php'; ?> 

    <div class="max-w-4xl ml-64 mx-auto mt-24 p-6 bg-white rounded-xl shadow-lg">
        <h2 class="text-3xl font-bold text-blue-700 mb-6 text-center">Add Material</h2>

        <!-- Message -->
        <?php if (!empty($message)): ?>
            <div class="mb-4 text-center text-white px-4 py-2 rounded 
                <?php echo (str_contains($message,'✅')) ? 'bg-green-500' : 'bg-red-500'; ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Add Material Form -->
        <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="material_name" class="block text-gray-700 font-medium mb-1">Material Name</label>
                <input type="text" id="material_name" name="material_name"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                       placeholder="e.g. Jerrycan" required>
            </div>
            <div>
                <label for="cost" class="block text-gray-700 font-medium mb-1">Cost (KSh)</label>
                <input type="number" step="0.01" id="cost" name="cost"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                       placeholder="e.g. 150.00" required>
            </div>
            <div>
                <label for="quantity" class="block text-gray-700 font-medium mb-1">Quantity</label>
                <input type="number" id="quantity" name="quantity"
                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                       placeholder="e.g. 100" required>
            </div>
            <div class="md:col-span-3 text-center">
                <button type="submit" name="add_material"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow-md transition">
                    Add Material
                </button>
            </div>
        </form>
    </div>

    <!-- Existing Materials -->
    <div class="max-w-6xl ml-64 mx-auto mt-8 p-6 bg-white rounded-xl shadow-lg">
        <h3 class="text-2xl font-bold text-blue-700 mb-4 text-center">Existing Materials</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-200 rounded-lg">
                <thead class="bg-blue-600 text-white">
                    <tr>
                        <th class="py-2 px-4 text-left">ID</th>
                        <th class="py-2 px-4 text-left">Material Name</th>
                        <th class="py-2 px-4 text-left">Cost (KSh)</th>
                        <th class="py-2 px-4 text-left">Quantity</th>
                        <th class="py-2 px-4 text-left">Date Added</th>
                        <th class="py-2 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-blue-50">
                            <td class="py-2 px-4"><?= $row['id'] ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($row['material_name']) ?></td>
                            <td class="py-2 px-4"><?= number_format($row['cost'], 2) ?></td>
                            <td class="py-2 px-4"><?= $row['quantity'] ?></td>
                            <td class="py-2 px-4"><?= $row['created_at'] ?></td>
                            <td class="py-2 px-4">
                                <!-- Edit Button (Modal Trigger) -->
                                <button onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['material_name']) ?>', <?= $row['cost'] ?>, <?= $row['quantity'] ?>)"
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
            <h3 class="text-xl font-bold mb-4 text-blue-700">Edit Material</h3>
            <form method="POST" id="editForm" class="space-y-3">
                <input type="hidden" name="id" id="edit_id">
                <div>
                    <label class="block text-gray-700 font-medium">Material Name</label>
                    <input type="text" name="material_name" id="edit_material_name" class="w-full border rounded px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-gray-700 font-medium">Cost (KSh)</label>
                    <input type="number" step="0.01" name="cost" id="edit_cost" class="w-full border rounded px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-gray-700 font-medium">Quantity</label>
                    <input type="number" name="quantity" id="edit_quantity" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="flex justify-between mt-4">
                    <button type="submit" name="edit_material" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save</button>
                    <button type="button" onclick="closeEditModal()" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name, cost, quantity) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_material_name').value = name;
            document.getElementById('edit_cost').value = cost;
            document.getElementById('edit_quantity').value = quantity;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>

</body>
</html>
