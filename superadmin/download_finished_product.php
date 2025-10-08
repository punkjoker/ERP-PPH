<?php
require('fpdf.php');
include 'db_con.php';

$bom_id = $_GET['id'] ?? 0;

// ✅ Fetch production + product + QC details
$sql = "SELECT pr.*, p.name AS product_name, bom.requested_by, bom.description, bom.bom_date, qc.created_at AS qc_date
        FROM production_runs pr
        JOIN bill_of_materials bom ON pr.request_id = bom.id
        JOIN products p ON bom.product_id = p.id
        LEFT JOIN qc_inspections qc ON qc.production_run_id = pr.id
        WHERE pr.request_id = $bom_id LIMIT 1";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) die("No record found for this product.");
$production = $result->fetch_assoc();

// ✅ Fetch related data
$procedures = $conn->query("SELECT * FROM production_procedures WHERE production_run_id = {$production['id']}");
$qc_tests = $conn->query("SELECT * FROM qc_inspections WHERE production_run_id = {$production['id']}");
$packs = $conn->query("
  SELECT pr.*, qc.test_name 
  FROM packaging_reconciliation pr
  JOIN qc_inspections qc ON pr.qc_inspection_id = qc.id
  WHERE qc.production_run_id = {$production['id']}
");

class PDF extends FPDF {
  function Header() {
    $this->Image('images/lynn_logo.png', 10, 8, 25);
    $this->SetFont('Arial', 'B', 14);
    $this->Cell(0, 10, 'Finished Product Report', 0, 1, 'C');
    $this->Ln(5);
  }

  function Footer() {
    $this->SetY(-15);
    $this->SetFont('Arial', 'I', 8);
    $this->Cell(0, 10, 'Generated on ' . date('d M Y, h:i A'), 0, 0, 'C');
  }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// ✅ Product Summary
$pdf->Cell(0, 10, 'Product Name: ' . $production['product_name'], 0, 1);
$pdf->Cell(0, 10, 'Requested By: ' . $production['requested_by'], 0, 1);
$pdf->Cell(0, 10, 'Description: ' . $production['description'], 0, 1);
$pdf->Cell(0, 10, 'Batch Date: ' . $production['bom_date'], 0, 1);
$pdf->Ln(4);

// ✅ Procedures
$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 10, 'Production Procedures', 0, 1);
$pdf->SetFont('Arial', '', 11);
if ($procedures->num_rows > 0) {
  while ($row = $procedures->fetch_assoc()) {
    $pdf->Cell(0, 8, "- " . $row['procedure_name'] . " (Done by: " . $row['done_by'] . ")", 0, 1);
  }
} else {
  $pdf->Cell(0, 8, 'No procedures recorded.', 0, 1);
}
$pdf->Ln(6);

// ✅ QC Inspections
$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 10, 'Quality Control Inspections', 0, 1);
$pdf->SetFont('Arial', '', 11);
if ($qc_tests->num_rows > 0) {
  while ($qc = $qc_tests->fetch_assoc()) {
    $pdf->MultiCell(0, 7, "Test: {$qc['test_name']}\nSpec: {$qc['specification']}\nResult: {$qc['procedure_done']}\nStatus: {$qc['qc_status']}\n", 1);
    $pdf->Ln(2);
  }
} else {
  $pdf->Cell(0, 8, 'No QC inspections recorded.', 0, 1);
}
$pdf->Ln(6);

// ✅ Packaging Reconciliation
$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 10, 'Packaging Reconciliation', 0, 1);
$pdf->SetFont('Arial', '', 11);
if ($packs->num_rows > 0) {
  while ($p = $packs->fetch_assoc()) {
    $pdf->MultiCell(0, 7, "{$p['item_name']}: Issued {$p['issued']}, Used {$p['used']}, Balance {$p['balance']}, Yield {$p['yield_percent']}%", 0);
  }
} else {
  $pdf->Cell(0, 8, 'No packaging reconciliation data.', 0, 1);
}

$pdf->Output("D", "Finished_Product_{$production['product_name']}.pdf");
exit;
?>
