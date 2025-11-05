<?php
session_start();
require 'db_con.php';
require('fpdf.php');

// Validate chemical code
if (!isset($_GET['code'])) {
    die("Chemical code missing.");
}
$chemical_code = trim($_GET['code']);

// Fetch chemical details
$stmt = $conn->prepare("SELECT chemical_name, category, description FROM chemical_names WHERE chemical_code = ?");
$stmt->bind_param("s", $chemical_code);
$stmt->execute();
$chem = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$chem) {
    die("Chemical not found.");
}

// Fetch all lots for this chemical
$stmt = $conn->prepare("
    SELECT 
        rm_lot_no,
        batch_no,
        po_number,
        std_quantity,
        original_quantity,
        remaining_quantity,
        total_cost,
        unit_price,
        status,
        date_added,
        action_type
    FROM chemicals_in
    WHERE chemical_code = ?
    ORDER BY date_added ASC, id ASC
");
$stmt->bind_param("s", $chemical_code);
$stmt->execute();
$lots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* -----------------------
   FPDF Document
------------------------ */
class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image('images/lynn_logo.png', 10, 8, 25);
        // Title
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 6, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->SetFont('Arial', '', 11);
        $this->Cell(0, 6, 'All Lots Report (FIFO)', 0, 1, 'C');
        $this->Ln(10);
        // Horizontal line
        $this->SetDrawColor(50, 50, 50);
        $this->Line(10, 28, 200, 28);
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'L');
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'R');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Chemical details header
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, "Chemical Details", 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, "Name: " . $chem['chemical_name'], 0, 1);
$pdf->Cell(0, 6, "Code: " . $chemical_code, 0, 1);
$pdf->Cell(0, 6, "Category: " . $chem['category'], 0, 1);
$pdf->MultiCell(0, 6, "Description: " . $chem['description'], 0, 1);
$pdf->Ln(5);

// Table header
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 250);
$pdf->Cell(8, 8, '#', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Lot No', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Batch No', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'PO No', 1, 0, 'C', true);
$pdf->Cell(22, 8, 'Date', 1, 0, 'C', true);
$pdf->Cell(22, 8, 'Action', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Unit Price', 1, 0, 'C', true);
$pdf->Cell(22, 8, 'Total Cost', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Remaining', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Status', 1, 1, 'C', true);

// Table content
$pdf->SetFont('Arial', '', 9);
$counter = 1;
foreach ($lots as $row) {
    $pdf->Cell(8, 7, $counter++, 1, 0, 'C');
    $pdf->Cell(25, 7, $row['rm_lot_no'], 1, 0, 'C');
    $pdf->Cell(25, 7, $row['batch_no'], 1, 0, 'C');
    $pdf->Cell(25, 7, 'PO#'.$row['po_number'], 1, 0, 'C');
    $pdf->Cell(22, 7, $row['date_added'], 1, 0, 'C');
    $pdf->Cell(22, 7, $row['action_type'], 1, 0, 'C');
    $pdf->Cell(20, 7, 'Ksh '.number_format($row['unit_price'], 2), 1, 0, 'R');
    $pdf->Cell(22, 7, 'Ksh '.number_format($row['total_cost'], 2), 1, 0, 'R');
    $pdf->Cell(20, 7, number_format($row['remaining_quantity'], 2).' kg', 1, 0, 'R');
    $pdf->Cell(20, 7, $row['status'], 1, 1, 'C');
}

// Output PDF
$pdf->Output('D', 'All_Lots_'.$chemical_code.'.pdf');
?>
