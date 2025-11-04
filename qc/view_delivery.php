<?php
include 'db_con.php';
require('fpdf.php'); // ✅ Ensure fpdf is installed in project

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

// Fetch delivery record
if ($id > 0) {
    $delivery_qry = $conn->query("SELECT * FROM deliveries WHERE po_id = '$id'");
    if ($delivery_qry->num_rows > 0) {
        $delivery = $delivery_qry->fetch_assoc();
    }
}

// Handle PDF download
if (isset($_GET['download']) && $po) {
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial','B',14);
            $this->Cell(0,10,'Delivery Details Report',0,1,'C');
            $this->Ln(5);
        }
    }

    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','',12);

    // Supplier Info
    $sup_qry = $conn->query("SELECT * FROM suppliers WHERE id = '{$po['supplier_id']}'");
    $supplier = $sup_qry->fetch_assoc();

    $pdf->Cell(0,8,"PO #: {$po['po_no']}   Date: ".date("Y-m-d", strtotime($po['created_at'])),0,1);
    $pdf->Cell(0,8,"Supplier: {$supplier['supplier_name']} | Contact: {$supplier['supplier_contact']}",0,1);
    $pdf->Cell(0,8,"Payment Terms: {$supplier['payment_terms']}",0,1);
    $pdf->Ln(5);

    // Ordered Items
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(20,8,'Qty',1);
    $pdf->Cell(25,8,'Unit',1);
    $pdf->Cell(60,8,'Product',1);
    $pdf->Cell(30,8,'Unit Price',1);
    $pdf->Cell(30,8,'Total',1);
    $pdf->Ln();

    $pdf->SetFont('Arial','',12);
    $sub_total = 0;
    $order_items_qry = $conn->query("
        SELECT o.*, p.product_name 
        FROM order_items o 
        LEFT JOIN procurement_products p ON o.product_id = p.id 
        WHERE o.po_id = '$id'
    ");
    while($row = $order_items_qry->fetch_assoc()) {
        $productName = $row['product_name'] ?: $row['manual_name'];
        $line_total = $row['quantity'] * $row['unit_price'];
        $sub_total += $line_total;

        $pdf->Cell(20,8,$row['quantity'],1);
        $pdf->Cell(25,8,$row['unit'],1);
        $pdf->Cell(60,8,$productName,1);
        $pdf->Cell(30,8,number_format($row['unit_price'],2),1);
        $pdf->Cell(30,8,number_format($line_total,2),1);
        $pdf->Ln();
    }

    $discount_amount = ($po['discount_percentage'] / 100) * $sub_total;
    $tax_amount = ($po['tax_percentage'] / 100) * ($sub_total - $discount_amount);
    $grand_total = $sub_total - $discount_amount + $tax_amount;

    $pdf->Ln(2);
    $pdf->Cell(135,8,'Sub Total',1);
    $pdf->Cell(30,8,number_format($sub_total,2),1,1,'R');
    $pdf->Cell(135,8,"Discount ({$po['discount_percentage']}%)",1);
    $pdf->Cell(30,8,'-'.number_format($discount_amount,2),1,1,'R');
    $pdf->Cell(135,8,"Tax ({$po['tax_percentage']}%)",1);
    $pdf->Cell(30,8,'+'.number_format($tax_amount,2),1,1,'R');
    $pdf->Cell(135,8,'Total',1);
    $pdf->Cell(30,8,number_format($grand_total,2),1,1,'R');

    $pdf->Ln(10);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,8,'Delivery Details',0,1);

    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,8,"Company: {$delivery['company_name']}",0,1);
    $pdf->Cell(0,8,"Contact: {$delivery['delivery_contact']}",0,1);
    $pdf->Cell(0,8,"Expected: {$delivery['expected_delivery']}",0,1);
    $pdf->Cell(0,8,"Delivered: {$delivery['delivered_date']}",0,1);
    $pdf->Cell(0,8,"Status: ".($delivery['status'] ? 'Delivered' : 'Pending'),0,1);

    $pdf->Output("D","delivery_po_{$po['po_no']}.pdf");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Delivery</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function printContent() {
      let printContent = document.getElementById("printArea").innerHTML;
      let w = window.open('', '', 'width=900,height=650');
      w.document.write('<html><head><title>Print</title></head><body>'+printContent+'</body></html>');
      w.document.close();
      w.print();
    }
  </script>
</head>
<body class="bg-gray-100 pt-20">
<div class="p-6 sm:ml-64">
  <?php include 'navbar.php'; ?>
  <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-gray-200">
    <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-600">
        <h3 class="text-lg font-semibold text-white">
            <?= $po ? "Delivery Details for PO #{$po['po_no']}" : "PO Not Found"; ?>
        </h3>
        <div class="space-x-2">
            <a href="delivered_purchases.php" class="px-3 py-1 text-sm bg-gray-200 rounded">Back</a>
            <?php if ($po): ?>
              <button onclick="printContent()" class="px-3 py-1 text-sm bg-yellow-500 text-white rounded">Print</button>
              <a href="view_delivery.php?id=<?= $id ?>&download=1" class="px-3 py-1 text-sm bg-green-600 text-white rounded">Download PDF</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="px-6 py-4" id="printArea">
      <?php if ($po): ?>
        <!-- Supplier + PO Info -->
        <div class="grid grid-cols-2 gap-6 mb-6">
          <div class="bg-gray-50 p-4 rounded border">
            <h4 class="font-semibold text-gray-700 mb-2">Supplier Details</h4>
            <?php 
            $sup_qry = $conn->query("SELECT * FROM suppliers WHERE id = '{$po['supplier_id']}'");
            $supplier = $sup_qry->fetch_assoc();
            ?>
            <p><b>Supplier:</b> <?= $supplier['supplier_name'] ?></p>
            <p><b>Contact:</b> <?= $supplier['supplier_contact'] ?></p>
            <p><b>Payment Terms:</b> <?= $supplier['payment_terms'] ?></p>
          </div>
          <div class="bg-gray-50 p-4 rounded border text-right">
            <p><b>PO #:</b> <?= $po['po_no'] ?></p>
            <p><b>Date:</b> <?= date("Y-m-d", strtotime($po['created_at'])) ?></p>
          </div>
        </div>

        <!-- Ordered Items Table -->
        <!-- You can reuse same code block from update_delivery -->
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


        <!-- Delivery Info -->
        <div class="bg-gray-50 p-4 rounded border mt-6">
          <h4 class="font-semibold text-gray-700 mb-2">Delivery Details</h4>
          <?php if ($delivery): ?>
    <p><b>Company:</b> <?= $delivery['company_name'] ?></p>
    <p><b>Contact:</b> <?= $delivery['delivery_contact'] ?></p>
    <p><b>Expected Delivery:</b> <?= $delivery['expected_delivery'] ?></p>
    <p><b>Delivered Date:</b> <?= $delivery['delivered_date'] ?></p>
    <p><b>Status:</b> <?= $delivery['status'] ? 'Delivered' : 'Pending' ?></p>
<?php else: ?>
    <p class="text-gray-600">No delivery record found for this Purchase Order.</p>
<?php endif; ?>

    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
