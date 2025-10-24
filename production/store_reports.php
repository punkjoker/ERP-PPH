<?php
require 'db_con.php';

// Default current month range
$currentMonth = date('Y-m-01');
$currentDate = date('Y-m-d');

$where_in = "1=1";
$where_out = "1=1";
$params_in = [];
$params_out = [];
$types_in = "";
$types_out = "";

// Date filter
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where_in .= " AND DATE(created_at) BETWEEN ? AND ?";
    $where_out .= " AND DATE(removed_at) BETWEEN ? AND ?";
    $params_in[] = $_GET['from_date'];
    $params_in[] = $_GET['to_date'];
    $params_out[] = $_GET['from_date'];
    $params_out[] = $_GET['to_date'];
    $types_in .= "ss";
    $types_out .= "ss";
} else {
    // Default: current month
    $where_in .= " AND DATE(created_at) BETWEEN ? AND ?";
    $where_out .= " AND DATE(removed_at) BETWEEN ? AND ?";
    $params_in[] = $currentMonth;
    $params_in[] = $currentDate;
    $params_out[] = $currentMonth;
    $params_out[] = $currentDate;
    $types_in .= "ss";
    $types_out .= "ss";
}

// Filters
$product = $_GET['stock_code'] ?? '';
$product_name = $_GET['stock_name'] ?? '';

// Dropdown for stock names
$stockList = $conn->query("SELECT DISTINCT stock_name FROM stock_in ORDER BY stock_name ASC");

// --- STOCK IN ---
$query_in = "SELECT SUM(original_quantity) AS total_in, 
                    SUM(original_quantity * unit_cost) AS amount_in
             FROM stock_in 
             WHERE $where_in" . 
             ($product ? " AND stock_code=?" : "") . 
             ($product_name ? " AND stock_name=?" : "");

$stmt_in = $conn->prepare($query_in);
if ($product && $product_name) {
    $params_in = [...$params_in, $product, $product_name];
    $types_in .= "ss";
} elseif ($product) {
    $params_in = [...$params_in, $product];
    $types_in .= "s";
} elseif ($product_name) {
    $params_in = [...$params_in, $product_name];
    $types_in .= "s";
}
$stmt_in->bind_param($types_in, ...$params_in);
$stmt_in->execute();
$data_in = $stmt_in->get_result()->fetch_assoc();

// --- STOCK OUT ---
$query_out = "SELECT SUM(quantity_removed) AS total_out, 
                     SUM(quantity_removed * unit_cost) AS amount_out
              FROM stock_out_history 
              WHERE $where_out" . 
              ($product ? " AND stock_code=?" : "") . 
              ($product_name ? " AND stock_name=?" : "");

$stmt_out = $conn->prepare($query_out);
if ($product && $product_name) {
    $params_out = [...$params_out, $product, $product_name];
    $types_out .= "ss";
} elseif ($product) {
    $params_out = [...$params_out, $product];
    $types_out .= "s";
} elseif ($product_name) {
    $params_out = [...$params_out, $product_name];
    $types_out .= "s";
}
$stmt_out->bind_param($types_out, ...$params_out);
$stmt_out->execute();
$data_out = $stmt_out->get_result()->fetch_assoc();

// Materials chart (optional)
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

<div class="max-w-5xl ml-64 mx-auto mt-24 bg-white p-6 shadow rounded">
  <div class="mb-4 border-b pb-3">
    <h1 class="text-xl font-bold text-center text-blue-700 mb-1">STOCK REPORTS</h1>
    <p class="text-center text-gray-600 text-sm">STOCK CARDS - QF 18 | LYNNTECH-QP-22</p>
  </div>

  <!-- Filters -->
  <form method="GET" class="flex flex-wrap gap-3 items-end mb-6 text-sm">
    <div>
      <label class="block font-semibold">From Date</label>
      <input type="date" name="from_date" value="<?php echo $_GET['from_date'] ?? ''; ?>" class="border p-1 rounded w-40">
    </div>
    <div>
      <label class="block font-semibold">To Date</label>
      <input type="date" name="to_date" value="<?php echo $_GET['to_date'] ?? ''; ?>" class="border p-1 rounded w-40">
    </div>
    <div>
      <label class="block font-semibold">Stock Code</label>
      <input type="text" name="stock_code" value="<?php echo htmlspecialchars($product); ?>" placeholder="Enter stock code" class="border p-1 rounded w-40">
    </div>
    <div>
      <label class="block font-semibold">Product Name</label>
      <select name="stock_name" class="border p-1 rounded w-48">
        <option value="">All Products</option>
        <?php while ($row = $stockList->fetch_assoc()): ?>
          <option value="<?php echo $row['stock_name']; ?>" <?php if ($row['stock_name'] == $product_name) echo 'selected'; ?>>
            <?php echo $row['stock_name']; ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">Filter</button>
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
    <div class="h-64"><canvas id="quantityChart"></canvas></div>
    <div class="h-64"><canvas id="amountChart"></canvas></div>
   
  </div>
 <?php
