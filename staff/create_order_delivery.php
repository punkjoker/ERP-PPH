<?php
include 'db_con.php';

// ✅ Handle form submission (unchanged logic you had)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_id = intval($_POST['delivery_id'] ?? 0);
    $remarks = $_POST['remarks'] ?? '';
$invoice_number = $_POST['invoice_number'] ?? '';
$delivery_number = $_POST['delivery_number'] ?? '';

$stmt = $conn->prepare("
    INSERT INTO delivery_orders (delivery_id, remarks, invoice_number, delivery_number, original_status)
    VALUES (?, ?, ?, ?, 'Pending')
");
$stmt->bind_param("isss", $delivery_id, $remarks, $invoice_number, $delivery_number);

    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();

    if (!empty($_POST['item_name'])) {
        for ($i = 0; $i < count($_POST['item_name']); $i++) {
            $item_name = $_POST['item_name'][$i];
            $source_table = $_POST['source_table'][$i];
            $item_id = intval($_POST['item_id'][$i] ?? 0);
            $quantity_removed = floatval($_POST['quantity_removed'][$i] ?? 0);
            $remaining_quantity = floatval($_POST['remaining_quantity'][$i] ?? 0);
            $unit = $_POST['unit'][$i] ?? '';

            // Insert order item
            $stmt = $conn->prepare("INSERT INTO delivery_order_items (order_id, item_name, source_table, item_id, quantity_removed, remaining_quantity, unit) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issidds", $order_id, $item_name, $source_table, $item_id, $quantity_removed, $remaining_quantity, $unit);
            $stmt->execute();
            $stmt->close();

            // Subtract from appropriate source table.
            // For chemicals_in and finished_products we want to deduct from the specific record (item_id).
            if ($item_id > 0 && $quantity_removed > 0) {
                if ($source_table === 'chemicals_in') {
                    $u = $conn->prepare("UPDATE chemicals_in SET remaining_quantity = GREATEST(0, remaining_quantity - ?) WHERE id = ?");
                    $u->bind_param("di", $quantity_removed, $item_id);
                    $u->execute();
                    $u->close();
                } elseif ($source_table === 'finished_products') {
                    $u = $conn->prepare("UPDATE finished_products SET remaining_size = GREATEST(0, remaining_size - ?) WHERE id = ?");
                    $u->bind_param("di", $quantity_removed, $item_id);
                    $u->execute();
                    $u->close();
               } elseif ($source_table === 'stock_in') {
    $u = $conn->prepare("
        UPDATE stock_in 
        SET 
            quantity = GREATEST(0, quantity - ?)
        WHERE id = ?
    ");
    $u->bind_param("di", $quantity_removed, $item_id);
    $u->execute();
    $u->close();
}

            }
        }
    }

    $msg = "Delivery order created successfully!";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Delivery Order</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    .autocomplete-list { position: absolute; z-index: 40; max-height: 220px; overflow:auto; width:100%; box-shadow:0 2px 8px rgba(0,0,0,0.12); }
    .autocomplete-item { padding: .5rem .75rem; cursor: pointer; }
    .autocomplete-item:hover { background:#f0f6ff; }
    .rel { position: relative; }
  </style>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="p-6 ml-64">
  <h1 class="text-3xl font-bold mb-4">🚚 Create Delivery Order</h1>

  <?php if (!empty($msg)): ?>
    <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="POST" id="deliveryForm" class="bg-white shadow-md rounded-lg p-6">
    <!-- Company -->
    <div class="mb-4 rel">
      <label class="block text-gray-700 font-medium mb-1">Select Company</label>
      <input type="text" id="company_search" placeholder="Type company..." autocomplete="off"
             class="w-full border rounded px-3 py-2" />
      <input type="hidden" name="delivery_id" id="delivery_id" />
      <div id="company_results" class="autocomplete-list hidden bg-white"></div>
    </div>

    <!-- Remarks -->
<div class="mb-4">
  <label class="block text-gray-700 font-medium mb-1">Remarks</label>
  <textarea name="remarks" rows="2" class="w-full border rounded px-3 py-2"></textarea>
</div>

<!-- Invoice Number -->
<div class="mb-4">
  <label class="block text-gray-700 font-medium mb-1">Invoice Number</label>
  <input type="text" name="invoice_number" placeholder="Enter invoice number" class="w-full border rounded px-3 py-2">
</div>

<!-- Delivery Number -->
<div class="mb-6">
  <label class="block text-gray-700 font-medium mb-1">Delivery Number</label>
  <input type="text" name="delivery_number" placeholder="Enter delivery number" class="w-full border rounded px-3 py-2">
</div>


    <!-- Items -->
    <h2 class="text-lg font-semibold mb-2">Add Items</h2>
    <table class="w-full border-collapse mb-4" id="itemsTable">
      <thead class="bg-blue-600 text-white">
        <tr>
          <th class="py-2 px-3 text-left">Item</th>
          <th class="py-2 px-3 text-left">Source</th>
          <th class="py-2 px-3 text-left">Remaining</th>
          <th class="py-2 px-3 text-left">Remove Qty</th>
          <th class="py-2 px-3 text-left">Unit</th>
          <th class="py-2 px-3"></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>

    <button type="button" id="addRow" class="bg-blue-600 text-white px-4 py-2 rounded">+ Add Item</button>

    <div class="mt-6">
      <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded">Save Order</button>
    </div>
  </form>
</div>
<?php
// ✅ Fetch all created delivery orders with company names
$query = "
  SELECT o.id, o.invoice_number, o.delivery_number, o.original_status, o.created_at,
         d.company_name
  FROM delivery_orders o
  JOIN delivery_details d ON o.delivery_id = d.id
  ORDER BY o.id DESC
";
$result = $conn->query($query);
?>

<!-- Delivery Orders Table -->
 <div class="p-6 ml-64">
<div class="mt-10 bg-white shadow-md rounded-lg p-6">
  <h2 class="text-2xl font-semibold mb-4">📦 Created Delivery Orders</h2>

  <table class="w-full border-collapse">
    <thead class="bg-gray-200">
      <tr>
        <th class="py-2 px-3 text-left">Company Name</th>
        <th class="py-2 px-3 text-left">Invoice Number</th>
        <th class="py-2 px-3 text-left">Delivery Number</th>
        <th class="py-2 px-3 text-left">Status</th>
        <th class="py-2 px-3 text-left">Created At</th>
        <th class="py-2 px-3 text-center">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr class="border-b hover:bg-gray-50">
            <td class="py-2 px-3"><?= htmlspecialchars($row['company_name']) ?></td>
            <td class="py-2 px-3"><?= htmlspecialchars($row['invoice_number']) ?></td>
            <td class="py-2 px-3"><?= htmlspecialchars($row['delivery_number']) ?></td>
            <td class="py-2 px-3">
              <span class="px-2 py-1 rounded text-sm <?= $row['original_status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                <?= htmlspecialchars($row['original_status']) ?>
              </span>
            </td>
            <td class="py-2 px-3"><?= htmlspecialchars($row['created_at']) ?></td>
            <td class="py-2 px-3 text-center">
              <a href="view_delivery_items.php?id=<?= $row['id'] ?>"
                 class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">View Items</a>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="6" class="text-center py-3 text-gray-500">No delivery orders found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
function rowTemplate() {
  return `
  <tr class="item-row">
    <td class="rel">
      <input type="text" name="item_name[]" class="item_search w-full border rounded px-2 py-1" autocomplete="off" />
      <input type="hidden" name="item_id[]" class="item_id" value="" />
      <div class="autocomplete-list suggestions hidden"></div>
    </td>
    <td>
      <select name="source_table[]" class="source_table border rounded px-2 py-1">
        <option value="finished_products">Finished Products</option>
        <option value="stock_in">Stock In</option>
        <option value="chemicals_in">Chemicals In</option>
      </select>
    </td>
    <td><input type="text" name="remaining_quantity[]" readonly class="remaining_quantity w-full border rounded px-2 py-1 text-gray-600"></td>
    <td><input type="number" name="quantity_removed[]" step="0.01" class="quantity_removed w-full border rounded px-2 py-1"></td>
    <td><input type="text" name="unit[]" readonly class="unit w-full border rounded px-2 py-1 text-gray-600"></td>
    <td><button type="button" class="removeRow text-red-600">✖</button></td>
  </tr>`;
}

$('#addRow').on('click', function() {
  $('#itemsTable tbody').append(rowTemplate());
});

// delete
$(document).on('click', '.removeRow', function() {
  $(this).closest('tr').remove();
});

// ---------- company autocomplete ----------
let companyXHR = null;
$('#company_search').on('input', function() {
  let q = $(this).val().trim();
  const container = $('#company_results');
  if (companyXHR) companyXHR.abort();
  if (q.length < 2) { container.addClass('hidden').empty(); return; }
  companyXHR = $.get('search_company.php', { q }, function(html) {
    container.html(html).removeClass('hidden');
  });
});
$(document).on('click', '.company-item', function() {
  $('#company_search').val($(this).text());
  $('#delivery_id').val($(this).data('id'));
  $('#company_results').addClass('hidden').empty();
});
$(document).on('click', function(e){
  if (!$(e.target).closest('#company_results, #company_search').length) {
    $('#company_results').addClass('hidden').empty();
  }
});

// ---------- item autocomplete ----------
$(document).on('input', '.item_search', function() {
  let $input = $(this);
  let q = $input.val().trim();
  let $suggestBox = $input.siblings('.suggestions');
  $suggestBox.empty();
  if (q.length < 2) { $suggestBox.addClass('hidden'); return; }

  // optional: pass selected source_table so results can be filtered to that source
  let source = $input.closest('tr').find('.source_table').val();

  $.get('search_item.php', { q, source }, function(html) {
    $suggestBox.html(html).removeClass('hidden');
  });
});

// click suggestion -> populate row fields
$(document).on('click', '.item-suggestion', function() {
  let $s = $(this);
  let $row = $s.closest('td').closest('tr');
  $row.find('.item_search').val($s.data('label'));
  $row.find('.item_id').val($s.data('id'));
  $row.find('.source_table').val($s.data('source'));
  $row.find('.remaining_quantity').val(Number($s.data('remaining')).toFixed(2));
  $row.find('.unit').val($s.data('unit'));
  $s.closest('.suggestions').addClass('hidden').empty();
});

// hide suggestions on click outside
$(document).on('click', function(e){
  if (!$(e.target).closest('.suggestions, .item_search').length) {
    $('.suggestions').addClass('hidden').empty();
  }
});
</script>
</body>
</html>
