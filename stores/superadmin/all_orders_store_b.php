<?php
include 'db_con.php';

// Fetch all delivery orders (Store B)
$sql = "
SELECT 
    do.id AS order_id,
    do.id,
    do.company_name,
   
    do.delivery_number,
    do.invoice_number,
    do.original_status,
    do.created_at
FROM delivery_orders_store_b do

ORDER BY do.id DESC
";

$orders = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Store B Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="p-6 ml-64">


    <h1 class="text-2xl font-semibold mb-6 text-center text-blue-700">All Store B Orders</h1>

    <?php if ($orders->num_rows > 0): ?>
        <table class="min-w-full border border-gray-300 divide-y divide-gray-200">
            <thead class="bg-blue-100">
                <tr>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Order ID</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Company Name</th>
               
                    <th class="px-4 py-2 text-left text-sm font-semibold">Invoice No</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Delivery No</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Status</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Date</th>
                    <th class="px-4 py-2 text-center text-sm font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php while ($order = $orders->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2"><?= htmlspecialchars($order['order_id']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($order['company_name']) ?></td>
                       
                        <td class="px-4 py-2"><?= htmlspecialchars($order['invoice_number']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($order['delivery_number']) ?></td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs font-semibold 
                                <?= $order['original_status'] == 'Pending' ? 'bg-yellow-200 text-yellow-800' : 'bg-green-200 text-green-800' ?>">
                                <?= htmlspecialchars($order['original_status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-sm"><?= htmlspecialchars($order['created_at']) ?></td>
                       <td class="px-4 py-2 text-center">
    <a href="view_store_b_order_items.php?id=<?= $order['order_id'] ?>" 
       class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
       View Items
    </a>
</td>

                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-center text-gray-500 py-6">No orders found for Store B.</p>
    <?php endif; ?>
</div>

</body>
</html>
