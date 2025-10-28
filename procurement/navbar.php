<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><!-- superadmin/navbar_procurement.php -->
<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      P
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Procurement Department</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <nav class="space-y-6 flex-1">
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Procurement</h3>
         <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_procurement_product.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-cart-plus mr-2"></i>Add Product</a></li>
        <li><a href="supplier_list.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-truck-field mr-2"></i>Supplier List</a></li>
<li><a href="purchases_list.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-list-xmark mr-2"></i>All Purchases</a></li>
        <li><a href="purchase_order.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-signature mr-2"></i>New Purchase</a></li>
        <li><a href="approved_purchases.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-list-check mr-2"></i>Purchase List</a></li>
        <li><a href="department_requests.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-clipboard-list mr-2"></i>Department Requests</a></li>
        <li><a href="delivered_purchases.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-truck-ramp-box mr-2"></i>Delivery Of Purchases</a></li>
        <li><a href="procurement_reports.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-line mr-2"></i>Procurement Reports</a></li>
      </ul>
</div>
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">Logout</a>
  </div>
</div>
