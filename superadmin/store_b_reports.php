<?php
include 'db_con.php';

// Capture date range inputs
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

// --- Fetch total quantities by item and category (with optional date filter) ---
function getCategoryData($conn, $tableName, $label, $from_date, $to_date) {
    $sql = "
        SELECT item_name, SUM(quantity_removed) AS total_removed
        FROM delivery_order_items_store_b
        WHERE source_table = ?
    ";

    // Apply date filter if provided
    if (!empty($from_date) && !empty($to_date)) {
        $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
    }

    $sql .= " GROUP BY item_name ORDER BY total_removed DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($from_date) && !empty($to_date)) {
        $stmt->bind_param("sss", $tableName, $from_date, $to_date);
    } else {
        $stmt->bind_param("s", $tableName);
    }

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

$chemicalsData   = getCategoryData($conn, 'store_b_chemicals_in', 'Chemicals', $from_date, $to_date);
$engineeringData = getCategoryData($conn, 'store_b_engineering_products_in', 'Engineering Products', $from_date, $to_date);
$finishedData    = getCategoryData($conn, 'store_b_finished_products_in', 'Finished Products', $from_date, $to_date);
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

    <!-- Date Range Filter -->
    <form method="GET" class="bg-white shadow-md rounded-xl p-4 mb-6 flex flex-wrap items-center justify-center gap-4">
        <div>
            <label for="from_date" class="font-semibold text-gray-700">From:</label>
            <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($from_date) ?>"
                   class="border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label for="to_date" class="font-semibold text-gray-700">To:</label>
            <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($to_date) ?>"
                   class="border border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
            Filter
        </button>

        <?php if (!empty($from_date) && !empty($to_date)): ?>
        <a href="store_b_reports.php" class="text-gray-600 hover:text-red-500 ml-4 underline">Clear Filter</a>
        <?php endif; ?>
    </form>

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
