<?php
require('fpdf.php');
include 'db_con.php';

$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$where = '';
$title_suffix = '';
if (!empty($from_date) && !empty($to_date)) {
    $where = "WHERE receiving_date BETWEEN '$from_date' AND '$to_date'";
    $title_suffix = " ($from_date to $to_date)";
}

// ✅ Fetch filtered data
$sql = "SELECT * FROM store_b_engineering_products_in $where ORDER BY receiving_date DESC";
$result = $conn->query($sql);

// ✅ PDF setup
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// --- Header ---
$pdf->Image('images/lynn_logo.png', 10, 8, 25);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 8, 'Store B Engineering Products Receiving Report' . $title_suffix, 0, 1, 'C');
$pdf->Ln(5);

// --- Table Header ---
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetFillColor(200, 220, 255);
$headers = [
    'Product Name', 'Group', 'Group Code', 'Product Code',
    'Delivery #', 'Qty', 'Remain', 'Units',
    'Pack', 'Unit Cost', 'PO #', 'Received By', 'Date'
];

// ✅ Adjusted widths (total ≈ 290 mm)
$widths = [35, 22, 18, 20, 18, 12, 15, 15, 15, 18, 18, 25, 25];

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 7, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// --- Table Rows ---
$pdf->SetFont('Arial', '', 7.5);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell($widths[0], 7, substr($row['product_name'], 0, 25), 1);
        $pdf->Cell($widths[1], 7, $row['group_name'], 1);
        $pdf->Cell($widths[2], 7, $row['group_code'], 1);
        $pdf->Cell($widths[3], 7, $row['product_code'], 1);
        $pdf->Cell($widths[4], 7, $row['delivery_number'], 1);
        $pdf->Cell($widths[5], 7, $row['quantity_received'], 1, 0, 'R');
        $pdf->Cell($widths[6], 7, $row['remaining_quantity'], 1, 0, 'R');
        $pdf->Cell($widths[7], 7, $row['units'], 1);
        $pdf->Cell($widths[8], 7, $row['pack_size'], 1);
        $pdf->Cell($widths[9], 7, $row['unit_cost'], 1, 0, 'R');
        $pdf->Cell($widths[10], 7, $row['po_number'], 1);
        $pdf->Cell($widths[11], 7, $row['received_by'], 1);
        $pdf->Cell($widths[12], 7, $row['receiving_date'], 1);
        $pdf->Ln();
    }
} else {
    $pdf->Cell(array_sum($widths), 10, 'No records found for the selected period.', 1, 1, 'C');
}

// --- Footer ---
$pdf->Ln(3);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 8, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'R');

// ✅ Output PDF
$pdf->Output('D', 'StoreB_Engineering_Receiving_Report.pdf');
?>
