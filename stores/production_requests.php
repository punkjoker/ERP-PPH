<?php
session_start();
require 'db_con.php';

// Fetch all BOM requests
$sql = "SELECT 
            b.id AS bom_id,
            b.bom_date,
            b.created_at,
            b.status,
            b.requested_by,
            b.description,
            b.batch_number,
            p.name AS product_name
        FROM bill_of_materials b
        JOIN products p ON b.product_id = p.id
        ORDER BY b.created_at DESC";
$boms = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Production Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="p-6 ml-64">
        <h1 class="text-3xl font-bold mb-6 text-gray-800">Production Requests</h1>

        <!-- Requests Table -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <table class="min-w-full border border-gray-200">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="border px-4 py-2 text-left">Date</th>
                        <th class="border px-4 py-2 text-left">Product</th>
                        <th class="border px-4 py-2 text-left">Batch Number</th>
                        <th class="border px-4 py-2 text-left">Requested By</th>
                        <th class="border px-4 py-2 text-left">Description</th>
                        <th class="border px-4 py-2 text-left">Status</th>
                        <th class="border px-4 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($boms)): ?>
                    <?php foreach ($boms as $b): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border px-4 py-2"><?= htmlspecialchars($b['bom_date']) ?></td>
                            <td class="border px-4 py-2"><?= htmlspecialchars($b['product_name']) ?></td>
                            <td class="border px-4 py-2"><?= htmlspecialchars($b['batch_number']) ?></td>
                            <td class="border px-4 py-2"><?= htmlspecialchars($b['requested_by']) ?></td>
                            <td class="border px-4 py-2"><?= htmlspecialchars($b['description']) ?></td>
                            <td class="border px-4 py-2 font-semibold 
                                <?= $b['status']=='Pending'?'text-yellow-600':
                                   ($b['status']=='Approved'?'text-green-600':'text-red-600') ?>">
                                <?= htmlspecialchars($b['status']) ?>
                            </td>
                            <td class="border px-4 py-2 space-x-2">
                                <!-- Update button -->
                                <a href="update_request.php?id=<?= $b['bom_id'] ?>" 
                                   class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded">
                                   Update
                                </a>
                                <!-- View button -->
                                <a href="view_bom.php?id=<?= $b['bom_id'] ?>" 
                                   class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">
                                   View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-gray-500">No production requests found</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
