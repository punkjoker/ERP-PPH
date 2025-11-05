<?php
require('fpdf.php');
require 'db_con.php';

// Capture filters
$where = "1=1";
$params = [];
$types = "";

// Date filter
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where .= " AND b.issue_date BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
}

// Chemical filter
if (!empty($_GET['chemical_name'])) {
    $where .= " AND ci.chemical_name = ?";
    $params[] = $_GET['chemical_name'];
    $types .= "s";
}

// Lot filter
if (!empty($_GET['rm_lot_no'])) {
    $where .= " AND ci.rm_lot_no = ?";
    $params[] = $_GET['rm_lot_no'];
    $types .= "s";
}

// --- Query ---
$query = "
    SELECT 
        ci.chemical_name,
        ci.rm_lot_no,
        ci.std_quantity,
        ci.unit_price,
        ci.total_cost,
        ci.date_added,
        b.id AS bom_id,
        b.description AS bom_description,
        b.requested_by,
        b.issued_by,
        b.batch_number,
        b.issue_date,
        bi.quantity_requested,
        bi.total_cost AS used_cost
    FROM bill_of_material_items bi
    JOIN chemicals_in ci ON bi.chemical_code = ci.chemical_code
    JOIN bill_of_materials b ON bi.bom_id = b.id
    WHERE $where
    ORDER BY ci.chemical_name, b.issue_date DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// === FPDF Setup ===
class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('images/lynn_logo.png', 10, 8, 25);

        // Company name
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(0, 51, 153);
        $this->Cell(0, 10, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(2);

        // Report title
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'Bill of Material Usage History', 0, 1, 'C');
        $this->Ln(3);

        // Date
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, 'Generated on: ' . date('d M Y, h:i A'), 0, 1, 'C');
        $this->Ln(4);

        // Table Header
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(224, 235, 255);
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Chemical', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Lot No', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Batch No', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Orig Qty', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Used', 1, 0, 'C', true);
        $this->Cell(22, 8, 'Unit (Ksh)', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Used Cost', 1, 0, 'C', true);
        $this->Cell(28, 8, 'Requested By', 1, 0, 'C', true);
        $this->Cell(28, 8, 'Issued By', 1, 0, 'C', true);
        $this->Cell(28, 8, 'Issue Date', 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

$i = 1;
$total_used_cost = 0;
$total_chemicals = [];

while ($row = $result->fetch_assoc()) {
    $pdf->Cell(10, 8, $i++, 1);
    $pdf->Cell(35, 8, utf8_decode($row['chemical_name']), 1);
    $pdf->Cell(25, 8, $row['rm_lot_no'], 1);
    $pdf->Cell(35, 8, $row['batch_number'], 1);
    $pdf->Cell(20, 8, number_format($row['std_quantity'], 2), 1, 0, 'R');
    $pdf->Cell(20, 8, number_format($row['quantity_requested'], 2), 1, 0, 'R');
    $pdf->Cell(22, 8, number_format($row['unit_price'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, number_format($row['used_cost'], 2), 1, 0, 'R');
    $pdf->Cell(28, 8, utf8_decode($row['requested_by']), 1);
    $pdf->Cell(28, 8, utf8_decode($row['issued_by']), 1);
    $pdf->Cell(28, 8, date('d-M-Y', strtotime($row['issue_date'])), 1, 1, 'C');

    $total_used_cost += $row['used_cost'];
    $total_chemicals[$row['chemical_name']] = true;
}

// Summary
$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, 'Summary', 0, 1, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(100, 8, 'Total Chemicals Used: ' . count($total_chemicals), 0, 1);
$pdf->Cell(100, 8, 'Total Used Cost: Kshs ' . number_format($total_used_cost, 2), 0, 1);

// Output
$pdf->Output('D', 'BOM_Usage_History.pdf');
exit;
?>
