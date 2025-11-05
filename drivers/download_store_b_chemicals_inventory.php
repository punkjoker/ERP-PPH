<?php
require('fpdf.php');
include 'db_con.php';

// ✅ Get chemical_code
$chemical_code = $_GET['chemical_code'] ?? '';

if (empty($chemical_code)) {
    die('Invalid request.');
}

// ✅ Fetch chemical details
$stmt = $conn->prepare("SELECT chemical_name, group_name, category, main_category 
                        FROM chemical_names WHERE chemical_code = ?");
$stmt->bind_param("s", $chemical_code);
$stmt->execute();
$chemical = $stmt->get_result()->fetch_assoc();

// ✅ Fetch inventory details
$sql = "SELECT * FROM store_b_chemicals_in 
        WHERE chemical_code = ? 
        ORDER BY receiving_date DESC, id DESC";
$stmt2 = $conn->prepare($sql);
$stmt2->bind_param("s", $chemical_code);
$stmt2->execute();
$result = $stmt2->get_result();

// ✅ Create PDF
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// --- Header ---
$pdf->Image('images/lynn_logo.png', 10, 8, 25);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 8, 'Store B Chemical Inventory Report', 0, 1, 'C');
$pdf->Ln(5);

// --- Chemical Details ---
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Chemical Name: ' . ($chemical['chemical_name'] ?? 'Unknown'), 0, 1);
$pdf->Cell(0, 6, 'Group: ' . ($chemical['group_name'] ?? '-') . '    Category: ' . ($chemical['category'] ?? '-') . '    Main Category: ' . ($chemical['main_category'] ?? '-'), 0, 1);
$pdf->Cell(0, 6, 'Chemical Code: ' . $chemical_code, 0, 1);
$pdf->Ln(3);

// --- Table Header ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(25, 8, 'Delivery #', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Qty Received', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'Remaining Qty', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Units', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Pack Size', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Unit Cost', 1, 0, 'C', true);
$pdf->Cell(35, 8, 'PO Number', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Received By', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'Receiving Date', 1, 1, 'C', true);

// --- Table Body ---
$pdf->SetFont('Arial', '', 9);
while ($row = $result->fetch_assoc()) {
    $pdf->Cell(25, 8, $row['delivery_number'], 1);
    $pdf->Cell(30, 8, $row['quantity_received'], 1);
    $pdf->Cell(35, 8, $row['remaining_quantity'], 1);
    $pdf->Cell(20, 8, $row['units'], 1);
    $pdf->Cell(25, 8, $row['pack_size'], 1);
    $pdf->Cell(30, 8, $row['unit_cost'], 1);
    $pdf->Cell(35, 8, $row['po_number'], 1);
    $pdf->Cell(40, 8, $row['received_by'], 1);
    $pdf->Cell(30, 8, $row['receiving_date'], 1, 1);
}

// --- Footer ---
$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 8, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'R');

// ✅ Output PDF
$pdf->Output('D', 'StoreB_Chemical_Inventory_' . $chemical_code . '.pdf');
?>
