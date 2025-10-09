<!-- superadmin/navbar_reports.php -->
<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      R
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Reports</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <nav class="space-y-6 flex-1">
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Reports</h3>
      <ul class="ml-4 mt-2 space-y-2 text-sm">
        <li><a href="all_reports.php" class="block hover:bg-blue-200 p-2 rounded">Generate Reports</a></li>
        <li><a href="procurement_reports.php" class="block hover:bg-blue-200 p-2 rounded">Procurement Reports</a></li>
        <li><a href="production_report.php" class="block hover:bg-blue-200 p-2 rounded">Production Reports</a></li>
        <li><a href="qc_reports.php" class="block hover:bg-blue-200 p-2 rounded">QC Reports</a></li>
        <li><a href="store_reports.php" class="block hover:bg-blue-200 p-2 rounded">Store Reports</a></li>
        <li><a href="vehicle_reports.php" class="block hover:bg-blue-200 p-2 rounded">Vehicle Reports</a></li>
        <li><a href="hr_report.php" class="block hover:bg-blue-200 p-2 rounded">HR Reports</a></li>
      </ul>
    </div>
  </nav>

  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">Logout</a>
  </div>
</div>
