<?php
require('fpdf.php');
include 'db_con.php';

// --- Fetch products ---
$sql = "SELECT id, name, product_code, category, description, created_at 
        FROM products ORDER BY id DESC";
$result = $conn->query($sql);

// --- PDF Class Setup ---
class PDF extends FPDF {
    function Header() {
        // Title
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, ' Product List Report', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Generated on: ' . date('d M Y, H:i'), 0, 1, 'C');
        $this->Ln(4);

        // Table Header
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(220, 220, 220);
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Product Name', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Product Code', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Category', 1, 0, 'C', true);
        $this->Cell(60, 8, 'Description', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Date Added', 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape for wider table
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// --- Fill Table Data ---
$count = 1;
while ($row = $result->fetch_assoc()) {
    $pdf->Cell(10, 8, $count++, 1, 0, 'C');
    $pdf->Cell(40, 8, utf8_decode($row['name']), 1);
    $pdf->Cell(30, 8, $row['product_code'], 1);
    $pdf->Cell(30, 8, $row['category'], 1);
    $pdf->Cell(60, 8, utf8_decode(substr($row['description'], 0, 50)), 1);
    $pdf->Cell(30, 8, $row['created_at'] ? date('d M Y', strtotime($row['created_at'])) : '-', 1, 1);
}

// --- No Records ---
if ($count === 1) {
    $pdf->Cell(0, 10, 'No product records found.', 1, 1, 'C');
}

// --- Output File ---
$pdf->Output('D', 'Product_List_' . date('Y-m-d') . '.pdf');
exit;
?>
