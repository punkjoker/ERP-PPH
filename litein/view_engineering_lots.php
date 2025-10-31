<?php
session_start();
require 'db_con.php';

// ✅ Ensure chemical code is passed
if (!isset($_GET['code'])) {
    die("Chemical code missing.");
}

$chemical_code = trim($_GET['code']);

// ✅ Fetch chemical details
$sql = "SELECT id, chemical_name, chemical_code, main_category, group_name, category, description, created_at 
        FROM chemical_names 
        WHERE chemical_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $chemical_code);
$stmt->execute();
$chemical = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$chemical) {
    die("Chemical not found.");
}

// ✅ Fetch lots from stock_in for this chemical (sorted oldest → newest)
$sql = "SELECT 
            id,
            stock_name,
            stock_code,
            po_number,
            batch_no,
            original_quantity,
            quantity,
            unit,
            unit_cost,
            total_cost,
            created_at
        FROM stock_in
        WHERE stock_code = ?
        ORDER BY created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $chemical_code);
$stmt->execute();
$lots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Engineering Lots - <?= htmlspecialchars($chemical['chemical_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include 'navbar.php'; ?>

    <div class="p-6 ml-64">
        <div class="bg-white shadow-lg rounded-lg p-8 max-w-6xl mx-auto">
            
            <!-- Header -->
            <div class="border-b pb-4 mb-6 text-center">
                <h1 class="text-3xl font-bold text-gray-800">Engineering Batch History</h1>
                <p class="text-gray-600">Production Department</p>
            </div>

            <!-- Chemical Info -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Material Details</h2>
                <p><span class="font-semibold">Chemical Name:</span> <?= htmlspecialchars($chemical['chemical_name']) ?></p>
                <p><span class="font-semibold">Chemical Code:</span> <?= htmlspecialchars($chemical['chemical_code']) ?></p>
                <p><span class="font-semibold">Main Category:</span> <?= htmlspecialchars($chemical['main_category'] ?? '-') ?></p>
                <p><span class="font-semibold">Group:</span> <?= htmlspecialchars($chemical['group_name'] ?? '-') ?> (<?= htmlspecialchars($chemical['group_code'] ?? '-') ?>)</p>
                <p><span class="font-semibold">Category:</span> <?= htmlspecialchars($chemical['category'] ?? '-') ?></p>
                <p><span class="font-semibold">Description:</span> <?= htmlspecialchars($chemical['description'] ?? '-') ?></p>
            </div>

            <!-- Lots Table -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Batch (Lot) Records</h2>

                <?php if (count($lots) > 0): ?>
                    <table class="w-full border border-gray-300 text-sm">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="border px-3 py-2 text-left">Batch No</th>
                                <th class="border px-3 py-2 text-left">PO Number</th>
                                <th class="border px-3 py-2 text-left">Stock Name</th>
                                <th class="border px-3 py-2 text-left">Original Qty</th>
                                <th class="border px-3 py-2 text-left">Remaining Qty</th>
                                <th class="border px-3 py-2 text-left">Unit</th>
                                <th class="border px-3 py-2 text-left">Unit Cost</th>
                                <th class="border px-3 py-2 text-left">Total Cost</th>
                                <th class="border px-3 py-2 text-left">Date Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lots as $lot): ?>
                            <tr class="hover:bg-blue-50">
                                <td class="border px-3 py-2"><?= htmlspecialchars($lot['batch_no'] ?? '-') ?></td>
                                <td class="border px-3 py-2">PO#<?= htmlspecialchars($lot['po_number'] ?? '-') ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($lot['stock_name']) ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($lot['original_quantity'] ?? '-') ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($lot['quantity'] ?? '-') ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($lot['unit']) ?></td>
                                <td class="border px-3 py-2"><?= number_format($lot['unit_cost'], 2) ?></td>
                                <td class="border px-3 py-2"><?= number_format($lot['total_cost'], 2) ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($lot['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-gray-600 mt-2">No lots found for this material.</p>
                <?php endif; ?>
            </div>

            <!-- Buttons -->
            <div class="flex justify-between mt-6">
                <a href="engineering_products.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded">
                    Back
                </a>
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">
                    Print
                </button>
            </div>

        </div>
    </div>
</body>
</html>