// --- STOCK IN BY NAME CHART ---
$stockChartData = $conn->prepare("
    SELECT stock_name, SUM(original_quantity) AS total_quantity
    FROM stock_in
    WHERE $where_in" . 
    ($product ? " AND stock_code=?" : "") . 
    ($product_name ? " AND stock_name=?" : "") . "
    GROUP BY stock_name
    ORDER BY stock_name ASC
");

$params_stock = $params_in;
$types_stock = $types_in;

$stockChartData->bind_param($types_stock, ...$params_stock);
$stockChartData->execute();
$resultStockChart = $stockChartData->get_result();

$stockNames = [];
$stockQuantities = [];
while($row = $resultStockChart->fetch_assoc()){
    $stockNames[] = $row['stock_name'];
    $stockQuantities[] = (int)$row['total_quantity'];
}
?>

<!-- Stock In By Name Chart (Full Page Width) -->
<div class="mt-8 w-full bg-gray-50 rounded-lg shadow p-4">
  <h3 class="text-lg font-bold mb-4 text-center text-blue-700">Stock In Quantities (By Product)</h3>
  <div class="h-96 w-full">
    <canvas id="stockInByNameChart"></canvas>
  </div>
</div>

<script>
const ctxStock = document.getElementById('stockInByNameChart').getContext('2d');
new Chart(ctxStock, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($stockNames); ?>,
        datasets: [{
            label: 'Original Quantity',
            data: <?php echo json_encode($stockQuantities); ?>,
            backgroundColor: '#3b82f6'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false, // Important to fill parent height
        plugins: { legend: { display: false } },
        scales: {
            x: { 
                title: { display: true, text: 'Stock Name' },
                ticks: { autoSkip: false, maxRotation: 90, minRotation: 45 }
            },
            y: { beginAtZero: true, title: { display: true, text: 'Quantity' } }
        }
    }
});
</script>
<?php
// --- STOCK OUT BY NAME CHART ---
$stockOutChartData = $conn->prepare("
    SELECT stock_name, SUM(quantity_removed) AS total_removed
    FROM stock_out_history
    WHERE $where_out" . 
    ($product ? " AND stock_code=?" : "") . 
    ($product_name ? " AND stock_name=?" : "") . "
    GROUP BY stock_name
    ORDER BY stock_name ASC
");

$params_stock_out = $params_out;
$types_stock_out = $types_out;

$stockOutChartData->bind_param($types_stock_out, ...$params_stock_out);
$stockOutChartData->execute();
$resultStockOutChart = $stockOutChartData->get_result();

$stockOutNames = [];
$stockOutQuantities = [];
while($row = $resultStockOutChart->fetch_assoc()){
    $stockOutNames[] = $row['stock_name'];
    $stockOutQuantities[] = (float)$row['total_removed'];
}
?>

<!-- Stock Out By Name Chart (Full Page Width) -->
<div class="mt-8 w-full bg-gray-50 rounded-lg shadow p-4">
  <h3 class="text-lg font-bold mb-4 text-center text-red-700">Stock Out Quantities (By Product)</h3>
  <div class="h-96 w-full">
    <canvas id="stockOutByNameChart"></canvas>
  </div>
</div>

<script>
const ctxStockOut = document.getElementById('stockOutByNameChart').getContext('2d');
new Chart(ctxStockOut, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($stockOutNames); ?>,
        datasets: [{
            label: 'Quantity Removed',
            data: <?php echo json_encode($stockOutQuantities); ?>,
            backgroundColor: '#f87171'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false, // fill parent height
        plugins: { legend: { display: false } },
        scales: {
            x: { 
                title: { display: true, text: 'Stock Name' },
                ticks: { autoSkip: false, maxRotation: 90, minRotation: 45 }
            },
            y: { beginAtZero: true, title: { display: true, text: 'Quantity Removed' } }
        }
    }
});
</script>
<?php
// --- MATERIALS OUT BY NAME CHART ---
$materialsOutChartData = $conn->prepare("
    SELECT material_name, SUM(quantity_removed) AS total_removed
    FROM material_out_history
    GROUP BY material_name
    ORDER BY material_name ASC
");

$materialsOutChartData->execute();
$resultMaterialsOutChart = $materialsOutChartData->get_result();

$materialNames = [];
$materialQuantities = [];
while($row = $resultMaterialsOutChart->fetch_assoc()){
    $materialNames[] = $row['material_name'];
    $materialQuantities[] = (int)$row['total_removed'];
}
?>

<!-- Materials Out By Name Chart (Full Page Width) -->
<div class="mt-8 w-full bg-gray-50 rounded-lg shadow p-4">
  <h3 class="text-lg font-bold mb-4 text-center text-purple-700">Materials Removed (By Material)</h3>
  <div class="h-96 w-full">
    <canvas id="materialsOutByNameChart"></canvas>
  </div>
</div>

<script>
const ctxMaterialsOut = document.getElementById('materialsOutByNameChart').getContext('2d');
new Chart(ctxMaterialsOut, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($materialNames); ?>,
        datasets: [{
            label: 'Quantity Removed',
            data: <?php echo json_encode($materialQuantities); ?>,
            backgroundColor: '#8b5cf6'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false, // fill parent height
        plugins: { legend: { display: false } },
        scales: {
            x: { 
                title: { display: true, text: 'Material Name' },
                ticks: { autoSkip: false, maxRotation: 90, minRotation: 45 }
            },
            y: { beginAtZero: true, title: { display: true, text: 'Quantity Removed' } }
        }
    }
});
</script>
<?php
// --- CHEMICALS BY STANDARD QUANTITY CHART ---
$chemicalsChartData = $conn->prepare("
    SELECT chemical_name, SUM(std_quantity) AS total_std_quantity
    FROM chemicals_in
    GROUP BY chemical_name
    ORDER BY chemical_name ASC
");

$chemicalsChartData->execute();
$resultChemicalsChart = $chemicalsChartData->get_result();

$chemicalNames = [];
$chemicalQuantities = [];
while($row = $resultChemicalsChart->fetch_assoc()){
    $chemicalNames[] = $row['chemical_name'];
    $chemicalQuantities[] = (float)$row['total_std_quantity'];
}
?>

<!-- Chemicals By Standard Quantity Chart (Full Page Width) -->
<div class="mt-8 w-full bg-gray-50 rounded-lg shadow p-4">
  <h3 class="text-lg font-bold mb-4 text-center text-green-700">Chemicals In Quantities (By Chemical)</h3>
  <div class="h-96 w-full">
    <canvas id="chemicalsByStdQtyChart"></canvas>
  </div>
</div>

<script>
const ctxChemicals = document.getElementById('chemicalsByStdQtyChart').getContext('2d');
new Chart(ctxChemicals, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chemicalNames); ?>,
        datasets: [{
            label: 'Standard Quantity',
            data: <?php echo json_encode($chemicalQuantities); ?>,
            backgroundColor: '#10b981' // green color for chemicals
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false, // fill parent height
        plugins: { legend: { display: false } },
        scales: {
            x: { 
                title: { display: true, text: 'Chemical Name' },
                ticks: { autoSkip: false, maxRotation: 90, minRotation: 45 }
            },
            y: { beginAtZero: true, title: { display: true, text: 'Quantity (kg/L)' } }
        }
    }
});
</script>
<?php
// --- CHEMICALS OUT BY REQUESTED QUANTITY CHART (using chemical_name only) ---
$chemicalsOutChartData = $conn->prepare("
    SELECT chemical_name, SUM(quantity_requested) AS total_requested
    FROM bill_of_material_items
    GROUP BY chemical_name
    ORDER BY chemical_name ASC
");

$chemicalsOutChartData->execute();
$resultChemicalsOutChart = $chemicalsOutChartData->get_result();

$chemOutNames = [];
$chemOutQuantities = [];
while($row = $resultChemicalsOutChart->fetch_assoc()){
    $chemOutNames[] = $row['chemical_name'];
    $chemOutQuantities[] = (float)$row['total_requested'];
}
?>

<!-- Chemicals Out By Quantity Chart (Full Page Width) -->
<div class="mt-8 w-full bg-gray-50 rounded-lg shadow p-4">
  <h3 class="text-lg font-bold mb-4 text-center text-red-700">Chemicals Out Quantities (Production)</h3>
  <div class="h-96 w-full">
    <canvas id="chemicalsOutChart"></canvas>
  </div>
</div>

<script>
const ctxChemOut = document.getElementById('chemicalsOutChart').getContext('2d');
new Chart(ctxChemOut, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chemOutNames); ?>,
        datasets: [{
            label: 'Quantity Requested',
            data: <?php echo json_encode($chemOutQuantities); ?>,
            backgroundColor: '#ef4444' // red for chemicals out
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false, // fill parent height
        plugins: { legend: { display: false } },
        scales: {
            x: { 
                title: { display: true, text: 'Chemical Name' },
                ticks: { autoSkip: false, maxRotation: 90, minRotation: 45 }
            },
            y: { beginAtZero: true, title: { display: true, text: 'Quantity Requested (kg/L)' } }
        }
    }
});
</script>

  <?php if ($materials && $materials->num_rows > 0): ?>
  <div class="mt-6">
    <h3 class="text-lg font-bold mb-2">Material Quantities</h3>
    <div class="h-64"><canvas id="materialChart"></canvas></div>
  </div>
  <?php endif; ?>
</div>

<script>
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

<?php if ($materials && $materials->num_rows > 0): ?>
new Chart(document.getElementById('materialChart'), {
  type: 'pie',
  data: {
    labels: [<?php $materials->data_seek(0); while($m = $materials->fetch_assoc()) echo "'".$m['material_name']."',"; ?>],
    datasets: [{
      data: [<?php $materials->data_seek(0); while($m = $materials->fetch_assoc()) echo $m['quantity'].","; ?>],
      backgroundColor: ['#3f51b5','#009688','#e91e63','#ff5722','#795548','#607d8b']
    }]
  }
});
<?php endif; ?>
</script>

</body>
</html>
