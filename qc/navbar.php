<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><!-- Quality Control Department Navbar -->
<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      QC
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Quality Control Department</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline">Edit Profile</a>
    </div>
  </div>

  <nav class="space-y-6 flex-1">
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
  </nav>

  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">Logout</a>
  </div>
</div>
