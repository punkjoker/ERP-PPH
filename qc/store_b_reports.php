<?php
include 'db_con.php';

// --- Fetch total quantities by item and category ---
function getCategoryData($conn, $tableName, $label) {
    $stmt = $conn->prepare("
        SELECT item_name, SUM(quantity_removed) AS total_removed
        FROM delivery_order_items_store_b
        WHERE source_table = ?
        GROUP BY item_name
        ORDER BY total_removed DESC
    ");
    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'item_name' => $row['item_name'],
            'total_removed' => (float)$row['total_removed']
        ];
    }
    return ['label' => $label, 'data' => $data];
}

$chemicalsData   = getCategoryData($conn, 'store_b_chemicals_in', 'Chemicals');
$engineeringData = getCategoryData($conn, 'store_b_engineering_products_in', 'Engineering Products');
$finishedData    = getCategoryData($conn, 'store_b_finished_products_in', 'Finished Products');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>📊 Store B Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-sans">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-8 pt-24">
    <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2 text-center">📈 Store B Reports Dashboard</h1>

    <!-- Chemicals Chart -->
    <div class="bg-white shadow-md rounded-2xl p-6 mb-10">
        <h2 class="text-xl font-semibold text-blue-700 mb-3">🧪 Chemicals Outflow</h2>
        <canvas id="chemicalsChart" height="120"></canvas>
    </div>

    <!-- Engineering Products Chart -->
    <div class="bg-white shadow-md rounded-2xl p-6 mb-10">
        <h2 class="text-xl font-semibold text-green-700 mb-3">⚙️ Engineering Products Outflow</h2>
        <canvas id="engineeringChart" height="120"></canvas>
    </div>

    <!-- Finished Products Chart -->
    <div class="bg-white shadow-md rounded-2xl p-6">
        <h2 class="text-xl font-semibold text-purple-700 mb-3">📦 Finished Products Outflow</h2>
        <canvas id="finishedChart" height="120"></canvas>
    </div>
</div>

<script>
// --- Chart Data from PHP ---
const chemicalsData = <?= json_encode($chemicalsData['data']); ?>;
const engineeringData = <?= json_encode($engineeringData['data']); ?>;
const finishedData = <?= json_encode($finishedData['data']); ?>;

// --- Reusable Chart Generator ---
function renderChart(canvasId, dataset, label, color) {
    const ctx = document.getElementById(canvasId);
    if (!dataset.length) {
        ctx.parentNode.innerHTML += '<p class="text-center text-gray-500 mt-4">No data available.</p>';
        return;
    }

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dataset.map(d => d.item_name),
            datasets: [{
                label: label,
                data: dataset.map(d => d.total_removed),
                backgroundColor: color,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: { display: false }
            },
            scales: {
                x: { ticks: { color: '#333', font: { size: 12 } } },
                y: { beginAtZero: true, ticks: { color: '#333' } }
            }
        }
    });
}

// --- Render All Charts ---
renderChart('chemicalsChart', chemicalsData, 'Total Quantity Removed (Chemicals)', 'rgba(59, 130, 246, 0.7)');
renderChart('engineeringChart', engineeringData, 'Total Quantity Removed (Engineering)', 'rgba(34, 197, 94, 0.7)');
renderChart('finishedChart', finishedData, 'Total Quantity Removed (Finished Products)', 'rgba(168, 85, 247, 0.7)');
</script>
</body>
</html>
