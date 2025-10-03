<!-- superadmin/navbar.php -->
<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <!-- Header -->
  <h2 class="text-xl font-bold text-blue-800 mb-6">Stores Panel</h2>

  <!-- Nav Links -->
  <nav class="space-y-6 flex-1">
    
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Stores</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_material.php" class="block hover:bg-blue-200 p-2 rounded">Add Raw Material</a></li>
        <li><a href="remove_material.php" class="block hover:bg-blue-200 p-2 rounded">Remove Raw Material</a></li>
        <li><a href="chemicals_in.php" class="block hover:bg-blue-200 p-2 rounded">Chemicals In</a></li>
        <li><a href="stock_in.php" class="block hover:bg-blue-200 p-2 rounded">Stock In</a></li>
        <li><a href="qc_approval.php" class="block hover:bg-blue-200 p-2 rounded">QC Approval</a></li>
        <li><a href="stock_out.php" class="block hover:bg-blue-200 p-2 rounded">Stock Out</a></li>
        <li><a href="view_inventory.php" class="block hover:bg-blue-200 p-2 rounded">View Inventory</a></li>
       
        <li><a href="store_reports.php" class="block hover:bg-blue-200 p-2 rounded">Reports</a></li>
      </ul>
    </div>
  </nav>

  <!-- Logout Button at the Bottom -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">
      Logout
    </a>
  </div>
</div>
