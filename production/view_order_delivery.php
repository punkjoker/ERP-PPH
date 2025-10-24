<?php 
include 'db_con.php';

$delivery_id = $_GET['id'] ?? 0;

// --- Fetch delivery batch info ---
$stmt = $conn->prepare("SELECT * FROM order_deliveries WHERE id = ?");
$stmt->bind_param('i', $delivery_id);
$stmt->execute();
$delivery = $stmt->get_result()->fetch_assoc();

// --- Fetch linked delivery orders ---
$query = "
    SELECT odi.destination, do.id AS delivery_order_id, 
           do.invoice_number, do.delivery_number, do.original_status,
           d.company_name
    FROM order_delivery_items odi
    JOIN delivery_orders do ON odi.delivery_order_id = do.id
    JOIN delivery_details d ON do.delivery_id = d.id
    WHERE odi.delivery_id = ?
";
$stmt2 = $conn->prepare($query);
$stmt2->bind_param('i', $delivery_id);
$stmt2->execute();
$linked_orders = $stmt2->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Delivery Batch</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-8 pt-24">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">📦 Delivery Batch Details</h2>

    <!-- Batch Info -->
    <div class="bg-white shadow-lg rounded-2xl p-6 mb-8">
        <p><strong>Delivery Day:</strong> <?= htmlspecialchars($delivery['delivery_day']) ?></p>
        <p><strong>Delivery Date:</strong> <?= htmlspecialchars($delivery['delivery_date']) ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($delivery['status']) ?></p>
    </div>

    <!-- Linked Delivery Orders -->
    <div class="bg-white shadow-lg rounded-2xl p-6">
        <h3 class="text-xl font-semibold mb-4 text-gray-700">Linked Delivery Orders</h3>
        <table class="min-w-full border-collapse text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-2 px-4 border-b text-left">#</th>
                    <th class="py-2 px-4 border-b text-left">Company</th>
                    <th class="py-2 px-4 border-b text-left">Destination</th>
                    <th class="py-2 px-4 border-b text-left">Invoice no#</th>
                    <th class="py-2 px-4 border-b text-left">Delivery no#</th>
                    <th class="py-2 px-4 border-b text-left">Status</th>
                    <th class="py-2 px-4 border-b text-center">Items</th>
                </tr>
            </thead>
            <tbody>
                <?php if($linked_orders->num_rows > 0): 
                    $i=1;
                    while($order = $linked_orders->fetch_assoc()):
                        // Fetch order items
                        $stmt3 = $conn->prepare("
                            SELECT item_name, quantity_removed, unit 
                            FROM delivery_order_items 
                            WHERE order_id = ?
                        ");
                        $stmt3->bind_param("i", $order['delivery_order_id']);
                        $stmt3->execute();
                        $items = $stmt3->get_result();
                ?>
                <tr class="<?= $i % 2 == 0 ? 'bg-gray-50' : 'bg-white' ?> hover:bg-gray-100">
                    <td class="py-2 px-4 border-b align-top"><?= $i++ ?></td>
                    <td class="py-2 px-4 border-b align-top"><?= htmlspecialchars($order['company_name']) ?></td>
                    <td class="py-2 px-4 border-b align-top"><?= htmlspecialchars($order['destination']) ?></td>
                    <td class="py-2 px-4 border-b align-top"><?= htmlspecialchars($order['invoice_number']) ?></td>
                    <td class="py-2 px-4 border-b align-top"><?= htmlspecialchars($order['delivery_number']) ?></td>
                    <td class="py-2 px-4 border-b align-top">
                        <span class="px-2 py-1 rounded text-xs 
                            <?= $order['original_status'] === 'Pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' ?>">
                            <?= htmlspecialchars($order['original_status']) ?>
                        </span>
                    </td>
                    <td class="py-2 px-4 border-b">
                        <?php if($items->num_rows > 0): ?>
                        <ul class="list-disc ml-5">
                            <?php while($it = $items->fetch_assoc()): ?>
                                <li><?= htmlspecialchars($it['item_name']) ?> - <?= htmlspecialchars($it['quantity_removed']) ?> <?= htmlspecialchars($it['unit']) ?></li>
                            <?php endwhile; ?>
                        </ul>
                        <?php else: ?>
                            <span class="text-gray-400 text-sm">No items</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="7" class="text-center py-4 text-gray-500">No linked delivery orders found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
