<?php
include 'db_con.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = $_POST['company_name'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $invoice_number = $_POST['invoice_number'] ?? '';
    $delivery_number = $_POST['delivery_number'] ?? '';

    // ✅ Insert into delivery_orders_store_b
    $stmt = $conn->prepare("
        INSERT INTO delivery_orders_store_b (company_name, remarks, invoice_number, delivery_number, original_status)
        VALUES (?, ?, ?, ?, 'Pending')
    ");
    $stmt->bind_param("ssss", $company_name, $remarks, $invoice_number, $delivery_number);
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
            $pack_size = floatval($_POST['pack_size'][$i] ?? 0);
            $material_name = $_POST['material_name'][$i] ?? '';

            // ✅ Insert into delivery_order_items_store_b
            $stmt = $conn->prepare("
                INSERT INTO delivery_order_items_store_b
                (order_id, item_name, material_name, pack_size, source_table, item_id, quantity_removed, remaining_quantity, unit)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issssddds",
                $order_id,
                $item_name,
                $material_name,
                $pack_size,
                $source_table,
                $item_id,
                $quantity_removed,
                $remaining_quantity,
                $unit
            );
            $stmt->execute();
            $stmt->close();

            // ✅ FIFO deduction logic (Store B)
            if ($source_table === 'store_b_chemicals_in') {
                $remaining_to_remove = $quantity_removed;

                $chemQuery = $conn->prepare("SELECT chemical_name FROM store_b_chemicals_in WHERE id = ?");
                $chemQuery->bind_param("i", $item_id);
                $chemQuery->execute();
                $chemQuery->bind_result($chemical_name);
                $chemQuery->fetch();
                $chemQuery->close();

                if (!empty($chemical_name)) {
                    $lotQuery = $conn->prepare("
                        SELECT id, remaining_quantity
                        FROM store_b_chemicals_in
                        WHERE chemical_name = ? AND remaining_quantity > 0
                        ORDER BY id ASC
                    ");
                    $lotQuery->bind_param("s", $chemical_name);
                    $lotQuery->execute();
                    $lots = $lotQuery->get_result();

                    while ($lot = $lots->fetch_assoc()) {
                        if ($remaining_to_remove <= 0) break;
                        $lot_id = $lot['id'];
                        $lot_remaining = $lot['remaining_quantity'];

                        if ($lot_remaining >= $remaining_to_remove) {
                            $u = $conn->prepare("UPDATE store_b_chemicals_in SET remaining_quantity = remaining_quantity - ? WHERE id = ?");
                            $u->bind_param("di", $remaining_to_remove, $lot_id);
                            $u->execute();
                            $u->close();
                            $remaining_to_remove = 0;
                        } else {
                            $u = $conn->prepare("UPDATE store_b_chemicals_in SET remaining_quantity = 0 WHERE id = ?");
                            $u->bind_param("i", $lot_id);
                            $u->execute();
                            $u->close();
                            $remaining_to_remove -= $lot_remaining;
                        }
                    }
                    $lotQuery->close();
                }

            } elseif ($source_table === 'store_b_finished_products_in') {
                $remaining_to_remove = $quantity_removed;

                $batchQuery = $conn->prepare("
                    SELECT id, remaining_quantity
                    FROM store_b_finished_products_in
                    WHERE product_name = (SELECT product_name FROM store_b_finished_products_in WHERE id = ?)
                    AND remaining_quantity > 0
                    ORDER BY id ASC
                ");
                $batchQuery->bind_param("i", $item_id);
                $batchQuery->execute();
                $batches = $batchQuery->get_result();

                while ($batch = $batches->fetch_assoc()) {
                    if ($remaining_to_remove <= 0) break;
                    $batch_id = $batch['id'];
                    $batch_remaining = $batch['remaining_quantity'];

                    if ($batch_remaining >= $remaining_to_remove) {
                        $u = $conn->prepare("UPDATE store_b_finished_products_in SET remaining_quantity = remaining_quantity - ? WHERE id = ?");
                        $u->bind_param("di", $remaining_to_remove, $batch_id);
                        $u->execute();
                        $u->close();
                        $remaining_to_remove = 0;
                    } else {
                        $u = $conn->prepare("UPDATE store_b_finished_products_in SET remaining_quantity = 0 WHERE id = ?");
                        $u->bind_param("i", $batch_id);
                        $u->execute();
                        $u->close();
                        $remaining_to_remove -= $batch_remaining;
                    }
                }
                $batchQuery->close();

            } elseif ($source_table === 'store_b_engineering_products_in') {
                $u = $conn->prepare("
                    UPDATE store_b_engineering_products_in
                    SET quantity_received = GREATEST(0, quantity_received - ?)
                    WHERE id = ?
                ");
                $u->bind_param("di", $quantity_removed, $item_id);
                $u->execute();
                $u->close();
            }
        }
    }

    $msg = "✅ Delivery order for Store B created successfully!";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Delivery Orders - Store B</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    .autocomplete-list { position:absolute;z-index:40;max-height:220px;overflow:auto;width:100%;box-shadow:0 2px 8px rgba(0,0,0,0.12);}
    .autocomplete-item { padding:.5rem .75rem; cursor:pointer;}
    .autocomplete-item:hover { background:#f0f6ff;}
    .rel { position:relative;}
  </style>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="p-6 ml-64">
  <h1 class="text-3xl font-bold mb-4">🚚 Create Delivery Order (Store B)</h1>

  <?php if (!empty($msg)): ?>
    <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="POST" class="bg-white shadow-md rounded-lg p-6">

    <!-- ✅ Search Company -->
    <div class="mb-4">
      <label class="block text-gray-700 font-medium mb-1">Customer Name</label>
      <input type="text" name="company_name" id="company_name" placeholder="Enter customer" class="w-full border rounded px-3 py-2">
      <div id="companySuggestions" class="autocomplete-list hidden"></div>
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

    <h2 class="text-lg font-semibold mb-2">Add Items</h2>
    <table class="w-full border-collapse mb-4" id="itemsTable">
      <thead class="bg-blue-600 text-white">
        <tr>
          <th class="py-2 px-3 text-left">Item</th>
          <th class="py-2 px-3 text-left">Source</th>
          <th class="py-2 px-3 text-left">Pack Size</th>
          <th class="py-2 px-3 text-left">Remaining Units</th>
          <th class="py-2 px-3 text-left">Remove Qty</th>
          <th class="py-2 px-3 text-left">Unit</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>

    <button type="button" id="addRow" class="bg-blue-600 text-white px-4 py-2 rounded">+ Add Item</button>

    <div class="mt-6">
      <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded">Save Delivery Order</button>
    </div>
  </form>
  <hr class="my-8">

<h2 class="text-2xl font-bold mb-4">📦 Existing Delivery Orders (Store B)</h2>

<table class="min-w-full bg-white border border-gray-300 shadow-md rounded-lg">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="py-2 px-3 text-left">#</th>
      <th class="py-2 px-3 text-left">Customer</th>
      <th class="py-2 px-3 text-left">Invoice #</th>
      <th class="py-2 px-3 text-left">Delivery #</th>
      <th class="py-2 px-3 text-left">Status</th>
      <th class="py-2 px-3 text-left">Created At</th>
      <th class="py-2 px-3 text-left">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $orders = $conn->query("SELECT * FROM delivery_orders_store_b ORDER BY id DESC");
    if ($orders->num_rows === 0) {
        echo "<tr><td colspan='7' class='text-center py-4 text-gray-500'>No delivery orders found.</td></tr>";
    } else {
        while ($row = $orders->fetch_assoc()) {
            echo "
            <tr class='border-t'>
              <td class='py-2 px-3'>{$row['id']}</td>
              <td class='py-2 px-3'>".htmlspecialchars($row['company_name'])."</td>
              <td class='py-2 px-3'>".htmlspecialchars($row['invoice_number'])."</td>
              <td class='py-2 px-3'>".htmlspecialchars($row['delivery_number'])."</td>
              <td class='py-2 px-3'>
                <span class='px-2 py-1 rounded text-sm ".(
                    $row['original_status']=='Completed' ? 'bg-green-100 text-green-700' :
                    ($row['original_status']=='Cancelled' ? 'bg-red-100 text-red-700' :
                    'bg-yellow-100 text-yellow-700')
                )."'>
                  {$row['original_status']}
                </span>
              </td>
              <td class='py-2 px-3'>".htmlspecialchars($row['created_at'])."</td>
              <td class='py-2 px-3'>
                <a href='view_store_b_order_items.php?id={$row['id']}' class='bg-blue-600 text-white px-3 py-1 rounded'>View Items</a>
              </td>
            </tr>";
        }
    }
    ?>
  </tbody>
</table>

</div>

<script>
function rowTemplate() {
  return `
  <tr class="item-row">
    <td class="rel">
      <input type="text" name="item_name[]" class="item_search w-full border rounded px-2 py-1" autocomplete="off" />
      <input type="hidden" name="item_id[]" class="item_id" />
      <input type="hidden" name="material_name[]" class="material_name" />
      <div class="autocomplete-list suggestions hidden"></div>
    </td>
    <td>
      <select name="source_table[]" class="source_table border rounded px-2 py-1">
        <option value="store_b_finished_products_in">Finished Products</option>
        <option value="store_b_engineering_products_in">Engineering Products</option>
        <option value="store_b_chemicals_in">Chemicals</option>
      </select>
    </td>
    <td><input type="text" name="pack_size[]" class="pack_size w-full border rounded px-2 py-1"></td>
    <td><input type="text" name="remaining_quantity[]" readonly class="remaining_quantity w-full border rounded px-2 py-1 text-gray-600"></td>
    <td><input type="number" name="quantity_removed[]" step="0.01" class="quantity_removed w-full border rounded px-2 py-1"></td>
    <td><input type="text" name="unit[]" readonly class="unit w-full border rounded px-2 py-1 text-gray-600"></td>
    <td><button type="button" class="removeRow text-red-600">✖</button></td>
  </tr>`;
}

$('#addRow').on('click', function() { $('#itemsTable tbody').append(rowTemplate()); });
$(document).on('click', '.removeRow', function() { $(this).closest('tr').remove(); });

$(document).on('input', '.item_search', function() {
  let $input = $(this);
  let q = $input.val().trim();
  let $suggestBox = $input.siblings('.suggestions');
  if (q.length < 2) { $suggestBox.addClass('hidden'); return; }
  let source = $input.closest('tr').find('.source_table').val();
  $.get('store_b_search_item.php', { q, source }, html => $suggestBox.html(html).removeClass('hidden'));
});

$(document).on('click', '.item-suggestion', function() {
  let $s = $(this), $row = $s.closest('tr');
  $row.find('.item_search').val($s.data('label'));
  $row.find('.item_id').val($s.data('id'));
  $row.find('.source_table').val($s.data('source'));
  $row.find('.remaining_quantity').val(Number($s.data('remaining')).toFixed(2));
  $row.find('.unit').val($s.data('unit'));
  $row.find('.material_name').val($s.data('material_name') || '');
  $row.find('.pack_size').val($s.data('pack_size') || '');
  $s.closest('.suggestions').addClass('hidden').empty();
});
</script>
</body>
</html>
