<?php
session_start();
require 'db_con.php';
require('fpdf.php');

// ✅ Fetch all chemicals (or you can filter)
$query = "SELECT * FROM chemicals_in ORDER BY date_added DESC";
$result = $conn->query($query);

// ✅ Create FPDF object
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);

// ✅ Header Section (logo + title)
$pdf->Image('images/lynn_logo.png', 10, 8, 25);
$pdf->Cell(40); // spacer
$pdf->Cell(190, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(275, 8, 'Chemicals In Report', 0, 1, 'C');
$pdf->Ln(8);

// ✅ Table Header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(10, 8, '#', 1, 0, 'C', true);
$pdf->Cell(45, 8, 'Chemical Name', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Code', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Batch No', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'RM Lot No', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'PO Number', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Remaining', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Unit Price', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Total Cost', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Date', 1, 1, 'C', true);

// ✅ Table Content
$pdf->SetFont('Arial', '', 9);
$counter = 1;
$total_sum = 0;

while ($row = $result->fetch_assoc()) {
    $pdf->Cell(10, 8, $counter++, 1, 0, 'C');
    $pdf->Cell(45, 8, $row['chemical_name'], 1);
    $pdf->Cell(25, 8, $row['chemical_code'], 1);
    $pdf->Cell(25, 8, $row['batch_no'], 1);
    $pdf->Cell(25, 8, $row['rm_lot_no'], 1);
    $pdf->Cell(30, 8, $row['po_number'], 1);
    $pdf->Cell(20, 8, number_format($row['std_quantity'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($row['remaining_quantity'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($row['unit_price'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($row['total_cost'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, $row['date_added'], 1, 1, 'C');
    $total_sum += $row['total_cost'];
}

// ✅ Total Row
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(230, 8, 'TOTAL', 1, 0, 'R', true);
$pdf->Cell(25, 8, number_format($total_sum, 2), 1, 0, 'R', true);
$pdf->Cell(25, 8, '', 1, 0, 'C', true);
$pdf->Cell(25, 8, '', 1, 1, 'C', true);

// ✅ Footer
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'R');

// ✅ Output PDF
$pdf->Output('D', 'Chemicals_List_Report.pdf');
exit;
?>
