<?php
require 'db_con.php';
require 'fpdf.php';

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
list($year, $monthNum) = explode('-', $selectedMonth);
$monthText = date('F', mktime(0, 0, 0, $monthNum, 10));

// Fetch payroll record
$stmt = $conn->prepare("
    SELECT pr.*, u.full_name, u.email, u.national_id, u.status
    FROM payroll_records pr
    INNER JOIN users u ON pr.user_id = u.user_id
    WHERE pr.user_id = ? AND pr.month = ? AND pr.year = ?
");
$stmt->bind_param("isi", $user_id, $monthText, $year);
$stmt->execute();
$result = $stmt->get_result();
$payroll = $result->fetch_assoc();

if (!$payroll) {
    die("No payroll record found.");
}

// Decode details
$details = json_decode($payroll['details'], true);
$allowances = $details['allowances'] ?? [];
$deductions = $details['deductions'] ?? [];

// Initialize PDF
$pdf = new FPDF();
$pdf->AddPage();

// --- Header with logo ---
$pdf->Image('images/lynn_logo.png', 10, 8, 30); // X, Y, Width
$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(0, 10, 'LYNNTECH CHEMICALS $ EQUIPEMENT LRD', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 6, 'Employee Payslip', 0, 1, 'C');
$pdf->Ln(10);

// --- Employee Info Box ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Employee Details', 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 8, 'Name: ' . $payroll['full_name'], 1, 0);
$pdf->Cell(95, 8, 'Status: ' . ucfirst($payroll['status']), 1, 1);
$pdf->Cell(95, 8, 'Email: ' . $payroll['email'], 1, 0);
$pdf->Cell(95, 8, 'National ID: ' . $payroll['national_id'], 1, 1);
$pdf->Cell(95, 8, 'Month: ' . $monthText, 1, 0);
$pdf->Cell(95, 8, 'Year: ' . $year, 1, 1);
$pdf->Ln(8);

// --- Salary Summary ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Salary Summary', 0, 1);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 8, 'Base Salary', 1);
$pdf->Cell(95, 8, 'Ksh ' . number_format($payroll['base_salary'], 2), 1, 1);
$pdf->Cell(95, 8, 'Total Allowances', 1);
$pdf->Cell(95, 8, 'Ksh ' . number_format($payroll['total_allowances'], 2), 1, 1);
$pdf->Cell(95, 8, 'Total Deductions', 1);
$pdf->Cell(95, 8, 'Ksh ' . number_format($payroll['total_deductions'], 2), 1, 1);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(95, 8, 'Net Pay', 1, 0, 'L', true);
$pdf->Cell(95, 8, 'Ksh ' . number_format($payroll['net_pay'], 2), 1, 1, 'L', true);
$pdf->Ln(8);

// --- Allowances Section ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Allowances', 0, 1);
$pdf->SetFont('Arial', '', 11);

if (count($allowances) > 0) {
    foreach ($allowances as $a) {
        $amount = floatval(preg_replace('/[^0-9.\-]/', '', $a['amount']));
        $pdf->Cell(95, 8, $a['name'], 1);
        $pdf->Cell(95, 8, 'Ksh ' . number_format($amount, 2), 1, 1);
    }
} else {
    $pdf->Cell(0, 8, 'No allowances available.', 1, 1);
}
$pdf->Ln(6);

// --- Deductions Section ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Deductions', 0, 1);
$pdf->SetFont('Arial', '', 11);

if (count($deductions) > 0) {
    foreach ($deductions as $d) {
        $raw = $d['amount'];
        $isPercentage = strpos($raw, '%') !== false;
        $rate = $isPercentage ? $raw : '-';
        $deductionAmount = $isPercentage
            ? (floatval(preg_replace('/[^0-9.\-]/', '', $raw)) / 100) * $payroll['base_salary']
            : floatval(preg_replace('/[^0-9.\-]/', '', $raw));

        $pdf->Cell(95, 8, $d['name'] . ' (' . $rate . ')', 1);
        $pdf->Cell(95, 8, 'Ksh ' . number_format($deductionAmount, 2), 1, 1);
    }
} else {
    $pdf->Cell(0, 8, 'No deductions available.', 1, 1);
}
$pdf->Ln(10);

// --- Footer ---
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'This payslip was generated electronically by LynnTech ERP on ' . date('d M Y'), 0, 1, 'C');

$pdf->Output("I", "Payslip_{$payroll['full_name']}_{$monthText}_{$year}.pdf");
?>
