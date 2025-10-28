<?php 
require 'db_con.php';
require 'fpdf.php';

$payroll_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch payroll by ID
$stmt = $conn->prepare("
    SELECT pr.*, u.full_name, u.email, u.national_id, u.status
    FROM payroll_records pr
    INNER JOIN users u ON pr.user_id = u.user_id
    WHERE pr.payroll_id = ?
");
$stmt->bind_param("i", $payroll_id);
$stmt->execute();
$result = $stmt->get_result();
$payroll = $result->fetch_assoc();
$stmt->close();

if (!$payroll) {
    die("<div style='margin:100px;text-align:center;color:red;font-weight:bold;'>Payroll record not found.</div>");
}

// Decode allowances & deductions
$details = json_decode($payroll['details'], true);
$allowances = $details['allowances'] ?? [];
$deductions = $details['deductions'] ?? [];

$monthText = $payroll['month'];
$year = $payroll['year'];

// Start PDF
$pdf = new FPDF();
$pdf->AddPage();

// === HEADER WITH LOGO ===
if (file_exists('images/lynn_logo.png')) {
    $pdf->Image('images/lynn_logo.png', 10, 10, 25); // (x, y, width)
}
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(0, 51, 102); // Dark blue text
$pdf->Cell(0, 10, 'LYNNTECH ERP PAYSLIP', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', 'I', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(224, 235, 255); // light blue background
$pdf->Cell(190, 8, 'CONFIDENTIAL PAYSLIP', 0, 1, 'C', true);
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(190, 8, 'Payroll for ' . $monthText . ' ' . $year, 0, 1, 'C');
$pdf->Ln(5);

// === EMPLOYEE DETAILS ===
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(240, 248, 255);
$pdf->Cell(95, 8, 'Name: ' . $payroll['full_name'], 1, 0, 'L', true);
$pdf->Cell(95, 8, 'National ID: ' . $payroll['national_id'], 1, 1, 'L', true);
$pdf->Cell(95, 8, 'Email: ' . $payroll['email'], 1, 0, 'L', true);
$pdf->Cell(95, 8, 'Status: ' . ucfirst($payroll['status']), 1, 1, 'L', true);
$pdf->Ln(5);

// === TABLE HEADER ===
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(135, 206, 250); // Sky blue
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(95, 8, 'Earnings', 1, 0, 'C', true);
$pdf->Cell(95, 8, 'Deductions', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);

// Determine max rows for alignment
$maxRows = max(count($allowances) + 1, count($deductions));

// Add rows
for ($i = 0; $i < $maxRows; $i++) {
    // Earnings (Base + Allowances)
    if ($i == 0) {
        $earnLabel = 'Basic Salary';
        $earnVal = number_format($payroll['base_salary'], 2);
    } elseif (isset($allowances[$i - 1])) {
        $earnLabel = $allowances[$i - 1]['name'];
        $earnVal = number_format($allowances[$i - 1]['amount'], 2);
    } else {
        $earnLabel = '';
        $earnVal = '';
    }

    // Deductions
    if (isset($deductions[$i])) {
        $ded = $deductions[$i];
        $raw = $ded['amount'];
        $isPercent = strpos($raw, '%') !== false;
        $rate = $isPercent ? $raw : '';
        $dedAmount = $isPercent
            ? (floatval(str_replace('%','',$raw)) / 100) * $payroll['base_salary']
            : floatval($raw);
        $dedLabel = $ded['name'] . ($rate ? " ($rate)" : '');
        $dedVal = number_format($dedAmount, 2);
    } else {
        $dedLabel = '';
        $dedVal = '';
    }

    $pdf->Cell(65, 8, $earnLabel, 1);
    $pdf->Cell(30, 8, $earnVal, 1, 0, 'R');
    $pdf->Cell(65, 8, $dedLabel, 1);
    $pdf->Cell(30, 8, $dedVal, 1, 1, 'R');
}

// === TOTALS ROW ===
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(70, 130, 180); // Steel blue
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(65, 8, 'Total Earnings', 1, 0, 'C', true);
$pdf->Cell(30, 8, number_format($payroll['base_salary'] + $payroll['total_allowances'], 2), 1, 0, 'R', true);
$pdf->Cell(65, 8, 'Total Deductions', 1, 0, 'C', true);
$pdf->Cell(30, 8, number_format($payroll['total_deductions'], 2), 1, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(6);

// === NET PAY ===
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetFillColor(176, 224, 230); // Light cyan
$pdf->Cell(160, 10, 'NET PAY', 1, 0, 'R', true);
$pdf->Cell(30, 10, 'Ksh ' . number_format($payroll['net_pay'], 2), 1, 1, 'R', true);
$pdf->Ln(5);

// === FOOTER ===
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'This payslip was generated electronically by LynnTech ERP on ' . date('d M Y'), 0, 1, 'C');

// Output PDF
$filename = "Payslip_{$payroll['full_name']}_{$monthText}_{$year}.pdf";

if (isset($_GET['download']) && $_GET['download'] == 1) {
    // Force download
    $pdf->Output("D", $filename);
} else {
    // Just open in browser
    $pdf->Output("I", $filename);
}

?>
