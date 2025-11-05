<?php
require('fpdf.php'); // make sure fpdf/ is in your project folder
include 'db_con.php';

// Fetch only active employees
$query = "SELECT first_name, last_name, national_id, kra_pin, nssf_number, nhif_number, phone, department, position, date_of_hire 
          FROM employees WHERE status = 'Active' ORDER BY first_name ASC";
$result = $conn->query($query);

class PDF extends FPDF {
    // Header
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Active Employees List', 0, 1, 'C');
        $this->Ln(5);

        // Table header
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(10, 8, '#', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Name', 1, 0, 'C', true);
        $this->Cell(25, 8, 'National ID', 1, 0, 'C', true);
        $this->Cell(25, 8, 'KRA PIN', 1, 0, 'C', true);
        $this->Cell(25, 8, 'NSSF', 1, 0, 'C', true);
        $this->Cell(25, 8, 'NHIF', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Phone', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Department', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Position', 1, 1, 'C', true);
    }

    // Footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

$counter = 1;
while ($row = $result->fetch_assoc()) {
    $pdf->Cell(10, 8, $counter++, 1);
    $pdf->Cell(40, 8, $row['first_name'] . ' ' . $row['last_name'], 1);
    $pdf->Cell(25, 8, $row['national_id'], 1);
    $pdf->Cell(25, 8, $row['kra_pin'], 1);
    $pdf->Cell(25, 8, $row['nssf_number'], 1);
    $pdf->Cell(25, 8, $row['nhif_number'], 1);
    $pdf->Cell(30, 8, $row['phone'], 1);
    $pdf->Cell(30, 8, $row['department'], 1);
    $pdf->Cell(30, 8, $row['position'], 1, 1);
}

$pdf->Output('D', 'Active_Employees_List_' . date('Y-m-d') . '.pdf');
exit;
?>
