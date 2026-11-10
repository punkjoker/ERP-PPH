
<?php
session_start();

require_once __DIR__ . '/db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    exit("Database connection failed.");
}

$conn->set_charset("utf8mb4");

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

function p9_pdf_number($value): ?float {
    if ($value === null || $value === '' || !is_scalar($value)) {
        return null;
    }

    if (is_string($value)) {
        $value = str_replace(',', '', trim($value));
    }

    return is_numeric($value) ? (float)$value : null;
}

function p9_pdf_path(array $source, array $paths): ?float {
    foreach ($paths as $path) {
        $value = $source;

        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                $value = null;
                break;
            }

            $value = $value[$part];
        }

        $number = p9_pdf_number($value);

        if ($number !== null) {
            return $number;
        }
    }

    return null;
}

function p9_pdf_line_amount(array $line): ?float {
    return p9_pdf_number(
        $line['amount']
        ?? $line['calculated_amount']
        ?? null
    );
}

function p9_pdf_find_deduction(array $lines, array $needles): float {
    $total = 0.0;

    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }

        $name = strtolower(trim(
            (string)($line['name'] ?? $line['code'] ?? '')
        ));

        foreach ($needles as $needle) {
            if (strpos($name, $needle) !== false) {
                $amount = p9_pdf_line_amount($line);

                if ($amount !== null) {
                    $total += $amount;
                }

                break;
            }
        }
    }

    return $total;
}

function p9_pdf_month_number($month): int {
    $month = trim((string)$month);

    if (ctype_digit($month)) {
        $number = (int)$month;
        return ($number >= 1 && $number <= 12) ? $number : 0;
    }

    $timestamp = strtotime($month);

    return $timestamp ? (int)date('n', $timestamp) : 0;
}

function p9_pdf_month_name(int $month): string {
    return date('F', mktime(0, 0, 0, $month, 1));
}

