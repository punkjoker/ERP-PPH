<?php
include 'db_con.php';

// Handle new vehicle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_vehicle'])) {
  $driver_name = $_POST['driver_name'];
  $vehicle_number = $_POST['vehicle_number'];
  $model = $_POST['model'];
  $license_expiry = $_POST['license_expiry'];
  $vehicle_name = $_POST['vehicle_name'];

  $query = "INSERT INTO vehicles (driver_name, vehicle_number, model, license_expiry, vehicle_name)
            VALUES ('$driver_name', '$vehicle_number', '$model', '$license_expiry', '$vehicle_name')";
  mysqli_query($conn, $query);
}

// Handle edit request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_expiry'])) {
  $id = $_POST['id'];
  $new_expiry = $_POST['new_expiry'];
  $update = "UPDATE vehicles SET license_expiry='$new_expiry' WHERE id='$id'";
  mysqli_query($conn, $update);
}

// Fetch all vehicles
$vehicles = mysqli_query($conn, "SELECT * FROM vehicles ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Vehicles</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">

  <!-- Include Sidebar -->
  <?php include 'navbar.php'; ?>

  <!-- Main Content -->
  <div class="ml-72 p-10 transition-all duration-300">
    <h1 class="text-3xl font-bold text-blue-800 mb-8">Manage Vehicles</h1>

    <!-- Add Vehicle Form -->
    <div class="bg-white/70 backdrop-blur-md p-6 rounded-2xl shadow-lg border border-blue-200 mb-10 w-full max-w-3xl">
      <h2 class="text-xl font-semibold text-blue-700 mb-4">Add New Vehicle</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Driver Name</label>
          <input type="text" name="driver_name" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Vehicle Number Plate</label>
          <input type="text" name="vehicle_number" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Model</label>
          <input type="text" name="model" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Insurance Expiration Date</label>
          <input type="date" name="license_expiry" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-blue-800 mb-1">Vehicle Name</label>
          <input type="text" name="vehicle_name" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div class="md:col-span-2 text-right">
          <button type="submit" name="add_vehicle" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">
            Add Vehicle
          </button>
        </div>
      </form>
    </div>

    <!-- Vehicle List -->
    <div class="bg-white/80 backdrop-blur-md p-6 rounded-2xl shadow-lg border border-blue-200">
      <h2 class="text-xl font-semibold text-blue-700 mb-4">Vehicle List</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left border border-blue-200">
          <thead class="bg-blue-100 text-blue-900 uppercase text-xs">
            <tr>
              <th class="px-4 py-2 border">#</th>
              <th class="px-4 py-2 border">Driver Name</th>
              <th class="px-4 py-2 border">Vehicle Number</th>
              <th class="px-4 py-2 border">Model</th>
              <th class="px-4 py-2 border">Insurance Expiry</th>
              <th class="px-4 py-2 border">Vehicle Name</th>
              <th class="px-4 py-2 border">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $count = 1;
            $today = date('Y-m-d');
            while ($row = mysqli_fetch_assoc($vehicles)) {
              $expired = ($row['license_expiry'] < $today);
              echo "
                <tr class='hover:bg-blue-50 border-b'>
                  <td class='px-4 py-2 border'>$count</td>
                  <td class='px-4 py-2 border'>{$row['driver_name']}</td>
                  <td class='px-4 py-2 border'>{$row['vehicle_number']}</td>
                  <td class='px-4 py-2 border'>{$row['model']}</td>
                  <td class='px-4 py-2 border " . ($expired ? "text-red-600 font-semibold" : "text-green-700") . "'>{$row['license_expiry']}</td>
                  <td class='px-4 py-2 border'>{$row['vehicle_name']}</td>
                  <td class='px-4 py-2 border text-center'>
                    <button 
                      class='bg-yellow-500 hover:bg-yellow-600 text-white text-xs px-3 py-1 rounded' 
                      onclick=\"openEditModal({$row['id']}, '{$row['license_expiry']}')\">
                      Edit
                    </button>
                  </td>
                </tr>
              ";
              $count++;
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
    <div class="bg-white p-6 rounded-2xl shadow-xl w-96">
      <h2 class="text-lg font-semibold text-blue-700 mb-4">Update Insurance Expiry</h2>
      <form method="POST">
        <input type="hidden" name="id" id="editId">
        <label class="block text-sm font-medium text-blue-800 mb-1">New Expiry Date</label>
        <input type="date" name="new_expiry" id="editExpiry" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400 mb-4">
        <div class="text-right">
          <button type="button" onclick="closeModal()" class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded mr-2">Cancel</button>
          <button type="submit" name="update_expiry" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openEditModal(id, expiry) {
      document.getElementById('editId').value = id;
      document.getElementById('editExpiry').value = expiry;
      document.getElementById('editModal').classList.remove('hidden');
      document.getElementById('editModal').classList.add('flex');
    }

    function closeModal() {
      document.getElementById('editModal').classList.add('hidden');
      document.getElementById('editModal').classList.remove('flex');
    }
  </script>

</body>
</html>
