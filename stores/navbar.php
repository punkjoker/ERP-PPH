<!-- superadmin/navbar_stores.php -->
<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <!-- Profile Section -->
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      S
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Stores Department</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <!-- Navigation Menu -->
  <nav class="space-y-6 flex-1">
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Stores</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_store_item.php" class="block hover:bg-blue-200 p-2 rounded">Add New Item</a></li>
        <li><a href="view_inventory.php" class="block hover:bg-blue-200 p-2 rounded">Inventory Overview</a></li>
        <li><a href="issue_items.php" class="block hover:bg-blue-200 p-2 rounded">Issue Items</a></li>
        <li><a href="return_items.php" class="block hover:bg-blue-200 p-2 rounded">Return Items</a></li>
        <li><a href="reorder_alerts.php" class="block hover:bg-blue-200 p-2 rounded">Reorder Alerts</a></li>
        <li><a href="received_deliveries.php" class="block hover:bg-blue-200 p-2 rounded">Received Deliveries</a></li>
        <li><a href="department_requests.php" class="block hover:bg-blue-200 p-2 rounded">Department Requests</a></li>
        <li><a href="store_reports.php" class="block hover:bg-blue-200 p-2 rounded">Store Reports</a></li>
      </ul>
    </div>
  </nav>

  <!-- Logout -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">Logout</a>
  </div>
</div>