function p9_pdf_build_month(array $payroll, int $year, int $month): array {
    $details = json_decode((string)($payroll['details'] ?? ''), true);

    if (!is_array($details)) {
        $details = [];
    }

    $deductions = is_array($details['deductions'] ?? null)
        ? $details['deductions']
        : [];

    $basic = (float)($payroll['base_salary'] ?? 0);
    $allowances = (float)($payroll['total_allowances'] ?? 0);

    $benefits = p9_pdf_path($details, [
        'p9.non_cash_benefits',
        'p9.benefits_non_cash',
        'audit.non_cash_benefits',
        'benefits.non_cash'
    ]) ?? 0.0;

    $quarters = p9_pdf_path($details, [
        'p9.value_of_quarters',
        'p9.quarters_value',
        'audit.value_of_quarters',
        'benefits.value_of_quarters'
    ]) ?? 0.0;

    $gross = $basic + $allowances + $benefits + $quarters;

    // Actual employee pension contribution.
    $pensionActual = p9_pdf_path($details, [
        'pension.employee_amount',
        'p9.pension_actual',
        'audit.employee_pension'
    ]);

    if ($pensionActual === null) {
        $pensionActual = p9_pdf_find_deduction(
            $deductions,
            ['employee pension', 'pension contribution']
        );
    }

    $pension30Percent = $basic * 0.30;

    // P9A instructions: Ksh 20,000 before December 2024
    // and Ksh 30,000 from December 2024.
    $pensionLimit = (
        $year < 2024 || ($year === 2024 && $month < 12)
    ) ? 20000.00 : 30000.00;

    $pensionTaxQualified = !(
        isset($details['pension']['tax_qualified']) &&
        !$details['pension']['tax_qualified']
    );

    $pensionAllowed = 0.0;

    if ($pensionTaxQualified && $pensionActual !== null) {
        $pensionAllowed = max(0, min(
            $pension30Percent,
            $pensionActual,
            $pensionLimit
        ));
    }

    $ahl = p9_pdf_path($details, [
        'p9.ahl',
        'p9.affordable_housing_levy',
        'audit.ahl'
    ]);

    if ($ahl === null) {
        $ahl = p9_pdf_find_deduction(
            $deductions,
            ['affordable housing levy', 'housing levy', 'ahl']
        );
    }

    $shif = p9_pdf_path($details, [
        'p9.shif',
        'p9.social_health_insurance',
        'audit.shif'
    ]);

    if ($shif === null) {
        $shif = p9_pdf_find_deduction(
            $deductions,
            ['shif', 'social health insurance']
        );
    }

    $prmf = p9_pdf_path($details, [
        'p9.prmf',
        'p9.post_retirement_medical_fund',
        'audit.prmf'
    ]);

    if ($prmf === null) {
        $prmf = p9_pdf_find_deduction(
            $deductions,
            ['post retirement medical', 'prmf']
        );
    }

    $ownerInterest = p9_pdf_path($details, [
        'p9.owner_occupied_interest',
        'p9.owner_occupier_interest',
        'audit.owner_occupied_interest'
    ]);

    if ($ownerInterest === null) {
        $ownerInterest = p9_pdf_find_deduction(
            $deductions,
            ['owner occupied interest', 'owner occupier interest']
        );
    }

    $ahl = $ahl ?? 0.0;
    $shif = $shif ?? 0.0;
    $prmf = $prmf ?? 0.0;
    $ownerInterest = $ownerInterest ?? 0.0;

    $totalDeductions = (
        $pensionAllowed +
        $ahl +
        $shif +
        $prmf +
        $ownerInterest
    );

    $chargeable = $gross - $totalDeductions;

    $taxCharged = p9_pdf_path($details, [
        'audit.tax_charged',
        'calculation.paye_before_relief',
        'calculation.tax_charged',
        'calculation.gross_paye',
        'calculation.tax_before_relief',
        'tax.tax_charged',
        'tax.gross_paye',
        'tax_charged',
        'gross_paye',
        'tax_before_relief'
    ]);

    $paye = p9_pdf_path($details, [
        'audit.paye_deducted',
        'calculation.tax_deducted',
        'calculation.paye',
        'calculation.paye_deducted',
        'tax.tax_deducted',
        'tax.paye',
        'tax_deducted',
        'paye',
        'paye_deducted'
    ]);

    if ($paye === null) {
        $paye = p9_pdf_find_deduction(
            $deductions,
            ['income tax (paye)', 'paye']
        );
    }

    $totalRelief = p9_pdf_path($details, [
        'audit.tax_relief',
        'calculation.tax_relief',
        'calculation.relief_applied',
        'tax.tax_relief',
        'tax_relief',
        'relief_applied'
    ]);

    if ($taxCharged !== null && $paye !== null) {
        $totalRelief = max(0, $taxCharged - $paye);
    }

    $personalRelief = p9_pdf_path($details, [
        'p9.personal_relief',
        'audit.personal_relief',
        'calculation.personal_relief',
        'tax.personal_relief'
    ]);

    $insuranceRelief = p9_pdf_path($details, [
        'p9.insurance_relief',
        'audit.insurance_relief',
        'calculation.insurance_relief',
        'tax.insurance_relief'
    ]);

    if ($personalRelief === null && $totalRelief !== null) {
        $personalRelief = min(2400.00, $totalRelief);
    }

    if ($insuranceRelief === null && $totalRelief !== null) {
        $insuranceRelief = max(
            0,
            $totalRelief - ($personalRelief ?? 0)
        );
    }

    return [
        'month_name' => p9_pdf_month_name($month),
        'basic' => $basic,
        'benefits' => $benefits,
        'quarters' => $quarters,
        'gross' => $gross,
        'pension_e1' => $pension30Percent,
        'pension_e2' => $pensionActual,
        'pension_e3' => $pensionLimit,
        'pension_allowed' => $pensionAllowed,
        'ahl' => $ahl,
        'shif' => $shif,
        'prmf' => $prmf,
        'owner_interest' => $ownerInterest,
        'total_deductions' => $totalDeductions,
        'chargeable' => $chargeable,
        'tax_charged' => $taxCharged,
        'personal_relief' => $personalRelief,
        'insurance_relief' => $insuranceRelief,
        'paye' => $paye
    ];
}

function p9_pdf_total(array $rows, string $key): ?float {
    $found = false;
    $total = 0.0;

    foreach ($rows as $row) {
        if (!isset($row[$key]) || $row[$key] === null) {
            continue;
        }

        $found = true;
        $total += (float)$row[$key];
    }

    return $found ? $total : null;
}

function p9_pdf_text($value): string {
    $value = trim((string)$value);

    if ($value === '') {
        return '';
    }

    $converted = iconv(
        'UTF-8',
        'Windows-1252//TRANSLIT//IGNORE',
        $value
    );

    return $converted === false ? '' : $converted;
}

