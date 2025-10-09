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
  <title>Procurement Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-gray-800">

  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-10">
    <h1 class="text-3xl font-bold text-purple-700 mb-6">Welcome Procurement Officer</h1>
    <p class="text-gray-600">Oversee purchase requests, supplier management, and order tracking.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8">
      <div class="bg-purple-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-purple-800">Purchase Requests</h2>
        <p class="text-sm text-gray-600">Review and approve material requisitions from departments.</p>
      </div>
      <div class="bg-purple-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-purple-800">Suppliers</h2>
        <p class="text-sm text-gray-600">Add, view, and evaluate supplier performance.</p>
      </div>
      <div class="bg-purple-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-purple-800">Purchase Orders</h2>
        <p class="text-sm text-gray-600">Generate and track ongoing purchase orders.</p>
      </div>
    </div>
  </div>
</body>
</html>
