<?php
require('fpdf.php');
include 'db_con.php';

// Get filters from query params
$search = trim($_GET['search'] ?? '');
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Build query dynamically
$query = "SELECT e.*, emp.first_name, emp.last_name FROM expenses e JOIN employees emp ON e.employee_id = emp.employee_id WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $query .= " AND CONCAT(emp.first_name,' ',emp.last_name) LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}
if ($from_date !== '') {
    $query .= " AND e.expense_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}
if ($to_date !== '') {
    $query .= " AND e.expense_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}

$query .= " ORDER BY e.expense_date DESC";
$stmt = $conn->prepare($query);
if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$expenses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate total
$total_amount = 0;
foreach($expenses as $exp) $total_amount += $exp['amount'];

// PDF Generation
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',16);
        $this->Cell(0,10,'Employee Expenses Report',0,1,'C');
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',10);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','B',12);

// Header
$pdf->SetFillColor(52, 152, 219); // Blue
$pdf->SetTextColor(255,255,255);
$pdf->Cell(10,10,'#',1,0,'C',true);
$pdf->Cell(45,10,'Employee',1,0,'C',true);
$pdf->Cell(35,10,'Expense',1,0,'C',true);
$pdf->Cell(25,10,'Date',1,0,'C',true);
$pdf->Cell(50,10,'Description',1,0,'C',true);
$pdf->Cell(20,10,'Amount',1,0,'C',true);
$pdf->Cell(15,10,'Status',1,1,'C',true);

$pdf->SetFont('Arial','',11);
$pdf->SetTextColor(0,0,0);

$count = 1;
foreach($expenses as $exp){
    $pdf->Cell(10,10,$count++,1,0,'C');
    $pdf->Cell(45,10,$exp['first_name'].' '.$exp['last_name'],1);
    $pdf->Cell(35,10,$exp['expense_name'],1);
    $pdf->Cell(25,10,$exp['expense_date'],1);
    
    // Description - wrap text
    $desc = substr($exp['description'],0,30);
    $pdf->Cell(50,10,$desc,1);

    $pdf->Cell(20,10,number_format($exp['amount'],2),1,0,'R');

    // Status with color
    if($exp['status']=='Paid'){
        $pdf->SetFillColor(46, 204, 113); // Green
    } else {
        $pdf->SetFillColor(231, 76, 60); // Red
    }
    $pdf->SetTextColor(255,255,255);
    $pdf->Cell(15,10,$exp['status'],1,1,'C',true);

    // Reset color
    $pdf->SetTextColor(0,0,0);
}

$pdf->SetFont('Arial','B',12);
$pdf->Cell(165,10,'Total',1,0,'R');
$pdf->Cell(20,10,number_format($total_amount,2),1,1,'C');

$pdf->Output('D','expenses_report.pdf');
