<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><!-- superadmin/navbar_stores.php -->
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
        <li><a href="add_material.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-box-open mr-2"></i>Add Raw Material</a></li>
        <li><a href="remove_material.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-minus-circle mr-2"></i>Remove Raw Material</a></li>
        <li><a href="chemicals_in.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-flask mr-2"></i>Chemicals In</a></li>
 <li><a href="packaging_request.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-box mr-2"></i>Packaging Request</a></li>
        <li><a href="chemical_list.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-list mr-2"></i>Items List</a></li>
        <li><a href="chemical_inventory.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-vials mr-2"></i>Chemicals Inventory</a></li>
        <li><a href="products_inventory.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-boxes mr-2"></i>Finished Product Inventory</a></li>
        <li><a href="engineering_products.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-gear mr-2"></i>Engineer Products Inventory</a></li>
        <li><a href="stock_in.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-arrow-down mr-2"></i>Stock In</a></li>
        <li><a href="qc_approval.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-thumbs-up mr-2"></i>QC Approval</a></li>
        <li><a href="stock_out.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-arrow-up mr-2"></i>Stock Out</a></li>
        <li><a href="production_requests.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-list-ul mr-2"></i>Production Requests</a></li>
        <li><a href="view_inventory.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-warehouse mr-2"></i>View Inventory</a></li>
        <li><a href="bill_of_material_history.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-scroll mr-2"></i>View Bill Of Material</a></li>
        <li><a href="order_deliveries.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-truck mr-2"></i>Create Delivery</a></li>
        <li><a href="create_order_delivery.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-pen mr-2"></i>Create Order</a></li>
        <li><a href="all_delivery_order_items.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-clipboard-list mr-2"></i>View All Orders List</a></li>
        <li><a href="delivery_details.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-info-circle mr-2"></i>Delivery Details</a></li>
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-plus mr-2"></i>New Item Request</a></li>
        <li><a href="store_reports.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-pie mr-2"></i>Reports</a></li>
      </ul>
    </div>
  </nav>

  <!-- Logout -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">Logout</a>
  </div>
</div>
