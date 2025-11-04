<?php
include 'db_con.php';

$from_date = $_GET['from'] ?? date('Y-m-01');
$to_date = $_GET['to'] ?? date('Y-m-t');

// --- Fetch totals ---
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

// ✅ Packaging cost now comes from the 'packaging' table
$stmt2 = $conn->prepare("
    SELECT SUM(p.total_cost) AS total_pack_cost
    FROM packaging p
    JOIN production_runs r ON p.production_run_id = r.id
    WHERE DATE(p.packaging_date) BETWEEN ? AND ?
");
$stmt2->bind_param('ss', $from_date, $to_date);
$stmt2->execute();
$pack_total = $stmt2->get_result()->fetch_assoc()['total_pack_cost'] ?? 0;

// ✅ Total production cost
$total_production_cost = $bill_total + $pack_total;


// --- Chemical stats for Chart ---
// --- Chemical stats for Chart --- (Grouped by chemical_code)
$stmt3 = $conn->prepare("
    SELECT 
        c.chemical_name,
        c.chemical_code,
        SUM(i.total_cost) AS total_cost
    FROM bill_of_material_items i
    JOIN chemicals_in c ON i.chemical_code = c.chemical_code
    JOIN bill_of_materials b ON i.bom_id = b.id
    WHERE b.bom_date BETWEEN ? AND ?
    GROUP BY c.chemical_code, c.chemical_name
    ORDER BY total_cost DESC
");
$stmt3->bind_param('ss', $from_date, $to_date);
$stmt3->execute();
$result = $stmt3->get_result();

$chemicals = [];
$costs = [];

while ($row = $result->fetch_assoc()) {
    // Display both code and name in chart labels
    $chemicals[] = $row['chemical_code'] . ' - ' . $row['chemical_name'];
    $costs[] = $row['total_cost'];
}
// --- Chemical quantity usage chart ---
$stmt4 = $conn->prepare("
    SELECT 
        c.chemical_name,
        c.chemical_code,
        SUM(i.quantity_requested) AS total_quantity
    FROM bill_of_material_items i
    JOIN chemicals_in c ON i.chemical_code = c.chemical_code
    JOIN bill_of_materials b ON i.bom_id = b.id
    WHERE b.bom_date BETWEEN ? AND ?
    GROUP BY c.chemical_code, c.chemical_name
    ORDER BY total_quantity DESC
");
$stmt4->bind_param('ss', $from_date, $to_date);
$stmt4->execute();
$result2 = $stmt4->get_result();

$chemicals_qty = [];
$quantities = [];

while ($row = $result2->fetch_assoc()) {
    $chemicals_qty[] = $row['chemical_code'] . ' - ' . $row['chemical_name'];
    $quantities[] = $row['total_quantity'];
}
// --- Packaging Material Cost Chart ---
$stmt5 = $conn->prepare("
    SELECT 
        p.item_name,
        SUM(p.total_cost) AS total_pack_cost
    FROM packaging p
    WHERE DATE(p.packaging_date) BETWEEN ? AND ?
    GROUP BY p.item_name
    ORDER BY total_pack_cost DESC
");
$stmt5->bind_param('ss', $from_date, $to_date);
$stmt5->execute();
$result3 = $stmt5->get_result();

$pack_items = [];
$pack_costs = [];

while ($row = $result3->fetch_assoc()) {
    $pack_items[] = $row['item_name'] ?: 'Unknown Item';
    $pack_costs[] = $row['total_pack_cost'];
}

// --- Packaging Quantity Used Chart ---
$stmt6 = $conn->prepare("
    SELECT 
        p.item_name,
        SUM(p.quantity_used) AS total_quantity_used
    FROM packaging p
    WHERE DATE(p.packaging_date) BETWEEN ? AND ?
    GROUP BY p.item_name
    ORDER BY total_quantity_used DESC
");
$stmt6->bind_param('ss', $from_date, $to_date);
$stmt6->execute();
$result4 = $stmt6->get_result();

$pack_items_qty = [];
$pack_quantities = [];

while ($row = $result4->fetch_assoc()) {
    $pack_items_qty[] = $row['item_name'] ?: 'Unknown Item';
    $pack_quantities[] = $row['total_quantity_used'];
}
// --- Label Cost Chart ---
$stmt7 = $conn->prepare("
    SELECT 
        l.material_name,
        SUM(l.total_cost) AS total_label_cost,
        SUM(l.used) AS total_used
    FROM label_reconciliation l
    WHERE DATE(l.reconciled_at) BETWEEN ? AND ?
    GROUP BY l.material_name
    ORDER BY total_label_cost DESC
");
$stmt7->bind_param('ss', $from_date, $to_date);
$stmt7->execute();
$result5 = $stmt7->get_result();

$label_items = [];
$label_costs = [];
$label_used = [];

while ($row = $result5->fetch_assoc()) {
    $label_items[] = $row['material_name'] ?: 'Unknown Label';
    $label_costs[] = $row['total_label_cost'];
    $label_used[] = $row['total_used'];
}
// --- Label Cost Total ---
$stmt8 = $conn->prepare("
    SELECT SUM(total_cost) AS label_total
    FROM label_reconciliation
    WHERE DATE(reconciled_at) BETWEEN ? AND ?
");
$stmt8->bind_param('ss', $from_date, $to_date);
$stmt8->execute();
$label_res = $stmt8->get_result()->fetch_assoc();
$stmt8->close();

$label_total = $label_res['label_total'] ?? 0;

// ✅ Update total production cost to include Label Cost
$total_production_cost = $bill_total + $pack_total + $label_total;

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Production Reports</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-100 via-blue-200 to-blue-300 min-h-screen">


  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">📊 Production Cost Report</h2>
<!-- Download Button -->
<div class="mb-4">
  <a href="download_production_report.php?from=<?= urlencode($from_date) ?>&to=<?= urlencode($to_date) ?>" 
     class="bg-green-600 text-white px-4 py-2 rounded-md shadow hover:bg-green-700 transition">
     📄 Download Report (PDF)
  </a>
</div>

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
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
  <!-- BOM Cost -->
  <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
    <p class="text-gray-500">Total BOM Cost</p>
    <h3 class="text-2xl font-semibold text-blue-600">
      <?= number_format($bill_total, 2) ?> Ksh
    </h3>
  </div>

  <!-- Packaging Cost -->
  <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
    <p class="text-gray-500">Total Packaging Cost</p>
    <h3 class="text-2xl font-semibold text-amber-600">
      <?= number_format($pack_total, 2) ?> Ksh
    </h3>
  </div>

  <!-- Label Cost -->
  <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
    <p class="text-gray-500">Total Label Cost</p>
    <h3 class="text-2xl font-semibold text-lime-600">
      <?= number_format($label_total, 2) ?> Ksh
    </h3>
  </div>

  <!-- Total Production Cost -->
  <div class="bg-white rounded-2xl shadow-lg p-6 text-center">
    <p class="text-gray-500">Total Production Cost (BOM + Packaging + Label)</p>
    <h3 class="text-2xl font-semibold text-green-700">
      <?= number_format($total_production_cost, 2) ?> Ksh
    </h3>
  </div>
</div>


    <!-- Chart Section -->
    <div class="bg-white p-6 rounded-2xl shadow-lg">
      <h3 class="text-xl font-semibold mb-4 text-gray-700">Chemical Cost Distribution</h3>
      <canvas id="chemChart" height="120"></canvas>
    </div>
    <!-- Chemical Quantity Usage Chart -->
<div class="bg-white p-6 mt-8 rounded-2xl shadow-lg">
  <h3 class="text-xl font-semibold mb-4 text-gray-700">Chemical Quantity Usage</h3>
  <canvas id="chemQtyChart" height="120"></canvas>
</div>
<!-- Packaging Material Cost Chart -->
<div class="bg-white p-6 mt-8 rounded-2xl shadow-lg">
  <h3 class="text-xl font-semibold mb-4 text-gray-700">Packaging Material Cost Distribution</h3>
  <canvas id="packChart" height="120"></canvas>
</div>
<!-- Packaging Quantity Used Chart -->
<div class="bg-white p-6 mt-8 rounded-2xl shadow-lg">
  <h3 class="text-xl font-semibold mb-4 text-gray-700">Packaging Quantity Used</h3>
  <canvas id="packQtyChart" height="120"></canvas>
</div>
<!-- Label Cost Chart -->
<div class="bg-white p-6 mt-8 rounded-2xl shadow-lg">
  <h3 class="text-xl font-semibold mb-4 text-gray-700">Label Cost Distribution</h3>
  <canvas id="labelChart" height="120"></canvas>
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
          bodyColor: '#8cb3dbff',
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
  <script>
const ctxQty = document.getElementById('chemQtyChart').getContext('2d');
const gradientQty = ctxQty.createLinearGradient(0, 0, 0, 400);
gradientQty.addColorStop(0, 'rgba(16, 185, 129, 0.7)');
gradientQty.addColorStop(1, 'rgba(167, 243, 208, 0.4)');

new Chart(ctxQty, {
  type: 'bar',
  data: {
    labels: <?= json_encode($chemicals_qty) ?>,
    datasets: [{
      label: 'Chemical Quantity Used',
      data: <?= json_encode($quantities) ?>,
      backgroundColor: gradientQty,
      borderColor: 'rgba(16, 185, 129, 1)',
      borderWidth: 1,
      borderRadius: 6,
      hoverBackgroundColor: 'rgba(34, 197, 94, 0.8)',
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: true, position: 'top' },
      tooltip: {
        backgroundColor: '#064e3b',
        titleColor: '#fff',
        bodyColor: '#8cb3dbff',
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
<script>
const ctxPack = document.getElementById('packChart').getContext('2d');
const gradientPack = ctxPack.createLinearGradient(0, 0, 0, 400);
gradientPack.addColorStop(0, 'rgba(249, 115, 22, 0.7)');
gradientPack.addColorStop(1, 'rgba(254, 215, 170, 0.4)');

new Chart(ctxPack, {
  type: 'bar',
  data: {
    labels: <?= json_encode($pack_items) ?>,
    datasets: [{
      label: 'Packaging Cost (Ksh)',
      data: <?= json_encode($pack_costs) ?>,
      backgroundColor: gradientPack,
      borderColor: 'rgba(234, 88, 12, 1)',
      borderWidth: 1,
      borderRadius: 6,
      hoverBackgroundColor: 'rgba(251, 146, 60, 0.8)',
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: true, position: 'top' },
      tooltip: {
        backgroundColor: '#78350f',
        titleColor: '#fff',
        bodyColor: '#8cb3dbff',
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
<script>
const ctxPackQty = document.getElementById('packQtyChart').getContext('2d');
const gradientPackQty = ctxPackQty.createLinearGradient(0, 0, 0, 400);
gradientPackQty.addColorStop(0, 'rgba(59, 130, 246, 0.7)');
gradientPackQty.addColorStop(1, 'rgba(191, 219, 254, 0.4)');

new Chart(ctxPackQty, {
  type: 'bar',
  data: {
    labels: <?= json_encode($pack_items_qty) ?>,
    datasets: [{
      label: 'Quantity Used (Units)',
      data: <?= json_encode($pack_quantities) ?>,
      backgroundColor: gradientPackQty,
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
        backgroundColor: '#1e3a8a',
        titleColor: '#fff',
        bodyColor: '#8cb3dbff',
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
<script>
const ctxLabel = document.getElementById('labelChart').getContext('2d');
const gradientLabel = ctxLabel.createLinearGradient(0, 0, 0, 400);
gradientLabel.addColorStop(0, 'rgba(132, 204, 22, 0.7)');
gradientLabel.addColorStop(1, 'rgba(217, 249, 157, 0.4)');

new Chart(ctxLabel, {
  type: 'bar',
  data: {
    labels: <?= json_encode($label_items) ?>,
    datasets: [{
      label: 'Label Cost (Ksh)',
      data: <?= json_encode($label_costs) ?>,
      backgroundColor: gradientLabel,
      borderColor: 'rgba(101, 163, 13, 1)',
      borderWidth: 1,
      borderRadius: 6,
      hoverBackgroundColor: 'rgba(132, 204, 22, 0.8)',
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: true, position: 'top' },
      tooltip: {
        callbacks: {
          // ✅ Tooltip will show used quantities
          afterBody: function(context) {
            const index = context[0].dataIndex;
            const used = <?= json_encode($label_used) ?>[index];
            return 'Quantity Used: ' + used;
          }
        },
        backgroundColor: '#365314',
        titleColor: '#fff',
        bodyColor: '#eaffd0',
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
