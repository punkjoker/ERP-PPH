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
  <title>Quality Control Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-gray-800">

  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-10">
    <h1 class="text-3xl font-bold text-blue-700 mb-6">Welcome Quality Control</h1>
    <p class="text-gray-600">Monitor inspection processes and product standards.</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Raw Material Inspection</h2>
        <p class="text-sm text-gray-600">Ensure quality before materials enter production.</p>
      </div>
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Finished Goods</h2>
        <p class="text-sm text-gray-600">Quality checks for completed products.</p>
      </div>
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Reports</h2>
        <p class="text-sm text-gray-600">Generate QC performance summaries.</p>
      </div>
    </div>
  </div>
</body>
</html>
