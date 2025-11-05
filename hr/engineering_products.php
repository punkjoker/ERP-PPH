<?php
session_start();
require 'db_con.php';

// ✅ Fetch engineering products with total remaining from stock_in
$query = "
    SELECT 
        c.id,
        c.chemical_name AS product_name,
        c.chemical_code AS product_code,
        c.category,
        c.description,
        c.created_at,
        IFNULL(SUM(s.quantity), 0) AS total_remaining
    FROM chemical_names c
    LEFT JOIN stock_in s ON c.chemical_code = s.stock_code
    WHERE c.main_category = 'Engineering Products'
    GROUP BY c.chemical_code
    ORDER BY c.id DESC
";
$result = $conn->query($query);
$products = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Engineering Products</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<!-- ✅ Include Navbar -->
<?php include 'navbar.php'; ?>

<!-- ✅ Main Content -->
<div class="p-6 sm:ml-64 mt-20">
    <div class="bg-white shadow-lg rounded-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-semibold text-blue-700">Engineering Products Inventory</h2>
            <!-- 🔍 Search bar -->
            <input 
                type="text" 
                id="searchInput" 
                placeholder="Search by name or code..." 
                class="border border-gray-300 rounded px-3 py-2 text-sm w-64 focus:ring focus:ring-blue-200"
            >
            <a href="download_engineering_inventory.php" 
   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow">
   ⬇ Download Report
</a>

        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-blue-50 text-gray-700 uppercase text-xs">
                    <tr>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Product Name</th>
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2">Remaining</th>
                        <th class="px-3 py-2">Description</th>
                        <th class="px-3 py-2">Date Added</th>
                        <th class="px-3 py-2 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="productTable">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $index => $prod): ?>
                            <tr class="<?= $index % 2 == 0 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50 transition">
                                <td class="px-3 py-2"><?= $index + 1 ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($prod['product_name']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($prod['product_code']) ?></td>
                                <td class="px-3 py-2"><?= htmlspecialchars($prod['category']) ?></td>
                                <td class="px-3 py-2 font-semibold <?= $prod['total_remaining'] <= 0 ? 'text-red-600' : 'text-green-700' ?>">
                                    <?= number_format($prod['total_remaining'], 2) ?> units
                                </td>
                                <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars($prod['description']) ?></td>
                                <td class="px-3 py-2 text-gray-500"><?= htmlspecialchars($prod['created_at']) ?></td>
                                <td class="px-3 py-2 text-center">
                                    <a href="view_engineering_lots.php?code=<?= urlencode($prod['product_code']) ?>" 
                                       class="text-blue-600 hover:text-blue-800 font-medium">
                                       View Engineering Lots
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-gray-500">No engineering products found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 🔍 Search Script -->
<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('#productTable tr');
    rows.forEach(row => {
        const name = row.cells[1]?.innerText.toLowerCase() || '';
        const code = row.cells[2]?.innerText.toLowerCase() || '';
        row.style.display = (name.includes(searchValue) || code.includes(searchValue)) ? '' : 'none';
    });
});
</script>

</body>
</html>
