<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <!-- Profile Section -->
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      <i class="fa-solid fa-user-tie"></i>
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Accounts</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">
        <i class="fa-solid fa-pen-to-square mr-1"></i>Edit Profile
      </a>
    </div>
  </div>

  <!-- Accounts Navigation -->
  <nav class="space-y-6 flex-1">
<div>
      <h3 class="text-blue-700 font-semibold uppercase">Main</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="dashboard.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-line mr-2"></i>Dashboard</a></li>
      </ul>
    </div>
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Accounts</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li>
          <a href="approved_purchases.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-list-check mr-2"></i>Purchase List
          </a>
        </li>
 <li>
          <a href="purchases_list.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-list-check mr-2"></i>All purchases
          </a>
        </li>
        <li>
          <a href="manage_expenses.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-money-bill mr-2"></i>Add Expense
          </a>
        </li>
        <li>
          <a href="add_lunch_expense.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-utensils mr-2"></i>Lunch Expense
          </a>
        </li>
        <li>
          <a href="breakfast_expense.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-mug-hot mr-2"></i>Breakfast Expense
          </a>
        </li>
        <li>
          <a href="payroll_details.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-file-invoice-dollar mr-2"></i>Payroll Details
          </a>
        </li>
        <li>
          <a href="payroll_deductions.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-minus mr-2"></i>Payroll Deductions
          </a>
        </li>
        <li>
          <a href="payroll_list.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-wallet mr-2"></i>Process Payroll
          </a>
        </li>
        <li>
          <a href="accounts_reports.php" class="block hover:bg-blue-200 p-2 rounded">
            <i class="fa-solid fa-chart-line mr-2"></i>Accounts Reports
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Logout Button -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">
      <i class="fa-solid fa-right-from-bracket mr-2"></i>Logout
    </a>
  </div>
</div>
