<?php
include 'db_con.php';

$from_date = $_GET['from'] ?? date('Y-m-01');
$to_date   = $_GET['to'] ?? date('Y-m-t');

// --- Fetch total spend ---
$stmt1 = $conn->prepare("
    SELECT SUM(quantity * unit_price) AS total_spend
    FROM order_items
    JOIN po_list p ON order_items.po_id = p.id
    WHERE p.created_at BETWEEN ? AND ?
");
$stmt1->bind_param('ss', $from_date, $to_date);
$stmt1->execute();
$total_spend = $stmt1->get_result()->fetch_assoc()['total_spend'] ?? 0;

// --- Fetch item stats for chart ---
$stmt2 = $conn->prepare("
    SELECT COALESCE(pr.product_name, order_items.manual_name) AS item_name, 
           SUM(order_items.quantity) AS total_qty, SUM(order_items.quantity * order_items.unit_price) AS spent
    FROM order_items
    LEFT JOIN procurement_products pr ON order_items.product_id = pr.id
    JOIN po_list p ON order_items.po_id = p.id
    WHERE p.created_at BETWEEN ? AND ?
    GROUP BY item_name
    ORDER BY spent DESC
");
$stmt2->bind_param('ss', $from_date, $to_date);
$stmt2->execute();
$result = $stmt2->get_result();

$item_names = [];
$quantities = [];
$spends = [];
while ($row = $result->fetch_assoc()) {
    $item_names[] = $row['item_name'];
    $quantities[] = $row['total_qty'];
    $spends[] = $row['spent'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Procurement Reports</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
body { font-family: 'Inter', sans-serif; }
.chart-container { width: 100%; max-width: 900px; margin: auto; }
.summary { text-align: center; font-size: 20px; margin-bottom: 30px; }
canvas { border-radius: 12px; background: #f9fafb; padding: 16px; }
</style>
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">

  <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">📊 Procurement Report</h2>

  <!-- Filter Form -->
  <form method="get" class="mb-6 flex flex-wrap gap-4 items-end">
    <div>
      <label class="font-semibold text-gray-700">From</label>
      <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>" 
             class="border rounded-md p-2 focus:ring focus:ring-blue-300">
    </div>
    <div>
      <label class="font-semibold text-gray-700">To</label>
      <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>" 
             class="border rounded-md p-2 focus:ring focus:ring-blue-300">
    </div>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">Filter</button>
  </form>

  <!-- Summary -->
  <div class="summary bg-white shadow-lg rounded-2xl p-6 mb-6">
    <p><strong>Total Procurement Spend:</strong> Ksh <?= number_format($total_spend,2) ?></p>
  </div>

  <!-- Charts -->
  <div id="report-content" class="bg-white shadow-lg rounded-2xl p-6 mb-6">

    <h3 class="text-xl font-semibold mb-4 text-gray-700">Items Purchased</h3>

    <div class="chart-container mb-8">
      <canvas id="qtyChart"></canvas>
    </div>

    <div class="chart-container mb-8">
      <canvas id="spendChart"></canvas>
    </div>

    <button id="downloadPdf" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition">
      Download as PDF
    </button>
  </div>

</div>

<script>
// Create gradient for charts
function createGradient(ctx, colorStart, colorEnd) {
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, colorStart);
    gradient.addColorStop(1, colorEnd);
    return gradient;
}

// Quantity Chart
const qtyCtx = document.getElementById('qtyChart').getContext('2d');
const qtyGradient = createGradient(qtyCtx, 'rgba(54, 162, 235, 0.8)', 'rgba(54, 162, 235, 0.3)');

new Chart(qtyCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($item_names) ?>,
        datasets: [{
            label: 'Quantity Bought',
            data: <?= json_encode($quantities) ?>,
            backgroundColor: qtyGradient,
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            borderRadius: 8,
            barPercentage: 0.6
        }]
    },
    options: {
        responsive: true,
        plugins: { 
            legend: { display: true },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: { y: { beginAtZero: true } }
    }
});

// Spend Chart
const spendCtx = document.getElementById('spendChart').getContext('2d');
const spendGradient = createGradient(spendCtx, 'rgba(255, 99, 132, 0.8)', 'rgba(255, 99, 132, 0.3)');

new Chart(spendCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($item_names) ?>,
        datasets: [{
            label: 'Money Spent (Ksh)',
            data: <?= json_encode($spends) ?>,
            backgroundColor: spendGradient,
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1,
            borderRadius: 8,
            barPercentage: 0.6
        }]
    },
    options: {
        responsive: true,
        plugins: { 
            legend: { display: true },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: { y: { beginAtZero: true } }
    }
});

// Download PDF
document.getElementById('downloadPdf').addEventListener('click', () => {
    const element = document.getElementById('report-content');
    html2pdf().set({margin:1, filename:'procurement_report.pdf', html2canvas:{scale:2}}).from(element).save();
});
</script>

</body>
</html>
