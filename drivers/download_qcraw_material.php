<?php
require('fpdf.php');
require 'db_con.php';

// Capture filters
$where = "1=1";
$params = [];
$types = "";

// Date filter
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where .= " AND DATE(date_added) BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
}

// Chemical name filter
if (!empty($_GET['search_name'])) {
    $where .= " AND chemical_name LIKE ?";
    $params[] = '%' . $_GET['search_name'] . '%';
    $types .= "s";
}

// Query
$query = "SELECT * FROM chemicals_in WHERE $where ORDER BY date_added DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// === PDF CLASS ===
class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('images/lynn_logo.png', 10, 8, 25);

        // Company Name
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(0, 51, 153);
        $this->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(2);

        // Report Title
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'QC Raw Material Inspection Report', 0, 1, 'C');
        $this->Ln(3);

        // Date
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Generated on: ' . date('d M Y, h:i A'), 0, 1, 'C');
        $this->Ln(4);

        // Table Header
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(224, 235, 255);
        $this->Cell(8, 8, '#', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Chemical Name', 1, 0, 'C', true);
        $this->Cell(28, 8, 'RM Lot No', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Std Qty', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Remain Qty', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Total Cost', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Unit Price', 1, 0, 'C', true);
        $this->Cell(28, 8, 'Date Added', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Status', 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

// === PDF GENERATION ===
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

$i = 1;
$total_cost = 0;

while ($row = $result->fetch_assoc()) {
    $pdf->Cell(8, 8, $i++, 1);
    $pdf->Cell(40, 8, utf8_decode($row['chemical_name']), 1);
    $pdf->Cell(28, 8, $row['rm_lot_no'], 1);
    $pdf->Cell(20, 8, number_format($row['std_quantity'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($row['remaining_quantity'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($row['total_cost'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($row['unit_price'], 2), 1, 0, 'R');
    $pdf->Cell(28, 8, date('d-M-Y', strtotime($row['date_added'])), 1);
    $pdf->Cell(25, 8, $row['status'], 1, 1, 'C');

    $total_cost += $row['total_cost'];
}

// Summary
$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, 'Summary', 0, 1, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(100, 8, 'Total Chemicals: ' . ($i - 1), 0, 1);
$pdf->Cell(100, 8, 'Total Cost: Ksh ' . number_format($total_cost, 2), 0, 1);

// Output
$pdf->Output('D', 'QC_Raw_Material_Report.pdf');
exit;
?>
