<?php
require('fpdf.php');
include 'db_con.php';

$id = intval($_GET['id'] ?? 0);
$qry = $conn->query("SELECT * FROM po_list WHERE id = '$id'");
$po = $qry->fetch_assoc();

// Supplier
$supplier = $conn->query("SELECT * FROM suppliers WHERE id = '{$po['supplier_id']}'")->fetch_assoc();

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();

// Logo
$pdf->Image('images/lynn_logo.png',150,10,40);
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Purchase Order',0,1,'L');
$pdf->Ln(10);

// Supplier Info
$pdf->SetFont('Arial','B',12);
$pdf->Cell(100,6,'Supplier Details',0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(100,6,$supplier['supplier_name'],0,1);

$pdf->Cell(100,6,$supplier['payment_terms'],0,1);
$pdf->Cell(100,6,$supplier['supplier_contact'],0,1);
$pdf->Ln(5);

// PO Info
$pdf->SetFont('Arial','B',12);
$pdf->Cell(40,6,'PO #: ',0,0);
$pdf->SetFont('Arial','',11);
$pdf->Cell(40,6,$po['po_no'],0,1);

$pdf->SetFont('Arial','B',12);
$pdf->Cell(40,6,'Date: ',0,0);
$pdf->SetFont('Arial','',11);
$pdf->Cell(40,6,date("Y-m-d", strtotime($po['created_at'])),0,1);
$pdf->Ln(10);

// Table Header
$pdf->SetFont('Arial','B',11);
$pdf->Cell(20,8,'Qty',1);
$pdf->Cell(25,8,'Unit',1);
$pdf->Cell(55,8,'Product',1);
$pdf->Cell(40,8,'Unit Price',1);
$pdf->Cell(40,8,'Total',1);
$pdf->Ln();

$sub_total = 0;
$items = $conn->query("SELECT o.*, p.product_name 
                       FROM order_items o 
                       LEFT JOIN procurement_products p ON o.product_id = p.id 
                       WHERE o.po_id = '$id'");
$pdf->SetFont('Arial','',10);

while($row = $items->fetch_assoc()){
    $productName = $row['product_name'] ?: $row['manual_name'];
    $line_total = $row['quantity'] * $row['unit_price'];
    $sub_total += $line_total;

    $pdf->Cell(20,8,$row['quantity'],1);
    $pdf->Cell(25,8,$row['unit'],1);
    $pdf->Cell(55,8,$productName,1);
    $pdf->Cell(40,8,number_format($row['unit_price'],2),1,0,'R');
    $pdf->Cell(40,8,number_format($line_total,2),1,0,'R');
    $pdf->Ln();
}

// Totals
$discount = $po['discount_amount'];
$tax = $po['tax_amount'];
$total = $sub_total - $discount + $tax;

$pdf->SetFont('Arial','B',11);
$pdf->Cell(140,8,'Sub Total',1);
$pdf->Cell(40,8,number_format($sub_total,2),1,0,'R');
$pdf->Ln();
$pdf->Cell(140,8,'Discount',1);
$pdf->Cell(40,8,'-'.number_format($discount,2),1,0,'R');
$pdf->Ln();
$pdf->Cell(140,8,'Tax',1);
$pdf->Cell(40,8,'+'.number_format($tax,2),1,0,'R');
$pdf->Ln();
$pdf->Cell(140,8,'Grand Total',1);
$pdf->Cell(40,8,number_format($total,2),1,0,'R');

$pdf->Output('D',"Purchase_Order_{$po['po_no']}.pdf");
