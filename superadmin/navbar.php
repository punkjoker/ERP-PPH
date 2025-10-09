<!-- superadmin/navbar.php -->
<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <!-- Profile Section -->
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Super Admin</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <!-- Nav Links -->
  <nav class="space-y-6 flex-1">

    <!-- Dashboard -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Main</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="dashboard.php" class="block hover:bg-blue-200 p-2 rounded">Dashboard</a></li>
      </ul>
    </div>

    <!-- HR -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">HR</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_employee.php" class="block hover:bg-blue-200 p-2 rounded">Add Employee</a></li>
        <li><a href="view_employees.php" class="block hover:bg-blue-200 p-2 rounded">View Employees</a></li>
        <li><a href="manage_leaves.php" class="block hover:bg-blue-200 p-2 rounded">Manage Leaves</a></li>
        <li><a href="manage_expenses.php" class="block hover:bg-blue-200 p-2 rounded">Add Expense</a></li>
        <li><a href="add_lunch_expense.php" class="block hover:bg-blue-200 p-2 rounded">Lunch Expense</a></li>
        <li><a href="breakfast_expense.php" class="block hover:bg-blue-200 p-2 rounded">Breakfast Expense</a></li>
        <li><a href="record_training.php" class="block hover:bg-blue-200 p-2 rounded">Record Training</a></li>
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded">New Item Request</a></li>
        <li><a href="hr_report.php" class="block hover:bg-blue-200 p-2 rounded">HR Report</a></li>
      </ul>
    </div>

    <!-- Production -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Production</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_product.php" class="block hover:bg-blue-200 p-2 rounded">Add Product</a></li>
        <li><a href="manage_bom.php" class="block hover:bg-blue-200 p-2 rounded">Manage BOM</a></li>
        <li><a href="record_production_run.php" class="block hover:bg-blue-200 p-2 rounded">Record Production Run</a></li>
        <li><a href="view_finished_products.php" class="block hover:bg-blue-200 p-2 rounded">View Finished Products</a></li>
        <li><a href="view_inventory.php" class="block hover:bg-blue-200 p-2 rounded">View Inventory</a></li>
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded">New Item Request</a></li>
        <li><a href="production_report.php" class="block hover:bg-blue-200 p-2 rounded">Reports</a></li>
      </ul>
    </div>

    <!-- Quality Control -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Quality Control</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="inspect_raw_materials.php" class="block hover:bg-blue-200 p-2 rounded">Inspect Chemicals In</a></li>
        <li><a href="inspect_finished_products.php" class="block hover:bg-blue-200 p-2 rounded">Inspect Finished Products</a></li>
        <li><a href="record_production_run.php" class="block hover:bg-blue-200 p-2 rounded">View Production Runs</a></li>
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded">New Item Request</a></li>
        <li><a href="disposables.php" class="block hover:bg-blue-200 p-2 rounded">Disposals</a></li>
        <li><a href="qc_reports.php" class="block hover:bg-blue-200 p-2 rounded">QC Reports</a></li>
      </ul>
    </div>

    <!-- Stores -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Stores</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_material.php" class="block hover:bg-blue-200 p-2 rounded">Add Raw Material</a></li>
        <li><a href="remove_material.php" class="block hover:bg-blue-200 p-2 rounded">Remove Raw Material</a></li>
        <li><a href="chemicals_in.php" class="block hover:bg-blue-200 p-2 rounded">Chemicals In</a></li>
        <li><a href="stock_in.php" class="block hover:bg-blue-200 p-2 rounded">Stock In</a></li>
        <li><a href="qc_approval.php" class="block hover:bg-blue-200 p-2 rounded">QC Approval</a></li>
        <li><a href="stock_out.php" class="block hover:bg-blue-200 p-2 rounded">Stock Out</a></li>
        <li><a href="production_requests.php" class="block hover:bg-blue-200 p-2 rounded">Production Requests</a></li>
        <li><a href="view_inventory.php" class="block hover:bg-blue-200 p-2 rounded">View Inventory</a></li>
         <li><a href="order_deliveries.php" class="block hover:bg-blue-200 p-2 rounded">Create Delivery</a></li>
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded">New Item Request</a></li>
        <li><a href="store_reports.php" class="block hover:bg-blue-200 p-2 rounded">Reports</a></li>
      </ul>
    </div>

    <!-- Procurement -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Procurement</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_procurement_product.php" class="block hover:bg-blue-200 p-2 rounded">Add Product</a></li>
        <li><a href="supplier_list.php" class="block hover:bg-blue-200 p-2 rounded">Supplier List</a></li>
        <li><a href="purchase_order.php" class="block hover:bg-blue-200 p-2 rounded">New Purchase</a></li>
        <li><a href="approved_purchases.php" class="block hover:bg-blue-200 p-2 rounded">Purchase List</a></li>
       <li><a href="department_requests.php" class="block hover:bg-blue-200 p-2 rounded">Department Requests</a></li>
        <li><a href="delivered_purchases.php" class="block hover:bg-blue-200 p-2 rounded">Delivery Of Purchases</a></li>
        <li><a href="procurement_reports.php" class="block hover:bg-blue-200 p-2 rounded">Procurement reports</a></li>
      </ul>
    </div>

    <!-- Sales -->
    <!--
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Sales</h3>
      <ul class="ml-4 space-y-2 text-sm">
      <li><a href="create_invoice.php" class="block hover:bg-blue-200 p-2 rounded">Create New Invoice</a></li>
      <li><a href="sales_products.php" class="block hover:bg-blue-200 p-2 rounded">Add Products & Prices</a></li>
      <li><a href="view_sales.php" class="block hover:bg-blue-200 p-2 rounded">View Sales</a></li>
      <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded">New Item Request</a></li>
      </ul>
    </div
    -->

    <!-- Drivers -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Drivers</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_vehicle.php" class="block hover:bg-blue-200 p-2 rounded">Manage vehicles</a></li>
         <li><a href="pending_deliveries.php" class="block hover:bg-blue-200 p-2 rounded">Pending Deliveries</a></li>
        <li><a href="delivered_orders.php" class="block hover:bg-blue-200 p-2 rounded">Delivered</a></li>
        <li><a href="manage_trips.php" class="block hover:bg-blue-200 p-2 rounded">Manage Trips</a></li>
        <li><a href="vehicle_maintenance.php" class="block hover:bg-blue-200 p-2 rounded">Vehicle Maintenance Costs</a></li>
        <li><a href="fuel.php" class="block hover:bg-blue-200 p-2 rounded">Fuel Section</a></li>
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded">New Item Request</a></li>
        <li><a href="vehicle_reports.php" class="block hover:bg-blue-200 p-2 rounded">Vehicles Report</a></li>
      </ul>
    </div>

    <!-- Reports -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Reports</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="all_reports.php" class="block hover:bg-blue-200 p-2 rounded">Generate Reports</a></li>
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
