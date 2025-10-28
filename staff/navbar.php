<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- staff/navbar_staff.php -->
<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <!-- Profile Section -->
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      S
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Staff Department</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <!-- Navigation Menu -->
  <nav class="space-y-6 flex-1">
<div>
      <h3 class="text-blue-700 font-semibold uppercase">Main</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="dashboard.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-line mr-2"></i>Dashboard</a></li>
      </ul>
    </div>
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Staff</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li>
          <a href="manage_leaves.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-calendar-days mr-2 text-blue-600"></i>Manage Leaves
          </a>
        </li>
        <li>
          <a href="training_request.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-graduation-cap mr-2 text-blue-600"></i>Request Trainings
          </a>
        </li>
        <li>
          <a href="add_department_request.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-plus-circle mr-2 text-blue-600"></i>New Item Request
          </a>
        </li>
        <li>
          <a href="my_payrolls.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-money-check-dollar mr-2 text-blue-600"></i>My Payrolls
          </a>
        </li>
        <li>
          <a href="view_performance.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-chart-line mr-2 text-blue-600"></i>Performance
          </a>
        </li>
        <li>
          <a href="staff_reports.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-file-alt mr-2 text-blue-600"></i>Reports
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Logout -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">
      <i class="fa-solid fa-right-from-bracket mr-2"></i>Logout
    </a>
  </div>
</div>
