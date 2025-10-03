<?php
require 'db_con.php';

// Handle filters
$where = "1=1";
$params = [];
$types = "";

// Date filter
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
}

// Product filter
$product = $_GET['stock_code'] ?? '';

// Stock In Data
$query_in = "SELECT stock_code, stock_name, SUM(quantity) as total_in, SUM(quantity * unit_cost) as amount_in 
             FROM stock_in 
             WHERE $where " . ($product ? " AND stock_code=?" : "") . "
             GROUP BY stock_code, stock_name";
$stmt_in = $conn->prepare($query_in);
if ($product) {
    $params_in = [...$params, $product];
    $types_in = $types . "s";
    $stmt_in->bind_param($types_in, ...$params_in);
} elseif (!empty($params)) {
    $stmt_in->bind_param($types, ...$params);
}
$stmt_in->execute();
$data_in = $stmt_in->get_result()->fetch_assoc();

// Stock Out Data
$query_out = "SELECT stock_code, stock_name, SUM(quantity_removed) as total_out, SUM(quantity_removed * unit_cost) as amount_out 
              FROM stock_out_history 
              WHERE $where " . ($product ? " AND stock_code=?" : "") . "
              GROUP BY stock_code, stock_name";
$stmt_out = $conn->prepare($query_out);
if ($product) {
    $params_out = [...$params, $product];
    $types_out = $types . "s";
    $stmt_out->bind_param($types_out, ...$params_out);
} elseif (!empty($params)) {
    $stmt_out->bind_param($types, ...$params);
}
$stmt_out->execute();
$data_out = $stmt_out->get_result()->fetch_assoc();

// Material Data
$materials = $conn->query("SELECT material_name, quantity, cost FROM materials");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Stock Reports</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">

  <?php include 'navbar.php'; ?>

  <div class="max-w-4xl ml-64 mx-auto mt-24 bg-white p-4 shadow rounded">
    <!-- Header -->
    <div class="mb-4 border-b pb-3">
      <h1 class="text-xl font-bold text-center text-blue-700 mb-1">STOCK REPORTS</h1>
      <p class="text-center text-gray-600 text-sm">STOCK CARDS - QF 18 | LYNNTECH-QP-22</p>
    </div>

  <!-- Filters -->
  <form method="GET" class="flex flex-wrap gap-3 items-end mb-4 text-sm">
    <div>
      <label class="block font-semibold">From Date</label>
      <input type="date" name="from_date" 
             value="<?php echo $_GET['from_date'] ?? ''; ?>" 
             class="border p-1 rounded w-40">
    </div>
    <div>
      <label class="block font-semibold">To Date</label>
      <input type="date" name="to_date" 
             value="<?php echo $_GET['to_date'] ?? ''; ?>" 
             class="border p-1 rounded w-40">
    </div>
    <div>
      <label class="block font-semibold">Stock Code</label>
      <input type="text" name="stock_code" 
             value="<?php echo htmlspecialchars($product); ?>" 
             placeholder="Enter stock code" 
             class="border p-1 rounded w-40">
    </div>
    <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
      Filter
    </button>
  </form>

  <!-- Totals -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div class="bg-green-100 p-3 rounded shadow text-center">
      <h3 class="font-bold text-green-700 text-sm">Total Stock In (Kshs)</h3>
      <p class="text-lg"><?php echo number_format($data_in['amount_in'] ?? 0, 2); ?></p>
    </div>
    <div class="bg-red-100 p-3 rounded shadow text-center">
      <h3 class="font-bold text-red-700 text-sm">Total Stock Out (Kshs)</h3>
      <p class="text-lg"><?php echo number_format($data_out['amount_out'] ?? 0, 2); ?></p>
    </div>
  </div>

  <!-- Charts -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="h-64">
      <canvas id="quantityChart"></canvas>
    </div>
    <div class="h-64">
      <canvas id="amountChart"></canvas>
    </div>
  </div>

  <div class="mt-6">
    <h3 class="text-lg font-bold mb-2">Material Quantities</h3>
    <div class="h-64">
      <canvas id="materialChart"></canvas>
    </div>
  </div>

</div>

<script>
  // Quantity Chart
  new Chart(document.getElementById('quantityChart'), {
    type: 'bar',
    data: {
      labels: ['Stock In', 'Stock Out'],
      datasets: [{
        label: 'Quantities',
        data: [<?php echo $data_in['total_in'] ?? 0; ?>, <?php echo $data_out['total_out'] ?? 0; ?>],
        backgroundColor: ['#4CAF50', '#F44336']
      }]
    }
  });

  // Amount Chart
  new Chart(document.getElementById('amountChart'), {
    type: 'bar',
    data: {
      labels: ['Stock In (Kshs)', 'Stock Out (Kshs)'],
      datasets: [{
        label: 'Amounts',
        data: [<?php echo $data_in['amount_in'] ?? 0; ?>, <?php echo $data_out['amount_out'] ?? 0; ?>],
        backgroundColor: ['#2196F3', '#FF9800']
      }]
    }
  });

  // Materials Chart
  new Chart(document.getElementById('materialChart'), {
    type: 'pie',
    data: {
      labels: [<?php while($m = $materials->fetch_assoc()){ echo "'".$m['material_name']."',"; } ?>],
      datasets: [{
        label: 'Material Quantities',
        data: [<?php $materials->data_seek(0); while($m = $materials->fetch_assoc()){ echo $m['quantity'].","; } ?>],
        backgroundColor: ['#3f51b5','#009688','#e91e63','#ff5722','#795548','#607d8b']
      }]
    }
  });
</script>
</body>
</html>
