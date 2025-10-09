
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

