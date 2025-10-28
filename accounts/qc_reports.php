<?php 
include 'db_con.php';

// --- DATE FILTER ---
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');

// --- 1️⃣ Count Disposals ---
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_disposals 
    FROM disposables 
    WHERE DATE(disposal_date) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$disposals = $stmt->get_result()->fetch_assoc()['total_disposals'] ?? 0;

// --- 2️⃣ QC Inspection Status Counts ---
$stmt = $conn->prepare("
    SELECT 
        SUM(qc_status = 'Approved Product') AS approved,
        SUM(qc_status = 'Not Approved') AS not_approved
    FROM qc_inspections
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$qcs = $stmt->get_result()->fetch_assoc();
$approved = $qcs['approved'] ?? 0;
$not_approved = $qcs['not_approved'] ?? 0;

// --- 3️⃣ Inspected Chemicals Summary (Approved Times per Chemical) ---
$stmt = $conn->prepare("
    SELECT 
        ch.chemical_name,
        COUNT(ic.id) AS approved_count
    FROM inspected_chemicals_in ic
    JOIN chemicals_in ci ON ic.chemical_id = ci.id
    JOIN chemical_names ch ON ci.chemical_code = ch.id
    WHERE DATE(ic.created_at) BETWEEN ? AND ?
    GROUP BY ch.chemical_name
    ORDER BY approved_count DESC
");

$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$chem_result = $stmt->get_result();

$chemical_names = [];
$approved_counts = [];
while ($row = $chem_result->fetch_assoc()) {
    $chemical_names[] = $row['chemical_name'];
    $approved_counts[] = $row['approved_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>QC Reports Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 via-blue-200 to-blue-300 min-h-screen">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">
  <h1 class="text-3xl font-bold text-blue-800 mb-6">Quality Control Reports</h1>

  <!-- 📅 Filter Form -->
  <form method="GET" class="flex flex-wrap gap-4 bg-white shadow-md p-4 rounded-lg mb-8">
    <div>
      <label class="block text-sm font-semibold text-gray-600 mb-1">From Date</label>
      <input type="date" name="from_date" value="<?= $from_date ?>" class="border p-2 rounded w-48 focus:ring-blue-400 focus:border-blue-400">
    </div>
    <div>
      <label class="block text-sm font-semibold text-gray-600 mb-1">To Date</label>
      <input type="date" name="to_date" value="<?= $to_date ?>" class="border p-2 rounded w-48 focus:ring-blue-400 focus:border-blue-400">
    </div>
    <div class="flex items-end">
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Filter</button>
    </div>
  </form>

  <!-- 🧾 Stats Summary -->
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
      <h2 class="text-gray-500 font-semibold mb-2">QC Not Approved</h2>
      <p class="text-4xl font-bold text-red-600"><?= $not_approved ?></p>
    </div>
  </div>

  <!-- 📊 Charts -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- QC Status Chart -->
    <div class="bg-white shadow rounded-2xl p-6">
      <h2 class="text-xl font-semibold text-gray-700 mb-4">QC Inspection Status (<?= htmlspecialchars($from_date) ?> to <?= htmlspecialchars($to_date) ?>)</h2>
      <canvas id="qcStatusChart"></canvas>
    </div>

    <!-- Chemical Chart -->
    <div class="bg-white shadow rounded-2xl p-6">
      <h2 class="text-xl font-semibold text-gray-700 mb-4">Approved Times per Chemical</h2>
      <canvas id="chemicalChart"></canvas>
    </div>
  </div>
</div>

<script>
  // QC Status Chart
  const qcCtx = document.getElementById('qcStatusChart').getContext('2d');
  new Chart(qcCtx, {
    type: 'doughnut',
    data: {
      labels: ['Approved', 'Not Approved'],
      datasets: [{
        data: [<?= $approved ?>, <?= $not_approved ?>],
        backgroundColor: ['#10b981', '#ef4444']
      }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
  });

  // Approved per Chemical Chart
  const chemCtx = document.getElementById('chemicalChart').getContext('2d');
  new Chart(chemCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($chemical_names) ?>,
      datasets: [{
        label: 'Approved Times',
        data: <?= json_encode($approved_counts) ?>,
        backgroundColor: '#3b82f6'
      }]
    },
    options: {
      scales: { x: { ticks: { autoSkip: false, maxRotation: 45 } }, y: { beginAtZero: true } },
      plugins: { legend: { display: false } }
    }
  });
</script>

</body>
</html>
