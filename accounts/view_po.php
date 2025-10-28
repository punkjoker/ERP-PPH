<?php
include 'db_con.php';

// Always define $id
$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $qry = $conn->query("SELECT * FROM po_list WHERE id = '$id'");
    if ($qry->num_rows > 0) {
        foreach ($qry->fetch_assoc() as $k => $v) {
            $$k = $v;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Purchase Order</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
@media print {
  body * {
    visibility: hidden;
  }
  #out_print, #out_print * {
    visibility: visible;
  }
  #out_print {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}
</style>

</head>
<body class="bg-gray-100 pt-20">
<div class="p-6 sm:ml-64">
  <?php include 'navbar.php'; ?>

<div class="bg-white shadow-lg rounded-lg overflow-hidden border border-gray-200">
    <!-- Header -->
    <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600">
        <h3 class="text-lg font-semibold text-white">
            <?= isset($id) ? "Purchase Order #{$po_no}" : "PO Not Found"; ?>
        </h3>
        <div class="space-x-2">
            <button onclick="window.print()" class="px-3 py-1 text-sm font-medium text-white bg-green-500 rounded hover:bg-green-600">Print</button>
          
            <a href="download_invoice.php?id=<?= $id ?>" 
   class="px-3 py-1 text-sm font-medium text-white bg-blue-500 rounded hover:bg-blue-600">
   Download Invoice
</a>

            <a href="approved_purchases.php" class="px-3 py-1 text-sm font-medium text-gray-700 bg-gray-200 rounded hover:bg-gray-300">Back</a>
        </div>
    </div>

    <!-- Body -->
    <div class="px-6 py-4" id="out_print">
        <!-- Supplier + PO Info -->
<div class="grid grid-cols-2 gap-6 mb-6">
    <div class="bg-gray-50 p-4 rounded border">
    <h4 class="font-semibold text-gray-700 mb-2">Supplier Details</h4>
    <?php 
    $sup_qry = $conn->query("SELECT * FROM suppliers WHERE id = '{$supplier_id}'");
    $supplier = $sup_qry->fetch_assoc();
    ?>
    <p><span class="font-semibold text-gray-700">Supplier Name:</span> <span class="text-gray-900 font-medium"><?= $supplier['supplier_name'] ?></span></p>
    <p><span class="font-semibold text-gray-700">Payment Terms:</span> <span class="text-gray-600"><?= $supplier['payment_terms'] ?></span></p>
    <p><span class="font-semibold text-gray-700">Contact:</span> <span class="text-gray-600"><?= $supplier['supplier_contact'] ?></span></p>
</div>

    <div class="bg-gray-50 p-4 rounded border text-right">
        <img src="images/lynn_logo.png" alt="Company Logo" class="h-16 ml-auto mb-2">
        <p><span class="font-semibold text-gray-700">PO #:</span> 
           <span class="text-gray-900"><?= $po_no ?></span></p>
        <p><span class="font-semibold text-gray-700">Date:</span> 
           <span class="text-gray-900"><?= date("Y-m-d", strtotime($created_at)) ?></span></p>
    </div>
</div> <!-- ✅ CLOSE the grid here -->

        <!-- Items Table -->
        <div class="overflow-x-auto">
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
            $sub_total = 0;
            $order_items_qry = $conn->query("SELECT o.*, p.product_name 
                                            FROM order_items o 
                                            LEFT JOIN procurement_products p 
                                            ON o.product_id = p.id 
                                            WHERE o.po_id = '$id'");
            while($row = $order_items_qry->fetch_assoc()):
                $productName = $row['product_name'] ?: $row['manual_name'];
                $line_total = $row['quantity'] * $row['unit_price'];
                $sub_total += $line_total;
            ?>
            <tr>
                <td class="px-3 py-2 text-sm text-gray-700"><?= $row['quantity'] ?></td>
                <td class="px-3 py-2 text-sm text-gray-700"><?= $row['unit'] ?></td>
                <td class="px-3 py-2 text-sm font-medium text-gray-900"><?= $productName ?></td>
                <td class="px-3 py-2 text-sm text-gray-700"><?= number_format($row['unit_price'], 2) ?></td>
                <td class="px-3 py-2 text-sm text-right text-gray-900 font-semibold"><?= number_format($line_total, 2) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot class="bg-gray-50">
            <tr>
                <th colspan="4" class="px-3 py-2 text-right text-gray-700">Sub Total</th>
                <th class="px-3 py-2 text-right text-gray-900 font-semibold"><?= number_format($sub_total, 2) ?></th>
            </tr>
            <tr>
                <th colspan="4" class="px-3 py-2 text-right text-gray-700">Discount (<?= $discount_percentage ?>%)</th>
                <th class="px-3 py-2 text-right text-gray-900 font-semibold">- <?= number_format($discount_amount, 2) ?></th>
            </tr>
            <tr>
                <th colspan="4" class="px-3 py-2 text-right text-gray-700">Tax (<?= $tax_percentage ?>%)</th>
                <th class="px-3 py-2 text-right text-gray-900 font-semibold">+ <?= number_format($tax_amount, 2) ?></th>
            </tr>
            <tr class="bg-gray-200">
                <th colspan="4" class="px-3 py-2 text-right text-gray-900 font-bold">Total</th>
                <th class="px-3 py-2 text-right text-gray-900 font-bold"><?= number_format($sub_total - $discount_amount + $tax_amount, 2) ?></th>
            </tr>
        </tfoot>
    </table>
</div>

        <!-- Notes + Status -->
        <div class="grid grid-cols-2 gap-6 mt-6">
            <div class="bg-gray-50 p-4 rounded border">
                <h4 class="font-semibold text-gray-700 mb-1">Notes</h4>
                <p class="text-gray-600"><?= isset($notes) ? nl2br($notes) : '—' ?></p>
            </div>
            <div class="flex items-center justify-end">
                <span class="px-4 py-2 rounded text-sm font-medium
                    <?php 
                        switch($status){
                            case 1: echo 'bg-green-100 text-green-700'; break;
                            case 2: echo 'bg-red-100 text-red-700'; break;
                            default: echo 'bg-gray-200 text-gray-700'; break;
                        }
                    ?>">
                    <?php 
                        switch($status){
                            case 1: echo "Approved"; break;
                            case 2: echo "Denied"; break;
                            default: echo "Pending"; break;
                        }
                    ?>
                </span>
            </div>
        </div>
    </div>
</div>
</body>
</html>
