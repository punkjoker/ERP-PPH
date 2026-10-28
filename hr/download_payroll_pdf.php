
<?php
require 'db_con.php';
require 'fpdf.php';

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Validate month format
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

[$year, $monthNum] = explode('-', $selectedMonth);
$year = (int)$year;
$monthNum = (int)$monthNum;

$monthText = date('F', mktime(0, 0, 0, $monthNum, 10));
$monthNameYear = $monthText . ' ' . $year;

if ($user_id <= 0) {
    die('Invalid employee ID.');
}

// Fetch payroll record and workflow information

$stmt = $conn->prepare("
    SELECT
        pr.*,
        u.full_name,
        u.email,
        u.national_id,
        u.status,
        es.bank_name,
        es.bank_account
    FROM payroll_records pr

    INNER JOIN users u
        ON pr.user_id = u.user_id

    LEFT JOIN employee_salary es
        ON pr.user_id = es.user_id

    WHERE pr.user_id = ?
      AND pr.month = ?
      AND pr.year = ?

    LIMIT 1
");


$stmt->bind_param("isi", $user_id, $monthText, $year);
$stmt->execute();
$result = $stmt->get_result();
$payroll = $result->fetch_assoc();
$stmt->close();

if (!$payroll) {
    die("No payroll record found for this employee in " .
        htmlspecialchars($monthNameYear, ENT_QUOTES, 'UTF-8') . ".");
}

// Decode allowances and deductions
$details = json_decode($payroll['details'] ?? '', true);
$allowances = $details['allowances'] ?? [];
$deductions = $details['deductions'] ?? [];

// Return an underline when a value is empty or NULL
function pdfField($value, $length = 25) {
    $value = trim((string)($value ?? ''));

    return $value !== ''
        ? $value
        : str_repeat('_', $length);
}

// Initialize PDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// --- Header with logo ---
if (file_exists('images/lynn_logo.png')) {
    $pdf->Image('images/lynn_logo.png', 10, 8, 30);
}

$pdf->SetY(12);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 9, 'LYNNTECH CHEMICALS & EQUIPMENT LTD', 0, 1, 'C');

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 7, 'Employee Payslip', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'Payroll Period: ' . $monthNameYear, 0, 1, 'C');
$pdf->Ln(5);


// --- Employee Details ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Employee Details', 0, 1, 'L');

$pdf->SetFont('Arial', '', 10);

// Row 1: Employee Name and Status
$pdf->Cell(
    95, 8,
    'Name: ' . ($payroll['full_name'] ?? '-'),
    1, 0
);

$pdf->Cell(
    95, 8,
    'Status: ' . ucfirst($payroll['status'] ?? '-'),
    1, 1
);

// Row 2: Email and National ID
$pdf->Cell(
    95, 8,
    'Email: ' . ($payroll['email'] ?? '-'),
    1, 0
);

$pdf->Cell(
    95, 8,
    'National ID: ' . ($payroll['national_id'] ?? '-'),
    1, 1
);

// Row 3: Payroll Number and Bank Name
$pdf->Cell(
    95, 8,
    'Payroll No: ' . (
        !empty($payroll['payroll_no'])
            ? $payroll['payroll_no']
            : '-'
    ),
    1, 0
);

$pdf->Cell(
    95, 8,
    'Bank Name: ' . (
        !empty($payroll['bank_name'])
            ? $payroll['bank_name']
            : '-'
    ),
    1, 1
);

// Row 4: Bank Account Number
$pdf->Cell(
    190, 8,
    'Bank Account Number: ' . (
        !empty($payroll['bank_account'])
            ? $payroll['bank_account']
            : '-'
    ),
    1, 1
);

// Row 5: Payroll Month and Year
$pdf->Cell(
    95, 8,
    'Month: ' . $monthText,
    1, 0
);

$pdf->Cell(
    95, 8,
    'Year: ' . $year,
    1, 1
);

$pdf->Ln(6);

// --- Salary Summary ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Salary Summary', 0, 1);

$pdf->SetFont('Arial', '', 10);

$pdf->Cell(95, 8, 'Base Salary', 1, 0);
$pdf->Cell(95, 8, 'Ksh ' . number_format((float)$payroll['base_salary'], 2), 1, 1);

$pdf->Cell(95, 8, 'Total Allowances', 1, 0);
$pdf->Cell(95, 8, 'Ksh ' . number_format((float)$payroll['total_allowances'], 2), 1, 1);

$pdf->Cell(95, 8, 'Total Deductions', 1, 0);
$pdf->Cell(95, 8, 'Ksh ' . number_format((float)$payroll['total_deductions'], 2), 1, 1);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(230, 230, 230);

$pdf->Cell(95, 9, 'NET PAY', 1, 0, 'L', true);
$pdf->Cell(
    95,
    9,
    'Ksh ' . number_format((float)$payroll['net_pay'], 2),
    1,
    1,
    'L',
    true
);
$pdf->Ln(6);

