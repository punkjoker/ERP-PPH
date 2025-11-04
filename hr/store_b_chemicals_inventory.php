<?php
include 'db_con.php';

$search = $_GET['search'] ?? '';
$search_sql = "";
if(!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $search_sql = " AND (cn.chemical_name LIKE '%$search_safe%' OR cn.chemical_code LIKE '%$search_safe%') ";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Store B Chemical Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl">
    <h2 class="text-xl font-bold mb-4">Store B Chemical Inventory</h2>

    <!-- Search Form -->
    <form method="GET" class="mb-4 flex items-center space-x-2">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
               placeholder="Search by Chemical Name or Code" 
               class="p-1 rounded border border-gray-300 w-64">
        <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">Search</button>
        <?php if(!empty($search)): ?>
            <a href="store_b_chemicals_inventory.php" class="text-blue-600 underline">Clear</a>
        <?php endif; ?>
    </form>

    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-blue-200">
                <tr>
                    <th class="px-4 py-2 text-left">Chemical Name</th>
                    <th class="px-4 py-2 text-left">Group Name</th>
                    <th class="px-4 py-2 text-left">Category</th>
                    <th class="px-4 py-2 text-left">Quantity</th>
                    <th class="px-4 py-2 text-left">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                $sql = "SELECT cn.chemical_name, cn.group_name, cn.category, cn.chemical_code,
                               IFNULL(SUM(sbci.remaining_quantity), '0') AS total_quantity
                        FROM chemical_names cn
                        LEFT JOIN store_b_chemicals_in sbci
                        ON cn.chemical_code = sbci.chemical_code
                        WHERE cn.main_category = 'Chemicals' $search_sql
                        GROUP BY cn.chemical_code, cn.chemical_name, cn.group_name, cn.category
                        ORDER BY cn.chemical_name ASC";

                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr class='hover:bg-gray-50'>
                            <td class='px-4 py-2'>{$row['chemical_name']}</td>
                            <td class='px-4 py-2'>{$row['group_name']}</td>
                            <td class='px-4 py-2'>{$row['category']}</td>
                            <td class='px-4 py-2'>{$row['total_quantity']}</td>
                            <td class='px-4 py-2'>
                                <a href='view_store_b_inventory.php?chemical_code={$row['chemical_code']}' 
                                   class='bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600'>
                                   View Inventories
                                </a>
                            </td>
                        </tr>";
                    }
                } else {
                    echo "<tr><td colspan='5' class='px-4 py-2 text-center'>No chemicals found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
