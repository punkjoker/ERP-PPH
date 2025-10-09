<?php
include 'db_con.php';

// Get PO ID
$id = intval($_GET['id'] ?? 0);
$po = null;
$delivery = null;

// Fetch PO details
if ($id > 0) {
    $qry = $conn->query("SELECT * FROM po_list WHERE id = '$id'");
    if ($qry->num_rows > 0) {
        $po = $qry->fetch_assoc();
    }
}

// Fetch or create delivery record
if ($id > 0) {
    $delivery_qry = $conn->query("SELECT * FROM deliveries WHERE po_id = '$id'");
    if ($delivery_qry->num_rows > 0) {
        $delivery = $delivery_qry->fetch_assoc();
    } else {
        $conn->query("INSERT INTO deliveries (po_id, status) VALUES ('$id', 0)");
        $delivery_qry = $conn->query("SELECT * FROM deliveries WHERE po_id = '$id'");
        $delivery = $delivery_qry->fetch_assoc();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = !empty($_POST['company_name']) ? "'".$conn->real_escape_string($_POST['company_name'])."'" : "NULL";
    $delivery_contact = !empty($_POST['delivery_contact']) ? "'".$conn->real_escape_string($_POST['delivery_contact'])."'" : "NULL";
    $expected = !empty($_POST['expected_delivery']) ? "'".$conn->real_escape_string($_POST['expected_delivery'])."'" : "NULL";
    $delivered = !empty($_POST['delivered_date']) ? "'".$conn->real_escape_string($_POST['delivered_date'])."'" : "NULL";
    $status = intval($_POST['status'] ?? 0);

    $conn->query("UPDATE deliveries 
                  SET company_name = $company_name,
                      delivery_contact = $delivery_contact,
                      expected_delivery = $expected, 
                      delivered_date = $delivered, 
                      status = $status 
                  WHERE po_id = '$id'");

    header("Location: update_delivery.php?id=$id&success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Update Delivery</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 pt-20">
<div class="p-6 sm:ml-64">
  <?php include 'navbar.php'; ?>

  <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-gray-200">
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600">
        <h3 class="text-lg font-semibold text-white">
            <?= $po ? "Update Delivery for PO #{$po['po_no']}" : "PO Not Found"; ?>
        </h3>
        <a href="delivered_purchases.php" class="px-3 py-1 text-sm font-medium text-gray-700 bg-gray-200 rounded hover:bg-gray-300">Back</a>
    </div>

    <!-- Body -->
    <div class="px-6 py-4">
      <?php if (isset($_GET['success'])): ?>
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">Delivery details updated successfully.</div>
      <?php endif; ?>

      <?php if ($po): ?>
        <!-- Supplier + PO Info -->
        <div class="grid grid-cols-2 gap-6 mb-6">
          <div class="bg-gray-50 p-4 rounded border">
            <h4 class="font-semibold text-gray-700 mb-2">Supplier Details</h4>
            <?php 
            $sup_qry = $conn->query("SELECT * FROM suppliers WHERE id = '{$po['supplier_id']}'");
            $supplier = $sup_qry->fetch_assoc();
            ?>
            <p><span class="font-semibold">Supplier:</span> <?= $supplier['supplier_name'] ?></p>
            <p><span class="font-semibold">Contact:</span> <?= $supplier['supplier_contact'] ?></p>
            <p><span class="font-semibold">Payment Terms:</span> <?= $supplier['payment_terms'] ?></p>
          </div>
          <div class="bg-gray-50 p-4 rounded border text-right">
            <p><span class="font-semibold">PO #:</span> <?= $po['po_no'] ?></p>
            <p><span class="font-semibold">Date:</span> <?= date("Y-m-d", strtotime($po['created_at'])) ?></p>
          </div>
        </div>
<!-- Ordered Items -->
<div class="overflow-x-auto mb-6">
  <h4 class="font-semibold text-gray-700 mb-2">Ordered Items</h4>
  <table class="min-w-full border border-gray-200 divide-y divide-gray-200 rounded-lg">
      <thead class="bg-gray-100">
          <tr>
              <th class="px-3 py-2 text-left text-sm font-semibold text-gray-700">Qty</th>
              <th class="px-3 py-2 text-left text-sm font-semibold text-gray-700">Unit</th>
              <th class="px-3 py-2 text-left text-sm font-semibold text-gray-700">Product</th>
              <th class="px-3 py-2 text-left text-sm font-semibold text-gray-700">Unit Price</th>
              <th class="px-3 py-2 text-left text-sm font-semibold text-gray-700">Total</th>
          </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
          <?php 
          // ✅ Fetch order items like in view_po.php
          $order_items_qry = $conn->query("
    SELECT o.*, p.product_name 
    FROM order_items o 
    LEFT JOIN procurement_products p ON o.product_id = p.id 
    WHERE o.po_id = '$id'
");

if ($order_items_qry && $order_items_qry->num_rows > 0):
    while($row = $order_items_qry->fetch_assoc()):
        $productName = $row['product_name'] ?: $row['manual_name'];
        $line_total = $row['quantity'] * $row['unit_price']; // ✅ calculate total here
?>
<tr>
    <td class="px-3 py-2 text-sm text-gray-700"><?= $row['quantity'] ?></td>
    <td class="px-3 py-2 text-sm text-gray-700"><?= $row['unit'] ?></td>
    <td class="px-3 py-2 text-sm font-medium text-gray-900"><?= $productName ?></td>
    <td class="px-3 py-2 text-sm text-gray-700"><?= number_format($row['unit_price'], 2) ?></td>
    <td class="px-3 py-2 text-sm text-right text-gray-900 font-semibold"><?= number_format($line_total, 2) ?></td>
</tr>
<?php 
    endwhile; 
else: ?>
<tr>
    <td colspan="5" class="px-3 py-2 text-center text-gray-500">No items found for this PO.</td>
</tr>
<?php endif; ?>

      </tbody>
      <tfoot class="bg-gray-50">
    <?php 
    // calculate totals like in view_po
    $sub_total = 0;
    $order_items_qry2 = $conn->query("
        SELECT o.*, p.product_name 
        FROM order_items o 
        LEFT JOIN procurement_products p ON o.product_id = p.id 
        WHERE o.po_id = '$id'
    ");
    while($r2 = $order_items_qry2->fetch_assoc()) {
        $sub_total += ($r2['quantity'] * $r2['unit_price']);
    }

    $discount_amount = ($po['discount_percentage'] / 100) * $sub_total;
    $tax_amount = ($po['tax_percentage'] / 100) * ($sub_total - $discount_amount);
    $grand_total = $sub_total - $discount_amount + $tax_amount;
    ?>
    <tr>
        <th colspan="4" class="px-3 py-2 text-right text-gray-700">Sub Total</th>
        <th class="px-3 py-2 text-right text-gray-900 font-semibold"><?= number_format($sub_total, 2) ?></th>
    </tr>
    <tr>
        <th colspan="4" class="px-3 py-2 text-right text-gray-700">Discount (<?= $po['discount_percentage'] ?>%)</th>
        <th class="px-3 py-2 text-right text-gray-900 font-semibold">- <?= number_format($discount_amount, 2) ?></th>
    </tr>
    <tr>
        <th colspan="4" class="px-3 py-2 text-right text-gray-700">Tax (<?= $po['tax_percentage'] ?>%)</th>
        <th class="px-3 py-2 text-right text-gray-900 font-semibold">+ <?= number_format($tax_amount, 2) ?></th>
    </tr>
    <tr class="bg-gray-200">
        <th colspan="4" class="px-3 py-2 text-right text-gray-900 font-bold">Total</th>
        <th class="px-3 py-2 text-right text-gray-900 font-bold"><?= number_format($grand_total, 2) ?></th>
    </tr>
</tfoot>

  </table>
</div>


        <!-- Delivery Form -->
        <form method="post" class="bg-gray-50 p-4 rounded border">
          <h4 class="font-semibold text-gray-700 mb-4">Delivery Details</h4>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Company/Delivery Name</label>
              <input type="text" name="company_name" value="<?= $delivery['company_name'] ?? '' ?>" class="w-full px-3 py-2 border rounded shadow-sm">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Contact</label>
              <input type="text" name="delivery_contact" value="<?= $delivery['delivery_contact'] ?? '' ?>" class="w-full px-3 py-2 border rounded shadow-sm">
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Expected Delivery Date</label>
              <input type="date" name="expected_delivery" value="<?= $delivery['expected_delivery'] ?? '' ?>" class="w-full px-3 py-2 border rounded shadow-sm">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Delivered Date</label>
              <input type="date" name="delivered_date" value="<?= $delivery['delivered_date'] ?? '' ?>" class="w-full px-3 py-2 border rounded shadow-sm">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select name="status" class="w-full px-3 py-2 border rounded shadow-sm">
                <option value="0" <?= $delivery['status'] == 0 ? 'selected' : '' ?>>Pending</option>
                <option value="1" <?= $delivery['status'] == 1 ? 'selected' : '' ?>>Delivered</option>
              </select>
            </div>
          </div>

          <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded shadow">Save Delivery</button>
        </form>
      <?php else: ?>
        <div class="p-4 text-gray-600">Purchase Order not found.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
