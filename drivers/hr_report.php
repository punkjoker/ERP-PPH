<?php
include 'db_con.php';

// Default filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$year_filter = $_GET['year'] ?? '';

// --- Helper to fetch totals ---
function getTotal($conn, $table, $date_field, $from_date, $to_date, $year_filter) {
  $where = "1=1";
  $params = [];
  $types = "";

  if (!empty($from_date) && !empty($to_date)) {
    $where .= " AND $date_field BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
    $types .= "ss";
  } elseif (!empty($year_filter)) {
    $where .= " AND YEAR($date_field) = ?";
    $params[] = $year_filter;
    $types .= "s";
  }

  $stmt = $conn->prepare("SELECT IFNULL(SUM(total_amount),0) as total FROM $table WHERE $where");
  if (!empty($params)) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  return $result['total'];
}

// Fetch totals
$breakfast_total = getTotal($conn, "breakfast_expense", "expense_date", $from_date, $to_date, $year_filter);
$lunch_total = getTotal($conn, "lunch_expense", "start_date", $from_date, $to_date, $year_filter);
// Fetch number of active employees
$active_employees_query = $conn->query("SELECT COUNT(*) AS total FROM employees WHERE status = 'Active'");
$active_employees = $active_employees_query->fetch_assoc()['total'];

// Other expenses
$where_exp = "1=1";
$params_exp = [];
$types_exp = "";

if (!empty($from_date) && !empty($to_date)) {
  $where_exp .= " AND expense_date BETWEEN ? AND ?";
  $params_exp = [$from_date, $to_date];
  $types_exp = "ss";
} elseif (!empty($year_filter)) {
  $where_exp .= " AND YEAR(expense_date) = ?";
  $params_exp = [$year_filter];
  $types_exp = "s";
}

$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) as total FROM expenses WHERE $where_exp");
if (!empty($params_exp)) $stmt->bind_param($types_exp, ...$params_exp);
$stmt->execute();
$other_expenses_total = $stmt->get_result()->fetch_assoc()['total'];

$total = $breakfast_total + $lunch_total + $other_expenses_total;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HR Expense Report</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-blue-700">HR Expense Report</h1>
      <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-sm font-semibold">From</label>
          <input type="date" name="from_date" value="<?= $from_date ?>" class="border p-2 rounded">
        </div>
        <div>
          <label class="block text-sm font-semibold">To</label>
          <input type="date" name="to_date" value="<?= $to_date ?>" class="border p-2 rounded">
        </div>
        <div>
          <label class="block text-sm font-semibold">Year</label>
          <input type="number" name="year" min="2020" max="2099" value="<?= $year_filter ?>" class="border p-2 rounded w-24">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
      </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white p-4 rounded-xl shadow">
        <h2 class="text-center font-semibold text-gray-700 mb-2">Breakfast</h2>
        <canvas id="breakfastChart"></canvas>
        <p class="text-center mt-2 text-sm text-gray-600">Total: Ksh <?= number_format($breakfast_total, 2) ?></p>
      </div>

      <div class="bg-white p-4 rounded-xl shadow">
        <h2 class="text-center font-semibold text-gray-700 mb-2">Lunch</h2>
        <canvas id="lunchChart"></canvas>
        <p class="text-center mt-2 text-sm text-gray-600">Total: Ksh <?= number_format($lunch_total, 2) ?></p>
      </div>

      <div class="bg-white p-4 rounded-xl shadow">
        <h2 class="text-center font-semibold text-gray-700 mb-2">Other Expenses</h2>
        <canvas id="otherChart"></canvas>
        <p class="text-center mt-2 text-sm text-gray-600">Total: Ksh <?= number_format($other_expenses_total, 2) ?></p>
      </div>
    </div>

    <div class="mt-8 text-center bg-white p-6 rounded-xl shadow">
  <h2 class="text-xl font-semibold text-gray-800 mb-2">Total HR Overview</h2>

  <p class="text-3xl text-blue-700 font-bold mb-2">
    Ksh <?= number_format($total, 2) ?>
  </p>
  <p class="text-lg text-gray-700 mb-4">
    Active Employees: <span class="font-bold text-green-600"><?= $active_employees ?></span>
  </p>

  <button onclick="window.print()" class="bg-green-600 text-white px-5 py-2 rounded hover:bg-green-700">
    Print Page
  </button>
</div>

  </div>

  <script>
    const createGradient = (ctx, color1, color2) => {
      const gradient = ctx.createLinearGradient(0, 0, 0, 400);
      gradient.addColorStop(0, color1);
      gradient.addColorStop(1, color2);
      return gradient;
    };

    const chartOptions = {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } },
      animation: { duration: 1500, easing: 'easeOutBounce' }
    };

    const breakfastCtx = document.getElementById('breakfastChart').getContext('2d');
    const lunchCtx = document.getElementById('lunchChart').getContext('2d');
    const otherCtx = document.getElementById('otherChart').getContext('2d');

    new Chart(breakfastCtx, {
      type: 'bar',
      data: { labels: ['Breakfast'], datasets: [{
        data: [<?= $breakfast_total ?>],
        backgroundColor: createGradient(breakfastCtx, '#60A5FA', '#3B82F6'),
        borderRadius: 8
      }]},
      options: chartOptions
    });

    new Chart(lunchCtx, {
      type: 'bar',
      data: { labels: ['Lunch'], datasets: [{
        data: [<?= $lunch_total ?>],
        backgroundColor: createGradient(lunchCtx, '#FBBF24', '#F59E0B'),
        borderRadius: 8
      }]},
      options: chartOptions
    });

    new Chart(otherCtx, {
      type: 'bar',
      data: { labels: ['Other'], datasets: [{
        data: [<?= $other_expenses_total ?>],
        backgroundColor: createGradient(otherCtx, '#34D399', '#10B981'),
        borderRadius: 8
      }]},
      options: chartOptions
    });
  </script>
</body>
</html>
