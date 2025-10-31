<!-- superadmin/navbar.php -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <!-- Profile Section -->
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      <i class="fa-solid fa-user-shield"></i>
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Super Admin</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline"><i class="fa-solid fa-pen-to-square mr-1"></i>Edit Profile</a>
    </div>
  </div>

  <!-- Nav Links -->
  <nav class="space-y-6 flex-1">

    <!-- Dashboard -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Main</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="dashboard.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-line mr-2"></i>Dashboard</a></li>
      </ul>
    </div>

    <!-- HR -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">HR</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_employee.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-user-plus mr-2"></i>Add Employee</a></li>
        <li><a href="view_employees.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-users mr-2"></i>View Employees</a></li>
        <li><a href="employee_information.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-id-card mr-2"></i>Employees Information</a></li>
        <li><a href="create_staff_account.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-calendar-alt mr-2"></i>Create Staff Account</a></li>
        
        <li><a href="leaves_request.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-calendar-check mr-2"></i>Leaves request</a></li>
        <li><a href="view_training_requests.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-invoice-dollar mr-2"></i>Training requests</a></li>
        <li><a href="manage_expenses.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-money-bill mr-2"></i>Add Expense</a></li>
        <li><a href="add_lunch_expense.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-utensils mr-2"></i>Lunch Expense</a></li>
        <li><a href="breakfast_expense.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-mug-hot mr-2"></i>Breakfast Expense</a></li>
        <li><a href="record_training.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chalkboard-user mr-2"></i>Record Training</a></li>
        <li><a href="all_daily_reports.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chalkboard-teacher mr-2"></i>Daily Reports</a></li>
        <li><a href="packaging_request.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-box mr-2"></i>Packaging Request</a></li>
        <li><a href="view_all_evaluations.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>View All Evaluations</a></li>
        <li><a href="employee_appraisal.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>Employee Appraisal</a></li>
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-plus mr-2"></i>New Item Request</a></li>
        <li><a href="hr_report.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-lines mr-2"></i>HR Report</a></li>
      </ul>
    </div>

    <!-- Production -->
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

    <!-- Quality Control -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Quality Control</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="inspect_raw_materials.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-vial mr-2"></i>Inspect Chemicals In</a></li>
        <li><a href="inspect_finished_products.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-clipboard-check mr-2"></i>Inspect Finished Products</a></li>
        <li><a href="quality_manager_review.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-user-tie mr-2"></i>Quality Manager Review</a></li>
        <li><a href="record_production_run.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-cogs mr-2"></i>View Production Runs</a></li>
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-plus mr-2"></i>New Item Request</a></li>
        <li><a href="disposables.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-trash-can mr-2"></i>Disposals</a></li>
        <li><a href="qc_reports.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-contract mr-2"></i>QC Reports</a></li>
      </ul>
    </div>

    <!-- Stores -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Stores</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_material.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-box-open mr-2"></i>Add Raw Material</a></li>
        <li><a href="remove_material.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-minus-circle mr-2"></i>Remove Raw Material</a></li>
        <li><a href="chemicals_in.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-flask mr-2"></i>Chemicals In</a></li>
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

    <!-- Procurement -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Procurement</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="add_procurement_product.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-cart-plus mr-2"></i>Add Product</a></li>
        <li><a href="supplier_list.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-truck-field mr-2"></i>Supplier List</a></li>
        <li><a href="purchase_order.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-signature mr-2"></i>New Purchase</a></li>
        <li><a href="approved_purchases.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-list-check mr-2"></i>Purchase List</a></li>
        <li><a href="department_requests.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-clipboard-list mr-2"></i>Department Requests</a></li>
        <li><a href="delivered_purchases.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-truck-ramp-box mr-2"></i>Delivery Of Purchases</a></li>
        <li><a href="procurement_reports.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-line mr-2"></i>Procurement Reports</a></li>
      </ul>
    </div>

    <!-- Drivers -->
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

      <div>
      <h3 class="text-blue-700 font-semibold uppercase">Accounts</h3>
      <ul class="ml-4 space-y-2 text-sm">
         <li><a href="approved_purchases.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-list-check mr-2"></i>Purchase List</a></li>
         <li><a href="purchases_list.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-list-xmark mr-2"></i>All Purchases</a></li>
         <li><a href="manage_expenses.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-money-bill mr-2"></i>Add Expense</a></li>
        <li><a href="add_lunch_expense.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-utensils mr-2"></i>Lunch Expense</a></li>
        <li><a href="breakfast_expense.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-mug-hot mr-2"></i>Breakfast Expense</a></li>
        <li><a href="payroll_details.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-invoice-dollar mr-2"></i>Payroll details</a></li>
        <li><a href="payroll_deductions.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-minus mr-2"></i>Payroll Deductions</a></li>
        <li><a href="Payroll_list.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-wallet mr-2"></i>Process Payroll</a></li>
        <li><a href="all_deductions.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-pie mr-2"></i>Deduction Reports</a></li>
      </ul>
    </div>
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Store B</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="store_b_chemicals_in.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>Store B chemicals In</a></li>
        <li><a href="store_b_chemicals_inventory.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-vials mr-2"></i>Store B Chemical Inventory</a></li>
        <li><a href="store_b_engineering_products_in.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>Store B Engineering Products In</a></li>
        <li><a href="store_b_engineering_products_inventory.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-boxes mr-2"></i>Store B Engineering Products Inventory</a></li>
        <li><a href="store_b_finished_products_in.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>Store B Finished Products In</a></li>
        <li><a href="store_b_finished_products_inventory.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-boxes mr-2"></i>Store B Finished Products Inventory</a></li>
        <li><a href="create_store_b_order.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>Store B create Order</a></li>
        <li><a href="all_orders_store_b.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-clipboard-list mr-2"></i>Store B All Orders</a></li>
        <li><a href="store_b_order_items.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-box mr-2"></i>Store B View Order Items</a></li>
        <li><a href="store_b_order_deliveries.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-truck mr-2"></i>Store B Order Deliveries</a></li>
        <li><a href="store_b_delivery_details.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-info-circle mr-2"></i>Store B Delivery Details</a></li>
        <li><a href="store_b_reports.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-pie mr-2"></i>Store B Reports</a></li>

      </ul>
    </div>
    <!-- Reports -->
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Reports</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="all_reports.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>Generate Reports</a></li>
      </ul>
    </div>

  </nav>

  <!-- Logout Button -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">
      <i class="fa-solid fa-right-from-bracket mr-2"></i>Logout
    </a>
  </div>
  <script>
  document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector(".overflow-y-auto");

    // Restore previous scroll position
    const savedScroll = localStorage.getItem("sidebar-scroll");
    if (savedScroll) {
      sidebar.scrollTop = parseInt(savedScroll, 10);
    }

    // Save scroll position whenever user scrolls
    sidebar.addEventListener("scroll", function () {
      localStorage.setItem("sidebar-scroll", sidebar.scrollTop);
    });
  });
</script>

</div>
