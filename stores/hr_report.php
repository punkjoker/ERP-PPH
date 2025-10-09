<?php
include 'db_con.php';

// Default month = current month
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Fetch totals
function getTotal($conn, $table, $date_field, $month_filter) {
  $stmt = $conn->prepare("SELECT IFNULL(SUM(total_amount),0) as total FROM $table WHERE DATE_FORMAT($date_field, '%Y-%m') = ?");
  $stmt->bind_param("s", $month_filter);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  return $result['total'];
}

// Fetch other expenses total
$stmt = $conn->prepare("SELECT IFNULL(SUM(amount),0) as total FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?");
$stmt->bind_param("s", $selected_month);
$stmt->execute();
$other_expenses_total = $stmt->get_result()->fetch_assoc()['total'];

$breakfast_total = getTotal($conn, "breakfast_expense", "expense_date", $selected_month);
$lunch_total = getTotal($conn, "lunch_expense", "start_date", $selected_month);

$total = $breakfast_total + $lunch_total + $other_expenses_total;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HR Report</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-blue-700">HR Monthly Expense Report</h1>
      <form method="GET" class="flex items-center gap-2">
        <input type="month" name="month" value="<?= $selected_month ?>" class="border p-2 rounded">
        <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700">Filter</button>
      </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-gray-100 p-4 rounded">
        <h2 class="text-center font-semibold text-gray-700 mb-2">Breakfast</h2>
        <canvas id="breakfastChart"></canvas>
        <p class="text-center mt-2 text-sm text-gray-600">Total: Ksh <?= number_format($breakfast_total, 2) ?></p>
      </div>

      <div class="bg-gray-100 p-4 rounded">
        <h2 class="text-center font-semibold text-gray-700 mb-2">Lunch</h2>
        <canvas id="lunchChart"></canvas>
        <p class="text-center mt-2 text-sm text-gray-600">Total: Ksh <?= number_format($lunch_total, 2) ?></p>
      </div>

      <div class="bg-gray-100 p-4 rounded">
        <h2 class="text-center font-semibold text-gray-700 mb-2">Other Expenses</h2>
        <canvas id="otherChart"></canvas>
        <p class="text-center mt-2 text-sm text-gray-600">Total: Ksh <?= number_format($other_expenses_total, 2) ?></p>
      </div>
    </div>

    <div class="mt-8 text-center">
      <h2 class="text-xl font-semibold text-gray-800">Total Expenses for <?= $selected_month ?></h2>
      <p class="text-2xl text-blue-700 font-bold">Ksh <?= number_format($total, 2) ?></p>
      <button onclick="window.print()" class="mt-4 bg-green-600 text-white px-5 py-2 rounded hover:bg-green-700">Print Page</button>
    </div>
  </div>

  <script>
    const chartOptions = {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    };

    new Chart(document.getElementById('breakfastChart'), {
      type: 'bar',
      data: {
        labels: ['<?= $selected_month ?>'],
        datasets: [{ label: 'Breakfast', data: [<?= $breakfast_total ?>], backgroundColor: '#60A5FA' }]
      },
      options: chartOptions
    });

    new Chart(document.getElementById('lunchChart'), {
      type: 'bar',
      data: {
        labels: ['<?= $selected_month ?>'],
        datasets: [{ label: 'Lunch', data: [<?= $lunch_total ?>], backgroundColor: '#FBBF24' }]
      },
      options: chartOptions
    });

    new Chart(document.getElementById('otherChart'), {
      type: 'bar',
      data: {
        labels: ['<?= $selected_month ?>'],
        datasets: [{ label: 'Other', data: [<?= $other_expenses_total ?>], backgroundColor: '#34D399' }]
      },
      options: chartOptions
    });
  </script>
</body>
</html>
