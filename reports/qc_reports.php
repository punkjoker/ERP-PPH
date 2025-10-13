<?php
include 'db_con.php';

// --- 1. Count Disposals ---
$disposals_query = $conn->query("SELECT COUNT(*) AS total_disposals FROM disposables");
$disposals = $disposals_query->fetch_assoc()['total_disposals'] ?? 0;

// --- 2. QC Inspection Status Counts ---
$qcs_query = $conn->query("
    SELECT 
        SUM(qc_status = 'Approved Product') AS approved,
        SUM(qc_status = 'Not Approved') AS not_approved
    FROM qc_inspections
");
$qcs = $qcs_query->fetch_assoc();
$approved = $qcs['approved'] ?? 0;
$not_approved = $qcs['not_approved'] ?? 0;

// --- 3. Inspected Chemicals ---
$chemicals_query = $conn->query("SELECT COUNT(*) AS total_chemicals FROM inspected_chemicals_in");
$chemicals = $chemicals_query->fetch_assoc()['total_chemicals'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>QC Reports Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">
  <h1 class="text-3xl font-bold text-blue-700 mb-6">Quality Control Reports</h1>

  <!-- Stats Summary Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white shadow-lg rounded-2xl p-6 text-center">
      <h2 class="text-gray-500 font-semibold mb-2">Total Disposals</h2>
      <p class="text-4xl font-bold text-blue-600"><?= $disposals ?></p>
    </div>
    <div class="bg-white shadow-lg rounded-2xl p-6 text-center">
      <h2 class="text-gray-500 font-semibold mb-2">QC Approved</h2>
      <p class="text-4xl font-bold text-green-600"><?= $approved ?></p>
    </div>
    <div class="bg-white shadow-lg rounded-2xl p-6 text-center">
      <h2 class="text-gray-500 font-semibold mb-2">Inspected Chemicals</h2>
      <p class="text-4xl font-bold text-purple-600"><?= $chemicals ?></p>
    </div>
  </div>

  <!-- Charts Section -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Disposal Chart -->
    <div class="bg-white shadow rounded-2xl p-6">
      <h2 class="text-xl font-semibold text-gray-700 mb-4">Disposals Overview</h2>
      <canvas id="disposalChart"></canvas>
    </div>

    <!-- QC Status Chart -->
    <div class="bg-white shadow rounded-2xl p-6">
      <h2 class="text-xl font-semibold text-gray-700 mb-4">QC Inspection Status</h2>
      <canvas id="qcStatusChart"></canvas>
    </div>
  </div>

  <!-- Chemical Chart -->
  <div class="bg-white shadow rounded-2xl p-6 mt-8">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Inspected Chemicals Summary</h2>
    <canvas id="chemicalChart"></canvas>
  </div>
</div>

<script>
  // Chart 1: Disposal count (just a simple single-value representation)
  const disposalCtx = document.getElementById('disposalChart').getContext('2d');
  new Chart(disposalCtx, {
    type: 'bar',
    data: {
      labels: ['Total Disposals'],
      datasets: [{
        label: 'Disposals',
        data: [<?= $disposals ?>],
        backgroundColor: ['#3b82f6']
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  // Chart 2: QC Approved vs Not Approved
  const qcCtx = document.getElementById('qcStatusChart').getContext('2d');
  new Chart(qcCtx, {
    type: 'doughnut',
    data: {
      labels: ['Approved', 'Not Approved'],
      datasets: [{
        label: 'QC Status',
        data: [<?= $approved ?>, <?= $not_approved ?>],
        backgroundColor: ['#10b981', '#ef4444']
      }]
    },
    options: {
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });

  // Chart 3: Inspected Chemicals trend (static since no time series)
  const chemicalCtx = document.getElementById('chemicalChart').getContext('2d');
  new Chart(chemicalCtx, {
    type: 'bar',
    data: {
      labels: ['Inspected Chemicals'],
      datasets: [{
        label: 'Total Inspected',
        data: [<?= $chemicals ?>],
        backgroundColor: ['#8b5cf6']
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
</script>

</body>
</html>
