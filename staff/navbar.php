<!-- Staff Department Navbar with Icons -->
<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <!-- Profile Section -->
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      S
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Staff</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="space-y-6 flex-1">
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Staff</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li>
          <a href="manage_leaves.php" class="flex items-center hover:bg-blue-200 p-2 rounded">
            <!-- Calendar Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Manage Leaves
          </a>
        </li>
        <li>
          <a href="training_request.php" class="flex items-center hover:bg-blue-200 p-2 rounded">
            <!-- Academic Cap Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.84 4.282V21l-7-3-7 3v-6.14a12.083 12.083 0 01.84-4.282L12 14z" />
            </svg>
            Request Trainings
          </a>
        </li>
        <li>
          <a href="add_department_request.php" class="flex items-center hover:bg-blue-200 p-2 rounded">
            <!-- Plus Circle Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Item Request
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Logout -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded flex items-center justify-center">
      <!-- Logout Icon -->
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
      </svg>
      Logout
    </a>
  </div>
</div>
