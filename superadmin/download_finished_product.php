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

// ✅ Fetch BOM
$bom_stmt = $conn->prepare("
  SELECT b.id, b.product_id, p.name AS product_name, b.status, b.description,
         b.requested_by, b.bom_date, b.issued_by, b.remarks, b.issue_date
  FROM bill_of_materials b
  JOIN products p ON b.product_id = p.id
  WHERE b.id = ?
");
$bom_stmt->bind_param("i", $bom_id);
$bom_stmt->execute();
$bom = $bom_stmt->get_result()->fetch_assoc();
$bom_stmt->close();

// ✅ Fetch BOM chemicals
$chem_stmt = $conn->prepare("
  SELECT i.chemical_name, i.chemical_code, i.rm_lot_no, i.po_number,
         i.quantity_requested, i.unit, i.unit_price, i.total_cost
  FROM bill_of_material_items i
  WHERE i.bom_id = ?
");
$chem_stmt->bind_param("i", $bom_id);
$chem_stmt->execute();
$chemicals = $chem_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$chem_stmt->close();

// ✅ Fetch packaging
$pack_stmt = $conn->prepare("
  SELECT m.material_name, p.pack_size, p.units, p.quantity_used, p.unpackaged_qty,
         p.cost_per_unit, p.total_cost, p.status
  FROM packaging p
  JOIN materials m ON p.material_id = m.id
  WHERE p.production_run_id = ?
");
$pack_stmt->bind_param("i", $production['id']);
$pack_stmt->execute();
$packaging_items = $pack_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pack_stmt->close();

// ✅ Fetch procedures
$procedures = [];
$proc_result = $conn->query("SELECT * FROM production_procedures WHERE production_run_id = {$production['id']} ORDER BY created_at ASC");
if ($proc_result) {
    while ($row = $proc_result->fetch_assoc()) $procedures[] = $row;
}

// ✅ Fetch QC tests
$qc_tests = [];
$qc_result = $conn->query("
  SELECT t.*, i.qc_status
  FROM qc_tests t
  JOIN qc_inspections i ON t.qc_inspection_id = i.id
  WHERE i.production_run_id = {$production['id']}
  ORDER BY t.created_at ASC
");
if ($qc_result) while ($row = $qc_result->fetch_assoc()) $qc_tests[] = $row;

// ✅ Fetch Quality Manager review
$review = $conn->query("
  SELECT * FROM quality_manager_review 
  WHERE qc_inspection_id IN (SELECT id FROM qc_inspections WHERE production_run_id = {$production['id']})
  ORDER BY checklist_no ASC
");

// ✅ Totals
$total_chem_cost = array_sum(array_column($chemicals, 'total_cost'));
$total_pack_cost = array_sum(array_column($packaging_items, 'total_cost'));
$grand_total = $total_chem_cost + $total_pack_cost;

// ---------------- FPDF ----------------
class PDF extends FPDF {
    function Header() {
        $this->Image('images/lynn_logo.png', 10, 8, 25);
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10,'FINISHED PRODUCT REPORT',0,1,'C');
        $this->Ln(3);
        $this->SetDrawColor(0,128,0);
        $this->Line(10,25,200,25);
        $this->Ln(10);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Generated on '.date('d M Y, h:i A'),0,0,'C');
    }
    function SectionTitle($title) {
        $this->SetFont('Arial','B',13);
        $this->SetTextColor(0,102,204);
        $this->Cell(0,10,$title,0,1);
        $this->SetTextColor(0,0,0);
    }
    function TableHeader($headers,$widths) {
        $this->SetFont('Arial','B',11);
        $this->SetFillColor(230,230,230);
        foreach($headers as $i=>$h) $this->Cell($widths[$i],8,$h,1,0,'C',true);
        $this->Ln();
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial','',11);

// Product Summary
$pdf->SectionTitle('Product Summary');
$pdf->Cell(0,8,"Product Name: ".$production['product_name'],0,1);
$pdf->Cell(0,8,"Requested By: ".$production['requested_by'],0,1);
$pdf->Cell(0,8,"Description: ".$production['description'],0,1);
$pdf->Cell(0,8,"Batch Date: ".$production['bom_date'],0,1);
$pdf->Cell(0,8,"Expected Yield: ".$production['expected_yield']." Kg/L",0,1);
$pdf->Cell(0,8,"Obtained Yield: ".$production['obtained_yield']." Kg/L",0,1);
$pdf->Ln(6);

// BOM Chemicals
$pdf->SectionTitle('Bill of Materials (Chemicals)');
$pdf->TableHeader(['Chemical','Code','RM LOT NO','PO NO','Qty','Unit','Unit Price','Total'], [40,25,25,25,20,20,25,30]);
$pdf->SetFont('Arial','',10);
foreach($chemicals as $c){
    $pdf->Cell(40,7,$c['chemical_name'],1);
    $pdf->Cell(25,7,$c['chemical_code'],1);
    $pdf->Cell(25,7,$c['rm_lot_no'],1);
    $pdf->Cell(25,7,'PO#'.$c['po_number'],1);
    $pdf->Cell(20,7,$c['quantity_requested'],1);
    $pdf->Cell(20,7,$c['unit'],1);
    $pdf->Cell(25,7,number_format($c['unit_price'],2),1);
    $pdf->Cell(30,7,number_format($c['total_cost'],2),1);
    $pdf->Ln();
}
$pdf->SetFont('Arial','B',10);
$pdf->Cell(180,7,'Total Chemicals Cost',1);
$pdf->Cell(30,7,number_format($total_chem_cost,2),1);
$pdf->Ln(10);

// Packaging
$pdf->SectionTitle('Packaging Reconciliation');
$pdf->TableHeader(['Item','Pack Size','Qty Packed','Unpacked','Cost/Unit','Total Cost','Status'], [50,30,25,25,25,25,20]);
$pdf->SetFont('Arial','',10);
foreach($packaging_items as $p){
    $pdf->Cell(50,7,$p['material_name'],1);
    $pdf->Cell(30,7,$p['pack_size'].' '.$p['units'],1);
    $pdf->Cell(25,7,$p['quantity_used'],1);
    $pdf->Cell(25,7,$p['unpackaged_qty'],1);
    $pdf->Cell(25,7,number_format($p['cost_per_unit'],2),1);
    $pdf->Cell(25,7,number_format($p['total_cost'],2),1);
    $pdf->Cell(20,7,$p['status'],1);
    $pdf->Ln();
}
$pdf->SetFont('Arial','B',10);
$pdf->Cell(180,7,'Total Packaging Cost',1);
$pdf->Cell(30,7,number_format($total_pack_cost,2),1);
$pdf->Ln(10);

// Grand Total
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,8,'GRAND TOTAL: '.number_format($grand_total,2).' Ksh',0,1,'R');
$pdf->Ln(6);

// Procedures
$pdf->SectionTitle('Production Procedures');
if(count($procedures)>0){
    $pdf->TableHeader(['#','Procedure','Done By','Checked By','Date'], [10,60,40,40,35]);
    $i=1;
    foreach($procedures as $p){
        $pdf->Cell(10,7,$i++,1);
        $pdf->Cell(60,7,$p['procedure_name'],1);
        $pdf->Cell(40,7,$p['done_by'],1);
        $pdf->Cell(40,7,$p['checked_by'],1);
        $pdf->Cell(35,7,date('d M Y',strtotime($p['created_at'])),1);
        $pdf->Ln();
    }
}else $pdf->Cell(0,8,'No procedures recorded.',0,1);
$pdf->Ln(6);

// QC Tests
$pdf->SectionTitle('QC Inspections');
if(count($qc_tests)>0){
    $pdf->TableHeader(['#','Test Name','Specification','Result','QC Status'], [10,50,50,40,30]);
    $i=1;
    foreach($qc_tests as $q){
        $pdf->Cell(10,7,$i++,1);
        $pdf->Cell(50,7,$q['test_name'],1);
        $pdf->Cell(50,7,$q['specification'],1);
        $pdf->Cell(40,7,$q['procedure_done'],1);
        $pdf->Cell(30,7,$q['qc_status'],1);
        $pdf->Ln();
    }
}else $pdf->Cell(0,8,'No QC inspections recorded.',0,1);
$pdf->Ln(6);

// Quality Manager Review
$pdf->SectionTitle('Quality Manager Review');
if($review && $review->num_rows>0){
    $pdf->TableHeader(['#','Checklist Item','Response'], [10,140,30]);
    $i=1;
    while($r=$review->fetch_assoc()){
        $pdf->Cell(10,7,$i++,1);
        $pdf->Cell(140,7,$r['checklist_item'],1);
        $pdf->Cell(30,7,$r['response'],1);
        $pdf->Ln();
    }
}else $pdf->Cell(0,8,'No Quality Manager review recorded.',0,1);

$pdf->Output("D","Finished_Product_{$production['product_name']}.pdf");
exit;
?>