function p9_pdf_amount($value): string {
    return $value === null
        ? ''
        : number_format((float)$value, 2, '.', ',');
}

function p9_pdf_write(
    $pdf,
    float $x,
    float $y,
    float $width,
    $value,
    bool $right = true,
    float $fontSize = 5.0
): void {
    if ($value === null || $value === '') {
        return;
    }

    $pdf->SetFont('Arial', '', $fontSize);
    $pdf->SetXY($x, $y);

    $pdf->Cell(
        $width,
        3.0,
        p9_pdf_text((string)$value),
        0,
        0,
        $right ? 'R' : 'L'
    );
}

function p9_pdf_write_amount(
    $pdf,
    float $x,
    float $y,
    float $width,
    $value,
    float $fontSize = 4.7
): void {
    if ($value === null) {
        return;
    }

    p9_pdf_write(
        $pdf,
        $x,
        $y,
        $width,
        p9_pdf_amount($value),
        true,
        $fontSize
    );
}

// Validate requested year.
$year = isset($_GET['year']) && ctype_digit((string)$_GET['year'])
    ? (int)$_GET['year']
    : 0;

if ($year < 2000 || $year > ((int)date('Y') + 1)) {
    http_response_code(400);
    exit("Invalid tax year.");
}

// Employee profile and KRA PIN.
$stmt = $conn->prepare("
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        e.kra_pin
    FROM users u
    LEFT JOIN employees e
        ON u.employee_id = e.employee_id
    WHERE u.user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employee) {
    http_response_code(404);
    exit("Employee profile not found.");
}

if (empty($employee['kra_pin'])) {
    http_response_code(400);
    exit("Your employee KRA PIN is missing. Please contact HR.");
}

// Employer details.
$stmt = $conn->prepare("
    SELECT employer_name, kra_pin, postal_address, town, phone, email
    FROM employer_tax_details
    WHERE id = 1
    LIMIT 1
");

$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employer || empty($employer['employer_name']) || empty($employer['kra_pin'])) {
    http_response_code(400);
    exit("Employer tax details are incomplete. Please contact HR.");
}

// Retrieve only this employee's payroll records for this year.
$stmt = $conn->prepare("
    SELECT
        payroll_id,
        user_id,
        month,
        year,
        base_salary,
        total_allowances,
        total_deductions,
        net_pay,
        details
    FROM payroll_records
    WHERE user_id = ?
      AND year = ?
    ORDER BY payroll_id ASC
");

$stmt->bind_param("ii", $userId, $year);
$stmt->execute();
$result = $stmt->get_result();

$payrollByMonth = [];

while ($payroll = $result->fetch_assoc()) {
    $monthNumber = p9_pdf_month_number($payroll['month']);

    if ($monthNumber >= 1 && $monthNumber <= 12) {
        $payrollByMonth[$monthNumber] = $payroll;
    }
}

$stmt->close();

if (!$payrollByMonth) {
    http_response_code(404);
    exit("No payroll records were found for tax year " . $year . ".");
}

// Build twelve monthly rows.
$rows = [];

for ($month = 1; $month <= 12; $month++) {
    if (isset($payrollByMonth[$month])) {
        $rows[] = p9_pdf_build_month(
            $payrollByMonth[$month],
            $year,
            $month
        );
    } else {
        $rows[] = [
            'month_name' => p9_pdf_month_name($month)
        ];
    }
}

/*
|--------------------------------------------------------------------------
| GENERATE P9A PDF FROM SCRATCH USING FPDF
|--------------------------------------------------------------------------
| No background PDF template and no FPDI coordinate overlay.
*/

// Composer is installed in the LYNNTECH_MNGMNT parent directory.
$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    // Fallback if Composer is installed directly in this folder.
    $autoload = __DIR__ . '/vendor/autoload.php';
}

if (!file_exists($autoload)) {
    http_response_code(500);
    exit("Composer autoload file not found. Check your vendor directory.");
}

require_once $autoload;

if (!class_exists('FPDF')) {
    http_response_code(500);
    exit("FPDF could not be loaded. Check your Composer installation.");
}

/**
 * Draw a P9A table heading cell.
 */
