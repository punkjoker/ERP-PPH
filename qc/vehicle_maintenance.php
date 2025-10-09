<?php
include 'db_con.php';

// --- Handle new maintenance record submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_record'])) {
  $driver_name = $_POST['driver_name'];
  $vehicle_number = $_POST['vehicle_number'];
  $maintenance_name = $_POST['maintenance_name'];
  $maintenance_company = $_POST['maintenance_company'];
  $maintenance_cost = $_POST['maintenance_cost'];
  $approved_by = $_POST['approved_by'];

  // Handle receipt upload
  $receipt_path = '';
  if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
    $target_dir = "uploads/receipts/";
    if (!is_dir($target_dir)) {
      mkdir($target_dir, 0777, true);
    }
    $filename = basename($_FILES["receipt"]["name"]);
    $target_file = $target_dir . time() . "_" . $filename;
    move_uploaded_file($_FILES["receipt"]["tmp_name"], $target_file);
    $receipt_path = $target_file;
  }

  $query = "INSERT INTO vehicle_maintenance (driver_name, vehicle_number, maintenance_name, maintenance_company, maintenance_cost, approved_by, receipt_path)
            VALUES ('$driver_name', '$vehicle_number', '$maintenance_name', '$maintenance_company', '$maintenance_cost', '$approved_by', '$receipt_path')";
  mysqli_query($conn, $query);
}

// --- Handle deletion ---
if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  mysqli_query($conn, "DELETE FROM vehicle_maintenance WHERE id=$id");
  header("Location: vehicle_maintenance.php");
  exit;
}

// --- Handle update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_record'])) {
  $id = $_POST['record_id'];
  // Optional if you want expiry edit later
  $maintenance_name = $_POST['maintenance_name'];
  $maintenance_company = $_POST['maintenance_company'];
  $maintenance_cost = $_POST['maintenance_cost'];
  $approved_by = $_POST['approved_by'];

  mysqli_query($conn, "UPDATE vehicle_maintenance 
                       SET maintenance_name='$maintenance_name', maintenance_company='$maintenance_company', 
                           maintenance_cost='$maintenance_cost', approved_by='$approved_by'
                       WHERE id=$id");
}

// --- Handle Filters ---
$where = "1=1";
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
  $from = $_GET['from_date'];
  $to = $_GET['to_date'];
  $where .= " AND DATE(created_at) BETWEEN '$from' AND '$to'";
}

if (!empty($_GET['vehicle_number'])) {
  $vehicle_number = $_GET['vehicle_number'];
  $where .= " AND vehicle_number='$vehicle_number'";
}

$records = mysqli_query($conn, "SELECT * FROM vehicle_maintenance WHERE $where ORDER BY id DESC");
$total_cost = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vehicle Maintenance Costs</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function printSection() {
      const printContent = document.getElementById('print-area').innerHTML;
      const win = window.open('', '', 'height=800,width=1000');
      win.document.write('<html><head><title>Maintenance Report</title>');
      win.document.write('<link rel="stylesheet" href="https://cdn.tailwindcss.com">');
      win.document.write('</head><body>');
      win.document.write(printContent);
      win.document.write('</body></html>');
      win.document.close();
      win.print();
    }

    function openEdit(id, name, company, cost, approved) {
      document.getElementById('editModal').classList.remove('hidden');
      document.getElementById('record_id').value = id;
      document.getElementById('edit_maintenance_name').value = name;
      document.getElementById('edit_maintenance_company').value = company;
      document.getElementById('edit_maintenance_cost').value = cost;
      document.getElementById('edit_approved_by').value = approved;
    }

    function closeModal() {
      document.getElementById('editModal').classList.add('hidden');
    }
  </script>
</head>

