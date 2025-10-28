<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
        <li><a href="add_employee.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-user-plus mr-2"></i>Add Employee</a></li>
        <li><a href="view_employees.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-users mr-2"></i>View Employees</a></li>
        <li><a href="employee_information.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-id-card mr-2"></i>Employees Information</a></li>
        <li><a href="create_staff_account.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-calendar-alt mr-2"></i>Create Staff Account</a></li>
        <li><a href="perfomance_evaluation_list.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-star mr-2"></i>Performance Evaluation</a></li>
        <li><a href="leaves_request.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-calendar-check mr-2"></i>Leaves request</a></li>
<li><a href="view_training_requests.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-invoice-dollar mr-2"></i>Training requests</a></li>
        <li><a href="manage_expenses.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-money-bill mr-2"></i>Add Expense</a></li>
        <li><a href="add_lunch_expense.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-utensils mr-2"></i>Lunch Expense</a></li>
        <li><a href="breakfast_expense.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-mug-hot mr-2"></i>Breakfast Expense</a></li>
        <li><a href="record_training.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chalkboard-user mr-2"></i>Record Training</a></li>
       
        <li><a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-plus mr-2"></i>New Item Request</a></li>
        <li><a href="hr_report.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-lines mr-2"></i>HR Report</a></li>
      </ul>
    </div>
  </nav>

  <!-- Logout -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">Logout</a>
  </div>
</div>
