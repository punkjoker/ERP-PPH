<?php
include 'db_con.php';

// --- Handle new record submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_fuel'])) {
  $vehicle_number = $_POST['vehicle_number'];
  $amount_refueled = $_POST['amount_refueled'];
  $refueled_by = $_POST['refueled_by'];
  $approved_by = $_POST['approved_by'];

  // Handle receipt upload
  $receipt_path = '';
  if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
    $target_dir = "uploads/fuel_receipts/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $filename = time() . "_" . basename($_FILES["receipt"]["name"]);
    $target_file = $target_dir . $filename;
    move_uploaded_file($_FILES["receipt"]["tmp_name"], $target_file);
    $receipt_path = $target_file;
  }

  mysqli_query($conn, "INSERT INTO fuel_cost (vehicle_number, amount_refueled, refueled_by, approved_by, receipt_path)
                       VALUES ('$vehicle_number', '$amount_refueled', '$refueled_by', '$approved_by', '$receipt_path')");
}

// --- Handle delete ---
if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  mysqli_query($conn, "DELETE FROM fuel_cost WHERE id = $id");
  header("Location: fuel.php");
  exit;
}

// --- Handle update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_fuel'])) {
  $id = $_POST['id'];
  $vehicle_number = $_POST['vehicle_number'];
  $amount_refueled = $_POST['amount_refueled'];
  $refueled_by = $_POST['refueled_by'];
  $approved_by = $_POST['approved_by'];

  mysqli_query($conn, "UPDATE fuel_cost SET 
                        vehicle_number='$vehicle_number',
                        amount_refueled='$amount_refueled',
                        refueled_by='$refueled_by',
                        approved_by='$approved_by'
                        WHERE id=$id");
}

// --- Filter Section ---
$filter_query = "SELECT * FROM fuel_cost WHERE 1";
if (!empty($_GET['vehicle_number'])) {
  $vehicle_filter = $_GET['vehicle_number'];
  $filter_query .= " AND vehicle_number = '$vehicle_filter'";
}
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
  $from_date = $_GET['from_date'];
  $to_date = $_GET['to_date'];
  $filter_query .= " AND DATE(created_at) BETWEEN '$from_date' AND '$to_date'";
}
$filter_query .= " ORDER BY id DESC";
$records = mysqli_query($conn, $filter_query);

// Fetch vehicles for dropdown
$vehicles = mysqli_query($conn, "SELECT vehicle_number FROM vehicles");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Fuel Records</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- jsPDF + html2canvas for PDF download -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

  <style>
    @media print {
      body * {
        visibility: hidden;
      }
      #reportSection, #reportSection * {
        visibility: visible;
      }
      #reportSection {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        background: white;
        color: black;
        box-shadow: none;
      }
      table {
        border-collapse: collapse;
        width: 100%;
      }
      th, td {
        border: 1px solid #333 !important;
      }
    }
  </style>

  <script>
    function openEditModal(id, vehicle, amount, refueled_by, approved_by) {
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_vehicle').value = vehicle;
      document.getElementById('edit_amount').value = amount;
      document.getElementById('edit_refueled_by').value = refueled_by;
      document.getElementById('edit_approved_by').value = approved_by;
      document.getElementById('editModal').classList.remove('hidden');
    }
    function closeModal() {
      document.getElementById('editModal').classList.add('hidden');
    }
    function printPage() {
      window.print();
    }
    async function downloadPDF() {
      const { jsPDF } = window.jspdf;
      const reportSection = document.getElementById('reportSection');
      const canvas = await html2canvas(reportSection, { scale: 2 });
      const imgData = canvas.toDataURL('image/png');
      const pdf = new jsPDF('p', 'pt', 'a4');
      const imgWidth = 560;
      const pageHeight = 842;
      const imgHeight = canvas.height * imgWidth / canvas.width;
      let heightLeft = imgHeight;
      let position = 0;

      pdf.addImage(imgData, 'PNG', 20, position + 20, imgWidth, imgHeight);
      heightLeft -= pageHeight;

      while (heightLeft >= 0) {
        position = heightLeft - imgHeight;
        pdf.addPage();
        pdf.addImage(imgData, 'PNG', 20, position + 20, imgWidth, imgHeight);
        heightLeft -= pageHeight;
      }

      pdf.save("Fuel_Records_Report.pdf");
    }
  </script>
</head>

