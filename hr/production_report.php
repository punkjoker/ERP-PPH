<?php
include 'db_con.php';

$from_date = $_GET['from'] ?? date('Y-m-01');
$to_date = $_GET['to'] ?? date('Y-m-t');

// --- Fetch totals ---
$stmt1 = $conn->prepare("
    SELECT SUM(i.total_cost) AS total_bom_cost
    FROM bill_of_material_items i
    JOIN bill_of_materials b ON i.bom_id = b.id
    WHERE b.bom_date BETWEEN ? AND ?
");
$stmt1->bind_param('ss', $from_date, $to_date);
$stmt1->execute();
$bill_total = $stmt1->get_result()->fetch_assoc()['total_bom_cost'] ?? 0;

$stmt2 = $conn->prepare("
    SELECT SUM(p.total_cost) AS total_pack_cost
    FROM packaging_reconciliation p
    JOIN qc_inspections q ON p.qc_inspection_id = q.id
    WHERE q.created_at BETWEEN ? AND ?
");
$stmt2->bind_param('ss', $from_date, $to_date);
$stmt2->execute();
$pack_total = $stmt2->get_result()->fetch_assoc()['total_pack_cost'] ?? 0;

$total_production_cost = $bill_total + $pack_total;

// --- Chemical stats for Chart ---
$stmt3 = $conn->prepare("
    SELECT c.chemical_name, SUM(i.total_cost) AS total_cost
    FROM bill_of_material_items i
    JOIN chemicals_in c ON i.chemical_id = c.id
    JOIN bill_of_materials b ON i.bom_id = b.id
    WHERE b.bom_date BETWEEN ? AND ?
    GROUP BY c.chemical_name
");
$stmt3->bind_param('ss', $from_date, $to_date);
$stmt3->execute();
$result = $stmt3->get_result();

$chemicals = [];
$costs = [];
while ($row = $result->fetch_assoc()) {
    $chemicals[] = $row['chemical_name'];
    $costs[] = $row['total_cost'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Production Reports</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-gray-100 to-blue-100 min-h-screen">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">📊 Production Cost Report</h2>

    <!-- Filter Form -->
    <form method="get" class="flex flex-wrap items-center gap-4 bg-white p-4 rounded-lg shadow-md w-fit mb-8">
      <div>
        <label class="font-semibold mr-2">From:</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>" class="border rounded-md p-2 focus:ring focus:ring-blue-300">
      </div>
      <div>
        <label class="font-semibold mr-2">To:</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>" class="border rounded-md p-2 focus:ring focus:ring-blue-300">
      </div>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md shadow hover:bg-blue-700 transition">
        Filter
      </button>
    </form>

    <!-- Summary Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
      <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
        <p class="text-gray-500">Total BOM Cost</p>
        <h3 class="text-2xl font-semibold text-blue-600"><?= number_format($bill_total, 2) ?> Ksh</h3>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
        <p class="text-gray-500">Total Packaging Cost</p>
        <h3 class="text-2xl font-semibold text-amber-600"><?= number_format($pack_total, 2) ?> Ksh</h3>
      </div>

      <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
        <p class="text-gray-500">Total Production Cost</p>
        <h3 class="text-2xl font-semibold text-green-600"><?= number_format($total_production_cost, 2) ?> Ksh</h3>
      </div>
    </div>

    <!-- Chart Section -->
    <div class="bg-white p-6 rounded-2xl shadow-lg">
      <h3 class="text-xl font-semibold mb-4 text-gray-700">Chemical Cost Distribution</h3>
      <canvas id="chemChart" height="120"></canvas>
    </div>
  </div>

  <script>
  const ctx = document.getElementById('chemChart').getContext('2d');
  const gradient = ctx.createLinearGradient(0, 0, 0, 400);
  gradient.addColorStop(0, 'rgba(37, 99, 235, 0.7)');
  gradient.addColorStop(1, 'rgba(147, 197, 253, 0.4)');

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($chemicals) ?>,
      datasets: [{
        label: 'Chemical Cost (Ksh)',
        data: <?= json_encode($costs) ?>,
        backgroundColor: gradient,
        borderColor: 'rgba(37, 99, 235, 1)',
        borderWidth: 1,
        borderRadius: 6,
        hoverBackgroundColor: 'rgba(59, 130, 246, 0.8)',
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: true, position: 'top' },
        tooltip: {
          backgroundColor: '#1e293b',
          titleColor: '#fff',
          bodyColor: '#f8fafc',
        }
      },
      scales: {
        x: { ticks: { color: '#475569' }, grid: { display: false } },
        y: { 
          beginAtZero: true, 
          ticks: { color: '#475569' },
          grid: { color: '#e2e8f0' }
        }
      }
    }
  });
  </script>
</body>
</html>
