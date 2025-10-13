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

// ✅ Fetch Bill of Materials (BOM) details
$bom_stmt = $conn->prepare("
  SELECT b.*, p.name AS product_name 
  FROM bill_of_materials b
  JOIN products p ON b.product_id = p.id
  WHERE b.id = ?
");
$bom_stmt->bind_param("i", $bom_id);
$bom_stmt->execute();
$bom = $bom_stmt->get_result()->fetch_assoc();
$bom_stmt->close();

// ✅ Fetch BOM raw materials (chemicals)
$chem_stmt = $conn->prepare("
  SELECT i.chemical_id, c.chemical_name, i.quantity_requested, i.unit, 
         i.unit_price, i.total_cost, i.rm_lot_no
  FROM bill_of_material_items i
  JOIN chemicals_in c ON i.chemical_id = c.id
  WHERE i.bom_id = ?
");
$chem_stmt->bind_param("i", $bom_id);
$chem_stmt->execute();
$chemicals = $chem_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$chem_stmt->close();

// ✅ Fetch packaging materials (linked to BOM)
$pack_stmt = $conn->prepare("
  SELECT pr.item_name, pr.units, pr.cost_per_unit, pr.total_cost
  FROM packaging_reconciliation pr
  JOIN qc_inspections qi ON qi.id = pr.qc_inspection_id
  JOIN production_runs r ON r.id = qi.production_run_id
  WHERE r.request_id = ?
");
$pack_stmt->bind_param("i", $bom_id);
$pack_stmt->execute();
$bom_packaging = $pack_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pack_stmt->close();

// ✅ Fetch related data
$procedures = $conn->query("SELECT * FROM production_procedures WHERE production_run_id = {$production['id']} ORDER BY created_at ASC");
$qc_tests = $conn->query("SELECT * FROM qc_inspections WHERE production_run_id = {$production['id']}");
$packs = $conn->query("SELECT pr.*, qc.test_name 
  FROM packaging_reconciliation pr
  JOIN qc_inspections qc ON pr.qc_inspection_id = qc.id
  WHERE qc.production_run_id = {$production['id']}");

// ✅ Fetch Quality Manager Review
$review = $conn->query("
  SELECT qmr.*, qc.test_name
  FROM quality_manager_review qmr
  JOIN qc_inspections qc ON qmr.qc_inspection_id = qc.id
  WHERE qc.production_run_id = {$production['id']}
");

class PDF extends FPDF {
  function Header() {
    $this->Image('images/lynn_logo.png', 10, 8, 25);
    $this->SetFont('Arial', 'B', 15);
    $this->Cell(0, 10, 'FINISHED PRODUCT REPORT', 0, 1, 'C');
    $this->Ln(3);
    $this->SetDrawColor(0, 128, 0);
    $this->Line(10, 25, 200, 25);
    $this->Ln(10);
  }

  function Footer() {
    $this->SetY(-15);
    $this->SetFont('Arial', 'I', 8);
    $this->Cell(0, 10, 'Generated on ' . date('d M Y, h:i A'), 0, 0, 'C');
  }

  function SectionTitle($title, $color = [0, 102, 204]) {
    $this->SetFont('Arial', 'B', 13);
    $this->SetTextColor($color[0], $color[1], $color[2]);
    $this->Cell(0, 10, $title, 0, 1, 'L');
    $this->SetTextColor(0, 0, 0);
  }

  function TableHeader($headers, $widths, $bgColor = [230, 230, 230]) {
    $this->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
    $this->SetFont('Arial', 'B', 11);
    foreach ($headers as $i => $header) {
      $this->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
    }
    $this->Ln();
  }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// ✅ Product Info
$pdf->SectionTitle('Product Summary', [0, 128, 0]);
$pdf->Cell(0, 8, "Product Name: " . $production['product_name'], 0, 1);
$pdf->Cell(0, 8, "Requested By: " . $production['requested_by'], 0, 1);
$pdf->Cell(0, 8, "Description: " . $production['description'], 0, 1);
$pdf->Cell(0, 8, "Batch Date: " . $production['bom_date'], 0, 1);
$pdf->Cell(0, 8, "Expected Yield: " . $production['expected_yield'] . " Kg/L", 0, 1);
$pdf->Cell(0, 8, "Obtained Yield: " . $production['obtained_yield'] . " Kg/L", 0, 1);
$pdf->Ln(6);

// ✅ Bill of Materials (BOM)
$pdf->SectionTitle('Bill of Materials (BOM)', [0, 0, 128]);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 8, "Requested By: " . $bom['requested_by'], 0, 1);
$pdf->Cell(0, 8, "Issued By: " . $bom['issued_by'], 0, 1);
$pdf->Cell(0, 8, "BOM Date: " . $bom['bom_date'], 0, 1);
$pdf->Cell(0, 8, "Issue Date: " . $bom['issue_date'], 0, 1);
$pdf->MultiCell(0, 8, "Remarks: " . $bom['remarks']);
$pdf->Ln(4);

// ✅ Raw Materials (Chemicals)
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Raw Materials (Chemicals)', 0, 1);
$pdf->TableHeader(['Chemical', 'RM LOT NO', 'Qty', 'Unit', 'Unit Price', 'Total Cost'], [45, 30, 20, 20, 35, 35]);

$pdf->SetFont('Arial', '', 10);
$chemical_total = 0;
foreach ($chemicals as $c) {
  $pdf->Cell(45, 7, $c['chemical_name'], 1);
  $pdf->Cell(30, 7, $c['rm_lot_no'], 1);
  $pdf->Cell(20, 7, $c['quantity_requested'], 1);
  $pdf->Cell(20, 7, $c['unit'], 1);
  $pdf->Cell(35, 7, number_format($c['unit_price'], 2), 1);
  $pdf->Cell(35, 7, number_format($c['total_cost'], 2), 1);
  $pdf->Ln();
  $chemical_total += $c['total_cost'];
}
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(150, 7, 'Total Chemicals Cost', 1);
$pdf->Cell(35, 7, number_format($chemical_total, 2), 1);
$pdf->Ln(10);

// ✅ Packaging Materials
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Packaging Materials', 0, 1);
$pdf->TableHeader(['Item', 'Units', 'Cost/Unit', 'Total Cost'], [80, 30, 40, 40]);

$pdf->SetFont('Arial', '', 10);
$packaging_total = 0;
foreach ($bom_packaging as $p) {
  $pdf->Cell(80, 7, $p['item_name'], 1);
  $pdf->Cell(30, 7, $p['units'], 1);
  $pdf->Cell(40, 7, number_format($p['cost_per_unit'], 2), 1);
  $pdf->Cell(40, 7, number_format($p['total_cost'], 2), 1);
  $pdf->Ln();
  $packaging_total += $p['total_cost'];
}
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(150, 7, 'Total Packaging Cost', 1);
$pdf->Cell(40, 7, number_format($packaging_total, 2), 1);
$pdf->Ln(10);

// ✅ Grand Total
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Grand Total BOM Cost: Ksh ' . number_format($chemical_total + $packaging_total, 2), 0, 1, 'R');
$pdf->Ln(6);

// ✅ Procedures
$pdf->SectionTitle('Production Procedures', [0, 102, 204]);
if ($procedures->num_rows > 0) {
  $pdf->TableHeader(['#', 'Procedure Name', 'Done By', 'Checked By', 'Date'], [10, 60, 40, 40, 35]);
  $pdf->SetFont('Arial', '', 10);
  $count = 1;
  while ($row = $procedures->fetch_assoc()) {
    $pdf->Cell(10, 7, $count++, 1);
    $pdf->Cell(60, 7, $row['procedure_name'], 1);
    $pdf->Cell(40, 7, $row['done_by'], 1);
    $pdf->Cell(40, 7, $row['checked_by'], 1);
    $pdf->Cell(35, 7, date('d M Y', strtotime($row['created_at'])), 1);
    $pdf->Ln();
  }
} else {
  $pdf->Cell(0, 8, 'No procedures recorded.', 0, 1);
}
$pdf->Ln(6);

// ✅ QC Inspections
$pdf->SectionTitle('Quality Control Inspections', [255, 102, 0]);
if ($qc_tests->num_rows > 0) {
  $pdf->TableHeader(['#', 'Test Name', 'Specification', 'Result', 'QC Status'], [10, 45, 45, 45, 45]);
  $pdf->SetFont('Arial', '', 10);
  $i = 1;
  while ($qc = $qc_tests->fetch_assoc()) {
    $pdf->Cell(10, 7, $i++, 1);
    $pdf->Cell(45, 7, $qc['test_name'], 1);
    $pdf->Cell(45, 7, $qc['specification'], 1);
    $pdf->Cell(45, 7, $qc['procedure_done'], 1);
    $pdf->Cell(45, 7, $qc['qc_status'], 1);
    $pdf->Ln();
  }
} else {
  $pdf->Cell(0, 8, 'No QC inspections recorded.', 0, 1);
}
$pdf->Ln(6);

// ✅ Quality Manager Review Section
$pdf->SectionTitle('Quality Manager Review', [153, 51, 255]);
if ($review->num_rows > 0) {
  $pdf->TableHeader(['#', 'Checklist Item', 'Response'], [10, 150, 30]);
  $pdf->SetFont('Arial', '', 10);
  $i = 1;
  while ($r = $review->fetch_assoc()) {
    $pdf->Cell(10, 7, $i++, 1);
    $pdf->Cell(150, 7, $r['checklist_item'], 1);
    $pdf->Cell(30, 7, $r['response'], 1);
    $pdf->Ln();
  }
} else {
  $pdf->Cell(0, 8, 'No quality manager review recorded.', 0, 1);
}
$pdf->Ln(6);

// ✅ Packaging Reconciliation
$pdf->SectionTitle('Packaging Reconciliation', [0, 153, 76]);
if ($packs->num_rows > 0) {
  $pdf->TableHeader(['Item', 'Issued', 'Used', 'Wasted', 'Balance', 'Yield %'], [40, 25, 25, 25, 25, 25]);
  $pdf->SetFont('Arial', '', 10);
  while ($p = $packs->fetch_assoc()) {
    $pdf->Cell(40, 7, $p['item_name'], 1);
    $pdf->Cell(25, 7, $p['issued'], 1);
    $pdf->Cell(25, 7, $p['used'], 1);
    $pdf->Cell(25, 7, $p['wasted'], 1);
    $pdf->Cell(25, 7, $p['balance'], 1);
    $pdf->Cell(25, 7, $p['yield_percent'] . '%', 1);
    $pdf->Ln();
  }
} else {
  $pdf->Cell(0, 8, 'No packaging reconciliation data recorded.', 0, 1);
}
$pdf->Ln(10);

// ✅ Batch Release for Sale
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'BATCH RELEASE FOR SALE', 0, 1, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Ln(4);
$pdf->Cell(0, 8, 'Approved By: _________________________   Sign: ___________   Date: ______________', 0, 1);
$pdf->Cell(0, 8, 'QUALITY MANAGER', 0, 1, 'R');
$pdf->Ln(4);
$pdf->Cell(0, 8, 'Authorized By: ________________________   Sign: ___________   Date: _______________', 0, 1);
$pdf->Cell(0, 8, 'TECHNICAL DIRECTOR', 0, 1, 'R');
$pdf->Ln(6);
$pdf->SetFont('Arial', 'I', 10);
$pdf->MultiCell(0, 8, 'NB: THE BATCH CAN ONLY BE POSTED INTO THE SYSTEM UPON RELEASE FOR SALE.');

$pdf->Output("D", "Finished_Product_{$production['product_name']}.pdf");
exit;
?>
