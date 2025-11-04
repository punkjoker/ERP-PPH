<?php
include 'db_con.php';

// ✅ Get the chemical_code from URL
$chemical_code = $_GET['chemical_code'] ?? '';

if (empty($chemical_code)) {
    die("<p class='text-red-600 text-center mt-10'>Invalid request. No chemical code provided.</p>");
}

// ✅ Fetch chemical details
$stmt = $conn->prepare("SELECT chemical_name, group_name, category, main_category 
                        FROM chemical_names WHERE chemical_code = ?");
$stmt->bind_param("s", $chemical_code);
$stmt->execute();
$chemical = $stmt->get_result()->fetch_assoc();

// ✅ Fetch all inventories for this chemical
$sql = "SELECT * FROM store_b_chemicals_in 
        WHERE chemical_code = ? 
        ORDER BY receiving_date DESC, id DESC";
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param("s", $chemical_code);
$stmt2->execute();
$result = $stmt2->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Store B Inventories - <?= htmlspecialchars($chemical['chemical_name'] ?? 'Unknown') ?></title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl">
    <h2 class="text-2xl font-bold mb-2 text-blue-700">
        <?= htmlspecialchars($chemical['chemical_name'] ?? 'Unknown Chemical') ?>
    </h2>
    <p class="text-sm text-gray-700 mb-6">
        <strong>Group:</strong> <?= htmlspecialchars($chemical['group_name'] ?? '-') ?> |
        <strong>Category:</strong> <?= htmlspecialchars($chemical['category'] ?? '-') ?> |
        <strong>Main Category:</strong> <?= htmlspecialchars($chemical['main_category'] ?? '-') ?> |
        <strong>Chemical Code:</strong> <?= htmlspecialchars($chemical_code) ?>
    </p>

    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-blue-200">
                <tr>
                    <th class="px-4 py-2 text-left">Delivery #</th>
                    <th class="px-4 py-2 text-left">Quantity Received</th>
                    <th class="px-4 py-2 text-left">Remaining Quantity</th>
                    <th class="px-4 py-2 text-left">Units</th>
                    <th class="px-4 py-2 text-left">Pack Size</th>
                    <th class="px-4 py-2 text-left">Unit Cost</th>
                    <th class="px-4 py-2 text-left">PO Number</th>
                    <th class="px-4 py-2 text-left">Received By</th>
                    <th class="px-4 py-2 text-left">Receiving Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr class='hover:bg-gray-50'>
                                <td class='px-4 py-2'>".htmlspecialchars($row['delivery_number'])."</td>
                                <td class='px-4 py-2'>".htmlspecialchars($row['quantity_received'])."</td>
                                <td class='px-4 py-2'>".htmlspecialchars($row['remaining_quantity'])."</td>
                                <td class='px-4 py-2'>".htmlspecialchars($row['units'])."</td>
                                <td class='px-4 py-2'>".htmlspecialchars($row['pack_size'])."</td>
                                <td class='px-4 py-2'>".htmlspecialchars($row['unit_cost'])."</td>
                                <td class='px-4 py-2'>".htmlspecialchars($row['po_number'])."</td>
                                <td class='px-4 py-2'>".htmlspecialchars($row['received_by'])."</td>
                                <td class='px-4 py-2'>".htmlspecialchars($row['receiving_date'])."</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='8' class='px-4 py-2 text-center text-gray-600'>No inventories found for this chemical.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <a href="store_b_chemicals_inventory.php" 
           class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">← Back to Inventory List</a>
    </div>
</div>

</body>
</html>
