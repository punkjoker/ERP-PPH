<?php
include 'db_con.php';

// Get product_code from query
$product_code = $_GET['product_code'] ?? '';

if (empty($product_code)) {
    die("<p class='text-red-600 text-center mt-10'>Error: Missing product code.</p>");
}

// Fetch basic product info
$stmt = $conn->prepare("SELECT product_name, category FROM store_b_finished_products_in WHERE product_code = ? LIMIT 1");
$stmt->bind_param("s", $product_code);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch all inventories with same product_code
$sql = "SELECT * FROM store_b_finished_products_in WHERE product_code = ? ORDER BY receiving_date DESC, id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $product_code);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Finished Product Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl">
    <h2 class="text-xl font-bold mb-2">Finished Product Inventory Details</h2>
    <?php if ($product): ?>
        <p class="text-gray-700 mb-4">
            <strong>Product:</strong> <?= htmlspecialchars($product['product_name']) ?><br>
            <strong>Category:</strong> <?= htmlspecialchars($product['category']) ?><br>
            <strong>Product Code:</strong> <?= htmlspecialchars($product_code) ?>
        </p>
    <?php endif; ?>

    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-blue-200">
                <tr>
                    <th class="px-4 py-2 text-left">Delivery No.</th>
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
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2"><?= htmlspecialchars($row['delivery_number']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['quantity_received']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['remaining_quantity']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['units']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['pack_size']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['unit_cost']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['po_number']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['received_by']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($row['receiving_date']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="px-4 py-3 text-center text-gray-600">No inventory records found for this product.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <a href="store_b_finished_products_inventory.php" class="text-blue-600 underline">← Back to Finished Products</a>
    </div>
</div>

</body>
</html>
