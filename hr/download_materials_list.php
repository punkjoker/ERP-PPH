<?php
require('fpdf.php');
require('db_con.php');

// --- Filters ---
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// --- Query ---
$query = "SELECT * FROM materials WHERE 1=1";

if (!empty($from_date) && !empty($to_date)) {
    $query .= " AND DATE(created_at) BETWEEN '$from_date' AND '$to_date'";
}
if (!empty($search_name)) {
    $safeSearch = $conn->real_escape_string($search_name);
    $query .= " AND (material_name LIKE '%$safeSearch%' OR po_number LIKE '%$safeSearch%')";
}

$query .= " ORDER BY created_at DESC";
$result = $conn->query($query);

// --- PDF Setup ---
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// --- Logo & Header ---
$pdf->Image('images/lynn_logo.png', 10, 8, 25);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'MATERIALS IN REPORT', 0, 1, 'C');
$pdf->Ln(5);

// --- Filters Display ---
$pdf->SetFont('Arial', '', 10);
if ($from_date && $to_date) {
    $pdf->Cell(0, 6, "Report Period: $from_date to $to_date", 0, 1, 'C');
}
if (!empty($search_name)) {
    $pdf->Cell(0, 6, "Search: " . ucfirst($search_name), 0, 1, 'C');
}
$pdf->Ln(5);

// --- Table Header ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 230, 255);
$pdf->Cell(10, 10, '#', 1, 0, 'C', true);
$pdf->Cell(60, 10, 'Material Name', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Material Code', 1, 0, 'C', true);
$pdf->Cell(35, 10, 'PO Number', 1, 0, 'C', true);
$pdf->Cell(20, 10, 'Unit', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Quantity', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'Unit Cost', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Total Cost', 1, 0, 'C', true);
$pdf->Cell(35, 10, 'Date Added', 1, 1, 'C', true);

// --- Table Content ---
$pdf->SetFont('Arial', '', 9);
$counter = 1;
$total_sum = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $total = $row['quantity'] * $row['cost'];
        $total_sum += $total;

        $pdf->Cell(10, 8, $counter++, 1, 0, 'C');
        $pdf->Cell(60, 8, $row['material_name'], 1);
        $pdf->Cell(30, 8, $row['material_code'], 1);
        $pdf->Cell(35, 8, $row['po_number'], 1);
        $pdf->Cell(20, 8, $row['unit'], 1);
        $pdf->Cell(25, 8, number_format($row['quantity'], 2), 1, 0, 'R');
        $pdf->Cell(25, 8, number_format($row['cost'], 2), 1, 0, 'R');
        $pdf->Cell(30, 8, number_format($total, 2), 1, 0, 'R');
        $pdf->Cell(35, 8, $row['created_at'], 1, 1, 'C');
    }

    // --- Grand Total ---
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(205, 10, 'Grand Total', 1, 0, 'R', true);
    $pdf->Cell(30, 10, number_format($total_sum, 2), 1, 0, 'R', true);
    $pdf->Cell(35, 10, '', 1, 1, 'R', true);
} else {
    $pdf->Cell(270, 10, 'No materials found for the selected filters.', 1, 1, 'C');
}

// --- Footer ---
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'R');

$pdf->Output('D', 'Materials_In_Report.pdf');
?>
