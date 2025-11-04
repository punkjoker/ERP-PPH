<?php
require('fpdf.php');
require('db_con.php');

// --- Filters ---
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// --- Build Query ---
$sql = "SELECT * FROM rejected_chemicals_in WHERE 1=1";

if ($from_date && $to_date) {
    $sql .= " AND date_added BETWEEN '$from_date' AND '$to_date'";
}
if (!empty($search_name)) {
    $sql .= " AND chemical_name LIKE '%" . $conn->real_escape_string($search_name) . "%'";
}

$sql .= " ORDER BY date_added DESC";
$result = $conn->query($sql);

// --- Initialize PDF ---
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// --- Header ---
$pdf->Image('images/lynn_logo.png', 10, 8, 25);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'REJECTED CHEMICALS REPORT', 0, 1, 'C');
$pdf->Ln(5);

// --- Date Range ---
$pdf->SetFont('Arial', '', 10);
if ($from_date && $to_date) {
    $pdf->Cell(0, 6, "Report Period: $from_date to $to_date", 0, 1, 'C');
}
if (!empty($search_name)) {
    $pdf->Cell(0, 6, "Chemical Search: " . ucfirst($search_name), 0, 1, 'C');
}
$pdf->Ln(5);

// --- Table Header ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(255, 230, 230);
$pdf->Cell(10, 10, '#', 1, 0, 'C', true);
$pdf->Cell(35, 10, 'Chemical Code', 1, 0, 'C', true);
$pdf->Cell(50, 10, 'Chemical Name', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Batch No', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'RM Lot No', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Orig Qty', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Remain Qty', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Unit Price', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Total Cost', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Date Added', 1, 1, 'C', true);

// --- Table Data ---
$pdf->SetFont('Arial', '', 9);
$counter = 1;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(10, 8, $counter++, 1, 0, 'C');
        $pdf->Cell(35, 8, $row['chemical_code'], 1);
        $pdf->Cell(50, 8, $row['chemical_name'], 1);
        $pdf->Cell(30, 8, $row['batch_no'], 1);
        $pdf->Cell(30, 8, $row['rm_lot_no'], 1);
        $pdf->Cell(25, 8, $row['original_quantity'], 1, 0, 'R');
        $pdf->Cell(25, 8, $row['remaining_quantity'], 1, 0, 'R');
        $pdf->Cell(25, 8, number_format($row['unit_price'], 2), 1, 0, 'R');
        $pdf->Cell(30, 8, number_format($row['total_cost'], 2), 1, 0, 'R');
        $pdf->Cell(30, 8, $row['date_added'], 1, 1, 'C');
    }
} else {
    $pdf->Cell(290, 10, 'No records found for the selected filters.', 1, 1, 'C');
}

// --- Footer ---
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'R');

$pdf->Output('D', 'Rejected_Chemicals_Report.pdf');
?>
