<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <!-- Profile Section -->
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      D
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Drivers Department</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="space-y-6 flex-1">
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Drivers</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_vehicle.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-car mr-2"></i>Manage Vehicles</a></li>
        <li><a href="pending_deliveries.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-clock mr-2"></i>Pending Deliveries</a></li>
        <li><a href="delivered_orders.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-check-circle mr-2"></i>Delivered</a></li>
        <li><a href="manage_trips.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-route mr-2"></i>Manage Trips</a></li>
        <li><a href="vehicle_maintenance.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-wrench mr-2"></i>Vehicle Maintenance Costs</a></li>
        <li><a href="fuel.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-gas-pump mr-2"></i>Fuel Section</a></li>
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-plus mr-2"></i>New Item Request</a></li>
        <li><a href="vehicle_reports.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-area mr-2"></i>Vehicles Report</a></li>
      </ul>
    </div>
  </nav>

  <!-- Logout -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">Logout</a>
  </div>
</div>
