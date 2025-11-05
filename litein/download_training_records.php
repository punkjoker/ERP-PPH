<?php
require('fpdf.php');
include 'db_con.php';

// --- Fetch training data ---
$query = "
SELECT t.training_id, t.training_name, t.training_date, t.status, 
       t.done_by, t.approved_by, e.first_name, e.last_name
FROM trainings t
JOIN employees e ON t.employee_id = e.employee_id
ORDER BY t.training_date DESC
";
$result = $conn->query($query);

// --- Setup PDF ---
class PDF extends FPDF {
    function Header() {
        // Title
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Employee Training Records', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Generated on: ' . date('d M Y H:i'), 0, 1, 'C');
        $this->Ln(5);

        // Table header
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(200, 200, 200);
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(45, 8, 'Employee Name', 1, 0, 'C', true);
        $this->Cell(45, 8, 'Training Name', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Date', 1, 0, 'C', true);
        $this->Cell(20, 8, 'Status', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Done By', 1, 0, 'C', true);
        $this->Cell(25, 8, 'Approved By', 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// --- Fill Table Data ---
$count = 1;
while ($row = $result->fetch_assoc()) {
    $pdf->Cell(10, 8, $count++, 1, 0, 'C');
    $pdf->Cell(45, 8, $row['first_name'].' '.$row['last_name'], 1);
    $pdf->Cell(45, 8, $row['training_name'], 1);
    $pdf->Cell(25, 8, $row['training_date'], 1);
    $pdf->Cell(20, 8, $row['status'], 1, 0, 'C');
    $pdf->Cell(25, 8, $row['done_by'], 1);
    $pdf->Cell(25, 8, $row['approved_by'], 1, 1);
}

// --- No records fallback ---
if ($count === 1) {
    $pdf->Cell(0, 10, 'No training records found.', 1, 1, 'C');
}

// --- Output file ---
$pdf->Output('D', 'Training_Records_' . date('Y-m-d') . '.pdf');
exit;
?>