<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">
  <?php include 'navbar.php'; ?>

  <div class="ml-72 p-10 transition-all duration-300">
    <h1 class="text-3xl font-bold text-blue-800 mb-8">Vehicle Maintenance Costs</h1>

    <!-- Add Maintenance Form -->
    <div class="bg-white/70 p-6 rounded-2xl shadow-lg border border-blue-200 mb-10 w-full max-w-3xl">
      <h2 class="text-xl font-semibold text-blue-700 mb-4">Add New Maintenance Record</h2>
      <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="save_record" value="1">

        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Driver Name</label>
          <input type="text" name="driver_name" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Vehicle Number</label>
          <input type="text" name="vehicle_number" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Maintenance Name</label>
          <input type="text" name="maintenance_name" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Maintenance Company/Person</label>
          <input type="text" name="maintenance_company" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Cost (KSh)</label>
          <input type="number" name="maintenance_cost" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Approved By</label>
          <input type="text" name="approved_by" required class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-blue-800 mb-1">Attach Receipt</label>
          <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="w-full p-2 border rounded-lg bg-white">
        </div>

        <div class="md:col-span-2 text-right">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">Save Record</button>
        </div>
      </form>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white/70 p-4 rounded-xl shadow border border-blue-200 mb-6 flex flex-wrap gap-4 items-end">
      <div>
        <label class="block text-sm text-blue-800 mb-1">From Date</label>
        <input type="date" name="from_date" class="border rounded p-2">
      </div>
      <div>
        <label class="block text-sm text-blue-800 mb-1">To Date</label>
        <input type="date" name="to_date" class="border rounded p-2">
      </div>
      <div>
        <label class="block text-sm text-blue-800 mb-1">Vehicle</label>
        <input type="text" name="vehicle_number" placeholder="e.g., KDD 123A" class="border rounded p-2">
      </div>
      <button class="bg-blue-500 hover:bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow">Filter</button>
      <button type="button" onclick="printSection()" class="bg-green-500 hover:bg-green-600 text-white font-semibold px-4 py-2 rounded-lg shadow">Print</button>
    </form>

    <!-- Maintenance List -->
    <div id="print-area" class="bg-white/80 p-6 rounded-2xl shadow-lg border border-blue-200">
      <h2 class="text-xl font-semibold text-blue-700 mb-4">Maintenance Records</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left border border-blue-200">
          <thead class="bg-blue-100 text-blue-900 uppercase text-xs">
            <tr>
              <th class="px-4 py-2 border">#</th>
              <th class="px-4 py-2 border">Driver</th>
              <th class="px-4 py-2 border">Vehicle</th>
              <th class="px-4 py-2 border">Maintenance</th>
              <th class="px-4 py-2 border">Company/Person</th>
              <th class="px-4 py-2 border">Cost</th>
              <th class="px-4 py-2 border">Approved By</th>
              <th class="px-4 py-2 border">Receipt</th>
              <th class="px-4 py-2 border">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $count = 1;
            while ($row = mysqli_fetch_assoc($records)) {
              $total_cost += $row['maintenance_cost'];
              $receipt_link = $row['receipt_path'] ? "<a href='{$row['receipt_path']}' target='_blank' class='text-blue-600 hover:underline'>View</a>" : "N/A";
              echo "
              <tr class='hover:bg-blue-50 border-b'>
                <td class='px-4 py-2 border'>$count</td>
                <td class='px-4 py-2 border'>{$row['driver_name']}</td>
                <td class='px-4 py-2 border'>{$row['vehicle_number']}</td>
                <td class='px-4 py-2 border'>{$row['maintenance_name']}</td>
                <td class='px-4 py-2 border'>{$row['maintenance_company']}</td>
                <td class='px-4 py-2 border font-medium text-green-700'>KSh {$row['maintenance_cost']}</td>
                <td class='px-4 py-2 border'>{$row['approved_by']}</td>
                <td class='px-4 py-2 border'>$receipt_link</td>
                <td class='px-4 py-2 border'>
                  <button onclick=\"openEdit('{$row['id']}', '{$row['maintenance_name']}', '{$row['maintenance_company']}', '{$row['maintenance_cost']}', '{$row['approved_by']}')\" class='text-blue-600 hover:underline'>Edit</button> |
                  <a href='?delete={$row['id']}' onclick='return confirm(\"Delete this record?\")' class='text-red-600 hover:underline'>Delete</a>
                </td>
              </tr>";
              $count++;
            }
            ?>
          </tbody>
          <tfoot class="bg-blue-50 font-semibold">
            <tr>
              <td colspan="5" class="text-right px-4 py-2 border">Total Cost:</td>
              <td class="px-4 py-2 border text-green-700">KSh <?= number_format($total_cost, 2) ?></td>
              <td colspan="3" class="border"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md">
      <h2 class="text-xl font-bold text-blue-700 mb-4">Edit Maintenance Record</h2>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="update_record" value="1">
        <input type="hidden" id="record_id" name="record_id">

        <div>
          <label class="block text-sm text-blue-800 mb-1">Maintenance Name</label>
          <input type="text" id="edit_maintenance_name" name="maintenance_name" class="w-full p-2 border rounded-lg">
        </div>

        <div>
          <label class="block text-sm text-blue-800 mb-1">Maintenance Company/Person</label>
          <input type="text" id="edit_maintenance_company" name="maintenance_company" class="w-full p-2 border rounded-lg">
        </div>

        <div>
          <label class="block text-sm text-blue-800 mb-1">Cost (KSh)</label>
          <input type="number" id="edit_maintenance_cost" name="maintenance_cost" class="w-full p-2 border rounded-lg">
        </div>

        <div>
          <label class="block text-sm text-blue-800 mb-1">Approved By</label>
          <input type="text" id="edit_approved_by" name="approved_by" class="w-full p-2 border rounded-lg">
        </div>

        <div class="text-right">
          <button type="button" onclick="closeModal()" class="bg-gray-400 text-white px-4 py-2 rounded-lg mr-2">Cancel</button>
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">Update</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