<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">
  <?php include 'navbar.php'; ?>

  <div class="ml-72 p-10 transition-all duration-300">
    <h1 class="text-3xl font-bold text-blue-800 mb-8">Fuel Records</h1>

    <!-- Add Fuel Form -->
    <div class="bg-white/70 backdrop-blur-md p-6 rounded-2xl shadow-lg border border-blue-200 mb-10 w-full max-w-3xl">
      <h2 class="text-xl font-semibold text-blue-700 mb-4">Add Fuel Record</h2>
      <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Vehicle Number</label>
          <select name="vehicle_number" required class="w-full p-2 border rounded-lg">
            <option value="">Select Vehicle</option>
            <?php while ($v = mysqli_fetch_assoc($vehicles)) echo "<option value='{$v['vehicle_number']}'>{$v['vehicle_number']}</option>"; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Amount Refueled (KSh)</label>
          <input type="number" name="amount_refueled" required class="w-full p-2 border rounded-lg">
        </div>
        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Refueled By</label>
          <input type="text" name="refueled_by" required class="w-full p-2 border rounded-lg">
        </div>
        <div>
          <label class="block text-sm font-medium text-blue-800 mb-1">Approved By</label>
          <input type="text" name="approved_by" required class="w-full p-2 border rounded-lg">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-blue-800 mb-1">Attach Receipt</label>
          <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="w-full p-2 border rounded-lg bg-white">
        </div>
        <div class="md:col-span-2 text-right">
          <button type="submit" name="add_fuel" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">
            Save Record
          </button>
        </div>
      </form>
    </div>

    <!-- Filter Section -->
    <div class="bg-white/80 p-4 rounded-lg border border-blue-200 mb-6 flex flex-wrap gap-4 items-center">
      <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
          <label class="text-sm font-medium text-blue-800">From</label>
          <input type="date" name="from_date" class="p-2 border rounded-lg">
        </div>
        <div>
          <label class="text-sm font-medium text-blue-800">To</label>
          <input type="date" name="to_date" class="p-2 border rounded-lg">
        </div>
        <div>
          <label class="text-sm font-medium text-blue-800">Vehicle</label>
          <input type="text" name="vehicle_number" placeholder="Vehicle No." class="p-2 border rounded-lg">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Filter</button>
        <button type="button" onclick="printPage()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Print</button>
        <button type="button" onclick="downloadPDF()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Download PDF</button>
      </form>
    </div>

    <!-- Fuel List / Report Section -->
    <div id="reportSection" class="bg-white/80 p-6 rounded-2xl shadow-lg border border-blue-200">
      <h2 class="text-xl font-semibold text-blue-700 mb-4">Fuel Records List</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left border border-blue-200">
          <thead class="bg-blue-100 text-blue-900 uppercase text-xs">
  <tr>
    <th class="px-4 py-2 border">#</th>
    <th class="px-4 py-2 border">Date</th>
    <th class="px-4 py-2 border">Vehicle</th>
    <th class="px-4 py-2 border">Amount (KSh)</th>
    <th class="px-4 py-2 border">Refueled By</th>
    <th class="px-4 py-2 border">Approved By</th>
    <th class="px-4 py-2 border">Receipt</th>
    <th class="px-4 py-2 border">Actions</th>
  </tr>
</thead>

<tbody>
  <?php
  $count = 1;
  $total = 0;
  while ($row = mysqli_fetch_assoc($records)) {
    $receipt_link = $row['receipt_path']
      ? "<a href='{$row['receipt_path']}' target='_blank' class='text-blue-600 hover:underline'>View</a>"
      : "N/A";
    $total += $row['amount_refueled'];
    $formatted_date = date("d M Y, H:i", strtotime($row['created_at'])); // Format date nicely

    echo "
      <tr class='hover:bg-blue-50 border-b'>
        <td class='px-4 py-2 border'>$count</td>
        <td class='px-4 py-2 border'>$formatted_date</td>
        <td class='px-4 py-2 border'>{$row['vehicle_number']}</td>
        <td class='px-4 py-2 border text-green-700 font-semibold'>KSh {$row['amount_refueled']}</td>
        <td class='px-4 py-2 border'>{$row['refueled_by']}</td>
        <td class='px-4 py-2 border'>{$row['approved_by']}</td>
        <td class='px-4 py-2 border'>$receipt_link</td>
        <td class='px-4 py-2 border flex gap-2'>
          <button onclick=\"openEditModal('{$row['id']}', '{$row['vehicle_number']}', '{$row['amount_refueled']}', '{$row['refueled_by']}', '{$row['approved_by']}')\" class='text-blue-600 hover:underline'>Edit</button>
          <a href='?delete={$row['id']}' class='text-red-600 hover:underline' onclick='return confirm(\"Delete this record?\")'>Delete</a>
        </td>
      </tr>
    ";
    $count++;
  }
  ?>
</tbody>

          <tfoot>
            <tr class="bg-blue-100 font-semibold">
              <td colspan="2" class="px-4 py-2 text-right">Total:</td>
              <td class="px-4 py-2 text-green-700">KSh <?= number_format($total, 2) ?></td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md relative">
      <button onclick="closeModal()" class="absolute top-2 right-2 text-gray-600 hover:text-red-600">&times;</button>
      <h3 class="text-xl font-semibold mb-4 text-blue-700">Edit Fuel Record</h3>
      <form method="POST">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3">
          <label class="block text-sm font-medium text-blue-800 mb-1">Vehicle Number</label>
          <input type="text" name="vehicle_number" id="edit_vehicle" class="w-full p-2 border rounded-lg">
        </div>
        <div class="mb-3">
          <label class="block text-sm font-medium text-blue-800 mb-1">Amount (KSh)</label>
          <input type="number" name="amount_refueled" id="edit_amount" class="w-full p-2 border rounded-lg">
        </div>
        <div class="mb-3">
          <label class="block text-sm font-medium text-blue-800 mb-1">Refueled By</label>
          <input type="text" name="refueled_by" id="edit_refueled_by" class="w-full p-2 border rounded-lg">
        </div>
        <div class="mb-3">
          <label class="block text-sm font-medium text-blue-800 mb-1">Approved By</label>
          <input type="text" name="approved_by" id="edit_approved_by" class="w-full p-2 border rounded-lg">
        </div>
        <div class="text-right">
          <button type="submit" name="update_fuel" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">Update</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
