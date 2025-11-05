<?php
session_start();
require 'db_con.php';
require('fpdf.php');

// ✅ Fetch inventory list
$query = "
    SELECT 
        id,
        chemical_name,
        main_category,
        group_name,
        group_code,
        chemical_code,
        category,
        description,
        created_at
    FROM chemical_names
    ORDER BY id DESC
";
$result = $conn->query($query);

// ✅ Initialize PDF
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// ✅ Header with company logo and name
$pdf->Image('images/lynn_logo.png', 10, 8, 25);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 8, 'Inventory List Report', 0, 1, 'C');
$pdf->Ln(5);

// ✅ Table Header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(220, 230, 255);
$pdf->Cell(10, 8, '#', 1, 0, 'C', true);
$pdf->Cell(55, 8, 'Item Name', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Main Category', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Group Name', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Group Code', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Item Code', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Category', 1, 0, 'C', true);
$pdf->Cell(70, 8, 'Description', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Date Added', 1, 1, 'C', true);

// ✅ Table Body
$pdf->SetFont('Arial', '', 9);
$counter = 1;

while ($row = $result->fetch_assoc()) {
    $pdf->Cell(10, 8, $counter++, 1);
    $pdf->Cell(55, 8, $row['chemical_name'], 1);
    $pdf->Cell(40, 8, $row['main_category'], 1);
    $pdf->Cell(35, 8, $row['group_name'], 1);
    $pdf->Cell(25, 8, $row['group_code'], 1);
    $pdf->Cell(25, 8, $row['chemical_code'], 1);
    $pdf->Cell(30, 8, $row['category'], 1);
    $pdf->Cell(70, 8, substr($row['description'], 0, 35), 1);
    $pdf->Cell(35, 8, $row['created_at'], 1, 1);
}

// ✅ Footer
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 10, 'Generated on: ' . date('d M Y, h:i A'), 0, 1, 'R');

// ✅ Output
$pdf->Output('D', 'Inventory_List_Report.pdf');
exit;
?>
