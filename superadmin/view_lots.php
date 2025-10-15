<?php
session_start();
require 'db_con.php';

// Get chemical code from URL
if (!isset($_GET['code'])) {
    die("Chemical code missing.");
}
$chemical_code = trim($_GET['code']);

// Fetch chemical details
$stmt = $conn->prepare("SELECT chemical_name, category, description FROM chemical_names WHERE chemical_code = ?");
$stmt->bind_param("s", $chemical_code);
$stmt->execute();
$chem_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$chem_details) {
    die("Chemical not found.");
}

// Fetch all lots for this chemical (FIFO order)
$stmt = $conn->prepare("
    SELECT 
        id,
        rm_lot_no,
        batch_no,
        po_number,
        std_quantity,
        original_quantity,
        remaining_quantity,
        total_cost,
        unit_price,
        status,
        date_added,
        action_type,
        created_at
    FROM chemicals_in
    WHERE chemical_code = ?
    ORDER BY date_added ASC, id ASC
");
$stmt->bind_param("s", $chemical_code);
$stmt->execute();
$lots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($chem_details['chemical_name']) ?> - Lot Details</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="p-6 ml-64">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($chem_details['chemical_name']) ?></h1>
                <p class="text-gray-600 text-sm">Code: <span class="font-semibold"><?= htmlspecialchars($chemical_code) ?></span></p>
                <p class="text-gray-600 text-sm">Category: <?= htmlspecialchars($chem_details['category']) ?></p>
                <p class="text-gray-600 text-sm">Description: <?= htmlspecialchars($chem_details['description']) ?></p>
            </div>
            <a href="chemical_list.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow">← Back to List</a>
        </div>

        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
    <h2 class="text-xl font-semibold mb-3 text-blue-700 px-4 pt-4">Lot Details (FIFO)</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
            <thead class="bg-blue-50 text-gray-700 uppercase text-xs">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">Lot No</th>
                    <th class="px-3 py-2">Batch No</th>
                    <th class="px-3 py-2">PO No</th>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Action</th>
                    <th class="px-3 py-2">Unit Price</th>
                    <th class="px-3 py-2">Total Cost</th>
                    <th class="px-3 py-2">Original Qty</th>
                    <th class="px-3 py-2">Remaining</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($lots)): ?>
                    <?php foreach ($lots as $i => $lot): ?>
                        <tr class="<?= $i % 2 == 0 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50 transition">
                            <td class="px-3 py-2"><?= $i + 1 ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($lot['rm_lot_no']) ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($lot['batch_no']) ?></td>
                            <td class="px-3 py-2">PO#<?= htmlspecialchars($lot['po_number']) ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($lot['date_added']) ?></td>
                            <td class="px-3 py-2"><?= htmlspecialchars($lot['action_type']) ?></td>
                            <td class="px-3 py-2">Ksh <?= number_format($lot['unit_price'], 2) ?></td>
                            <td class="px-3 py-2">Ksh <?= number_format($lot['total_cost'], 2) ?></td>
                            <td class="px-3 py-2"><?= number_format($lot['std_quantity'], 2) ?> kg</td>
                            <td class="px-3 py-2 font-semibold <?= $lot['remaining_quantity'] <= 0 ? 'text-red-600' : 'text-green-700' ?>">
                                <?= number_format($lot['remaining_quantity'], 2) ?> kg
                            </td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    <?= $lot['status'] == 'Approved' ? 'bg-green-100 text-green-800' : ($lot['status'] == 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                    <?= htmlspecialchars($lot['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" class="px-3 py-4 text-center text-gray-500">No lot records found for this chemical.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
