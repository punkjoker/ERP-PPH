<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><!-- Production Department Navbar -->
<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      P
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Production Department</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <nav class="space-y-6 flex-1">
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Production</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_product.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-plus-circle mr-2"></i>Add Product</a></li>
        <li><a href="manage_bom.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-list-check mr-2"></i>Manage BOM</a></li>
        <li><a href="record_production_run.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-industry mr-2"></i>Record Production Run</a></li>
        <li><a href="packaging_list.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-boxes-stacked mr-2"></i>Packaging List</a></li>
        <li><a href="view_finished_products.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-check-double mr-2"></i>View Finished Products</a></li>
        <li><a href="bill_of_material_history.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-invoice mr-2"></i>View Bill Of Material</a></li>
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-plus mr-2"></i>New Item Request</a></li>
        <li><a href="production_report.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-bar mr-2"></i>Reports</a></li>
      </ul>
    </div>
  </nav>

  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">Logout</a>
  </div>
</div>
