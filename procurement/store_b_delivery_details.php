<?php
session_start();
require 'db_con.php';

// ✅ Date filter
$from_date = $_GET['from_date'] ?? date('Y-m-01'); // default: first day of current month
$to_date = $_GET['to_date'] ?? date('Y-m-t'); // default: last day of current month

// Fetch filtered deliveries
$stmt = $conn->prepare("
    SELECT * FROM order_deliveries_store_b
    WHERE delivery_date BETWEEN ? AND ?
    ORDER BY delivery_date DESC
");
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$deliveries = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Store B Delivery Batches</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-8 pt-24">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">📦 Store B Delivery Batches</h2>

    <!-- 🗓 Filter Form -->
    <form method="GET" class="flex gap-4 mb-6 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700">From</label>
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>"
                   class="border rounded-md p-2 focus:ring focus:ring-blue-300 mt-1">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">To</label>
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>"
                   class="border rounded-md p-2 focus:ring focus:ring-blue-300 mt-1">
        </div>
        <div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filter</button>
        </div>
    </form>

    <!-- 📋 Delivery Table -->
    <div class="bg-white shadow-lg rounded-2xl p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-2 px-4 border-b text-left">#</th>
                        <th class="py-2 px-4 border-b text-left">Delivery Day</th>
                        <th class="py-2 px-4 border-b text-left">Delivery Date</th>
                        <th class="py-2 px-4 border-b text-left">Status</th>
                        <th class="py-2 px-4 border-b text-left">Created</th>
                        <th class="py-2 px-4 border-b text-left">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($deliveries->num_rows > 0): 
                        $count = 1;
                        while ($row = $deliveries->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 border-b"><?= $count++ ?></td>
                            <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['delivery_day']) ?></td>
                            <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['delivery_date']) ?></td>
                            <td class="py-2 px-4 border-b">
                                <span class="px-3 py-1 rounded-full text-sm 
                                    <?= $row['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['created_at']) ?></td>
                            <td class="py-2 px-4 border-b">
                                <a href="view_store_b_delivery.php?id=<?= $row['id'] ?>" 
                                   class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600">
                                   View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-gray-500">
                                No Store B deliveries found for selected dates.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
