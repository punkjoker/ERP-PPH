
<?php
include 'db_con.php';

$bom_id = $_GET['id'] ?? 0;

// ✅ Fetch production run details
$sql = "SELECT * FROM production_runs WHERE request_id = $bom_id LIMIT 1";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) {
    die("No production run found for BOM ID: $bom_id");
}
$production = $result->fetch_assoc();

// ✅ Fetch associated procedures
$procedures = [];
$proc_result = $conn->query("SELECT * FROM production_procedures WHERE production_run_id = {$production['id']}");
if ($proc_result && $proc_result->num_rows > 0) {
    while ($row = $proc_result->fetch_assoc()) {
        $procedures[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Production Run</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex font-sans">

    <?php include 'navbar.php'; ?>

    <div class="flex-1 p-8 ml-64">
        <!-- Header Section -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-6 flex items-center border-b-4 border-blue-600">
            <img src="images/lynn_logo.png" alt="Logo" class="h-16 mr-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">BATCH MANUFACTURING (QF-27)</h1>
                <p class="text-sm text-gray-600">PRODUCT NAME: 
                    <span class="font-semibold text-blue-700"><?= htmlspecialchars($production['product_name']) ?></span></p>
                <p class="text-sm text-gray-600">REQUESTED BY: 
                    <span class="font-semibold"><?= htmlspecialchars($production['requested_by']) ?></span></p>
                <p class="text-sm text-gray-600">STATUS: 
                    <span class="font-semibold <?= $production['status'] == 'Completed' ? 'text-green-600' : 'text-yellow-600' ?>">
                        <?= htmlspecialchars($production['status']) ?>
                    </span></p>
            </div>
        </div>
<?php
// ✅ Fetch BOM items
$sql = "SELECT 
            i.chemical_name, 
            i.chemical_code, 
            i.rm_lot_no, 
            i.po_number, 
            i.quantity_requested, 
            i.unit, 
            i.unit_price, 
            i.total_cost
        FROM bill_of_material_items i
        WHERE i.bom_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$chemicals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ✅ Calculate total (expected yield)
$total_quantity_requested = 0;
$total_cost = 0;
foreach ($chemicals as $c) {
    $total_quantity_requested += $c['quantity_requested'];
    $total_cost += $c['total_cost'];
}

// ✅ Autofill expected yield in production record
if (empty($production['expected_yield'])) {
    $production['expected_yield'] = $total_quantity_requested;
}
?>

<!-- ✅ Bill of Materials Section -->
<section class="mb-8">
    <h3 class="text-lg font-semibold text-blue-700 border-b pb-2 mb-4">Bill of Materials</h3>
    <div class="overflow-x-auto">
        <table class="w-full border border-gray-300 text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border px-3 py-2 text-left">Chemical</th>
                    <th class="border px-3 py-2 text-left">Chemical Code</th>
                    <th class="border px-3 py-2 text-left">RM LOT NO</th>
                    <th class="border px-3 py-2 text-left">PO NO</th>
                    <th class="border px-3 py-2 text-left">Qty Requested</th>
                    <th class="border px-3 py-2 text-left">Unit</th>
                    <th class="border px-3 py-2 text-left">Unit Price</th>
                    <th class="border px-3 py-2 text-left">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chemicals as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="border px-3 py-2"><?= htmlspecialchars($c['chemical_name']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($c['chemical_code']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($c['rm_lot_no']) ?></td>
                    <td class="border px-3 py-2">PO#<?= htmlspecialchars($c['po_number']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($c['quantity_requested']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($c['unit']) ?></td>
                    <td class="border px-3 py-2"><?= number_format($c['unit_price'], 2) ?></td>
                    <td class="border px-3 py-2"><?= number_format($c['total_cost'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="bg-gray-100 font-semibold">
                    <td colspan="7" class="text-right border px-3 py-2">Total Production Cost</td>
                    <td class="border px-3 py-2"><?= number_format($total_cost, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

        <!-- Production Details -->
        <div class="bg-white shadow-lg rounded-lg p-6 border mb-8">
            <h2 class="text-xl font-semibold text-blue-700 mb-4 border-b pb-2">Production Details</h2>
            <div class="grid grid-cols-2 gap-6 text-sm">
                <p><span class="font-medium text-gray-700">Expected Yield:</span> <?= htmlspecialchars($production['expected_yield']) ?> Kg/L</p>
                <p><span class="font-medium text-gray-700">Obtained Yield:</span> <?= htmlspecialchars($production['obtained_yield']) ?> Kg/L</p>
                <p><span class="font-medium text-gray-700">Description:</span> <?= htmlspecialchars($production['description']) ?></p>
                <p><span class="font-medium text-gray-700">Completed At:</span> 
                    <?= !empty($production['completed_at']) ? date('d M Y, h:i A', strtotime($production['completed_at'])) : '—' ?>
                </p>
            </div>
        </div>

        <!-- Procedures List -->
        <div class="bg-white shadow-lg rounded-lg p-6 border">
            <h2 class="text-xl font-semibold text-blue-700 mb-4 border-b pb-2">Procedures Done</h2>
            <?php if (count($procedures) > 0): ?>
                <table class="min-w-full border border-gray-300 rounded-lg overflow-hidden text-sm">
                    <thead class="bg-blue-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Procedure Name</th>
                            <th class="px-4 py-2 text-left">Done By</th>
                            <th class="px-4 py-2 text-left">Checked By</th>
                            <th class="px-4 py-2 text-left">Date Recorded</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($procedures as $index => $proc): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2"><?= $index + 1 ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($proc['procedure_name']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($proc['done_by']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($proc['checked_by']) ?></td>
                                <td class="px-4 py-2"><?= date('d M Y, h:i A', strtotime($proc['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-500 italic">No procedures recorded yet for this production run.</p>
            <?php endif; ?>
        </div>

        <!-- Back Button -->
        <div class="mt-8 flex justify-end">
            <a href="record_production_run.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg shadow hover:bg-blue-700 transition">
                ⬅ Back to Production Runs
            </a>
        </div>
    </div>
</body>
</html>

