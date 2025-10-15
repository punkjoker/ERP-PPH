<?php
session_start();
require 'db_con.php';

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_chemical'])) {
    $chemical_name = trim($_POST['chemical_name']);
    $chemical_code = trim($_POST['chemical_code']);
    $batch_no = trim($_POST['batch_no']);
    $rm_lot_no = trim($_POST['rm_lot_no']);
    $po_number = trim($_POST['po_number']);
    $std_quantity = floatval($_POST['std_quantity']);
    $remaining_quantity = $std_quantity;
    $unit_price = floatval($_POST['unit_price']);
    $total_cost = floatval($_POST['total_cost']);
    $date_added = $_POST['date_added'];

    $stmt = $conn->prepare("INSERT INTO chemicals_in 
        (chemical_name, chemical_code, batch_no, rm_lot_no, po_number, std_quantity, remaining_quantity, unit_price, total_cost, date_added, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("sssssdidds", $chemical_name, $chemical_code, $batch_no, $rm_lot_no, $po_number, $std_quantity, $remaining_quantity, $unit_price, $total_cost, $date_added);
    $stmt->execute();
}

// ✅ Fetch approved POs
$poQuery = $conn->query("SELECT id, po_no FROM po_list WHERE status = 1 ORDER BY created_at DESC");

// ✅ Fetch chemical names
$chemNames = $conn->query("SELECT chemical_name, chemical_code FROM chemical_names ORDER BY chemical_name ASC");

// ✅ Filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$search_name = $_GET['search_name'] ?? '';

$query = "SELECT * FROM chemicals_in WHERE 1=1";
if (!empty($from_date) && !empty($to_date)) {
    $query .= " AND DATE(date_added) BETWEEN '{$from_date}' AND '{$to_date}'";
}
if (!empty($search_name)) {
    $query .= " AND (chemical_name LIKE '%{$search_name}%' OR batch_no LIKE '%{$search_name}%')";
}
$query .= " ORDER BY date_added DESC";
$chemicals = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Chemicals In</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-blue-50 min-h-screen px-4">

<?php include 'navbar.php'; ?>

<div class="max-w-6xl ml-64 mx-auto mt-24 p-6 bg-white rounded-xl shadow-lg">
  <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Chemicals In</h2>

  <!-- ✅ Add Chemical Form -->
  <form method="POST" id="chemForm" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

    <div>
      <label class="block text-sm font-medium text-gray-700">Chemical Name</label>
      <select name="chemical_name" id="chemical_name" class="w-full border rounded px-3 py-2" required>
        <option value="">-- Select Chemical --</option>
        <?php while($chem = $chemNames->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($chem['chemical_name']) ?>" data-code="<?= htmlspecialchars($chem['chemical_code']) ?>">
            <?= htmlspecialchars($chem['chemical_name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Chemical Code</label>
      <input type="text" name="chemical_code" id="chemical_code" class="w-full border rounded px-3 py-2 bg-gray-100" readonly>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">PO Number (Approved)</label>
      <select name="po_number" id="po_number" class="w-full border rounded px-3 py-2">
        <option value="">-- Select PO --</option>
        <?php while($po = $poQuery->fetch_assoc()): ?>
          <option value="<?= $po['po_no'] ?>"><?= $po['po_no'] ?></option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Batch No</label>
      <input type="text" name="batch_no" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">RM LOT NO</label>
      <input type="text" name="rm_lot_no" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">STD Quantity</label>
      <input type="number" step="0.01" name="std_quantity" id="std_quantity" class="w-full border rounded px-3 py-2" required>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Unit Price (Auto)</label>
      <input type="number" step="0.01" name="unit_price" id="unit_price" class="w-full border rounded px-3 py-2 bg-gray-100" readonly>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Total Cost</label>
      <input type="number" step="0.01" name="total_cost" id="total_cost" class="w-full border rounded px-3 py-2 bg-gray-100" readonly>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Date Added</label>
      <input type="date" name="date_added" class="w-full border rounded px-3 py-2" required>
    </div>

    <div class="md:col-span-2 flex justify-center mt-4">
      <button type="submit" name="add_chemical" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
        Add Chemical
      </button>
    </div>
  </form>

  <!-- ✅ Filter Section -->
  <form method="GET" class="flex flex-wrap gap-4 mb-4 items-end">
    <div>
      <label class="text-sm font-medium text-gray-700">From Date</label>
      <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border rounded px-3 py-2">
    </div>
    <div>
      <label class="text-sm font-medium text-gray-700">To Date</label>
      <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border rounded px-3 py-2">
    </div>
    <div>
      <label class="text-sm font-medium text-gray-700">Search</label>
      <input type="text" name="search_name" value="<?= htmlspecialchars($search_name) ?>" class="border rounded px-3 py-2" placeholder="Chemical or Batch">
    </div>
    <div>
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filter</button>
    </div>
  </form>

  <!-- ✅ Chemicals Table -->
  <div class="overflow-x-auto">
    <table class="w-full border text-sm">
      <thead class="bg-blue-100">
        <tr>
          <th class="border px-2 py-1">Chemical</th>
          <th class="border px-2 py-1">Code</th>
          <th class="border px-2 py-1">Batch</th>
          <th class="border px-2 py-1">RM Lot</th>
          <th class="border px-2 py-1">PO</th>
          <th class="border px-2 py-1">Qty</th>
          <th class="border px-2 py-1">Remaining</th>
          <th class="border px-2 py-1">Unit Price</th>
          <th class="border px-2 py-1">Total Cost</th>
          <th class="border px-2 py-1">Date</th>
          <th class="border px-2 py-1">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($chemicals->num_rows > 0): ?>
          <?php while ($row = $chemicals->fetch_assoc()): ?>
          <tr>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['chemical_name']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['chemical_code']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['batch_no']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['rm_lot_no']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['po_number']) ?></td>
            <td class="border px-2 py-1"><?= $row['std_quantity'] ?></td>
            <td class="border px-2 py-1 text-green-700"><?= $row['remaining_quantity'] ?></td>
            <td class="border px-2 py-1"><?= number_format($row['unit_price'],2) ?></td>
            <td class="border px-2 py-1"><?= number_format($row['total_cost'],2) ?></td>
            <td class="border px-2 py-1"><?= $row['date_added'] ?></td>
            <td class="border px-2 py-1 font-bold 
              <?= $row['status']=='Pending' ? 'text-yellow-600' : ($row['status']=='Approved' ? 'text-green-600' : 'text-red-600') ?>">
              <?= htmlspecialchars($row['status']) ?>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="11" class="text-center text-gray-500 py-2">No chemicals found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// ✅ Auto-fill Chemical Code
$('#chemical_name').on('change', function() {
  const code = $(this).find(':selected').data('code');
  $('#chemical_code').val(code || '');
});

// ✅ Auto-fill Unit Price & Quantity from PO (AJAX)
$('#po_number').on('change', function() {
  const po = $(this).val();
  const chem = $('#chemical_name').val();
  if(po && chem) {
    $.ajax({
      url: 'fetch_po_details.php',
      type: 'GET',
      data: { po_no: po, chemical_name: chem },
      success: function(res) {
        try {
          const data = JSON.parse(res);
          $('#std_quantity').val(data.quantity || '');
          $('#unit_price').val(data.unit_price || '');
          updateTotal();
        } catch (e) { console.log('Invalid JSON'); }
      }
    });
  }
});

// ✅ Auto-update total cost
$('#std_quantity').on('input', updateTotal);
function updateTotal() {
  const qty = parseFloat($('#std_quantity').val()) || 0;
  const price = parseFloat($('#unit_price').val()) || 0;
  $('#total_cost').val((qty * price).toFixed(2));
}
</script>

</body>
</html>
