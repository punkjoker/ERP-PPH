<?php
include 'db_con.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Store B Finished Products Receiving</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 w-[calc(100%-16rem)]">

  <h2 class="text-xl font-bold mb-4">Store B Finished Products Receiving</h2>

  <!-- ✅ Input Form -->
  <form method="POST" action="" class="bg-blue-100 p-4 rounded-lg shadow-md space-y-3 text-sm">
    <div>
      <label class="block font-medium">Product Name</label>
      <input type="text" id="product_name" name="product_name" autocomplete="off"
             class="w-full p-1 rounded border border-gray-300 text-sm">
      <input type="hidden" id="product_id" name="product_id">
    </div>

    <div class="grid grid-cols-2 gap-3 text-sm">
      <div>
        <label class="block font-medium">Product Code</label>
        <input type="text" id="product_code" name="product_code" readonly
               class="w-full p-1 rounded border border-gray-300 bg-gray-200 text-sm">
      </div>
      <div>
        <label class="block font-medium">Category</label>
        <input type="text" id="category" name="category" readonly
               class="w-full p-1 rounded border border-gray-300 bg-gray-200 text-sm">
      </div>
      <div class="col-span-2">
        <label class="block font-medium">Description</label>
        <textarea id="description" name="description" readonly
                  class="w-full p-1 rounded border border-gray-300 bg-gray-200 text-sm"></textarea>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3 text-sm">
      <div>
        <label class="block font-medium">Delivery Number</label>
        <input type="text" name="delivery_number" class="w-full p-1 rounded border border-gray-300">
      </div>
      <div>
        <label class="block font-medium">Quantity Received</label>
        <input type="text" name="quantity_received" class="w-full p-1 rounded border border-gray-300">
      </div>
      <div>
        <label class="block font-medium">Units</label>
        <input type="text" name="units" class="w-full p-1 rounded border border-gray-300">
      </div>
      <div>
        <label class="block font-medium">Pack Size</label>
        <input type="text" name="pack_size" class="w-full p-1 rounded border border-gray-300">
      </div>
      <div>
        <label class="block font-medium">Unit Cost</label>
        <input type="text" name="unit_cost" class="w-full p-1 rounded border border-gray-300">
      </div>
      <div>
        <label class="block font-medium">P.O Number</label>
        <input type="text" name="po_number" class="w-full p-1 rounded border border-gray-300">
      </div>
      <div>
        <label class="block font-medium">Received By</label>
        <input type="text" name="received_by" class="w-full p-1 rounded border border-gray-300">
      </div>
      <div>
        <label class="block font-medium">Receiving Date</label>
        <input type="date" name="receiving_date" class="w-full p-1 rounded border border-gray-300">
      </div>
    </div>

    <button type="submit" name="submit"
            class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-sm">
      Save
    </button>
  </form>

  <!-- ✅ Filter Section -->
  <div class="bg-gray-100 p-3 rounded-lg shadow-md mt-6">
    <form method="GET" action="" class="flex items-center space-x-3 text-sm">
      <div>
        <label class="block font-medium">From Date</label>
        <input type="date" name="from_date" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>"
               class="border border-gray-300 rounded p-1">
      </div>
      <div>
        <label class="block font-medium">To Date</label>
        <input type="date" name="to_date" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>"
               class="border border-gray-300 rounded p-1">
      </div>
      <div class="pt-5 flex space-x-2">
        <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">Filter</button>
        <a href="store_b_finished_products_in.php" class="bg-gray-400 text-white px-3 py-1 rounded hover:bg-gray-500">Reset</a>
        <a href="download_store_b_finished_list.php" 
           class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded shadow">
           Download Finished Products List
        </a>
      </div>
    </form>
  </div>

  <!-- ✅ Display Table -->
  <?php
  if (isset($_POST['submit'])) {
      $product_id       = intval($_POST['product_id'] ?? 0);
      $product_name     = $_POST['product_name'] ?? '';
      $product_code     = $_POST['product_code'] ?? '';
      $category         = $_POST['category'] ?? '';
      $description      = $_POST['description'] ?? '';
      $delivery_number  = $_POST['delivery_number'] ?? '';
      $quantity_received= floatval($_POST['quantity_received'] ?? 0);
      $units            = $_POST['units'] ?? '';
      $pack_size        = $_POST['pack_size'] ?? '';
      $unit_cost        = floatval($_POST['unit_cost'] ?? 0);
      $po_number        = $_POST['po_number'] ?? '';
      $received_by      = $_POST['received_by'] ?? '';
      $receiving_date   = $_POST['receiving_date'] ?? '';

      $stmt = $conn->prepare("INSERT INTO store_b_finished_products_in
        (product_id, product_name, product_code, category, description, delivery_number,
         quantity_received, remaining_quantity, units, pack_size, unit_cost,
         po_number, received_by, receiving_date)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

      if ($stmt) {
          $stmt->bind_param(
              "isssssddssdsss",
              $product_id, $product_name, $product_code, $category, $description, $delivery_number,
              $quantity_received, $quantity_received, $units, $pack_size, $unit_cost,
              $po_number, $received_by, $receiving_date
          );
          if ($stmt->execute()) {
              echo "<p class='text-green-600 mt-2 font-semibold'>Data saved successfully!</p>";
          } else {
              echo "<p class='text-red-600 mt-2 font-semibold'>Execute failed: " . htmlspecialchars($stmt->error) . "</p>";
          }
          $stmt->close();
      } else {
          echo "<p class='text-red-600 mt-2 font-semibold'>Prepare failed: " . htmlspecialchars($conn->error) . "</p>";
      }
  }

  $where = "";
  if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
      $from = $conn->real_escape_string($_GET['from_date']);
      $to   = $conn->real_escape_string($_GET['to_date']);
      $where = "WHERE receiving_date BETWEEN '$from' AND '$to'";
  }

  $query = "SELECT * FROM store_b_finished_products_in $where ORDER BY receiving_date DESC";
  $result = $conn->query($query);

  if ($result && $result->num_rows > 0) {
      echo "<div class='overflow-x-auto mt-6'>
      <table class='min-w-full bg-white rounded-lg shadow-md text-sm'>
      <thead>
      <tr class='bg-blue-200 text-left'>
          <th class='px-2 py-1'>Product Name</th>
          <th class='px-2 py-1'>Product Code</th>
          <th class='px-2 py-1'>Category</th>
          <th class='px-2 py-1'>Description</th>
          <th class='px-2 py-1'>Delivery No.</th>
          <th class='px-2 py-1'>Qty Received</th>
          <th class='px-2 py-1'>Units</th>
          <th class='px-2 py-1'>Pack Size</th>
          <th class='px-2 py-1'>Unit Cost</th>
          <th class='px-2 py-1'>PO Number</th>
          <th class='px-2 py-1'>Received By</th>
          <th class='px-2 py-1'>Receiving Date</th>
      </tr>
      </thead><tbody>";
      while ($row = $result->fetch_assoc()) {
          echo "<tr class='border-b hover:bg-gray-100'>
              <td class='px-2 py-1'>" . htmlspecialchars($row['product_name']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['product_code']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['category']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['description']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['delivery_number']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['quantity_received']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['units']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['pack_size']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['unit_cost']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['po_number']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['received_by']) . "</td>
              <td class='px-2 py-1'>" . htmlspecialchars($row['receiving_date']) . "</td>
          </tr>";
      }
      echo "</tbody></table></div>";
  } else {
      echo "<p class='text-gray-600 mt-4'>No records found.</p>";
  }
  ?>

</div>

<script>
$("#product_name").autocomplete({
  source: "store_b_finished_products_autocomplete.php",
  minLength: 1,
  select: function(event, ui) {
    $("#product_name").val(ui.item.value);
    $("#product_id").val(ui.item.id);
    $("#product_code").val(ui.item.product_code);
    $("#category").val(ui.item.category);
    $("#description").val(ui.item.description);
    return false;
  }
});
</script>

</body>
</html>
