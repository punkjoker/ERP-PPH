<?php
require('fpdf.php');
require 'db_con.php';

$month = $_GET['month'] ?? date('F');
$year = $_GET['year'] ?? date('Y');

// ✅ Fetch employees (active staff)
$employees = [];
$empQuery = $conn->query("
    SELECT user_id, full_name 
    FROM users 
    WHERE group_id = (SELECT group_id FROM groups WHERE group_name='Staff') 
      AND status='active'
    ORDER BY full_name ASC
");
while ($e = $empQuery->fetch_assoc()) $employees[] = $e;

// ✅ Fetch payroll records
$payrolls = [];
$deductionsList = [];

$payQuery = $conn->query("SELECT * FROM payroll_records WHERE month='{$month}' AND year={$year}");
while ($p = $payQuery->fetch_assoc()) {
    $payrolls[$p['user_id']] = $p;
    $details = json_decode($p['details'], true);
    if ($details && isset($details['deductions'])) {
        foreach ($details['deductions'] as $ded) {
            $deductionsList[$ded['name']] = $ded['name'];
        }
    }
}

ksort($deductionsList);
$totalsDeductions = array_fill_keys(array_keys($deductionsList), 0);

// ✅ Custom PDF class
class PDF extends FPDF {
    function Header() {
        // --- Company Header ---
        $this->Image('images/lynn_logo.png', 10, 6, 25);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, 'LYNNTECH CHEMICALS & EQUIPMENT', 0, 1, 'C');
        $this->Ln(2);
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 8, 'ALL STAFF DEDUCTIONS REPORT', 0, 1, 'C');
        $this->Ln(3);
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 8, 'For Month: ' . date('F Y'), 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, 'Generated on ' . date('d M Y h:i A') . ' | Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

// ✅ Create PDF (Landscape, Full Page)
$pdf = new PDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Ln(3);

// ✅ Table Header
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(55, 8, 'Employee Name', 1, 0, 'C', true);

// Dynamic deduction columns
foreach ($deductionsList as $ded) {
    $pdf->Cell(35, 8, substr($ded, 0, 15), 1, 0, 'C', true);
}

$pdf->Cell(35, 8, 'Total Deductions', 1, 1, 'C', true);

// ✅ Table Rows
$pdf->SetFont('Arial', '', 9);

foreach ($employees as $emp) {
    $user_id = $emp['user_id'];
    $pdf->Cell(55, 8, substr($emp['full_name'], 0, 30), 1);

    $totalDed = 0;
    $details = isset($payrolls[$user_id]) ? json_decode($payrolls[$user_id]['details'], true) : null;

    foreach ($deductionsList as $ded) {
        $amt = 0;
        if ($details && isset($details['deductions'])) {
            foreach ($details['deductions'] as $d) {
                if ($d['name'] == $ded) {
                    $val = $d['amount'];
                    if (str_ends_with($val, '%')) {
                        $base = $payrolls[$user_id]['base_salary'] ?? 0;
                        $amt = floatval(str_replace('%','',$val)) * $base / 100;
                    } else {
                        $amt = floatval($val);
                    }
                    $totalsDeductions[$ded] += $amt;
                    $totalDed += $amt;
                }
            }
        }
        $pdf->Cell(35, 8, number_format($amt, 2), 1, 0, 'R');
    }

    $pdf->Cell(35, 8, number_format($totalDed, 2), 1, 1, 'R');
}

// ✅ Totals Row
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(55, 8, 'TOTALS', 1, 0, 'C', true);

foreach ($totalsDeductions as $t) {
    $pdf->Cell(35, 8, number_format($t, 2), 1, 0, 'R', true);
}

$pdf->Cell(35, 8, '', 1, 1, 'C', true);

// ✅ Output
$pdf->Output('D', "All_Deductions_{$month}_{$year}.pdf");
exit;
?>
