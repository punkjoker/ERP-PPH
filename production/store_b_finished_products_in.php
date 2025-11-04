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

<div class="ml-64 p-6 max-w-4xl">
  <h2 class="text-xl font-bold mb-4">Store B Finished Products Receiving</h2>

  <!-- Form -->
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

  <!-- PHP Save Logic -->
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
      if (!$stmt) {
          echo "<p class='text-red-600 mt-2 font-semibold'>Prepare failed: " . htmlspecialchars($conn->error) . "</p>";
      } else {
          $stmt->bind_param(
              "isssssddssdsss",
              $product_id, $product_name, $product_code, $category, $description, $delivery_number,
              $quantity_received, $quantity_received, $units, $pack_size, $unit_cost,
              $po_number, $received_by, $receiving_date
          );
          if (!$stmt->execute()) {
              echo "<p class='text-red-600 mt-2 font-semibold'>Execute failed: " . htmlspecialchars($stmt->error) . "</p>";
          } else {
              echo "<p class='text-green-600 mt-2 font-semibold'>Data saved successfully!</p>";
              $stmt->close();

              $result = $conn->query("SELECT * FROM store_b_finished_products_in ORDER BY created_at DESC");
              if ($result) {
                  if ($result->num_rows > 0) {
                      echo "<div class='overflow-x-auto mt-4'>
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
              } else {
                  echo "<p class='text-yellow-600 mt-2 font-semibold'>Query failed: " . htmlspecialchars($conn->error) . "</p>";
              }
          }
      }
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
