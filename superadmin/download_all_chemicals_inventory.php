<?php
session_start();
require 'db_con.php';
require('fpdf.php');

class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('images/lynn_logo.png', 10, 8, 25);
        // Title
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 8, 'All Chemicals Inventory Report', 0, 1, 'C');
        $this->Ln(5);
        // Date
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 6, 'Generated on: ' . date('F d, Y h:i A'), 0, 1, 'C');
        $this->Ln(5);
        // Table Header
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(200, 220, 255);
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(45, 8, 'Chemical Name', 1, 0, 'C', true);
        $this->Cell(28, 8, 'Code', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Category', 1, 0, 'C', true);
        $this->Cell(28, 8, 'Remaining (kg)', 1, 0, 'C', true);
        $this->Cell(45, 8, 'Description', 1, 0, 'C', true);
        $this->Cell(28, 8, 'Date Added', 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

// Fetch data
$query = "
    SELECT 
        c.id,
        c.chemical_name, 
        c.chemical_code,
        c.category,
        c.description,
        c.created_at,
        IFNULL(SUM(ci.remaining_quantity), 0) AS total_remaining
    FROM chemical_names c
    LEFT JOIN chemicals_in ci ON c.chemical_code = ci.chemical_code
    WHERE c.main_category = 'chemicals'
    GROUP BY c.chemical_code
    ORDER BY c.id DESC
";
$result = $conn->query($query);

// PDF setup
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// Data rows
$counter = 1;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(10, 7, $counter++, 1, 0, 'C');
        $pdf->Cell(45, 7, substr($row['chemical_name'], 0, 28), 1, 0);
        $pdf->Cell(28, 7, $row['chemical_code'], 1, 0);
        $pdf->Cell(25, 7, $row['category'], 1, 0);
        $pdf->Cell(28, 7, number_format($row['total_remaining'], 2), 1, 0, 'R');
        $pdf->Cell(45, 7, substr($row['description'], 0, 35), 1, 0);
        $pdf->Cell(28, 7, $row['created_at'], 1, 1);
    }
} else {
    $pdf->Cell(0, 10, 'No chemical inventory data found.', 1, 1, 'C');
}

$pdf->Output('D', 'All_Chemicals_Inventory.pdf');
exit;
?>
