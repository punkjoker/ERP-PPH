<?php
include 'db_con.php';

$delivery_id = $_GET['id'] ?? 0;

// Fetch delivery info
$stmt = $conn->prepare("SELECT * FROM order_deliveries WHERE id = ?");
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$delivery = $stmt->get_result()->fetch_assoc();

// Fetch delivery items from the correct table
$stmt2 = $conn->prepare("SELECT * FROM order_delivery_items WHERE delivery_id = ?");
$stmt2->bind_param('i', $delivery_id);
$stmt2->execute();
$items = $stmt2->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Delivery</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">📦 Delivery Details</h2>

    <div class="bg-white shadow-lg rounded-2xl p-6 mb-6">
        <p><strong>Delivery Day:</strong> <?= htmlspecialchars($delivery['delivery_day']) ?></p>
        <p><strong>Delivery Date:</strong> <?= htmlspecialchars($delivery['delivery_date']) ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($delivery['status']) ?></p>
    </div>

    <div class="bg-white shadow-lg rounded-2xl p-6">
        <h3 class="text-xl font-semibold mb-4 text-gray-700">Items</h3>
        <table class="min-w-full border-collapse text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-2 px-4 border-b text-left">#</th>
                    <th class="py-2 px-4 border-b text-left">Destination</th>
                    <th class="py-2 px-4 border-b text-left">Product</th>
                    <th class="py-2 px-4 border-b text-left">Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php if($items->num_rows > 0):
                    $i=1; while($item = $items->fetch_assoc()): ?>
                    <tr class="<?= $i % 2 == 0 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-gray-100">
                        <td class="py-1 px-3 border-b"><?= $i++ ?></td>
                        <td class="py-1 px-3 border-b"><?= htmlspecialchars($item['destination']) ?></td>
                        <td class="py-1 px-3 border-b"><?= htmlspecialchars($item['product_name']) ?></td>
                        <td class="py-1 px-3 border-b"><?= htmlspecialchars($item['quantity']) ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="4" class="text-center py-4 text-gray-500">No items found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
