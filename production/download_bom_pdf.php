<?php
session_start();
require 'db_con.php';
require('fpdf.php');

// Get BOM ID
if (!isset($_GET['id'])) {
    die("Request ID missing");
}
$bom_id = intval($_GET['id']);

// Fetch BOM main info
$sql = "SELECT b.id, b.product_id, p.name as product_name, b.status, b.description, b.requested_by, b.bom_date, 
               b.issued_by, b.remarks, b.issue_date
        FROM bill_of_materials b
        JOIN products p ON b.product_id = p.id
        WHERE b.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$bom = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bom) {
    die("BOM request not found.");
}

// Fetch BOM items
$sql = "SELECT i.id, i.chemical_id, c.chemical_name, i.quantity_requested, 
               i.unit, i.unit_price, i.total_cost
        FROM bill_of_material_items i
        JOIN chemicals_in c ON i.chemical_id = c.id
        WHERE i.bom_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$chemicals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();

// Header
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'Bill of Materials Report',0,1,'C');
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,10,'Production Department',0,1,'C');
$pdf->Ln(5);

// Product Info
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,'Product Details',0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,8,'Product Name: ' . $bom['product_name'],0,1);
$pdf->Cell(0,8,'Status: ' . $bom['status'],0,1);
$pdf->Ln(3);

// Chemicals Table
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,'Chemicals & Costs',0,1);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(60,8,'Chemical',1);
$pdf->Cell(30,8,'Qty Requested',1);
$pdf->Cell(20,8,'Unit',1);
$pdf->Cell(30,8,'Unit Price',1);
$pdf->Cell(40,8,'Total Cost',1);
$pdf->Ln();

$pdf->SetFont('Arial','',10);
$total_cost = 0;
foreach ($chemicals as $c) {
    $pdf->Cell(60,8,$c['chemical_name'],1);
    $pdf->Cell(30,8,$c['quantity_requested'],1);
    $pdf->Cell(20,8,$c['unit'],1);
    $pdf->Cell(30,8,number_format($c['unit_price'],2),1);
    $pdf->Cell(40,8,number_format($c['total_cost'],2),1);
    $pdf->Ln();
    $total_cost += $c['total_cost'];
}

// Total
$pdf->SetFont('Arial','B',11);
$pdf->Cell(140,8,'Total Production Cost',1,0,'R');
$pdf->Cell(40,8,number_format($total_cost,2),1,1,'C');
$pdf->Ln(5);

// Request Info
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,'Request Information',0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,8,'Requested By: ' . $bom['requested_by'],0,1);
$pdf->Cell(0,8,'Description: ' . $bom['description'],0,1);
$pdf->Cell(0,8,'Date: ' . $bom['bom_date'],0,1);
$pdf->Ln(5);
$pdf->Cell(0,8,'Signature: ____________________________',0,1);
$pdf->Ln(5);

// Issuing Info
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,'Issuing Information',0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,8,'Issued By: ' . $bom['issued_by'],0,1);
$pdf->Cell(0,8,'Remarks: ' . $bom['remarks'],0,1);
$pdf->Cell(0,8,'Date of Issue: ' . $bom['issue_date'],0,1);
$pdf->Ln(5);
$pdf->Cell(0,8,'Signature: ____________________________',0,1);

// Output
$pdf->Output('D', 'BOM_Report_'.$bom_id.'.pdf');  // "D" forces download
?>
