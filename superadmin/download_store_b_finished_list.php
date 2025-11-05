<?php
require('fpdf.php');
include 'db_con.php';

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

$where = "";
if (!empty($from_date) && !empty($to_date)) {
    $where = "WHERE receiving_date BETWEEN '$from_date' AND '$to_date'";
}

class PDF extends FPDF {
    function Header() {
        $this->Image('images/lynn_logo.png', 10, 8, 20);
        $this->SetFont('Arial','B',13);
        $this->Cell(0,10,'Litein Store B - Finished Products Receiving Report',0,1,'C');
        $this->Ln(2);
        $this->SetFont('Arial','',10);
        $this->Cell(0,5,'Generated on: '.date('F d, Y h:i A'),0,1,'C');
        $this->Ln(5);
        $this->SetFont('Arial','B',9);
        $this->SetFillColor(200,220,255);
        $this->Cell(35,8,'Product Name',1,0,'C',true);
        $this->Cell(25,8,'Product Code',1,0,'C',true);
        $this->Cell(25,8,'Category',1,0,'C',true);
        $this->Cell(25,8,'Delivery No.',1,0,'C',true);
        $this->Cell(20,8,'Qty',1,0,'C',true);
        $this->Cell(15,8,'Units',1,0,'C',true);
        $this->Cell(20,8,'Pack Size',1,0,'C',true);
        $this->Cell(20,8,'Unit Cost',1,0,'C',true);
        $this->Cell(25,8,'Received By',1,0,'C',true);
        $this->Cell(25,8,'Receiving Date',1,1,'C',true);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',8);

$query = "SELECT * FROM store_b_finished_products_in $where ORDER BY receiving_date DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($r = $result->fetch_assoc()) {
        $pdf->Cell(35,7,utf8_decode($r['product_name']),1);
        $pdf->Cell(25,7,$r['product_code'],1);
        $pdf->Cell(25,7,$r['category'],1);
        $pdf->Cell(25,7,$r['delivery_number'],1);
        $pdf->Cell(20,7,$r['quantity_received'],1,0,'R');
        $pdf->Cell(15,7,$r['units'],1);
        $pdf->Cell(20,7,$r['pack_size'],1);
        $pdf->Cell(20,7,number_format($r['unit_cost'],2),1,0,'R');
        $pdf->Cell(25,7,$r['received_by'],1);
        $pdf->Cell(25,7,$r['receiving_date'],1,1);
    }
} else {
    $pdf->Cell(0,10,'No records found for selected date range.',1,1,'C');
}

$pdf->Output('D','StoreB_Finished_Products_'.date('Ymd').'.pdf');
exit;
?>