function p9_draw_heading_cell(
    $pdf,
    float $x,
    float $y,
    float $width,
    float $height,
    string $label
): void {
    $pdf->SetDrawColor(50, 50, 50);
    $pdf->SetLineWidth(0.15);
    $pdf->Rect($x, $y, $width, $height);

    $pdf->SetFont('Arial', 'B', 4.5);
    $pdf->SetTextColor(20, 20, 20);

    $pdf->SetXY($x + 0.4, $y + 1);

    $pdf->MultiCell(
        $width - 0.8,
        3.0,
        p9_pdf_text($label),
        0,
        'C'
    );
}

/**
 * Draw a monthly or annual amount cell.
 */
function p9_draw_amount_cell(
    $pdf,
    float $x,
    float $y,
    float $width,
    float $height,
    $value,
    bool $bold = false
): void {
    $pdf->SetDrawColor(70, 70, 70);
    $pdf->SetLineWidth(0.12);
    $pdf->Rect($x, $y, $width, $height);

    if ($value === null || $value === '') {
        return;
    }

    $pdf->SetFont('Arial', $bold ? 'B' : '', 4.5);
    $pdf->SetTextColor(15, 15, 15);

    $pdf->SetXY($x + 0.3, $y + 1.0);

    $pdf->Cell(
        $width - 0.6,
        $height - 1.5,
        p9_pdf_text(p9_pdf_amount($value)),
        0,
        0,
        'R'
    );
}

function p9_draw_info_field(
    $pdf,
    float $x,
    float $y,
    float $width,
    string $label,
    $value
): void {
    $labelWidth = 30;
    $lineY = $y + 5;

    // Field label.
    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetXY($x, $y);

    $pdf->Cell(
        $labelWidth,
        4,
        p9_pdf_text($label),
        0,
        0,
        'L'
    );

    // Field value.
    $valueX = $x + $labelWidth;
    $valueWidth = $width - $labelWidth;

    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0, 0, 0);

    $displayValue = trim((string)$value);

    // Fit long values within the available width.
    while (
        $pdf->GetStringWidth(p9_pdf_text($displayValue)) > $valueWidth - 2
        && $pdf->GetFontSizePt() > 5
    ) {
        $pdf->SetFontSize($pdf->GetFontSizePt() - 0.5);
    }

    $pdf->SetXY($valueX, $y);

    $pdf->Cell(
        $valueWidth,
        4,
        p9_pdf_text($displayValue),
        0,
        0,
        'L'
    );

    // Underline beneath the value.
    $pdf->SetDrawColor(40, 40, 40);
    $pdf->SetLineWidth(0.2);

    $pdf->Line(
        $valueX,
        $lineY,
        $valueX + $valueWidth,
        $lineY
    );
}
try {

    $pdf = new FPDF('L', 'mm', 'A4');

    $pdf->SetCreator('Lynntech Manager');
    $pdf->SetAuthor('Lynntech');
    $pdf->SetTitle('P9A Tax Deduction Card - ' . $year);
    $pdf->SetSubject('Employee Annual P9A Tax Deduction Card');

    $pdf->SetMargins(6, 6, 6);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    $pdf->SetTextColor(0, 0, 0);

    /*
|--------------------------------------------------------------------------
| PAGE TITLE AND KRA LOGO
|--------------------------------------------------------------------------
*/

$logoPath = __DIR__ . '/images/KRA.png';

// KRA logo at the top-left.
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 10, 7, 25, 0, 'PNG');
}

