<?php
// all_reports.php
include 'db_con.php';

// Unified filters
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');

// Ensure safe strings (we assume YYYY-MM-DD inputs)
$from_date = $from;
$to_date = $to;

// ---------- QC: Disposals count ----------
$disposals_stmt = $conn->prepare("SELECT COUNT(*) AS total_disposals FROM disposables WHERE (disposal_date BETWEEN ? AND ? OR disposal_date IS NULL)");
$disposals_stmt->bind_param('ss', $from_date, $to_date);
$disposals_stmt->execute();
$disposals = $disposals_stmt->get_result()->fetch_assoc()['total_disposals'] ?? 0;
$disposals_stmt->close();

// ---------- QC: QC inspections approved vs not ----------
$qcs_stmt = $conn->prepare("
  SELECT 
    SUM(qc_status = 'Approved Product') AS approved,
    SUM(qc_status = 'Not Approved') AS not_approved
  FROM qc_inspections
  WHERE DATE(created_at) BETWEEN ? AND ?
");
$qcs_stmt->bind_param('ss', $from_date, $to_date);
$qcs_stmt->execute();
$qc_row = $qcs_stmt->get_result()->fetch_assoc();
$qc_approved = $qc_row['approved'] ?? 0;
$qc_not_approved = $qc_row['not_approved'] ?? 0;
$qcs_stmt->close();

// ---------- QC: Inspected chemicals ----------
$chem_stmt = $conn->prepare("SELECT COUNT(*) AS total_chemicals FROM inspected_chemicals_in WHERE DATE(created_at) BETWEEN ? AND ?");
$chem_stmt->bind_param('ss', $from_date, $to_date);
$chem_stmt->execute();
$chemicals = $chem_stmt->get_result()->fetch_assoc()['total_chemicals'] ?? 0;
$chem_stmt->close();

// ---------- HR: Active vs Inactive employees ----------
$hr_stmt = $conn->prepare("
  SELECT 
    SUM(status = 'Active') AS active_count,
    SUM(status = 'Inactive') AS inactive_count
  FROM employees
");
$hr_stmt->execute();
$hr_row = $hr_stmt->get_result()->fetch_assoc();
$hr_active = $hr_row['active_count'] ?? 0;
$hr_inactive = $hr_row['inactive_count'] ?? 0;
$hr_stmt->close();

// ---------- Production: BOM + Packaging totals (same logic used earlier) ----------
$bill_stmt = $conn->prepare("
    SELECT IFNULL(SUM(i.total_cost),0) AS total_bom_cost
    FROM bill_of_material_items i
    JOIN bill_of_materials b ON i.bom_id = b.id
    WHERE b.bom_date BETWEEN ? AND ?
");
$bill_stmt->bind_param('ss', $from_date, $to_date);
$bill_stmt->execute();
$bill_total = $bill_stmt->get_result()->fetch_assoc()['total_bom_cost'] ?? 0;
$bill_stmt->close();

$pack_stmt = $conn->prepare("
    SELECT IFNULL(SUM(p.total_cost),0) AS total_pack_cost
    FROM packaging_reconciliation p
    JOIN qc_inspections q ON p.qc_inspection_id = q.id
    WHERE q.created_at BETWEEN ? AND ?
");
$pack_stmt->bind_param('ss', $from_date, $to_date);
$pack_stmt->execute();
$pack_total = $pack_stmt->get_result()->fetch_assoc()['total_pack_cost'] ?? 0;
$pack_stmt->close();

$total_production_cost = floatval($bill_total) + floatval($pack_total);

// Production: chemical cost distribution (names & costs)
$chem_dist_stmt = $conn->prepare("
    SELECT c.chemical_name, IFNULL(SUM(i.total_cost),0) AS total_cost
    FROM bill_of_material_items i
    JOIN chemicals_in c ON i.chemical_id = c.id
    JOIN bill_of_materials b ON i.bom_id = b.id
    WHERE b.bom_date BETWEEN ? AND ?
    GROUP BY c.chemical_name
    ORDER BY total_cost DESC
");
$chem_dist_stmt->bind_param('ss', $from_date, $to_date);
$chem_dist_stmt->execute();
$res = $chem_dist_stmt->get_result();
$prod_chemicals = [];
$prod_costs = [];
while ($r = $res->fetch_assoc()) {
    $prod_chemicals[] = $r['chemical_name'];
    $prod_costs[] = floatval($r['total_cost']);
}
$chem_dist_stmt->close();

// ---------- Stock: total stock in / out (use reasonable fields) ----------
$stock_in_stmt = $conn->prepare("
    SELECT IFNULL(SUM(quantity),0) AS total_in, IFNULL(SUM(quantity * unit_cost),0) as amount_in
    FROM stock_in
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stock_in_stmt->bind_param('ss', $from_date, $to_date);
$stock_in_stmt->execute();
$stock_in_row = $stock_in_stmt->get_result()->fetch_assoc();
$stock_total_in_qty = $stock_in_row['total_in'] ?? 0;
$stock_total_in_amount = $stock_in_row['amount_in'] ?? 0;
$stock_in_stmt->close();

$stock_out_stmt = $conn->prepare("
    SELECT IFNULL(SUM(quantity_removed),0) AS total_out, IFNULL(SUM(quantity_removed * unit_cost),0) as amount_out
    FROM stock_out_history
    WHERE DATE(stock_date) BETWEEN ? AND ?
");
$stock_out_stmt->bind_param('ss', $from_date, $to_date);
$stock_out_stmt->execute();
$stock_out_row = $stock_out_stmt->get_result()->fetch_assoc();
$stock_total_out_qty = $stock_out_row['total_out'] ?? 0;
$stock_total_out_amount = $stock_out_row['amount_out'] ?? 0;
$stock_out_stmt->close();

// ---------- Drivers / Vehicle: Trips / Maintenance / Fuel ----------
$trip_stmt = $conn->prepare("
    SELECT driver_name, COUNT(*) AS total_trips
    FROM trips
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY driver_name
");
$trip_stmt->bind_param('ss', $from_date, $to_date);
$trip_stmt->execute();
$trips_res = $trip_stmt->get_result();
$drivers = [];
$tripCounts = [];
while ($r = $trips_res->fetch_assoc()) {
    $drivers[] = $r['driver_name'];
    $tripCounts[] = intval($r['total_trips']);
}
$trip_stmt->close();

$maintenance_stmt = $conn->prepare("
    SELECT driver_name, IFNULL(SUM(maintenance_cost),0) AS total_maintenance
    FROM vehicle_maintenance
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY driver_name
");
$maintenance_stmt->bind_param('ss', $from_date, $to_date);
$maintenance_stmt->execute();
$maint_res = $maintenance_stmt->get_result();
$maintenanceMap = [];
while ($r = $maint_res->fetch_assoc()) {
    $maintenanceMap[$r['driver_name']] = floatval($r['total_maintenance']);
}
$maintenance_stmt->close();

$fuel_stmt = $conn->prepare("
    SELECT v.driver_name, IFNULL(SUM(f.amount_refueled),0) AS total_fuel
    FROM fuel_cost f
    JOIN vehicles v ON f.vehicle_number = v.vehicle_number
    WHERE DATE(f.created_at) BETWEEN ? AND ?
    GROUP BY v.driver_name
");
$fuel_stmt->bind_param('ss', $from_date, $to_date);
$fuel_stmt->execute();
$fuel_res = $fuel_stmt->get_result();
$fuelMap = [];
while ($r = $fuel_res->fetch_assoc()) {
    $fuelMap[$r['driver_name']] = floatval($r['total_fuel']);
}
$fuel_stmt->close();

// Combine driver-level arrays to ensure same ordering
$driverLabels = $drivers;
$driverMaintenance = [];
$driverFuel = [];
foreach ($driverLabels as $d) {
    $driverMaintenance[] = isset($maintenanceMap[$d]) ? $maintenanceMap[$d] : 0;
    $driverFuel[] = isset($fuelMap[$d]) ? $fuelMap[$d] : 0;
}

// ---------- Procurement: total spend + item stats (from earlier code) ----------
$proc_stmt1 = $conn->prepare("
    SELECT IFNULL(SUM(quantity * unit_price),0) AS total_spend
    FROM order_items
    JOIN po_list p ON order_items.po_id = p.id
    WHERE DATE(p.created_at) BETWEEN ? AND ?
");
$proc_stmt1->bind_param('ss', $from_date, $to_date);
$proc_stmt1->execute();
$total_procurement = $proc_stmt1->get_result()->fetch_assoc()['total_spend'] ?? 0;
$proc_stmt1->close();

$proc_stmt2 = $conn->prepare("
    SELECT COALESCE(pr.product_name, order_items.manual_name) AS item_name,
           SUM(order_items.quantity) AS total_qty,
           SUM(order_items.quantity * order_items.unit_price) AS spent
    FROM order_items
    LEFT JOIN procurement_products pr ON order_items.product_id = pr.id
    JOIN po_list p ON order_items.po_id = p.id
    WHERE DATE(p.created_at) BETWEEN ? AND ?
    GROUP BY item_name
    ORDER BY spent DESC
    LIMIT 12
");
$proc_stmt2->bind_param('ss', $from_date, $to_date);
$proc_stmt2->execute();
$proc_res = $proc_stmt2->get_result();
$proc_items = [];
$proc_qtys = [];
$proc_spends = [];
while ($r = $proc_res->fetch_assoc()) {
    $proc_items[] = $r['item_name'];
    $proc_qtys[] = floatval($r['total_qty']);
    $proc_spends[] = floatval($r['spent']);
}
$proc_stmt2->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>All Reports — LynnTech</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* keep page content from being hidden behind fixed navbar */
    body { font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; }
    .card { border-radius: 14px; }
    .small-stat { font-size: 0.9rem; color: #6b7280; }
    /* make canvases nicely padded */
    canvas { background: linear-gradient(180deg, rgba(143, 182, 222, 0.8), rgba(255,255,255,0.8)); border-radius: 12px; padding: 8px; }
  </style>
</head>
<body class="bg-gray-50">

  <?php include 'navbar.php'; ?>

  <main class="ml-64 p-6">
    <header class="flex items-start justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-slate-800">All Reports</h1>
        <p class="text-sm text-slate-500">Combined dashboard — filtered by date range</p>
      </div>

      <!-- Filters -->
      <form method="get" class="flex items-center gap-3 bg-white p-3 rounded-lg shadow">
        <div>
          <label class="text-xs text-slate-500">From</label>
          <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>" class="border rounded px-2 py-1 w-40">
        </div>
        <div>
          <label class="text-xs text-slate-500">To</label>
          <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>" class="border rounded px-2 py-1 w-40">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Apply</button>
        <button id="downloadAllPdf" type="button" class="bg-green-600 text-white px-4 py-2 rounded">Download PDF</button>
      </form>
    </header>

    <!-- GRID: QC / HR / Procurement -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      <!-- QC Card -->
      <div class="card bg-white p-5 shadow">
        <h2 class="text-lg font-semibold mb-3">Quality Control</h2>
        <div class="grid grid-cols-1 gap-3">
          <div class="flex items-center justify-between">
            <div>
              <p class="small-stat">Total Disposals</p>
              <p class="text-2xl font-bold text-blue-600"><?= number_format($disposals) ?></p>
            </div>
            <div>
              <p class="small-stat">QC Approved</p>
              <p class="text-2xl font-bold text-green-600"><?= number_format($qc_approved) ?></p>
            </div>
            <div>
              <p class="small-stat">Inspected Chemicals</p>
              <p class="text-2xl font-bold text-purple-600"><?= number_format($chemicals) ?></p>
            </div>
          </div>

          <div class="mt-2">
            <canvas id="qcDisposalChart" height="100"></canvas>
          </div>

          <div class="mt-3">
            <canvas id="qcStatusDonut" height="100"></canvas>
          </div>

          <div class="mt-3">
            <canvas id="qcChemChart" height="100"></canvas>
          </div>
        </div>
      </div>

    <!-- HR Card -->
<?php
$selected_month = date('Y-m');

function getTotal($conn, $table, $date_field, $month_filter) {
  $stmt = $conn->prepare("SELECT IFNULL(SUM(total_amount),0) as total FROM $table WHERE DATE_FORMAT($date_field, '%Y-%m') = ?");
  $stmt->bind_param("s", $month_filter);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  return $result['total'];
}

$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) as total FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?");
$stmt->bind_param("s", $selected_month);
$stmt->execute();
$other_expenses_total = $stmt->get_result()->fetch_assoc()['total'];

$breakfast_total = getTotal($conn, "breakfast_expense", "expense_date", $selected_month);

// ✅ Updated lunch expense calculation
$lunch_stmt = $conn->prepare("
  SELECT IFNULL(SUM(total_amount),0) AS total 
  FROM lunch_expense 
  WHERE DATE_FORMAT(start_date, '%Y-%m') = ? 
     OR DATE_FORMAT(end_date, '%Y-%m') = ?
");
$lunch_stmt->bind_param("ss", $selected_month, $selected_month);
$lunch_stmt->execute();
$lunch_total = $lunch_stmt->get_result()->fetch_assoc()['total'];

$total_hr_expenses = $breakfast_total + $lunch_total + $other_expenses_total;
?>

<div class="card bg-white p-5 shadow">
  <h2 class="text-lg font-semibold mb-3">HR Monthly Expenses</h2>
  <p class="small-stat mb-3">For <?= date('F Y', strtotime($selected_month)) ?></p>

  <div class="grid grid-cols-3 gap-8 text-center"> <!-- Increased gap -->
    <div>
      <p class="text-2xl font-bold text-blue-600"><?= number_format($breakfast_total, 0) ?></p>
      <p class="small-stat text-gray-600">Breakfast</p>
    </div>
    <div>
      <p class="text-2xl font-bold text-yellow-600"><?= number_format($lunch_total, 0) ?></p>
      <p class="small-stat text-gray-600">Lunch</p>
    </div>
    <div>
      <p class="text-2xl font-bold text-green-600"><?= number_format($other_expenses_total, 0) ?></p>
      <p class="small-stat text-gray-600">Other</p>
    </div>
  </div>

  <div class="mt-8">
    <canvas id="hrExpenseChart" height="110"></canvas>
  </div>

  <p class="mt-6 text-center font-semibold text-gray-700">
    Total: <span class="text-blue-700 text-xl">Ksh <?= number_format($total_hr_expenses, 2) ?></span>
  </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  new Chart(document.getElementById('hrExpenseChart'), {
    type: 'doughnut',
    data: {
      labels: ['Breakfast', 'Lunch', 'Other'],
      datasets: [{
        data: [<?= $breakfast_total ?>, <?= $lunch_total ?>, <?= $other_expenses_total ?>],
        backgroundColor: ['#60A5FA', '#FBBF24', '#34D399']
      }]
    },
    options: {
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });
</script>

      <!-- Procurement Card -->
      <div class="card bg-white p-5 shadow">
        <h2 class="text-lg font-semibold mb-3">Procurement</h2>
        <p class="small-stat">Total Spend (filtered period)</p>
        <p class="text-2xl font-bold text-indigo-600 mb-3">Ksh <?= number_format($total_procurement,2) ?></p>

        <div class="mt-2">
          <canvas id="procQtyChart" height="100"></canvas>
        </div>

        <div class="mt-3">
          <canvas id="procSpendChart" height="100"></canvas>
        </div>
      </div>
    </section>

    <!-- GRID: Production / Stock -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Production -->
      <div class="card bg-white p-5 shadow">
        <h2 class="text-lg font-semibold mb-3">Production Costs</h2>
        <div class="grid grid-cols-3 gap-3 mb-4">
          <div class="text-center">
            <p class="small-stat">BOM Cost</p>
            <p class="text-xl font-semibold text-blue-600"><?= number_format($bill_total,2) ?> Ksh</p>
          </div>
          <div class="text-center">
            <p class="small-stat">Packaging</p>
            <p class="text-xl font-semibold text-amber-600"><?= number_format($pack_total,2) ?> Ksh</p>
          </div>
          <div class="text-center">
            <p class="small-stat">Total Production</p>
            <p class="text-xl font-semibold text-green-600"><?= number_format($total_production_cost,2) ?> Ksh</p>
          </div>
        </div>

        <div>
          <canvas id="prodChemChart" height="140"></canvas>
        </div>
      </div>

      <!-- Stock -->
      <div class="card bg-white p-5 shadow">
        <h2 class="text-lg font-semibold mb-3">Stock Summary</h2>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div class="text-center">
            <p class="small-stat">Stock In Qty</p>
            <p class="text-xl font-bold"><?= number_format($stock_total_in_qty,2) ?></p>
            <p class="small-stat">Ksh <?= number_format($stock_total_in_amount,2) ?></p>
          </div>
          <div class="text-center">
            <p class="small-stat">Stock Out Qty</p>
            <p class="text-xl font-bold"><?= number_format($stock_total_out_qty,2) ?></p>
            <p class="small-stat">Ksh <?= number_format($stock_total_out_amount,2) ?></p>
          </div>
        </div>

        <div>
          <canvas id="stockChart" height="140"></canvas>
        </div>
      </div>
    </section>

    <!-- Drivers & Vehicles -->
    <section class="card bg-white p-5 shadow mb-6">
      <h2 class="text-lg font-semibold mb-3">Drivers & Vehicle Costs</h2>
      <p class="small-stat mb-3">Trips / Maintenance / Fuel (per driver)</p>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <canvas id="tripChart" height="160"></canvas>
        </div>
        <div>
          <canvas id="maintenanceChart" height="160"></canvas>
        </div>
        <div>
          <canvas id="fuelChart" height="160"></canvas>
        </div>
      </div>

      <div class="mt-6 overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead class="bg-gray-100">
            <tr>
              <th class="p-2 border">Driver</th>
              <th class="p-2 border text-right">Trips</th>
              <th class="p-2 border text-right">Maintenance (Ksh)</th>
              <th class="p-2 border text-right">Fuel (Ksh)</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if (count($driverLabels) === 0) {
                echo '<tr><td colspan="4" class="p-3 text-center text-gray-500">No driver records for selected range.</td></tr>';
            } else {
                foreach ($driverLabels as $idx => $drv) {
                    $t = $tripCounts[$idx] ?? 0;
                    $m = $driverMaintenance[$idx] ?? 0;
                    $f = $driverFuel[$idx] ?? 0;
                    echo "<tr class='odd:bg-white even:bg-gray-50'>
                            <td class='p-2 border'>".htmlspecialchars($drv)."</td>
                            <td class='p-2 border text-right'>".number_format($t)."</td>
                            <td class='p-2 border text-right'>".number_format($m,2)."</td>
                            <td class='p-2 border text-right'>".number_format($f,2)."</td>
                          </tr>";
                }
            }
            ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <script>
    // Helper for gradients
    function gradient(ctx, c1, c2) {
      const g = ctx.createLinearGradient(0,0,0,300);
      g.addColorStop(0, c1);
      g.addColorStop(1, c2);
      return g;
    }

    // ---- QC charts ----
    // Disposal single bar
    new Chart(document.getElementById('qcDisposalChart'), {
      type: 'bar',
      data: {
        labels: ['Disposals'],
        datasets: [{ label: 'Disposals', data: [<?= (int)$disposals ?>], backgroundColor: gradient(document.getElementById('qcDisposalChart').getContext('2d'), '#60A5FA80', '#3B82F680') }]
      },
      options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
    });

    // QC status donut
    new Chart(document.getElementById('qcStatusDonut'), {
      type: 'doughnut',
      data: {
        labels: ['Approved','Not Approved'],
        datasets: [{ data: [<?= (int)$qc_approved ?>, <?= (int)$qc_not_approved ?>], backgroundColor: ['#10B981','#EF4444'] }]
      },
      options: { responsive:true, plugins:{legend:{position:'bottom'}} }
    });

    // inspected chemicals simple bar
    new Chart(document.getElementById('qcChemChart'), {
      type: 'bar',
      data: { labels: ['Inspected Chemicals'], datasets:[{ data: [<?= (int)$chemicals ?>], backgroundColor: gradient(document.getElementById('qcChemChart').getContext('2d'),'#A78BFA80','#8B5CF680') }]},
      options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
    });

    // ---- HR donut ----
    new Chart(document.getElementById('hrDonut'), {
      type:'doughnut',
      data: { labels:['Active','Inactive'], datasets:[{ data:[<?= (int)$hr_active ?>, <?= (int)$hr_inactive ?>], backgroundColor:['#10B981','#EF4444'] }]},
      options:{ responsive:true, plugins:{legend:{position:'bottom'}} }
    });

    // ---- Procurement charts ----
    const procQtyCtx = document.getElementById('procQtyChart').getContext('2d');
    const procSpendCtx = document.getElementById('procSpendChart').getContext('2d');

    new Chart(procQtyCtx, {
      type: 'bar',
      data: { labels: <?= json_encode($proc_items) ?>, datasets: [{ label:'Qty', data: <?= json_encode($proc_qtys) ?>, backgroundColor: gradient(procQtyCtx,'#60A5FA80','#3B82F680') }]},
      options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
    });

    new Chart(procSpendCtx, {
      type: 'bar',
      data: { labels: <?= json_encode($proc_items) ?>, datasets: [{ label:'Spent', data: <?= json_encode($proc_spends) ?>, backgroundColor: gradient(procSpendCtx,'#FDBA74','#FB718580') }]},
      options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
    });

    // ---- Production chemical distribution ----
    const prodCtx = document.getElementById('prodChemChart').getContext('2d');
    new Chart(prodCtx, {
      type: 'bar',
      data: { labels: <?= json_encode($prod_chemicals) ?>, datasets: [{ label:'Cost (Ksh)', data: <?= json_encode($prod_costs) ?>, backgroundColor: gradient(prodCtx,'#60A5FA80','#93C5FD80') }]},
      options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
    });

    // ---- Stock chart ----
    new Chart(document.getElementById('stockChart'), {
      type: 'bar',
      data: {
        labels: ['Stock In','Stock Out'],
        datasets: [
          { label: 'Qty', data: [<?= (float)$stock_total_in_qty ?>, <?= (float)$stock_total_out_qty ?>], backgroundColor: ['#34D399','#F87171'] },
          { label: 'Amount (Ksh)', data: [<?= (float)$stock_total_in_amount ?>, <?= (float)$stock_total_out_amount ?>], backgroundColor: ['#60A5FA','#FDBA74'] }
        ]
      },
      options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
    });

    // ---- Driver charts ----
    new Chart(document.getElementById('tripChart'), {
      type:'bar',
      data: { labels: <?= json_encode($driverLabels) ?>, datasets:[{ label:'Trips', data: <?= json_encode($tripCounts) ?>, backgroundColor: gradient(document.getElementById('tripChart').getContext('2d'),'#60A5FA80','#3B82F680') }]},
      options:{ responsive:true, scales:{ y:{ beginAtZero:true } } }
    });

    new Chart(document.getElementById('maintenanceChart'), {
      type:'bar',
      data: { labels: <?= json_encode($driverLabels) ?>, datasets:[{ label:'Maintenance (Ksh)', data: <?= json_encode($driverMaintenance) ?>, backgroundColor: gradient(document.getElementById('maintenanceChart').getContext('2d'),'#86EFAC','#34D399') }]},
      options:{ responsive:true, scales:{ y:{ beginAtZero:true } } }
    });

    new Chart(document.getElementById('fuelChart'), {
      type:'bar',
      data: { labels: <?= json_encode($driverLabels) ?>, datasets:[{ label:'Fuel (Ksh)', data: <?= json_encode($driverFuel) ?>, backgroundColor: gradient(document.getElementById('fuelChart').getContext('2d'),'#FDBA74','#FB7185') }]},
      options:{ responsive:true, scales:{ y:{ beginAtZero:true } } }
    });

    // ---- Download whole page as PDF ----
    document.getElementById('downloadAllPdf').addEventListener('click', () => {
      const element = document.querySelector('main');
      html2pdf().set({ margin: 0.8, filename: 'all_reports_<?= date("Ymd_His") ?>.pdf', html2canvas:{ scale: 2 } }).from(element).save();
    });
  </script>
</body>
</html>
