<?php
include 'db_con.php';

$search = $_GET['search'] ?? '';
$search_sql = "";
if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $search_sql = " AND (p.name LIKE '%$search_safe%' OR p.product_code LIKE '%$search_safe%') ";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Store B Finished Products Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl">
    <h2 class="text-xl font-bold mb-4">Store B Finished Products Inventory</h2>

    <!-- Search Form -->
    <form method="GET" class="mb-4 flex items-center space-x-2">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
               placeholder="Search by Product Name or Code" 
               class="p-1 rounded border border-gray-300 w-64">
        <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">Search</button>
        <?php if (!empty($search)): ?>
            <a href="store_b_finished_products_inventory.php" class="text-blue-600 underline">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Table -->
    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-blue-200">
                <tr>
                    <th class="px-4 py-2 text-left">Product Name</th>
                    <th class="px-4 py-2 text-left">Category</th>
                    <th class="px-4 py-2 text-left">Description</th>
                    <th class="px-4 py-2 text-left">Remaining Quantity</th>
                    <th class="px-4 py-2 text-left">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                $sql = "SELECT 
                            p.name AS product_name,
                            p.category,
                            p.description,
                            p.product_code,
                            IFNULL(SUM(sfp.remaining_quantity), 0) AS total_remaining
                        FROM products p
                        LEFT JOIN store_b_finished_products_in sfp
                        ON p.product_code = sfp.product_code
                        WHERE 1 $search_sql
                        GROUP BY p.product_code, p.name, p.category, p.description
                        ORDER BY p.name ASC";

                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr class='hover:bg-gray-50'>
                            <td class='px-4 py-2'>" . htmlspecialchars($row['product_name']) . "</td>
                            <td class='px-4 py-2'>" . htmlspecialchars($row['category']) . "</td>
                            <td class='px-4 py-2'>" . htmlspecialchars($row['description']) . "</td>
                            <td class='px-4 py-2'>" . htmlspecialchars($row['total_remaining']) . "</td>
                            <td class='px-4 py-2'>
                                <a href='view_store_b_finished_inventory.php?product_code=" . urlencode($row['product_code']) . "' 
                                   class='bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600'>
                                   View Inventories
                                </a>
                            </td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' class='px-4 py-2 text-center'>No finished products found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
