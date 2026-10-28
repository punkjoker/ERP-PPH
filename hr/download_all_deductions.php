
<?php
require 'fpdf.php';
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Selected payroll period
$months = [
    'January', 'February', 'March', 'April',
    'May', 'June', 'July', 'August',
    'September', 'October', 'November', 'December'
];

$month = $_GET['month'] ?? date('F');
$year = filter_var(
    $_GET['year'] ?? date('Y'),
    FILTER_VALIDATE_INT
);

if (
    !in_array($month, $months, true) ||
    !$year ||
    $year < 2000 ||
    $year > 2100
) {
    http_response_code(400);
    exit('Invalid payroll month or year.');
}

$period = $month . ' ' . $year;

// Fetch active staff
$employees = [];

$empQuery = $conn->query("
    SELECT u.user_id, u.full_name
    FROM users u
    INNER JOIN groups g ON u.group_id = g.group_id
    WHERE g.group_name = 'Staff'
      AND u.status = 'active'
    ORDER BY u.full_name ASC
");

while ($row = $empQuery->fetch_assoc()) {
    $employees[] = $row;
}

// Fetch saved payroll records
$payrolls = [];
$deductionsList = [];

$stmt = $conn->prepare("
    SELECT *
    FROM payroll_records
    WHERE month = ?
      AND year = ?
");

$stmt->bind_param('si', $month, $year);
$stmt->execute();

$result = $stmt->get_result();

while ($payroll = $result->fetch_assoc()) {

    $userId = (int) $payroll['user_id'];

    $details = json_decode(
        $payroll['details'] ?? '{}',
        true
    );

    if (!is_array($details)) {
        $details = [];
    }

    $payroll['decoded_details'] = $details;
    $payrolls[$userId] = $payroll;

    foreach (($details['deductions'] ?? []) as $deduction) {

        $name = trim((string) ($deduction['name'] ?? ''));

        if ($name !== '') {
            $deductionsList[$name] = $name;
        }
    }
}

$stmt->close();

ksort($deductionsList);

// Calculate a deduction amount.
// Percentage deductions use gross pay, matching process_payroll.php.
function calculateDeduction($value, $grossPay)
{
    $raw = trim((string) $value);

    $numeric = (float) str_replace(
        [',', '%'],
        '',
        $raw
    );

    if (str_contains($raw, '%')) {
        return ($numeric / 100) * $grossPay;
    }

    return $numeric;
}

// Prepare report rows and totals
$reportRows = [];

$totalsDeductions = array_fill_keys(
    array_keys($deductionsList),
    0.0
);

$totalGrossPay = 0.0;
$totalAllDeductions = 0.0;
$totalNetPay = 0.0;

foreach ($employees as $employee) {

    $userId = (int) $employee['user_id'];
    $payroll = $payrolls[$userId] ?? null;

    $grossPay = 0.0;
    $totalDeductions = 0.0;
    $netPay = 0.0;

    $deductionAmounts = array_fill_keys(
        array_keys($deductionsList),
        0.0
    );

    if ($payroll) {

        $baseSalary = (float) ($payroll['base_salary'] ?? 0);

        $totalAllowances = (float) (
            $payroll['total_allowances'] ?? 0
        );

        $grossPay = $baseSalary + $totalAllowances;

        // Use the saved payroll totals.
        $totalDeductions = (float) (
            $payroll['total_deductions'] ?? 0
        );

        $netPay = (float) (
            $payroll['net_pay'] ?? 0
        );

        $details = $payroll['decoded_details'];

        foreach (($details['deductions'] ?? []) as $deduction) {

            $name = trim((string) (
                $deduction['name'] ?? ''
            ));

            if (
                $name === '' ||
                !array_key_exists($name, $deductionAmounts)
            ) {
                continue;
            }

            $amount = calculateDeduction(
                $deduction['amount'] ?? 0,
                $grossPay
            );

            // Accumulate repeated deduction names.
            $deductionAmounts[$name] += $amount;
            $totalsDeductions[$name] += $amount;
        }
    }

    $reportRows[] = [
        'employee' => $employee['full_name'],
        'gross_pay' => $grossPay,
        'deductions' => $deductionAmounts,
        'total_deductions' => $totalDeductions,
        'net_pay' => $netPay
    ];

    $totalGrossPay += $grossPay;
    $totalAllDeductions += $totalDeductions;
    $totalNetPay += $netPay;
}

// PDF class
class PDF extends FPDF
{
    public $reportPeriod = '';
    public $columnWidths = [];
    public $deductionNames = [];

    function Header()
    {
        $logo = __DIR__ . '/images/lynn_logo.png';

        if (file_exists($logo)) {
            $this->Image($logo, 10, 6, 25);
        }

        $this->SetFont('Arial', 'B', 15);

        $this->Cell(
            0, 8,
            'LYNNTECH CHEMICALS & EQUIPMENT LTD',
            0, 1, 'C'
        );

        $this->SetFont('Arial', 'B', 11);

        $this->Cell(
            0, 7,
            'ALL STAFF PAYROLL DEDUCTIONS REPORT',
            0, 1, 'C'
        );

        $this->SetFont('Arial', '', 10);

        $this->Cell(
            0, 7,
            'Payroll Period: ' . $this->reportPeriod,
            0, 1, 'C'
        );

        $this->Ln(5);

        $this->drawTableHeader();
    }

    function drawTableHeader()
    {
        $w = $this->columnWidths;

        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(225, 235, 245);

        $this->Cell(
            $w['employee'], 9,
            'Employee Name', 1, 0, 'C', true
        );

        $this->Cell(
            $w['gross'], 9,
            'Gross Pay', 1, 0, 'C', true
        );

        foreach ($this->deductionNames as $name) {

            $label = strlen($name) > 15
                ? substr($name, 0, 12) . '...'
                : $name;

            $this->Cell(
                $w['deduction'], 9,
                $label, 1, 0, 'C', true
            );
        }

        $this->Cell(
            $w['total'], 9,
            'Total Ded.', 1, 0, 'C', true
        );

        $this->Cell(
            $w['net'], 9,
            'Net Pay', 1, 1, 'C', true
        );
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);

        $this->Cell(
            0, 8,
            'Generated on ' . date('d M Y h:i A') .
            ' | Page ' . $this->PageNo(),
            0, 0, 'C'
        );
    }
}

// Calculate column widths to fit A4 landscape
$pageWidth = 277; // 297mm minus 10mm margins each side

$employeeWidth = 55;
$grossWidth = 32;
$totalWidth = 34;
$netWidth = 32;

$deductionCount = count($deductionsList);

$remainingWidth = $pageWidth
    - $employeeWidth
    - $grossWidth
    - $totalWidth
    - $netWidth;

$deductionWidth = $deductionCount > 0
    ? $remainingWidth / $deductionCount
    : 0;

// Prevent unreadably narrow deduction columns.
// Use a wider A3 landscape page when needed.
$useA3 = $deductionCount > 4;

if ($useA3) {

    $pageWidth = 400; // A3 landscape minus margins

    $employeeWidth = 65;
    $grossWidth = 38;
    $totalWidth = 40;
    $netWidth = 38;

    $remainingWidth = $pageWidth
        - $employeeWidth
        - $grossWidth
        - $totalWidth
        - $netWidth;

    $deductionWidth = $remainingWidth / $deductionCount;
}

$pdf = new PDF(
    'L',
    'mm',
    $useA3 ? 'A3' : 'A4'
);

$pdf->reportPeriod = $period;
$pdf->deductionNames = array_values($deductionsList);

$pdf->columnWidths = [
    'employee' => $employeeWidth,
    'gross' => $grossWidth,
    'deduction' => $deductionWidth,
    'total' => $totalWidth,
    'net' => $netWidth
];

$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Table rows
$pdf->SetFont('Arial', '', 8);

$w = $pdf->columnWidths;

foreach ($reportRows as $row) {

    $pdf->Cell(
        $w['employee'], 8,
        substr($row['employee'], 0, 30),
        1, 0
    );

    $pdf->Cell(
        $w['gross'], 8,
        number_format($row['gross_pay'], 2),
        1, 0, 'R'
    );

    foreach ($deductionsList as $name) {

        $amount = $row['deductions'][$name] ?? 0;

        $pdf->Cell(
            $w['deduction'], 8,
            number_format($amount, 2),
            1, 0, 'R'
        );
    }

    $pdf->Cell(
        $w['total'], 8,
        number_format($row['total_deductions'], 2),
        1, 0, 'R'
    );

    $pdf->Cell(
        $w['net'], 8,
        number_format($row['net_pay'], 2),
        1, 1, 'R'
    );
}

// Grand totals
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(220, 230, 240);

$pdf->Cell(
    $w['employee'], 9,
    'GRAND TOTAL', 1, 0, 'L', true
);

$pdf->Cell(
    $w['gross'], 9,
    number_format($totalGrossPay, 2),
    1, 0, 'R', true
);

foreach ($deductionsList as $name) {

    $pdf->Cell(
        $w['deduction'], 9,
        number_format($totalsDeductions[$name], 2),
        1, 0, 'R', true
    );
}

$pdf->Cell(
    $w['total'], 9,
    number_format($totalAllDeductions, 2),
    1, 0, 'R', true
);

$pdf->Cell(
    $w['net'], 9,
    number_format($totalNetPay, 2),
    1, 1, 'R', true
);

// Output PDF
$fileName = 'All_Deductions_' .
    $month . '_' . $year . '.pdf';

$pdf->Output('D', $fileName);
exit;
?>
