<?php
session_start();
require 'db_con.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chemical_name = trim($_POST['chemical_name']);
    $chemical_code = trim($_POST['chemical_code']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);

    if (!empty($chemical_name) && !empty($chemical_code) && !empty($category)) {
        $stmt = $conn->prepare("INSERT INTO chemical_names (chemical_name, chemical_code, category, description) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $chemical_name, $chemical_code, $category, $description);
        $stmt->execute();
        $stmt->close();
        $success = "✅ Chemical added successfully!";
    } else {
        $error = "⚠️ Please fill in all required fields.";
    }
}

// ✅ Fetch only chemicals where main_category = 'chemicals'
$query = "
    SELECT 
        c.id,
        c.chemical_name, 
        c.chemical_code,
        c.category,
        c.description,
        c.created_at,
        IFNULL(SUM(ci.remaining_quantity), 0) AS total_remaining
    FROM chemical_names c
    LEFT JOIN chemicals_in ci ON c.chemical_code = ci.chemical_code
    WHERE c.main_category = 'chemicals'
    GROUP BY c.chemical_code
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

<!-- Sidebar/Navbar -->
<?php include 'navbar.php'; ?>

<!-- Main Content Container -->
<div class="p-6 sm:ml-64 mt-20"> <!-- ✅ Add margin-top to fix overlap -->
    <div class="bg-white shadow-lg rounded-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-blue-700">Chemicals Inventory</h2>
            <!-- 🔍 Search bar -->
            <input 
                type="text" 
                id="searchInput" 
                placeholder="Search by name or code..." 
                class="border border-gray-300 rounded px-3 py-2 text-sm w-64 focus:ring focus:ring-blue-200"
            >
            <!-- ⬇️ Download button -->
        <a href="download_all_chemicals_inventory.php" 
           class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded shadow">
            ⬇️ Download
        </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-blue-50 text-gray-700 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Chemical Name</th>
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2">Remaining</th>
                        <th class="px-3 py-2">Description</th>
                        <th class="px-3 py-2">Date Added</th>
                        <th class="px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="chemicalTable">
                    <?php if (!empty($chemicals)): ?>
                        <?php foreach ($chemicals as $index => $chem): ?>
                            <tr class="<?= $index % 2 == 0 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50 transition">
                                <td class="px-3 py-2"><?= $index + 1 ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($chem['chemical_name']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($chem['chemical_code']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($chem['category']) ?></td>
                                <td class="px-3 py-2 font-semibold <?= $chem['total_remaining'] <= 0 ? 'text-red-600' : 'text-green-700' ?>">
                                    <?= number_format($chem['total_remaining'], 2) ?> kg
                                </td>
                                <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars($chem['description']) ?></td>
                                <td class="px-3 py-2 text-gray-500"><?= htmlspecialchars($chem['created_at']) ?></td>
                                <td class="px-3 py-2 text-center">
                                    <a href="view_lots.php?code=<?= urlencode($chem['chemical_code']) ?>" 
                                       class="text-blue-600 hover:text-blue-800 font-medium">View Lots</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-gray-500">No chemicals added yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 🔍 Search Filter Script -->
<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('#chemicalTable tr');

    rows.forEach(row => {
        const name = row.cells[1]?.innerText.toLowerCase() || '';
        const code = row.cells[2]?.innerText.toLowerCase() || '';
        row.style.display = (name.includes(searchValue) || code.includes(searchValue)) ? '' : 'none';
    });
});
</script>

</body>
</html>
