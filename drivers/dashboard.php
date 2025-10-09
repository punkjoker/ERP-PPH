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
  <title>Drivers Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-gray-800">

  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-10">
    <h1 class="text-3xl font-bold text-yellow-700 mb-6">Welcome Driver</h1>
    <p class="text-gray-600">Manage your daily deliveries and monitor fleet assignments.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
      <div class="bg-yellow-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-yellow-800">Assigned Deliveries</h2>
        <p class="text-sm text-gray-600">View and confirm your assigned delivery routes.</p>
      </div>
      <div class="bg-yellow-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-yellow-800">Delivery History</h2>
        <p class="text-sm text-gray-600">Track completed and pending deliveries.</p>
      </div>
      <div class="bg-yellow-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-yellow-800">Vehicle Logs</h2>
        <p class="text-sm text-gray-600">Submit and view vehicle maintenance reports.</p>
      </div>
    </div>
  </div>
</body>
</html>
