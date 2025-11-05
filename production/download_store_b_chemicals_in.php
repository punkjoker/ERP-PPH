<?php
require('fpdf.php');
require('db_con.php');

class PDF extends FPDF
{
    // Header
    function Header()
    {
        // Logo
        $this->Image('images/lynn_logo.png', 10, 8, 20);
        // Title
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'STORE B - CHEMICALS RECEIVED REPORT', 0, 1, 'C');
        $this->Ln(4);

        // Generated date
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 8, 'Generated on: ' . date('d M Y, h:i A'), 0, 1, 'C');
        $this->Ln(2);

        // Table header
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetFillColor(220, 220, 220);
        $this->Cell(35, 8, 'Chemical Name', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Main Category', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Group Name', 1, 0, 'C', true);
        $this->Cell(18, 8, 'Group Code', 1, 0, 'C', true);
        $this->Cell(22, 8, 'Chemical Code', 1, 0, 'C', true);
        $this->Cell(15, 8, 'Qty', 1, 0, 'C', true);
        $this->Cell(18, 8, 'Units', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Pack Size', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Received By', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Receiving Date', 1, 1, 'C', true);
    }

    // Footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8.5);

$query = "SELECT * FROM store_b_chemicals_in ORDER BY receiving_date DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(35, 7, substr($row['chemical_name'], 0, 25), 1);
        $pdf->Cell(25, 7, substr($row['main_category'], 0, 20), 1);
        $pdf->Cell(25, 7, substr($row['group_name'], 0, 20), 1);
        $pdf->Cell(18, 7, $row['group_code'], 1, 0, 'C');
        $pdf->Cell(22, 7, $row['chemical_code'], 1, 0, 'C');
        $pdf->Cell(15, 7, $row['quantity_received'], 1, 0, 'C');
        $pdf->Cell(18, 7, $row['units'], 1, 0, 'C');
        $pdf->Cell(20, 7, $row['pack_size'], 1, 0, 'C');
        $pdf->Cell(30, 7, substr($row['received_by'], 0, 18), 1);
        $pdf->Cell(30, 7, $row['receiving_date'], 1, 1, 'C');
    }
} else {
    $pdf->Cell(0, 10, 'No chemical records found in Store B.', 1, 1, 'C');
}

$pdf->Output('D', 'StoreB_Chemicals_Received_Report.pdf');
?>
