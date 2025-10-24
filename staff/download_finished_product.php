<?php
require('fpdf.php');
include 'db_con.php';

$bom_id = $_GET['id'] ?? 0;

// ✅ Fetch production + product + QC details
$sql = "
SELECT 
    pr.*, 
    p.name AS product_name, 
    p.product_code,
    bom.requested_by, 
    bom.description, 
    bom.bom_date, 
    bom.batch_number,
    qc.created_at AS qc_date,
    SUM(bm.std_quantity) AS std_batch_size  -- sum of standard quantities
FROM production_runs pr
JOIN bill_of_materials bom ON pr.request_id = bom.id
JOIN products p ON bom.product_id = p.id
LEFT JOIN qc_inspections qc ON qc.production_run_id = pr.id
LEFT JOIN bom b ON b.product_id = p.id
LEFT JOIN bom_materials bm ON bm.bom_id = b.id
WHERE pr.request_id = $bom_id
GROUP BY pr.id
LIMIT 1
";

$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) die("No record found for this product.");
$production = $result->fetch_assoc();


// ✅ Fetch BOM
$bom_stmt = $conn->prepare("
  SELECT b.id, b.product_id, p.name AS product_name, b.status, b.description,
         b.requested_by, b.bom_date, b.issued_by, b.remarks, b.issue_date, b.batch_number
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
  WHERE production_run_id = {$production['id']}
  ORDER BY checklist_no ASC
");

// ✅ Totals
$total_chem_cost = array_sum(array_column($chemicals, 'total_cost'));
$total_pack_cost = array_sum(array_column($packaging_items, 'total_cost'));
$grand_total = $total_chem_cost + $total_pack_cost;

// ---------------- FPDF ----------------
class PDF extends FPDF {

    function Header() {
        // --- Top Row with Logo, Title, and Doc No ---
        $this->Image('images/lynn_logo.png', 12, 10, 35); // Logo
        // You can add more header content here
    } // <-- close Header properly

    function HeaderTable($data, $packaging_items) {
    $this->SetFont('Arial','',10);
    $this->SetDrawColor(180,180,180);

    // --- Add spacing to avoid overlap with header/logo ---
    $this->Ln(15);

    // Grab first packaging unit (if exists) for the "PACK SIZE" field
    $pack_size_full = '';
if(count($packaging_items) > 0) {
    $pack_size_full = $packaging_items[0]['pack_size'] . ' ' . $packaging_items[0]['units'];
}


    $rows = [
         ['PRODUCT NAME', $data['product_name'], 'PRODUCT CODE', $data['product_code']], // dynamic code
        ['BATCH NUMBER', $data['batch_number'], 'EDITION NO.', '007'],
        ['MFG. DATE', date('d M Y', strtotime($data['bom_date'])), 'STD BATCH SIZE', $data['std_batch_size'].' Kg'], // dynamic
        ['EXP. DATE', '', 'ACTUAL BATCH SIZE', $data['obtained_yield']],  // ✅ Actual batch from obtained_yield
        ['EFFECTIVE DATE', '1ST SEPTEMBER 2024', 'PACK SIZE', $pack_size_full], // ✅ full pack size
        ['REVIEW DATE', '1ST AUGUST 2027', 'PAGES', '1 of 1']
    ];

    foreach ($rows as $row) {
        $this->Cell(40, 8, $row[0], 1, 0, 'L');
        $this->Cell(55, 8, strtoupper($row[1]), 1, 0, 'L');
        $this->Cell(40, 8, $row[2], 1, 0, 'L');
        $this->Cell(55, 8, strtoupper($row[3]), 1, 1, 'L');
    }
    $this->Ln(5);
}


    // -------------------------
    // SECTION TITLE HELPER
    // -------------------------
    function SectionTitle($title) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(40, 40, 40);
        $this->Cell(0, 10, strtoupper($title), 0, 1, 'L');
        $this->SetDrawColor(0, 102, 204);
        $this->SetLineWidth(0.4);
        $this->Line($this->GetX(), $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    // -------------------------
    // TABLE HEADER HELPER
    // -------------------------
    function TableHeader($headers, $widths) {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(230, 230, 230);
        $this->SetDrawColor(180,180,180);
        foreach ($headers as $i => $header) {
            $this->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
        }
        $this->Ln();
    }
}

// ------------------- PDF BODY -------------------
$pdf = new PDF();
$pdf->AddPage();
$pdf->HeaderTable($production, $packaging_items);


// ✅ Product Summary (Two Columns)
$pdf->SectionTitle('Product Summary');

// Left column width and right column width
$left_w = 95;
$right_w = 95;

// Row 1
$pdf->Cell($left_w,8,"Product Name: ".$production['product_name'],1,0);
$pdf->Cell($right_w,8,"Requested By: ".$production['requested_by'],1,1);

// Row 2
$pdf->Cell($left_w,8,"Description: ".$production['description'],1,0);
$pdf->Cell($right_w,8,"Batch Date: ".$production['bom_date'],1,1);

// Row 3
$pdf->Cell($left_w,8,"Expected Yield: ".$production['expected_yield']." Kg/L",1,0);
$pdf->Cell($right_w,8,"Obtained Yield: ".$production['obtained_yield']." Kg/L",1,1);

$pdf->Ln(6);

// ✅ Bill of Materials
$pdf->SectionTitle('Bill of Materials (Chemicals)');
$pdf->TableHeader(['Chemical','Code','RM LOT NO','PO NO','Qty','Unit','Unit Price','Total'], [35,20,20,20,20,15,25,25]);
$pdf->SetFont('Arial','',9);
foreach($chemicals as $c){
    $pdf->Cell(35,7,$c['chemical_name'],1);
    $pdf->Cell(20,7,$c['chemical_code'],1);
    $pdf->Cell(20,7,$c['rm_lot_no'],1);
    $pdf->Cell(20,7,'PO#'.$c['po_number'],1);
    $pdf->Cell(20,7,$c['quantity_requested'],1);
    $pdf->Cell(15,7,$c['unit'],1);
    $pdf->Cell(25,7,number_format($c['unit_price'],2),1,0,'R');
    $pdf->Cell(25,7,number_format($c['total_cost'],2),1,1,'R');
}
$pdf->SetFont('Arial','B',10);
$pdf->Cell(155,8,'Total BOM Cost',1,0,'R',true);
$pdf->Cell(25,8,number_format($total_chem_cost,2),1,1,'R');
$pdf->Ln(5);

// ✅ Packaging Section
$pdf->SectionTitle('Packaging Reconciliation');
$pdf->TableHeader(['Material','Pack Size','Units','Cost/Unit','Total Cost','Status'], [40,25,20,20,25,25,25]);
$pdf->SetFont('Arial','',9);
foreach($packaging_items as $p){
    $pdf->Cell(40,7,$p['material_name'],1);
    $pdf->Cell(25,7,$p['pack_size'].' '.$p['units'],1);
    $pdf->Cell(20,7,$p['quantity_used'],1);
   
    $pdf->Cell(25,7,number_format($p['cost_per_unit'],2),1,0,'R');
    $pdf->Cell(25,7,number_format($p['total_cost'],2),1,0,'R');
    $pdf->Cell(25,7,$p['status'],1,1);
}
$pdf->SetFont('Arial','B',10);
$pdf->Cell(155,8,'Total Packaging Cost',1,0,'R',true);
$pdf->Cell(25,8,number_format($total_pack_cost,2),1,1,'R');
$pdf->Cell(155,8,'GRAND TOTAL (BOM + Packaging)',1,0,'R',true);
$pdf->Cell(25,8,number_format($grand_total,2),1,1,'R');
$pdf->Ln(6);

// ✅ Procedures
$pdf->SectionTitle('Production Procedures');
if(count($procedures) > 0){
    $pdf->TableHeader(['#','Procedure','Done By','Checked By','Date'], [10,60,40,40,40]);
    $pdf->SetFont('Arial','',9);
    foreach($procedures as $i=>$prc){
        $pdf->Cell(10,7,$i+1,1);
        $pdf->Cell(60,7,$prc['procedure_name'],1);
        $pdf->Cell(40,7,$prc['done_by'],1);
        $pdf->Cell(40,7,$prc['checked_by'],1);
        $pdf->Cell(40,7,date('d M Y',strtotime($prc['created_at'])),1,1);
    }
} else {
    $pdf->Cell(0,8,'No procedures recorded.',0,1);
}
$pdf->Ln(5);

// ✅ QC Tests
$pdf->SectionTitle('Quality Control Tests');
if(count($qc_tests) > 0){
    $pdf->TableHeader(['#','Test','Specification','Result','Status'], [10,50,50,50,35]);
    $pdf->SetFont('Arial','',9);
    foreach($qc_tests as $i=>$q){
        $pdf->Cell(10,7,$i+1,1);
        $pdf->Cell(50,7,$q['test_name'],1);
        $pdf->Cell(50,7,$q['specification'],1);
        $pdf->Cell(50,7,$q['procedure_done'],1);
        $pdf->Cell(35,7,$q['qc_status'],1,1);
    }
} else {
    $pdf->Cell(0,8,'No QC inspections recorded.',0,1);
}
$pdf->Ln(5);

// ✅ Quality Manager Review
$pdf->SectionTitle('Quality Manager Review');
if($review && $review->num_rows > 0){
    $pdf->TableHeader(['#','Checklist Item','Response','Status'], [10,100,40,40]);
    $pdf->SetFont('Arial','',9);
    $i=1;
    while($r = $review->fetch_assoc()){
        $pdf->Cell(10,7,$i++,1);
        $pdf->Cell(100,7,$r['checklist_item'],1);
        $pdf->Cell(40,7,$r['response'],1);
        $pdf->Cell(40,7,$r['status'],1,1);
    }
} else {
    $pdf->Cell(0,8,'No Quality Manager review recorded.',0,1);
}

// -------------------------
// Batch Release Section
// -------------------------
$pdf->Ln(6); // small gap after QC/Review tables
$pdf->SetFont('Arial', 'B', 10);

$pdf->Cell(0, 6, 'BATCH RELEASE FOR SALE', 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Approved By : _________________________   Sign: ___________   Date: ______________', 0, 1, 'C');
$pdf->Cell(0, 6, 'QUALITY MANAGER', 0, 1, 'C');
$pdf->Ln(4);

$pdf->Cell(0, 6, 'Authorized By : ________________________   Sign: ___________   Date: _______________', 0, 1, 'C');
$pdf->Cell(0, 6, 'TECHNICAL DIRECTOR', 0, 1, 'C');
$pdf->Ln(4);

$pdf->SetFont('Arial', 'I', 9);
$pdf->MultiCell(0, 5, 'NB: THE BATCH CAN ONLY BE POSTED INTO THE SYSTEM UPON RELEASE FOR SALE.', 0, 'C');

// ✅ Output PDF
$pdf->Output('D', 'Finished_Product_Report_'.$production['product_name'].'.pdf');
?>
