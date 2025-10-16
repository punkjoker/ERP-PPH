<?php
session_start();
require 'db_con.php';
require('fpdf.php');

// ✅ Get BOM ID
if (!isset($_GET['id'])) {
    die("Request ID missing");
}
$bom_id = intval($_GET['id']);

// ✅ Fetch BOM main info
$sql = "SELECT b.id, b.product_id, p.name as product_name, b.status, b.description, 
               b.requested_by, b.bom_date, b.issued_by, b.remarks, b.issue_date
        FROM bill_of_materials b
        JOIN products p ON b.product_id = p.id
        WHERE b.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$bom = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$bom) {
    die("BOM request not found.");
}

// ✅ Fetch BOM items
$sql = "SELECT i.id, i.chemical_name, i.chemical_code, i.rm_lot_no, i.quantity_requested, 
               i.unit, i.unit_price, i.total_cost, i.po_number
        FROM bill_of_material_items i
        WHERE i.bom_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bom_id);
$stmt->execute();
$chemicals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ✅ Calculate total quantity to produce
$total_quantity_requested = 0;
foreach ($chemicals as $c) {
    $total_quantity_requested += $c['quantity_requested'];
}

class PDF extends FPDF {
    function Header() {
        // ✅ Add Logo (top-left)
        $this->Image('images/lynn_logo.png', 10, 8, 25); // (file, x, y, width)
        
        // ✅ Title Header beside logo
        $this->SetFont('Arial','B',16);
        $this->SetTextColor(33,37,41); // dark gray
        $this->Cell(0,10,'Bill of Materials Report',0,1,'C');
        
        $this->SetFont('Arial','',12);
        $this->SetTextColor(90,90,90);
        $this->Cell(0,8,'Production Department',0,1,'C');
        
        $this->Ln(10); // space after header
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',9);
        $this->SetTextColor(130,130,130);
        $this->Cell(0,10,'Generated on '.date('Y-m-d H:i').' | Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// ✅ Product Info
$pdf->SetFont('Arial','B',12);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(0,10,'Product Details',0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,8,'Product Name: ' . $bom['product_name'],0,1);
$pdf->Cell(0,8,'Quantity to Produce: ' . number_format($total_quantity_requested, 2),0,1);
$pdf->Cell(0,8,'Status: ' . $bom['status'],0,1);
$pdf->Ln(4);

// ✅ Chemicals Table Header
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(52,152,219); // blue
$pdf->SetTextColor(255,255,255);
$pdf->Cell(35,8,'Chemical',1,0,'C',true);
$pdf->Cell(25,8,'Chemical Code',1,0,'C',true);
$pdf->Cell(25,8,'PO Number',1,0,'C',true);
$pdf->Cell(25,8,'RM LOT NO',1,0,'C',true);
$pdf->Cell(20,8,'Qty Req.',1,0,'C',true);
$pdf->Cell(15,8,'Unit',1,0,'C',true);
$pdf->Cell(25,8,'Unit Price',1,0,'C',true);
$pdf->Cell(30,8,'Total Cost',1,1,'C',true);

// ✅ Table Rows
$pdf->SetFont('Arial','',10);
$pdf->SetTextColor(0,0,0);
$total_cost = 0;
$fill = false;

foreach ($chemicals as $c) {
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
    $pdf->Cell(35,8,$c['chemical_name'],1,0,'L',true);
    $pdf->Cell(25,8,$c['chemical_code'],1,0,'L',true);
    $pdf->Cell(25,8,$c['po_number'],1,0,'L',true);
    $pdf->Cell(25,8,$c['rm_lot_no'],1,0,'L',true);
    $pdf->Cell(20,8,$c['quantity_requested'],1,0,'C',true);
    $pdf->Cell(15,8,$c['unit'],1,0,'C',true);
    $pdf->Cell(25,8,number_format($c['unit_price'],2),1,0,'R',true);
    $pdf->Cell(30,8,number_format($c['total_cost'],2),1,1,'R',true);
    $total_cost += $c['total_cost'];
    $fill = !$fill;
}

// ✅ Total Row
$pdf->SetFont('Arial','B',11);
$pdf->SetFillColor(230,230,230);
$pdf->Cell(170,8,'Total Production Cost',1,0,'R',true);
$pdf->Cell(30,8,number_format($total_cost,2),1,1,'C',true);


// ✅ Request Info
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,'Request Information',0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,8,'Requested By: ' . $bom['requested_by'],0,1);
$pdf->Cell(0,8,'Description: ' . $bom['description'],0,1);
$pdf->Cell(0,8,'Date: ' . $bom['bom_date'],0,1);
$pdf->Ln(5);
$pdf->Cell(0,8,'Signature: ____________________________',0,1);
$pdf->Ln(6);

// ✅ Issuing Info
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,10,'Issuing Information',0,1);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0,8,'Issued By: ' . $bom['issued_by'],0,1);
$pdf->Cell(0,8,'Remarks: ' . $bom['remarks'],0,1);
$pdf->Cell(0,8,'Date of Issue: ' . $bom['issue_date'],0,1);
$pdf->Ln(5);
$pdf->Cell(0,8,'Signature: ____________________________',0,1);

// ✅ Output
$pdf->Output('D', 'BOM_Report_'.$bom_id.'.pdf');
?>
