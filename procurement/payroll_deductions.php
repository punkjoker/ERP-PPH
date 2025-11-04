<?php
require 'db_con.php';

// ✅ Handle Add Deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_deduction'])) {
    $name = trim($_POST['deduction_name']);
    $rate = floatval($_POST['rate']);
    $desc = trim($_POST['description']);

    if (!empty($name) && $rate > 0) {
        $stmt = $conn->prepare("INSERT INTO deductions (deduction_name, rate, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $name, $rate, $desc);
        $stmt->execute();
        echo "<script>alert('Deduction added successfully'); window.location='payroll_deductions.php';</script>";
        exit;
    } else {
        echo "<script>alert('Please enter valid deduction name and rate');</script>";
    }
}

// ✅ Handle Delete Deduction
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM deductions WHERE deduction_id = $id");
    echo "<script>alert('Deduction deleted successfully'); window.location='payroll_deductions.php';</script>";
    exit;
}

// ✅ Handle Edit Deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_deduction'])) {
    $id = intval($_POST['deduction_id']);
    $name = trim($_POST['edit_name']);
    $rate = floatval($_POST['edit_rate']);
    $desc = trim($_POST['edit_description']);

    $stmt = $conn->prepare("UPDATE deductions SET deduction_name=?, rate=?, description=? WHERE deduction_id=?");
    $stmt->bind_param("sdsi", $name, $rate, $desc, $id);
    $stmt->execute();
    echo "<script>alert('Deduction updated successfully'); window.location='payroll_deductions.php';</script>";
    exit;
}

// ✅ Fetch all deductions
$result = $conn->query("SELECT * FROM deductions ORDER BY deduction_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payroll Deductions</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
<script>
function openEditModal(id, name, rate, desc) {
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_rate').value = rate;
    document.getElementById('edit_description').value = desc;
}

function closeModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Payroll Deductions</h1>

    <!-- ✅ Add Deduction Form -->
    <div class="bg-white shadow-md rounded-lg p-6 mb-8">
        <h2 class="text-lg font-semibold mb-4">Add New Deduction</h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-600">Deduction Name</label>
                <input type="text" name="deduction_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-blue-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600">Rate (%)</label>
                <input type="number" name="rate" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring focus:ring-blue-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600">Description</label>
                <input type="text" name="description" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="col-span-3">
                <button type="submit" name="add_deduction" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg mt-3">Add Deduction</button>
            </div>
        </form>
    </div>

    <!-- ✅ Deductions List -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full table-auto">
            <thead class="bg-blue-500 text-white">
                <tr>
                    <th class="px-4 py-2 text-left">#</th>
                    <th class="px-4 py-2 text-left">Deduction Name</th>
                    <th class="px-4 py-2 text-left">Rate (%)</th>
                    <th class="px-4 py-2 text-left">Description</th>
                    <th class="px-4 py-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    $i = 1;
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr class='border-b hover:bg-gray-50'>
                                <td class='px-4 py-2'>{$i}</td>
                                <td class='px-4 py-2'>" . htmlspecialchars($row['deduction_name']) . "</td>
                                <td class='px-4 py-2'>" . htmlspecialchars($row['rate']) . "%</td>
                                <td class='px-4 py-2'>" . htmlspecialchars($row['description']) . "</td>
                                <td class='px-4 py-2'>
                                    <button onclick=\"openEditModal('{$row['deduction_id']}', '{$row['deduction_name']}', '{$row['rate']}', '{$row['description']}')\" 
                                        class='bg-yellow-400 hover:bg-yellow-500 text-white px-3 py-1 rounded-lg text-sm'>Edit</button>
                                    <a href='?delete_id={$row['deduction_id']}' onclick='return confirm(\"Delete this deduction?\")' 
                                        class='bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-sm ml-2'>Delete</a>
                                </td>
                              </tr>";
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center py-4 text-gray-500'>No deductions found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ✅ Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-center">
    <div class="bg-white rounded-lg p-6 w-96 shadow-lg">
        <h2 class="text-lg font-semibold mb-4">Edit Deduction</h2>
        <form method="POST">
            <input type="hidden" name="deduction_id" id="edit_id">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-600">Name</label>
                <input type="text" name="edit_name" id="edit_name" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-600">Rate (%)</label>
                <input type="number" name="edit_rate" id="edit_rate" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-600">Description</label>
                <textarea name="edit_description" id="edit_description" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal()" class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded-lg">Cancel</button>
                <button type="submit" name="update_deduction" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">Update</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
