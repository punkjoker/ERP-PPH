<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
// optional: verify user belongs to store B / superadmin group
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Litein Store B Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-gray-800">

  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-10">
    <h1 class="text-3xl font-bold text-blue-700 mb-6">Welcome to Litein Store B</h1>
    <p class="text-gray-600">Use the menu on the left to manage Store B operations.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">

      <!-- Chemicals -->
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Chemicals</h2>
        <p class="text-sm text-gray-600">View inventory and record chemicals in</p>
      </div>

      <!-- Engineering Products -->
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Engineering Products</h2>
        <p class="text-sm text-gray-600">Manage incoming and stored engineering products</p>
      </div>

      <!-- Finished Products -->
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Finished Products</h2>
        <p class="text-sm text-gray-600">Track finished products and inventory</p>
      </div>

      <!-- Orders & Deliveries -->
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Orders & Deliveries</h2>
        <p class="text-sm text-gray-600">Create orders and monitor deliveries for Store B</p>
      </div>

    </div>
  </div>
</body>
</html>
