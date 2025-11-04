<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
// optional: verify user belongs to superadmin group
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SuperAdmin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-gray-800">

  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-10">
    <h1 class="text-3xl font-bold text-blue-700 mb-6">Welcome SuperAdmin</h1>
    <p class="text-gray-600">Use the menu on the left to access all modules.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">HR</h2>
        <p class="text-sm text-gray-600">Manage employees and payroll</p>
      </div>
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Production</h2>
        <p class="text-sm text-gray-600">Production scheduling and BOM</p>
      </div>
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Quality Control</h2>
        <p class="text-sm text-gray-600">Inspection and reporting</p>
      </div>
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Stores</h2>
        <p class="text-sm text-gray-600">Inventory and materials</p>
      </div>
    </div>
  </div>
</body>
</html>