$pdf->SetFont('Arial', 'B', 13);
$pdf->SetXY(6, 7);
$pdf->Cell(285, 7, 'PAY AS YOU EARN (P.A.Y.E.)', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetXY(6, 14);
$pdf->Cell(285, 6, 'P9A - TAX DEDUCTION CARD', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetXY(6, 21);
$pdf->Cell(285, 5, 'YEAR: ' . $year, 0, 1, 'C');
    /*
    |--------------------------------------------------------------------------
    | EMPLOYER AND EMPLOYEE DETAILS
    |--------------------------------------------------------------------------
    */
/*
|--------------------------------------------------------------------------
| EMPLOYER AND EMPLOYEE DETAILS
|--------------------------------------------------------------------------
*/

// Left side: employer details.
p9_draw_info_field(
    $pdf, 9, 31, 135,
    "Employer's Name:",
    $employer['employer_name']
);

p9_draw_info_field(
    $pdf, 9, 40, 135,
    "Employer's PIN:",
    $employer['kra_pin']
);

// Right side: employee details.
p9_draw_info_field(
    $pdf, 150, 31, 137,
    "Employee's Main Name:",
    $employee['full_name']
);

p9_draw_info_field(
    $pdf, 150, 40, 137,
    "Employee's PIN:",
    $employee['kra_pin']
);
    /*
    |--------------------------------------------------------------------------
    | P9A MONTHLY TABLE
    |--------------------------------------------------------------------------
    */

    $tableX = 6;
    $tableY = 54;
    $monthWidth = 23;
    $headerHeight = 22;
    $rowHeight = 4.4;

    /*
     * The 17 columns correspond to the P9A form:
     *
     * A   Basic Salary
     * B   Benefits - Non Cash
     * C   Value of Quarters
     * D   Total Gross Pay
     * E1  30% of A
     * E2  Actual Pension
     * E3  Fixed 30,000 p.m.
     * F   Affordable Housing Levy
     * G   SHIF
     * H   Post Retirement Medical Fund
     * I   Owner Occupied Interest
     * J   Total Deductions
     * K   Chargeable Pay
     * L   Tax Charged
     * M   Personal Relief
     * N   Insurance Relief
     * O   PAYE Tax
     */

    $columns = [
        ['key' => 'basic',             'label' => 'A' . "\n" . 'BASIC' . "\n" . 'SALARY', 'width' => 16],
        ['key' => 'benefits',          'label' => 'B' . "\n" . 'BENEFITS' . "\n" . 'NON-CASH', 'width' => 15],
        ['key' => 'quarters',          'label' => 'C' . "\n" . 'VALUE OF' . "\n" . 'QUARTERS', 'width' => 15],
        ['key' => 'gross',             'label' => 'D' . "\n" . 'TOTAL GROSS' . "\n" . 'PAY', 'width' => 16],
        ['key' => 'pension_e1',        'label' => 'E1' . "\n" . '30% OF A', 'width' => 15],
        ['key' => 'pension_e2',        'label' => 'E2' . "\n" . 'ACTUAL', 'width' => 13],
        ['key' => 'pension_e3',        'label' => 'E3' . "\n" . 'FIXED 30,000' . "\n" . 'P.M.', 'width' => 15],
        ['key' => 'ahl',               'label' => 'F' . "\n" . 'AHL', 'width' => 14],
        ['key' => 'shif',              'label' => 'G' . "\n" . 'SHIF', 'width' => 14],
        ['key' => 'prmf',              'label' => 'H' . "\n" . 'PRMF', 'width' => 14],
        ['key' => 'owner_interest',    'label' => 'I' . "\n" . 'OWNER OCCUPIED' . "\n" . 'INTEREST', 'width' => 15],
        ['key' => 'total_deductions',  'label' => 'J' . "\n" . 'TOTAL' . "\n" . 'DEDUCTIONS', 'width' => 16],
        ['key' => 'chargeable',        'label' => 'K' . "\n" . 'CHARGEABLE' . "\n" . 'PAY', 'width' => 15],
        ['key' => 'tax_charged',       'label' => 'L' . "\n" . 'TAX' . "\n" . 'CHARGED', 'width' => 14],
        ['key' => 'personal_relief',   'label' => 'M' . "\n" . 'PERSONAL' . "\n" . 'RELIEF', 'width' => 14],
        ['key' => 'insurance_relief',  'label' => 'N' . "\n" . 'INSURANCE' . "\n" . 'RELIEF', 'width' => 14],
        ['key' => 'paye',              'label' => 'O' . "\n" . 'PAYE' . "\n" . 'TAX', 'width' => 14]
    ];

    // Month heading.
    p9_draw_heading_cell(
        $pdf,
        $tableX,
        $tableY,
        $monthWidth,
        $headerHeight,
        'MONTH'
    );

    // Draw the 17 column headings.
    $currentX = $tableX + $monthWidth;

    foreach ($columns as $column) {
        p9_draw_heading_cell(
            $pdf,
            $currentX,
            $tableY,
            $column['width'],
            $headerHeight,
            $column['label']
        );

        $currentX += $column['width'];
    }

    /*
    |--------------------------------------------------------------------------
    | JANUARY TO DECEMBER
    |--------------------------------------------------------------------------
    */

    $bodyY = $tableY + $headerHeight;

    for ($monthIndex = 0; $monthIndex < 12; $monthIndex++) {

        $row = $rows[$monthIndex] ?? [];
        $currentY = $bodyY + ($monthIndex * $rowHeight);

        // Month name.
        $pdf->SetDrawColor(70, 70, 70);
        $pdf->Rect(
            $tableX,
            $currentY,
            $monthWidth,
            $rowHeight
        );

        $pdf->SetFont('Arial', 'B', 5);
        $pdf->SetXY($tableX + 0.5, $currentY + 1);

        $pdf->Cell(
            $monthWidth - 1,
            $rowHeight - 1,
            p9_pdf_text(p9_pdf_month_name($monthIndex + 1)),
            0,
            0,
            'L'
        );

        // Monthly figures.
        $currentX = $tableX + $monthWidth;

        foreach ($columns as $column) {

            $key = $column['key'];
            $value = $row[$key] ?? null;

            p9_draw_amount_cell(
                $pdf,
                $currentX,
                $currentY,
                $column['width'],
                $rowHeight,
                $value
            );

            $currentX += $column['width'];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ANNUAL TOTAL ROW
    |--------------------------------------------------------------------------
    */

    $totalY = $bodyY + (12 * $rowHeight);
    $totalHeight = 5;

    $pdf->SetFillColor(225, 230, 235);
    $pdf->SetDrawColor(50, 50, 50);

    $pdf->Rect(
        $tableX,
        $totalY,
        $monthWidth,
        $totalHeight,
        'DF'
    );

    $pdf->SetFont('Arial', 'B', 5.5);
    $pdf->SetXY($tableX + 0.5, $totalY + 1.2);

    $pdf->Cell(
        $monthWidth - 1,
        3,
        'TOTAL',
        0,
        0,
        'L'
    );

    $currentX = $tableX + $monthWidth;

    foreach ($columns as $column) {

        $key = $column['key'];
        $total = p9_pdf_total($rows, $key);

        $pdf->SetFillColor(225, 230, 235);

        p9_draw_amount_cell(
            $pdf,
            $currentX,
            $totalY,
            $column['width'],
            $totalHeight,
            $total,
            true
        );

        $currentX += $column['width'];
    }

    /*
    |--------------------------------------------------------------------------
    | ANNUAL SUMMARY
    |--------------------------------------------------------------------------
    */

    $summaryY = $totalY + 10;

    $annualChargeable = p9_pdf_total($rows, 'chargeable');
    $annualTax = p9_pdf_total($rows, 'paye');

    // Summary heading.
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetXY(6, $summaryY);

    $pdf->Cell(
        285,
        5,
        'ANNUAL TAX SUMMARY',
        0,
        1,
        'C'
    );

    // Chargeable pay box.
    $pdf->SetDrawColor(70, 70, 70);
    $pdf->Rect(35, $summaryY + 7, 105, 13);

    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetXY(38, $summaryY + 9);
    $pdf->Cell(48, 4, 'TOTAL CHARGEABLE PAY:', 0, 0, 'L');

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY(88, $summaryY + 9);
    $pdf->Cell(
        48,
        4,
        p9_pdf_text(p9_pdf_amount($annualChargeable)),
        0,
        0,
        'R'
    );

    // PAYE box.
    $pdf->Rect(157, $summaryY + 7, 105, 13);

    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetXY(160, $summaryY + 9);
    $pdf->Cell(48, 4, 'TOTAL PAYE TAX:', 0, 0, 'L');

    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY(210, $summaryY + 9);
    $pdf->Cell(
        48,
        4,
        p9_pdf_text(p9_pdf_amount($annualTax)),
        0,
        0,
        'R'
    );

    /*
    |--------------------------------------------------------------------------
    | FOOTER
    |--------------------------------------------------------------------------
    */

    $pdf->SetFont('Arial', 'I', 6);
    $pdf->SetTextColor(90, 90, 90);

    $pdf->SetXY(6, 198);

    $pdf->Cell(
        285,
        4,
        'Generated by Lynntech Manager | P9A Tax Deduction Card | Tax Year ' . $year,
        0,
        0,
        'C'
    );

    /*
    |--------------------------------------------------------------------------
    | OUTPUT
    |--------------------------------------------------------------------------
    */

    $safeName = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '_',
        (string)$employee['full_name']
    );

    $filename = 'P9A_' . $safeName . '_' . $year . '.pdf';

    $pdf->Output('D', $filename);
    exit;

} catch (Throwable $e) {

    error_log(
        "P9 PDF generation error: " . $e->getMessage()
    );

    if (!headers_sent()) {
        http_response_code(500);
    }

    exit(
        "The P9A PDF could not be generated. Please contact HR."
    );
}
