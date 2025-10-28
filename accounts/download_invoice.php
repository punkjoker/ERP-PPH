<?php
require('fpdf.php');
include 'db_con.php';

$id = intval($_GET['id'] ?? 0);
$qry = $conn->query("SELECT * FROM po_list WHERE id = '$id'");
$po = $qry->fetch_assoc();

// Supplier Info
$supplier = $conn->query("SELECT * FROM suppliers WHERE id = '{$po['supplier_id']}'")->fetch_assoc();

$pdf = new FPDF();
$pdf->AddPage();

// --- COLORS ---
$blue = [40, 75, 130];   // corporate navy
$lightBlue = [220, 230, 245]; // soft table header background
$gray = [240, 240, 240];

// --- HEADER ---
$pdf->Image('images/lynn_logo.png',150,10,40);
$pdf->SetFont('Arial','B',20);
$pdf->SetTextColor($blue[0], $blue[1], $blue[2]);
$pdf->Cell(0,15,'PURCHASE ORDER',0,1,'L');
$pdf->Ln(5);

$pdf->SetDrawColor($blue[0], $blue[1], $blue[2]);
$pdf->SetLineWidth(0.6);
$pdf->Line(10, 35, 200, 35);
$pdf->Ln(5);

// --- SUPPLIER DETAILS ---
$pdf->SetFont('Arial','B',12);
$pdf->SetTextColor(0);
$pdf->Cell(100,8,'Supplier Details',0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(100,6,$supplier['supplier_name'],0,1);
$pdf->Cell(100,6,$supplier['payment_terms'],0,1);
$pdf->Cell(100,6,$supplier['supplier_contact'],0,1);
$pdf->Ln(5);

// --- PO DETAILS ---
$pdf->SetFont('Arial','B',12);
$pdf->Cell(40,6,'PO Number:',0,0);
$pdf->SetFont('Arial','',11);
$pdf->Cell(40,6,$po['po_no'],0,1);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(40,6,'Date:',0,0);
$pdf->SetFont('Arial','',11);
$pdf->Cell(40,6,date("Y-m-d", strtotime($po['created_at'])),0,1);
$pdf->Ln(10);

// --- TABLE HEADER ---
$pdf->SetFillColor($blue[0], $blue[1], $blue[2]);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(15,8,'#',1,0,'C',true);
$pdf->Cell(25,8,'Qty',1,0,'C',true);
$pdf->Cell(25,8,'Unit',1,0,'C',true);
$pdf->Cell(60,8,'Product',1,0,'C',true);
$pdf->Cell(35,8,'Unit Price',1,0,'C',true);
$pdf->Cell(35,8,'Total',1,1,'C',true);

// --- TABLE BODY ---
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(0);
$items = $conn->query("SELECT o.*, p.product_name 
                       FROM order_items o 
                       LEFT JOIN procurement_products p ON o.product_id = p.id 
                       WHERE o.po_id = '$id'");

$sub_total = 0;
$count = 1;

while($row = $items->fetch_assoc()){
    $productName = $row['product_name'] ?: $row['manual_name'];
    $line_total = $row['quantity'] * $row['unit_price'];
    $sub_total += $line_total;

    $pdf->SetFillColor(($count % 2 == 0) ? 255 : $gray[0], $gray[1], $gray[2]);
    $pdf->Cell(15,8,$count,1,0,'C',true);
    $pdf->Cell(25,8,$row['quantity'],1,0,'C',true);
    $pdf->Cell(25,8,$row['unit'],1,0,'C',true);
    $pdf->Cell(60,8,$productName,1,0,'L',true);
    $pdf->Cell(35,8,number_format($row['unit_price'],2),1,0,'R',true);
    $pdf->Cell(35,8,number_format($line_total,2),1,1,'R',true);

    $count++;
}

// --- TOTALS ---
$discount = $po['discount_amount'];
$tax = $po['tax_amount'];
$total = $sub_total - $discount + $tax;

$pdf->Ln(6);
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor($lightBlue[0], $lightBlue[1], $lightBlue[2]);

$pdf->Cell(140,8,'Subtotal',1,0,'R',true);
$pdf->Cell(40,8,number_format($sub_total,2),1,1,'R',true);

$pdf->Cell(140,8,'Discount',1,0,'R',true);
$pdf->Cell(40,8,'- '.number_format($discount,2),1,1,'R',true);

$pdf->Cell(140,8,'Tax',1,0,'R',true);
$pdf->Cell(40,8,'+ '.number_format($tax,2),1,1,'R',true);

$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(100,180,100); // green for total
$pdf->SetTextColor(255,255,255);
$pdf->Cell(140,10,'GRAND TOTAL',1,0,'R',true);
$pdf->Cell(40,10,number_format($total,2),1,1,'R',true);

$pdf->Ln(12);

// --- FOOTER NOTE ---
$pdf->SetFont('Arial','I',10);
$pdf->SetTextColor(80,80,80);
$pdf->MultiCell(0,6,"Thank you for your business!\nThis is a system-generated purchase order from LynnTech Management ERP.",0,'C');

$pdf->Output('I',"Purchase_Order_{$po['po_no']}.pdf");
?>
