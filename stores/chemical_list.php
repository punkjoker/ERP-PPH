<?php
session_start();
require 'db_con.php';

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chemical_name = trim($_POST['chemical_name']);
    $main_category = trim($_POST['main_category']);
    $group_name = trim($_POST['group_name']);
    $group_code = trim($_POST['group_code']);
    $chemical_code = trim($_POST['chemical_code']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);

    if (!empty($chemical_name) && !empty($main_category) && !empty($group_name) && !empty($group_code) && !empty($chemical_code)) {
        $stmt = $conn->prepare("
            INSERT INTO chemical_names 
            (chemical_name, main_category, group_name, group_code, chemical_code, category, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssss", $chemical_name, $main_category, $group_name, $group_code, $chemical_code, $category, $description);
        $stmt->execute();
        $stmt->close();
        $success = "✅ Chemical added successfully!";
    } else {
        $error = "⚠️ Please fill in all required fields.";
    }
}

// ✅ Fetch chemical list
$query = "
    SELECT 
        c.id,
        c.chemical_name,
        c.main_category,
        c.group_name,
        c.group_code,
        c.chemical_code,
        c.category,
        c.description,
        c.created_at
    FROM chemical_names c
    ORDER BY c.id DESC
";
$result = $conn->query($query);
$chemicals = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chemical Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<!-- Navbar -->
<?php include 'navbar.php'; ?>

<!-- Main Content -->
<div class="p-6 ml-64">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">Add New Item</h1>

    <!-- Messages -->
    <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php elseif (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Add Chemical Form -->
    <div class="bg-white shadow-lg rounded p-6 mb-8">
        <form method="POST" class="grid grid-cols-2 gap-6">
            <div>
                <label class="block text-gray-700 font-semibold">Item Name *</label>
                <input type="text" name="chemical_name" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold">Main Category *</label>
                <select name="main_category" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300">
                    <option value="">-- Select Main Category --</option>
                    <option value="Chemicals">Chemicals</option>
                    <option value="Packaging Materials">Packaging Materials</option>
                    <option value="Engineering Products">Engineering Products</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 font-semibold">Group Name *</label>
                <input type="text" name="group_name" placeholder="e.g., Acids" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold">Group Code *</label>
                <input type="text" name="group_code" placeholder="e.g., GRP001" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold">Item Code *</label>
                <input type="text" name="chemical_code" placeholder="e.g., CHM001" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold">Category *</label>
                <input type="text" name="category" placeholder="e.g., Liquid" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300">
            </div>

            <div class="col-span-2">
                <label class="block text-gray-700 font-semibold">Description</label>
                <textarea name="description" rows="3" placeholder="Optional description..." class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-300"></textarea>
            </div>

            <div class="col-span-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    ➕ Add Item
                </button>
            </div>
        </form>
    </div>

    <!-- Chemicals List -->
    <div class="bg-white shadow-lg rounded-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-blue-700">Items Names</h2>
            <!-- 🔍 Search bar -->
            <input 
                type="text" 
                id="searchInput" 
                placeholder="Search by name, code, or group..." 
                class="border border-gray-300 rounded px-3 py-2 text-sm w-64 focus:ring focus:ring-blue-200"
            >
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-blue-50 text-gray-700 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Main Category</th>
                        <th class="px-3 py-2">Group Name</th>
                        <th class="px-3 py-2">Group Code</th>
                        <th class="px-3 py-2">Chemical Code</th>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2">Description</th>
                        <th class="px-3 py-2">Date Added</th>
                    </tr>
                </thead>
                <tbody id="chemicalTable">
                    <?php if (!empty($chemicals)): ?>
                        <?php foreach ($chemicals as $index => $chem): ?>
                            <tr class="<?= $index % 2 == 0 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50 transition">
                                <td class="px-3 py-2"><?= $index + 1 ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($chem['chemical_name']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($chem['main_category']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($chem['group_name']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($chem['group_code']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($chem['chemical_code']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($chem['category']) ?></td>
                                <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars($chem['description']) ?></td>
                                <td class="px-3 py-2 text-gray-500"><?= htmlspecialchars($chem['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-3 py-4 text-center text-gray-500">No chemicals added yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 🔍 Search Filter -->
<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    const val = this.value.toLowerCase();
    document.querySelectorAll('#chemicalTable tr').forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(val) ? '' : 'none';
    });
});
</script>

</body>
</html>
