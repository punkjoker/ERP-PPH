<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch user info (optional)
$user_name = $_SESSION['full_name'] ?? 'Accounts Officer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accounts Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">

  <!-- Include Accounts Sidebar -->
  <?php include 'navbar.php'; ?>

  <!-- Main Content -->
  <div class="ml-64 p-10">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-bold text-blue-700">Welcome, <?= htmlspecialchars($user_name) ?></h1>
        <p class="text-gray-600">Monitor and manage all financial activities below.</p>
      </div>
      <div class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow">
        <i class="fa-solid fa-calendar-days mr-2"></i><?php echo date('F Y'); ?>
      </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <!-- Payroll -->
      <a href="payroll_details.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition transform hover:-translate-y-1">
        <div class="flex items-center">
          <div class="bg-blue-100 p-4 rounded-full text-blue-600">
            <i class="fa-solid fa-file-invoice-dollar text-2xl"></i>
          </div>
          <div class="ml-4">
            <h2 class="text-xl font-semibold text-blue-800">Payroll Management</h2>
            <p class="text-sm text-gray-600">Update and review staff salary details.</p>
          </div>
        </div>
      </a>

      <!-- Deductions -->
      <a href="payroll_deductions.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition transform hover:-translate-y-1">
        <div class="flex items-center">
          <div class="bg-red-100 p-4 rounded-full text-red-600">
            <i class="fa-solid fa-minus text-2xl"></i>
          </div>
          <div class="ml-4">
            <h2 class="text-xl font-semibold text-red-800">Payroll Deductions</h2>
            <p class="text-sm text-gray-600">Manage deductions and rates.</p>
          </div>
        </div>
      </a>

      <!-- Process Payroll -->
      <a href="payroll_list.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition transform hover:-translate-y-1">
        <div class="flex items-center">
          <div class="bg-green-100 p-4 rounded-full text-green-600">
            <i class="fa-solid fa-wallet text-2xl"></i>
          </div>
          <div class="ml-4">
            <h2 class="text-xl font-semibold text-green-800">Process Payroll</h2>
            <p class="text-sm text-gray-600">Generate monthly salary summaries.</p>
          </div>
        </div>
      </a>

      <!-- Expenses -->
      <a href="manage_expenses.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition transform hover:-translate-y-1">
        <div class="flex items-center">
          <div class="bg-yellow-100 p-4 rounded-full text-yellow-600">
            <i class="fa-solid fa-money-bill-wave text-2xl"></i>
          </div>
          <div class="ml-4">
            <h2 class="text-xl font-semibold text-yellow-800">Expense Management</h2>
            <p class="text-sm text-gray-600">Add and track organization expenses.</p>
          </div>
        </div>
      </a>

      <!-- Reports -->
      <a href="accounts_reports.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition transform hover:-translate-y-1">
        <div class="flex items-center">
          <div class="bg-purple-100 p-4 rounded-full text-purple-600">
            <i class="fa-solid fa-chart-line text-2xl"></i>
          </div>
          <div class="ml-4">
            <h2 class="text-xl font-semibold text-purple-800">Financial Reports</h2>
            <p class="text-sm text-gray-600">View monthly and annual financial reports.</p>
          </div>
        </div>
      </a>
    </div>
  </div>

</body>
</html>
