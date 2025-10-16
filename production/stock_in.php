<?php
session_start();
require 'db_con.php';

// ✅ Handle Add Stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock'])) {
    $stock_name = trim($_POST['stock_name']);
    $stock_code = trim($_POST['stock_code']);
    $batch_no = trim($_POST['batch_no']);
    $po_number = trim($_POST['po_number']);
    $quantity = floatval($_POST['quantity']);
    $unit = trim($_POST['unit']);
    $unit_price = floatval($_POST['unit_price']);
    $total_cost = floatval($_POST['total_cost']);
    $created_at = $_POST['created_at'];

    $stmt = $conn->prepare("INSERT INTO stock_in 
        (stock_name, stock_code, po_number, batch_no, quantity, original_quantity, unit, unit_cost, total_cost, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssddsdds", 
        $stock_name, 
        $stock_code, 
        $po_number, 
        $batch_no, 
        $quantity, 
        $quantity, // same as original_quantity initially
        $unit, 
        $unit_price, 
        $total_cost, 
        $created_at
    );
    $stmt->execute();
}

// ✅ Fetch approved POs
$poQuery = $conn->query("SELECT id, po_no FROM po_list WHERE status = 1 ORDER BY created_at DESC");

// ✅ Fetch stock names from `chemical_names` where main_category = 'Engineering Products'
$stockNames = $conn->query("SELECT chemical_name AS stock_name, chemical_code AS stock_code 
    FROM chemical_names 
    WHERE main_category = 'Engineering Products' 
    ORDER BY chemical_name ASC");

// ✅ Filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$search_name = $_GET['search_name'] ?? '';

$query = "SELECT * FROM stock_in WHERE 1=1";
if (!empty($from_date) && !empty($to_date)) {
    $query .= " AND DATE(created_at) BETWEEN '{$from_date}' AND '{$to_date}'";
}
if (!empty($search_name)) {
    $query .= " AND (stock_name LIKE '%{$search_name}%' OR batch_no LIKE '%{$search_name}%')";
}
$query .= " ORDER BY created_at DESC";
$stocks = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Stock In</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-blue-50 min-h-screen px-4">

<?php include 'navbar.php'; ?>

<div class="max-w-6xl ml-64 mx-auto mt-24 p-6 bg-white rounded-xl shadow-lg">
  <h2 class="text-2xl font-bold text-blue-700 mb-6 text-center">Stock In - Engineering Products</h2>

  <!-- ✅ Add Stock Form -->
  <form method="POST" id="stockForm" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

    <div>
      <label class="block text-sm font-medium text-gray-700">Stock Name</label>
      <select name="stock_name" id="stock_name" class="w-full border rounded px-3 py-2" required>
        <option value="">-- Select Engineering Product --</option>
        <?php while($s = $stockNames->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($s['stock_name']) ?>" data-code="<?= htmlspecialchars($s['stock_code']) ?>">
            <?= htmlspecialchars($s['stock_name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Stock Code</label>
      <input type="text" name="stock_code" id="stock_code" class="w-full border rounded px-3 py-2 bg-gray-100" readonly>
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
      <label class="block text-sm font-medium text-gray-700">Quantity</label>
      <input type="number" step="0.01" name="quantity" id="quantity" class="w-full border rounded px-3 py-2" readonly>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Unit (e.g. pcs, kg)</label>
      <input type="text" name="unit" id="unit" class="w-full border rounded px-3 py-2" readonly>
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
      <input type="date" name="created_at" class="w-full border rounded px-3 py-2" required>
    </div>

    <div class="md:col-span-2 flex justify-center mt-4">
      <button type="submit" name="add_stock" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
        Add Stock
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
      <input type="text" name="search_name" value="<?= htmlspecialchars($search_name) ?>" class="border rounded px-3 py-2" placeholder="Stock or Batch">
    </div>
    <div>
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filter</button>
    </div>
  </form>

  <!-- ✅ Stocks Table -->
  <div class="overflow-x-auto">
    <table class="w-full border text-sm">
      <thead class="bg-blue-100">
        <tr>
          <th class="border px-2 py-1">Stock</th>
          <th class="border px-2 py-1">Code</th>
          <th class="border px-2 py-1">Batch</th>
          <th class="border px-2 py-1">PO</th>
          <th class="border px-2 py-1">Qty</th>
          <th class="border px-2 py-1">Remaining</th>
          <th class="border px-2 py-1">Unit</th>
          <th class="border px-2 py-1">Unit Price</th>
          <th class="border px-2 py-1">Total Cost</th>
          <th class="border px-2 py-1">Date</th>
         
        </tr>
      </thead>
      <tbody>
        <?php if ($stocks->num_rows > 0): ?>
          <?php while ($row = $stocks->fetch_assoc()): ?>
          <tr>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['stock_name']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['stock_code']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['batch_no']) ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['po_number']) ?></td>
            <td class="border px-2 py-1"><?= $row['quantity'] ?></td>
            <td class="border px-2 py-1 text-green-700"><?= $row['quantity'] ?></td>
            <td class="border px-2 py-1"><?= htmlspecialchars($row['unit']) ?></td>
            <td class="border px-2 py-1"><?= number_format($row['unit_cost'],2) ?></td>
            <td class="border px-2 py-1"><?= number_format($row['total_cost'],2) ?></td>
            <td class="border px-2 py-1"><?= $row['created_at'] ?></td>
            
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="11" class="text-center text-gray-500 py-2">No stock found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
  // ✅ Auto-fill Stock Code
  $('#stock_name').on('change', function() {
    const code = $(this).find(':selected').data('code');
    $('#stock_code').val(code || '');
  });

  // ✅ When a P.O is selected, load details
  $('#po_number').on('change', function() {
    const poNumber = $(this).val();
    if (poNumber) {
      $.ajax({
        url: 'get_po_details.php',
        type: 'GET',
        data: { po_no: poNumber },
        dataType: 'json',
        success: function(data) {
          if (data.success) {
            $('#quantity').val(data.quantity);
            $('#unit').val(data.unit);
            $('#unit_price').val(data.unit_price);
            $('#total_cost').val((data.quantity * data.unit_price).toFixed(2));
          } else {
            alert('No matching PO details found.');
          }
        },
        error: function() {
          alert('Error loading PO details.');
        }
      });
    } else {
      $('#quantity, #unit, #unit_price, #total_cost').val('');
    }
  });

  // ✅ Auto-update total cost when quantity changes
  $('#quantity').on('input', updateTotal);
  function updateTotal() {
    const qty = parseFloat($('#quantity').val()) || 0;
    const price = parseFloat($('#unit_price').val()) || 0;
    $('#total_cost').val((qty * price).toFixed(2));
  }
</script>

<script>
// ✅ Auto-fill Stock Code
$('#stock_name').on('change', function() {
  const code = $(this).find(':selected').data('code');
  $('#stock_code').val(code || '');
});

// ✅ Auto-update total cost
$('#quantity').on('input', updateTotal);
function updateTotal() {
  const qty = parseFloat($('#quantity').val()) || 0;
  const price = parseFloat($('#unit_price').val()) || 0;
  $('#total_cost').val((qty * price).toFixed(2));
}
</script>

</body>
</html>
