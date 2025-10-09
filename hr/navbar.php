<!-- HR Department Navbar -->
<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <!-- Profile Section -->
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      HR
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">HR Department</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="space-y-6 flex-1">
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
  </nav>

  <!-- Logout -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">Logout</a>
  </div>
</div>
