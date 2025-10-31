<?php
session_start();
require 'db_con.php';

// ✅ Handle date filter
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

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
        JOIN products p ON b.product_id = p.id";

$conditions = [];
$params = [];

if (!empty($start_date)) {
    $conditions[] = "DATE(b.bom_date) >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $conditions[] = "DATE(b.bom_date) <= ?";
    $params[] = $end_date;
}

if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$boms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Production Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="p-6 ml-64">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">🧪 Production Requests</h1>

    <!-- ✅ Filter Form -->
    <form method="GET" class="flex flex-wrap items-center gap-4 mb-6 bg-white p-4 rounded-lg shadow">
        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700">From:</label>
            <input type="date" id="start_date" name="start_date"
                   value="<?= htmlspecialchars($start_date) ?>"
                   class="border border-gray-300 rounded-md px-3 py-1 text-sm focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700">To:</label>
            <input type="date" id="end_date" name="end_date"
                   value="<?= htmlspecialchars($end_date) ?>"
                   class="border border-gray-300 rounded-md px-3 py-1 text-sm focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg mt-5 text-sm shadow">
            Filter
        </button>
        <a href="production_requests.php" class="ml-2 text-blue-600 mt-5 underline text-sm">Reset</a>
    </form>

    <!-- ✅ Requests Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <table class="min-w-full">
            <thead class="bg-blue-600 text-white text-sm uppercase">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold">Date</th>
                    <th class="px-3 py-2 text-left font-semibold">Product</th>
                    <th class="px-3 py-2 text-left font-semibold">Batch No.</th>
                    <th class="px-3 py-2 text-left font-semibold">Requested By</th>
                    <th class="px-3 py-2 text-left font-semibold">Description</th>
                    <th class="px-3 py-2 text-left font-semibold">Status</th>
                    <th class="px-3 py-2 text-left font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="text-sm">
            <?php if (!empty($boms)): ?>
                <?php foreach ($boms as $b): ?>
                    <tr class="border-b hover:bg-gray-50 shadow-sm transition duration-150">
                        <td class="px-3 py-1"><?= htmlspecialchars($b['bom_date']) ?></td>
                        <td class="px-3 py-1"><?= htmlspecialchars($b['product_name']) ?></td>
                        <td class="px-3 py-1"><?= htmlspecialchars($b['batch_number']) ?></td>
                        <td class="px-3 py-1"><?= htmlspecialchars($b['requested_by']) ?></td>
                        <td class="px-3 py-1 text-gray-700"><?= htmlspecialchars($b['description']) ?></td>
                        <td class="px-3 py-1 font-semibold 
                            <?= $b['status']=='Pending'?'text-yellow-600':
                               ($b['status']=='Approved'?'text-green-600':'text-red-600') ?>">
                            <?= htmlspecialchars($b['status']) ?>
                        </td>
                        <td class="px-3 py-1 space-x-2">
                            <a href="update_request.php?id=<?= $b['bom_id'] ?>"
                               class="bg-yellow-500 hover:bg-yellow-600 text-white px-2 py-1 rounded text-xs shadow">
                               Update
                            </a>
                            <a href="view_bom.php?id=<?= $b['bom_id'] ?>"
                               class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs shadow">
                               View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center text-gray-500 py-4">No production requests found</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
