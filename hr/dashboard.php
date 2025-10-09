<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HR Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-gray-800">

  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-10">
    <h1 class="text-3xl font-bold text-blue-700 mb-6">Welcome HR Department</h1>
    <p class="text-gray-600">Manage employees, leaves, and departmental requests.</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Employees</h2>
        <p class="text-sm text-gray-600">Add, view, and update employee details.</p>
      </div>
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Leaves</h2>
        <p class="text-sm text-gray-600">Manage employee leave requests.</p>
      </div>
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Reports</h2>
        <p class="text-sm text-gray-600">Generate HR reports and analytics.</p>
      </div>
    </div>
  </div>
</body>
</html>
