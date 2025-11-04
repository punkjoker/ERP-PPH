<?php
include 'db_con.php';

// --- Get filters ---
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$item_name = $_GET['item_name'] ?? '';

// --- Base query ---
$sql = "
    SELECT 
        o.id AS order_id,
        o.company_name,
        o.invoice_number,
        o.delivery_number,
        o.original_status,
        i.item_name,
        i.material_name,
        i.pack_size,
        i.quantity_removed,
        i.unit,
        o.created_at
    FROM delivery_order_items_store_b i
    JOIN delivery_orders_store_b o ON i.order_id = o.id
    WHERE 1
";

// --- Apply filters ---
$params = [];
$types = '';

if (!empty($from_date) && !empty($to_date)) {
    $sql .= " AND DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
    $types .= "ss";
}

if (!empty($item_name)) {
    $sql .= " AND i.item_name = ?";
    $params[] = $item_name;
    $types .= "s";
}

$sql .= " ORDER BY o.id DESC, i.id ASC";

// --- Prepare & execute ---
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// --- Fetch unique item names for filter dropdown ---
$items_list = $conn->query("SELECT DISTINCT item_name FROM delivery_order_items_store_b ORDER BY item_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Store B Delivery Items</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="p-6 ml-64">
    <h1 class="text-2xl font-semibold text-blue-700 mb-6 text-center">📦 All Store B Delivery Items</h1>

    <!-- 🔍 Filter Form -->
    <form method="GET" class="bg-white shadow p-4 mb-6 rounded-lg flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">From Date</label>
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">To Date</label>
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Item Name</label>
            <select name="item_name" class="border rounded px-3 py-2">
                <option value="">-- All Items --</option>
                <?php while ($it = $items_list->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($it['item_name']) ?>" 
                        <?= ($item_name === $it['item_name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($it['item_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
        </div>

        <div>
            <a href="store_b_order_items.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Reset</a>
        </div>
    </form>

    <!-- 📋 Table -->
    <?php if ($result->num_rows > 0): ?>
        <table class="min-w-full border border-gray-300 divide-y divide-gray-200 bg-white shadow rounded-lg">
            <thead class="bg-blue-100">
                <tr>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Company</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Invoice No</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Delivery No</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Item</th>
        
                    <th class="px-4 py-2 text-left text-sm font-semibold">Pack Size</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Quantity</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Unit</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Status</th>
                    <th class="px-4 py-2 text-left text-sm font-semibold">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2"><?= htmlspecialchars($row['company_name']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['invoice_number']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['delivery_number']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['item_name']) ?></td>
                        
                        <td class="px-4 py-2"><?= htmlspecialchars($row['pack_size']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['quantity_removed']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($row['unit']) ?></td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded text-xs font-semibold 
                                <?= $row['original_status'] === 'Pending' ? 'bg-yellow-200 text-yellow-800' : 
                                    ($row['original_status'] === 'Completed' ? 'bg-green-200 text-green-800' : 'bg-red-200 text-red-800') ?>">
                                <?= htmlspecialchars($row['original_status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-sm"><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-center text-gray-500 py-6">No records found for the selected filters.</p>
    <?php endif; ?>

    
</div>

</body>
</html>
