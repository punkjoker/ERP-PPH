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
  <title>Reports Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-gray-800">

  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-10">
    <h1 class="text-3xl font-bold text-teal-700 mb-6">Welcome Reports Department</h1>
    <p class="text-gray-600">Analyze performance and generate departmental reports.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
      <div class="bg-teal-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-teal-800">Production Reports</h2>
        <p class="text-sm text-gray-600">Review output and production statistics.</p>
      </div>
      <div class="bg-teal-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-teal-800">Quality Reports</h2>
        <p class="text-sm text-gray-600">Analyze inspection and testing results.</p>
      </div>
      <div class="bg-teal-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-teal-800">Procurement Reports</h2>
        <p class="text-sm text-gray-600">Monitor spending and supplier efficiency.</p>
      </div>
    </div>
  </div>
</body>
</html>