// --- Allowances ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Allowances Breakdown', 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(120, 8, 'Allowance', 1, 0);
$pdf->Cell(70, 8, 'Amount (Ksh)', 1, 1, 'R');

$pdf->SetFont('Arial', '', 10);

if (!empty($allowances)) {
    foreach ($allowances as $a) {
        $allowanceName = $a['name'] ?? 'Allowance';
        $amount = (float)preg_replace(
            '/[^0-9.\-]/',
            '',
            (string)($a['amount'] ?? '0')
        );

        $pdf->Cell(120, 8, substr($allowanceName, 0, 60), 1, 0);
        $pdf->Cell(70, 8, number_format($amount, 2), 1, 1, 'R');
    }
} else {
    $pdf->Cell(190, 8, 'No allowances available.', 1, 1);
}

$pdf->Ln(5);

// --- Deductions ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Deductions Breakdown', 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(90, 8, 'Deduction', 1, 0);
$pdf->Cell(35, 8, 'Rate', 1, 0, 'C');
$pdf->Cell(65, 8, 'Amount (Ksh)', 1, 1, 'R');

$pdf->SetFont('Arial', '', 10);

if (!empty($deductions)) {
    foreach ($deductions as $d) {
        $raw = (string)($d['amount'] ?? '0');
        $isPercentage = strpos($raw, '%') !== false;
        $rate = $isPercentage ? $raw : '-';

        $numericValue = (float)preg_replace('/[^0-9.\-]/', '', $raw);

        $deductionAmount = $isPercentage
            ? ($numericValue / 100) * (float)$payroll['base_salary']
            : $numericValue;

        $deductionName = $d['name'] ?? 'Deduction';

        $pdf->Cell(90, 8, substr($deductionName, 0, 45), 1, 0);
        $pdf->Cell(35, 8, $rate, 1, 0, 'C');
        $pdf->Cell(65, 8, number_format($deductionAmount, 2), 1, 1, 'R');
    }
} else {
    $pdf->Cell(190, 8, 'No deductions available.', 1, 1);
}

$pdf->Ln(7);

// --- Payroll Workflow and Authorization ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Payroll Preparation, Approval & Payment', 0, 1);

$pdf->SetFont('Arial', '', 10);

// Prepared By
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(63, 8, 'PREPARED BY', 1, 0, 'C');
$pdf->Cell(63, 8, 'APPROVED BY', 1, 0, 'C');
$pdf->Cell(64, 8, 'PAID BY', 1, 1, 'C');

$pdf->SetFont('Arial', '', 9);

$pdf->Cell(
    63, 9,
    'Name: ' . pdfField($payroll['prepared_by'] ?? null, 16),
    1, 0
);
$pdf->Cell(
    63, 9,
    'Name: ' . pdfField($payroll['approved_by'] ?? null, 16),
    1, 0
);
$pdf->Cell(
    64, 9,
    'Name: ' . pdfField($payroll['accountant_name'] ?? null, 16),
    1, 1
);

$pdf->Cell(
    63, 9,
    'Date: ' . pdfField($payroll['date_prepared'] ?? null, 16),
    1, 0
);
$pdf->Cell(
    63, 9,
    'Date: ' . pdfField($payroll['approval_date'] ?? null, 16),
    1, 0
);
$pdf->Cell(
    64, 9,
    'Date: ' . pdfField($payroll['payment_date'] ?? null, 16),
    1, 1
);

$pdf->Cell(
    63, 9,
    'Status: ' . pdfField($payroll['preparation_status'] ?? null, 12),
    1, 0
);
$pdf->Cell(
    63, 9,
    'Status: ' . pdfField($payroll['approval_status'] ?? null, 12),
    1, 0
);
$pdf->Cell(
    64, 9,
    'Status: ' . pdfField($payroll['payment_status'] ?? null, 12),
    1, 1
);

$pdf->Ln(5);

// Signature lines for printed copies
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(63, 8, 'Signature: __________________', 0, 0);
$pdf->Cell(63, 8, 'Signature: __________________', 0, 0);
$pdf->Cell(64, 8, 'Signature: __________________', 0, 1);

$pdf->Ln(5);

// --- Footer ---
$pdf->SetFont('Arial', 'I', 8);
$pdf->MultiCell(
    0,
    5,
    'This payslip was generated electronically by LynnTech HR Manager on ' .
    date('d M Y') . '.',
    0,
    'C'
);

// Clean up output buffer before PDF output
if (ob_get_length()) {
    ob_end_clean();
}

$safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $payroll['full_name']);
$fileName = "Payslip_{$safeName}_{$monthText}_{$year}.pdf";

$pdf->Output('I', $fileName);
exit;
?>
