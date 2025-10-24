<?php
session_start();
require 'db_con.php';

// Ensure product ID is provided
if (!isset($_GET['id'])) {
    die("Product ID missing.");
}
$product_id = intval($_GET['id']);

// Fetch product details
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    die("Product not found.");
}

// Fetch BOM info
$bom_query = $conn->prepare("
    SELECT b.id AS bom_id, b.std_quantity, b.unit, b.created_at
    FROM bom b
    WHERE b.product_id = ?
");
$bom_query->bind_param("i", $product_id);
$bom_query->execute();
$bom_result = $bom_query->get_result()->fetch_assoc();
$bom_query->close();

$bom_id = $bom_result['bom_id'] ?? null;

// Fetch BOM materials if available
$materials_query = $conn->prepare("
    SELECT 
        n.chemical_name, 
        n.chemical_code, 
        bm.std_quantity, 
        bm.unit
    FROM bom_materials bm
    JOIN chemical_names n ON bm.chemical_id = n.id
    WHERE bm.bom_id = ?
    ORDER BY n.chemical_name ASC
");

    $materials_query->bind_param("i", $bom_id);
    $materials_query->execute();
    $materials = $materials_query->get_result()->fetch_all(MYSQLI_ASSOC);
    $materials_query->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Product BOM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>

    <div class="p-6 ml-64">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">
            View BOM for <?= htmlspecialchars($product['name']) ?>
        </h1>

        <?php if (!$bom_id): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-6">
                ⚠️ No Bill of Materials found for this product yet.
            </div>
            <a href="update_bom.php?id=<?= $product['id'] ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create BOM</a>

        <?php else: ?>
            <div class="bg-white shadow-lg rounded p-6 mb-6">
                <div class="mb-4 space-y-1">
                    <p class="text-gray-700"><strong>Product:</strong> <?= htmlspecialchars($product['name']) ?></p>
                    <p class="text-gray-700"><strong>Standard Batch Quantity:</strong> 
                        <?= htmlspecialchars($bom_result['std_quantity'] ?? 'N/A') ?> <?= htmlspecialchars($bom_result['unit'] ?? '') ?>
                    </p>
                    <p class="text-gray-700"><strong>Created On:</strong> <?= htmlspecialchars($bom_result['created_at']) ?></p>
                </div>

                <h2 class="text-xl font-semibold text-blue-700 mb-3">Raw Materials Used</h2>

                <?php if (!empty($materials)): ?>
                    <table class="w-full border border-gray-300 rounded">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="border px-3 py-2 text-left">#</th>
                                <th class="border px-3 py-2 text-left">Chemical Name</th>
                                <th class="border px-3 py-2 text-left">Chemical Code</th>
                                <th class="border px-3 py-2 text-left">Quantity (per Standard Batch)</th>
                                <th class="border px-3 py-2 text-left">Unit Kg/Ltrs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $index => $m): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="border px-3 py-2"><?= $index + 1 ?></td>
                                    <td class="border px-3 py-2"><?= htmlspecialchars($m['chemical_name']) ?></td>
                                    <td class="border px-3 py-2"><?= htmlspecialchars($m['chemical_code']) ?></td>
                                    <td class="border px-3 py-2"><?= htmlspecialchars($m['std_quantity']) ?></td>
                                    <td class="border px-3 py-2"><?= htmlspecialchars($m['unit']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-gray-600">No raw materials have been added for this BOM yet.</p>
                <?php endif; ?>
            </div>

            <a href="update_bom.php?id=<?= $product['id'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">✏️ Update BOM</a>
            <a href="add_product.php" class="ml-3 text-gray-700 underline">← Back to Products</a>
        <?php endif; ?>
    </div>
</body>
</html>
