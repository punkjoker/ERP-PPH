<?php 
include 'db_con.php';

// --- Handle Month Filter ---
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$dateFilter = "$year-$month";

// --- Trips Data ---
$tripsQuery = $conn->query("
  SELECT driver_name, COUNT(*) AS total_trips
  FROM trips
  WHERE DATE_FORMAT(created_at, '%Y-%m') = '$dateFilter'
  GROUP BY driver_name
");

// --- Maintenance Data ---
$maintenanceQuery = $conn->query("
  SELECT driver_name, SUM(maintenance_cost) AS total_maintenance
  FROM vehicle_maintenance
  WHERE DATE_FORMAT(created_at, '%Y-%m') = '$dateFilter'
  GROUP BY driver_name
");

// --- Fuel Data ---
$fuelQuery = $conn->query("
  SELECT v.driver_name, SUM(f.amount_refueled) AS total_fuel
  FROM fuel_cost f
  JOIN vehicles v ON f.vehicle_number = v.vehicle_number
  WHERE DATE_FORMAT(f.created_at, '%Y-%m') = '$dateFilter'
  GROUP BY v.driver_name
");

// --- Combine Results by Driver ---
$reportData = [];

while ($row = $tripsQuery->fetch_assoc()) {
  $reportData[$row['driver_name']]['trips'] = $row['total_trips'];
}
while ($row = $maintenanceQuery->fetch_assoc()) {
  $reportData[$row['driver_name']]['maintenance'] = $row['total_maintenance'];
}
while ($row = $fuelQuery->fetch_assoc()) {
  $reportData[$row['driver_name']]['fuel'] = $row['total_fuel'];
}

// --- Prepare Chart Data ---
$drivers = [];
$tripCounts = [];
$maintenanceCosts = [];
$fuelCosts = [];

foreach ($reportData as $driver => $data) {
  $drivers[] = $driver;
  $tripCounts[] = $data['trips'] ?? 0;
  $maintenanceCosts[] = $data['maintenance'] ?? 0;
  $fuelCosts[] = $data['fuel'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Vehicles Report</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    function printReport() {
      const printContents = document.getElementById('reportContent').innerHTML;
      const printWindow = window.open('', '', 'width=1000,height=700');
      printWindow.document.write('<html><head><title>Vehicle Report</title>');
      printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">');
      printWindow.document.write('</head><body>');
      printWindow.document.write('<h1 class="text-center text-2xl font-bold mb-4">Vehicle Report</h1>');
      printWindow.document.write(printContents);
      printWindow.document.write('</body></html>');
      printWindow.document.close();
      printWindow.print();
    }
  </script>
  <!-- Add these scripts inside <head> before closing -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

</head>

<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-6">
    <div class="max-w-7xl mx-auto bg-white shadow-md rounded-lg p-6" id="reportContent">
      <h1 class="text-2xl font-bold mb-6 text-blue-700">Vehicle & Driver Reports</h1>

      <!-- Filter Form -->
      <form method="GET" class="flex flex-wrap gap-3 mb-6">
        <div>
          <label class="font-semibold">Month</label>
          <select name="month" class="p-2 border rounded">
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= sprintf('%02d', $m) ?>" <?= $m == $month ? 'selected' : '' ?>>
                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="font-semibold">Year</label>
          <select name="year" class="p-2 border rounded">
            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
              <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
      </form>

      <!-- Charts Grid -->
      <div class="grid md:grid-cols-2 gap-6 mb-8">
        <!-- Trips Chart -->
        <div class="bg-gray-50 p-4 rounded-lg shadow">
          <h2 class="text-lg font-semibold mb-2 text-gray-700">Trips Made per Driver</h2>
          <canvas id="tripChart" height="150"></canvas>
        </div>

        <!-- Maintenance Chart -->
        <div class="bg-gray-50 p-4 rounded-lg shadow">
          <h2 class="text-lg font-semibold mb-2 text-gray-700">Maintenance Cost per Driver</h2>
          <canvas id="maintenanceChart" height="150"></canvas>
        </div>

        <!-- Fuel Chart -->
        <div class="bg-gray-50 p-4 rounded-lg shadow md:col-span-2">
          <h2 class="text-lg font-semibold mb-2 text-gray-700">Fuel Cost per Driver</h2>
          <canvas id="fuelChart" height="120"></canvas>
        </div>
      </div>

      <!-- Summary Table -->
      <h2 class="text-lg font-semibold mb-2 text-gray-700">Monthly Summary</h2>
      <div class="overflow-x-auto">
        <table class="w-full border-collapse border border-gray-300 text-sm">
          <thead>
            <tr class="bg-gray-200">
              <th class="border p-2">Driver</th>
              <th class="border p-2">Total Trips</th>
              <th class="border p-2">Maintenance Cost (KSh)</th>
              <th class="border p-2">Fuel Cost (KSh)</th>
              <th class="border p-2">Total (KSh)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reportData as $driver => $data): 
              $trips = $data['trips'] ?? 0;
              $maintenance = $data['maintenance'] ?? 0;
              $fuel = $data['fuel'] ?? 0;
              $total = $maintenance + $fuel;
            ?>
              <tr>
                <td class="border p-2"><?= htmlspecialchars($driver) ?></td>
                <td class="border p-2 text-center"><?= $trips ?></td>
                <td class="border p-2 text-right"><?= number_format($maintenance, 2) ?></td>
                <td class="border p-2 text-right"><?= number_format($fuel, 2) ?></td>
                <td class="border p-2 text-right font-semibold"><?= number_format($total, 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Print Button -->
    <!-- Download PDF Button -->
<div class="mt-4 flex justify-center">
  <button onclick="downloadPDF()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
    Download PDF Report
  </button>
</div>

  </div>

  <script>
    // Trips Chart
    new Chart(document.getElementById('tripChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode($drivers) ?>,
        datasets: [{
          label: 'Trips',
          data: <?= json_encode($tripCounts) ?>,
          backgroundColor: 'rgba(37, 99, 235, 0.7)'
        }]
      },
      options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });

    // Maintenance Chart
    new Chart(document.getElementById('maintenanceChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode($drivers) ?>,
        datasets: [{
          label: 'Maintenance Cost (KSh)',
          data: <?= json_encode($maintenanceCosts) ?>,
          backgroundColor: 'rgba(34, 197, 94, 0.7)'
        }]
      },
      options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });

    // Fuel Chart
    new Chart(document.getElementById('fuelChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode($drivers) ?>,
        datasets: [{
          label: 'Fuel Cost (KSh)',
          data: <?= json_encode($fuelCosts) ?>,
          backgroundColor: 'rgba(249, 115, 22, 0.7)'
        }]
      },
      options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
  </script>
  <script>
async function downloadPDF() {
  const { jsPDF } = window.jspdf;
  const report = document.getElementById('reportContent');

  // Use html2canvas to render the section
  const canvas = await html2canvas(report, {
    scale: 2,
    useCORS: true
  });

  const imgData = canvas.toDataURL('image/png');
  const pdf = new jsPDF('p', 'mm', 'a4');

  const pdfWidth = pdf.internal.pageSize.getWidth();
  const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

  pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
  pdf.save('Vehicle_Report.pdf');
}
</script>

</body>
</html>
