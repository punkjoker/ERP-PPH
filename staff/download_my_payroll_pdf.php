
<?php
session_start();

require 'db_con.php';
require 'fpdf.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// --------------------------------------------------
// SECURITY: EMPLOYEE CAN ONLY DOWNLOAD OWN PAYSLIP
// --------------------------------------------------

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Please log in to download your payslip.');
}

$userId = (int)$_SESSION['user_id'];
$payrollId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$payrollId) {
    http_response_code(400);
    exit('Invalid payroll record.');
}

$stmt = $conn->prepare("
    SELECT pr.*, u.full_name, u.email, u.national_id, u.status
    FROM payroll_records pr
    INNER JOIN users u ON pr.user_id = u.user_id
    WHERE pr.payroll_id = ?
      AND pr.user_id = ?
    LIMIT 1
");

$stmt->bind_param('ii', $payrollId, $userId);
$stmt->execute();

$payroll = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payroll) {
    http_response_code(404);
    exit('Payroll record not found or access denied.');
}

// --------------------------------------------------
// DECODE SAVED PAYROLL DETAILS
// --------------------------------------------------

$details = json_decode($payroll['details'] ?? '', true);
$details = is_array($details) ? $details : [];

$allowances = is_array($details['allowances'] ?? null)
    ? $details['allowances'] : [];

$deductions = is_array($details['deductions'] ?? null)
    ? $details['deductions'] : [];

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

function pdfPath($root, $paths) {
    foreach ($paths as $path) {
        $value = $root;

        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                $value = null;
                break;
            }

            $value = $value[$key];
        }

        if (is_scalar($value) && is_numeric($value)) {
            return (float)$value;
        }
    }

    return null;
}

function pdfLineAmount($line) {
    $value = $line['calculated_amount'] ?? $line['amount'] ?? null;

    if (is_numeric($value)) {
        return (float)$value;
    }

    if (is_string($value) &&
        preg_match('/^\s*-?[\d,]+(?:\.\d+)?\s*$/', $value)) {
        return (float)str_replace(',', '', $value);
    }

    return null;
}

function pdfNamedDeduction($lines, $names) {
    foreach ($lines as $line) {
        if (!is_array($line)) continue;

        $name = strtoupper(trim((string)($line['name'] ?? '')));

        if (in_array($name, $names, true)) {
            return pdfLineAmount($line);
        }
    }

    return null;
}

function pdfMoney($amount) {
    return number_format((float)$amount, 2);
}

function pdfText($value) {
    $value = (string)($value ?? '');

    if (function_exists('iconv')) {
        $converted = @iconv(
            'UTF-8',
            'windows-1252//TRANSLIT//IGNORE',
            $value
        );

        if ($converted !== false) {
            return $converted;
        }
    }

    return preg_replace('/[^\x20-\x7E]/', '', $value);
}

function pdfStatus($value) {
    $value = trim((string)$value);
    return $value !== '' ? $value : 'Not recorded';
}

// --------------------------------------------------
// SALARY FIGURES
// --------------------------------------------------

$basicSalary = (float)$payroll['base_salary'];
$totalAllowances = (float)$payroll['total_allowances'];

$grossPay = round(
    $basicSalary + $totalAllowances,
    2
);

$totalDeductions = (float)$payroll['total_deductions'];
$netPay = (float)$payroll['net_pay'];

// --------------------------------------------------
// PENSION
// --------------------------------------------------

$pensionDetails = $details['pension'] ?? [];

$pensionEnabled = !empty($pensionDetails['enabled']);

$employeePension = $pensionEnabled
    ? (float)($pensionDetails['employee_amount'] ?? 0)
    : 0;

if ($pensionEnabled && !isset($pensionDetails['employee_amount'])) {
    $employeePension = (float)(
        pdfNamedDeduction(
            $deductions,
            ['EMPLOYEE PENSION (6% GROSS)', 'EMPLOYEE PENSION']
        ) ?? 0
    );
}

$employerPensionRate = 6;

$employerPension = 0;

if ($pensionEnabled) {
    $employerPension = isset($pensionDetails['employer_amount'])
        ? (float)$pensionDetails['employer_amount']
        : round($grossPay * $employerPensionRate / 100, 2);
}

$totalPension = $employeePension + $employerPension;

$qualifiedPension = ($pensionDetails['tax_qualified'] ?? false) === true;

// --------------------------------------------------
// STATUTORY DEDUCTIONS AND TAX AUDIT
// --------------------------------------------------

$nssf = pdfNamedDeduction($deductions, ['NSSF']);
$shif = pdfNamedDeduction($deductions, ['SHIF']);

$ahl = pdfNamedDeduction(
    $deductions,
    ['AFFORDABLE HOUSING LEVY', 'AHL', 'HOUSING LEVY']
);

$paye = pdfNamedDeduction(
    $deductions,
    ['PAYE', 'INCOME TAX (PAYE)']
);

$taxCharged = pdfPath($details, [
    'audit.tax_charged',
    'calculation.paye_before_relief',
    'calculation.tax_charged',
    'paye_before_relief'
]);

$taxRelief = pdfPath($details, [
    'audit.tax_relief',
    'calculation.relief_applied'
]);

if ($taxRelief === null && $taxCharged !== null && $paye !== null) {
    $taxRelief = max(0, round($taxCharged - $paye, 2));
}

$taxableGross = pdfPath($details, [
    'audit.taxable_pay',
    'audit.gross_taxable_pay',
    'calculation.gross_taxable_pay'
]);

if (
    $taxableGross === null &&
    ($details['calculation']['rule_set'] ?? '') === 'KE_STANDARD_2026_02_PENSION'
) {
    $taxableGross = $grossPay;
}

$netTaxable = pdfPath($details, [
    'audit.net_taxable_pay',
    'calculation.net_taxable_pay',
    'calculation.taxable_pay',
    'taxable_income',
    'taxable_pay'
]);

$preTaxNssfPension = pdfPath($details, [
    'audit.pension_tax_deduction',
    'pension.tax_deduction_including_nssf',
    'calculation.pension_tax_deduction'
]);

$taxLines = [
    ['Gross taxable earnings', $taxableGross],
    ['Less: NSSF (employee)', $nssf === null ? null : -$nssf],
    ['Less: SHIF', $shif === null ? null : -$shif],
    ['Less: Housing Levy', $ahl === null ? null : -$ahl],
    ['Less: qualifying pension', $qualifiedPension ? -$employeePension : 0],
    ['Net taxable pay', $netTaxable],
    ['Tax charged before relief', $taxCharged],
    ['Tax relief applied', $taxRelief === null ? null : -$taxRelief],
    ['PAYE deducted', $paye],
    ['Net pay', $netPay]
];

// Respect the shared NSSF/pension tax cap where recorded.
if (
    $preTaxNssfPension !== null &&
    $nssf !== null &&
    $qualifiedPension &&
    $employeePension > 0 &&
    $preTaxNssfPension + 0.01 < $nssf + $employeePension
) {
    $taxLines[4][0] = 'Pension (shared cap; see saved rules)';
    $taxLines[4][1] = null;
}

// --------------------------------------------------
// SACCO INFORMATION
// --------------------------------------------------

$saccoMembers = [];
$saccoError = '';

$monthDate = strtotime(
    ($payroll['month'] ?? date('F')) . ' 1 ' .
    ($payroll['year'] ?? date('Y'))
);

$selectedMonth = date('Y-m', $monthDate);
$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

try {

    $saccoStmt = $conn->prepare("
        SELECT
            m.sacco_member_id,
            m.sacco_account_no,
            s.sacco_name
        FROM hr_sacco_members m
        INNER JOIN hr_saccos s ON s.sacco_id = m.sacco_id
        WHERE m.user_id = ?
          AND m.membership_date <= ?
        ORDER BY s.sacco_name
    ");

    $saccoStmt->bind_param('is', $userId, $monthEnd);
    $saccoStmt->execute();

    $members = $saccoStmt->get_result();

    while ($member = $members->fetch_assoc()) {

        $memberId = (int)$member['sacco_member_id'];

        // Opening balance
        $openingStmt = $conn->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN transaction_type IN (
                        'opening_balance',
                        'contribution',
                        'adjustment_credit'
                    ) THEN amount
                    ELSE -amount
                END
            ), 0) AS opening_balance
            FROM hr_sacco_transactions
            WHERE sacco_member_id = ?
              AND (
                    CASE
                        WHEN transaction_type = 'contribution'
                             AND contribution_month IS NOT NULL
                        THEN DATE_FORMAT(contribution_month, '%Y-%m-01')
                        ELSE DATE_FORMAT(transaction_date, '%Y-%m-01')
                    END
                  ) < ?
        ");

        $openingStmt->bind_param('is', $memberId, $monthStart);
        $openingStmt->execute();

        $member['opening_balance'] = (float)$openingStmt
            ->get_result()->fetch_assoc()['opening_balance'];

        $openingStmt->close();

        // Contributions for the selected month
        $contributionStmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS monthly_contribution
            FROM hr_sacco_transactions
            WHERE sacco_member_id = ?
              AND transaction_type = 'contribution'
              AND (
                    (
                        contribution_month IS NOT NULL
                        AND DATE_FORMAT(contribution_month, '%Y-%m') = ?
                    )
                    OR
                    (
                        contribution_month IS NULL
                        AND transaction_date BETWEEN ? AND ?
                    )
                  )
        ");

        $contributionStmt->bind_param(
            'isss',
            $memberId,
            $selectedMonth,
            $monthStart,
            $monthEnd
        );

        $contributionStmt->execute();

        $member['monthly_contribution'] = (float)$contributionStmt
            ->get_result()->fetch_assoc()['monthly_contribution'];

        $contributionStmt->close();

        $member['balance_after_contribution'] =
            $member['opening_balance'] +
            $member['monthly_contribution'];

        // Loans
        $loanStmt = $conn->prepare("
            SELECT
                l.loan_id,
                l.loan_reference,
                l.principal_amount,
                l.interest_amount,

                COALESCE((
                    SELECT SUM(r1.amount)
                    FROM hr_sacco_loan_repayments r1
                    WHERE r1.loan_id = l.loan_id
                      AND r1.repayment_date BETWEEN ? AND ?
                ), 0) AS paid_this_month,

                COALESCE((
                    SELECT SUM(r2.amount)
                    FROM hr_sacco_loan_repayments r2
                    WHERE r2.loan_id = l.loan_id
                      AND r2.repayment_date <= ?
                ), 0) AS total_repaid

            FROM hr_sacco_loans l
            WHERE l.sacco_member_id = ?
              AND l.issue_date <= ?
            ORDER BY l.issue_date, l.loan_id
        ");

        $loanStmt->bind_param(
            'sssis',
            $monthStart,
            $monthEnd,
            $monthEnd,
            $memberId,
            $monthEnd
        );

        $loanStmt->execute();

        $loanResult = $loanStmt->get_result();
        $member['loans'] = [];

        while ($loan = $loanResult->fetch_assoc()) {

            $loanAmount =
                (float)$loan['principal_amount'] +
                (float)$loan['interest_amount'];

            $loan['loan_amount'] = $loanAmount;

            $loan['paid_this_month'] =
                (float)$loan['paid_this_month'];

            $loan['pending_balance'] = max(
                0,
                $loanAmount - (float)$loan['total_repaid']
            );

            $member['loans'][] = $loan;
        }

        $loanStmt->close();

        $saccoMembers[] = $member;
    }

    $saccoStmt->close();

} catch (Throwable $e) {
    $saccoError = 'SACCO information could not be loaded.';
}

// --------------------------------------------------
// PDF CLASS AND LAYOUT HELPERS
// --------------------------------------------------

class LynnEmployeePayslipPDF extends FPDF {

    public function safeCell(
        $w,
        $h,
        $value,
        $border = 0,
        $ln = 0,
        $align = 'L',
        $fill = false
    ) {
        $this->Cell(
            $w,
            $h,
            pdfText($value),
            $border,
            $ln,
            $align,
            $fill
        );
    }

    public function Header() {

        $this->SetFillColor(24, 49, 80);
        $this->Rect(10, 8, 190, 23, 'F');

        $logo = __DIR__ . '/images/lynn_logo.png';

      if (is_file($logo)) {
    $this->Image($logo, 13, 10, 22, 18);
}

$this->SetTextColor(255, 255, 255);

// Company name
$this->SetFont('Arial', 'B', 12);
$this->SetXY(39, 10);
$this->safeCell(
    158,
    8,
    'LYNNTECH CHEMICALS & EQUIPMENT LTD',
    0,
    1
);

// Confidential heading
$this->SetFont('Arial', '', 9);
$this->SetXY(39, 20);
$this->safeCell(
    158,
    6,
    'EMPLOYEE PAYSLIP - CONFIDENTIAL',
    0,
    1
);

$this->SetTextColor(28, 43, 61);

// Start document content below the header
$this->SetY(36);
    }

    public function Footer() {

        $this->SetY(-14);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(110, 110, 110);

        $this->Cell(
            0,
            5,
            pdfText('Generated by Lynntech HR Manager on ' . date('d M Y')),
            0,
            0,
            'C'
        );

        $this->SetY(-9);
        $this->Cell(
            0,
            5,
            'Page ' . $this->PageNo(),
            0,
            0,
            'C'
        );
    }
}

function pdfSection($pdf, $title) {
    $pdf->Ln(2);
    $pdf->SetFillColor(232, 239, 247);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->safeCell(190, 6, $title, 0, 1, 'L', true);
}

function pdfDetail($pdf, $label, $value) {
    $pdf->SetFont('Arial', '', 8);
    $pdf->safeCell(95, 6, $label . ': ' . $value, 1, 0);
}

function pdfTableRow($pdf, $label, $amount, $bold = false) {
    $pdf->SetFont('Arial', $bold ? 'B' : '', 8);

    $pdf->safeCell(125, 6, $label, 1, 0);

    $display = $amount === null
        ? 'Not recorded'
        : 'Ksh ' . pdfMoney($amount);

    $pdf->safeCell(65, 6, $display, 1, 1, 'R');
}

function pdfBreakdown($pdf, $title, $items, $isDeduction = false) {

    pdfSection($pdf, $title);

    $pdf->SetFillColor(245, 247, 250);
    $pdf->SetFont('Arial', 'B', 8);

    if ($isDeduction) {
        $pdf->safeCell(105, 6, 'Deduction', 1, 0, 'L', true);
        $pdf->safeCell(30, 6, 'Rate', 1, 0, 'C', true);
        $pdf->safeCell(55, 6, 'Amount (Ksh)', 1, 1, 'R', true);
    } else {
        $pdf->safeCell(135, 6, 'Allowance', 1, 0, 'L', true);
        $pdf->safeCell(55, 6, 'Amount (Ksh)', 1, 1, 'R', true);
    }

    $pdf->SetFont('Arial', '', 8);

    if (empty($items)) {
        $pdf->safeCell(190, 6, 'None recorded', 1, 1);
        return;
    }

    foreach ($items as $line) {

        if (!is_array($line)) continue;

        $name = $line['name'] ?? ($isDeduction ? 'Deduction' : 'Allowance');
        $amount = pdfLineAmount($line);

        if ($isDeduction) {

            $rate = '-';

            if (
                ($line['basis'] ?? '') === 'percent' &&
                isset($line['value'])
            ) {
                $rate = pdfMoney($line['value']) . '%';

            } elseif (
                isset($line['rate']) &&
                is_numeric($line['rate'])
            ) {
                $rate = pdfMoney($line['rate']) . '%';

            } elseif (
                is_string($line['amount'] ?? null) &&
                strpos($line['amount'], '%') !== false
            ) {
                $rate = $line['amount'];
            }

            $pdf->safeCell(105, 6, $name, 1, 0);
            $pdf->safeCell(30, 6, $rate, 1, 0, 'C');
            $pdf->safeCell(
                55,
                6,
                $amount === null ? 'Not recorded' : pdfMoney($amount),
                1,
                1,
                'R'
            );

        } else {

            $pdf->safeCell(135, 6, $name, 1, 0);
            $pdf->safeCell(
                55,
                6,
                $amount === null ? 'Not recorded' : pdfMoney($amount),
                1,
                1,
                'R'
            );
        }
    }
}

// --------------------------------------------------
// CREATE PDF
// --------------------------------------------------

$pdf = new LynnEmployeePayslipPDF('P', 'mm', 'A4');

$pdf->SetMargins(10, 8, 10);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

$period = ($payroll['month'] ?? '') . ' ' . ($payroll['year'] ?? '');

// Employee information
pdfSection($pdf, 'EMPLOYEE & PAYROLL INFORMATION');

pdfDetail($pdf, 'Name', $payroll['full_name'] ?? '-');
pdfDetail($pdf, 'Payroll Number', $payroll['payroll_no'] ?? '-');
$pdf->Ln();

pdfDetail($pdf, 'Email', $payroll['email'] ?? '-');
pdfDetail($pdf, 'National ID', $payroll['national_id'] ?? '-');
$pdf->Ln();

pdfDetail($pdf, 'Employment Status', $payroll['status'] ?? '-');
pdfDetail($pdf, 'Payroll Period', $period);
$pdf->Ln();

// Salary summary
pdfSection($pdf, 'SALARY SUMMARY');

pdfTableRow($pdf, 'Basic Salary', $basicSalary);
pdfTableRow($pdf, 'Total Allowances', $totalAllowances);
pdfTableRow($pdf, 'GROSS PAY', $grossPay, true);
pdfTableRow($pdf, 'Total Deductions', $totalDeductions);
pdfTableRow($pdf, 'Employer Pension Contribution (6%)', $employerPension);

$pdf->SetFillColor(225, 239, 253);
$pdf->SetFont('Arial', 'B', 10);
$pdf->safeCell(125, 8, 'NET PAY', 1, 0, 'L', true);
$pdf->safeCell(65, 8, 'Ksh ' . pdfMoney($netPay), 1, 1, 'R', true);

$pdf->SetFont('Arial', 'I', 7);
$pdf->safeCell(
    190,
    5,
    'Employer pension is company-funded and is not deducted from employee net pay.',
    0,
    1
);

// Pension details
pdfSection($pdf, 'PENSION CONTRIBUTION SUMMARY');

if ($pensionEnabled) {

    pdfTableRow($pdf, 'Employee Pension Contribution (6%)', $employeePension);
    pdfTableRow($pdf, 'Employer Pension Contribution (6%)', $employerPension);
    pdfTableRow($pdf, 'TOTAL PENSION CONTRIBUTION (12%)', $totalPension, true);

    $pdf->SetFont('Arial', 'I', 7);
    $pdf->safeCell(
        190,
        5,
        'Calculation basis: gross pay. Employer contribution does not reduce employee net pay.',
        0,
        1
    );

} else {

    $pdf->SetFont('Arial', 'I', 8);
    $pdf->safeCell(
        190,
        6,
        'Employee was not enrolled in the pension scheme for this payroll period.',
        1,
        1
    );
}

// Tax breakdown
pdfSection($pdf, 'PAYROLL TAX & STATUTORY BREAKDOWN');

foreach ($taxLines as $index => $line) {
    $bold = in_array($index, [0, 5, 6, 8, 9], true);
    pdfTableRow($pdf, $line[0], $line[1], $bold);
}

// Reconciliation
$pdf->Ln(1);
$pdf->SetFont('Arial', 'I', 7);

$reconciledNet = round($grossPay - $totalDeductions, 2);
$reconciliationStatus = abs($reconciledNet - $netPay) <= 0.02
    ? 'MATCH'
    : 'MISMATCH';

$pdf->safeCell(
    190,
    5,
    'Reconciliation: Gross pay - deductions = Ksh ' .
    pdfMoney($reconciledNet) .
    ' [' . $reconciliationStatus . ']',
    0,
    1
);

// Allowances and deductions
pdfBreakdown($pdf, 'ALLOWANCES BREAKDOWN', $allowances, false);
pdfBreakdown($pdf, 'DEDUCTIONS BREAKDOWN', $deductions, true);

// --------------------------------------------------
// SACCO TRACKING
// --------------------------------------------------

pdfSection($pdf, 'SACCO CONTRIBUTION & LOAN TRACKING');

if ($saccoError !== '') {

    $pdf->SetFont('Arial', 'I', 8);
    $pdf->safeCell(190, 6, $saccoError, 1, 1);

} elseif (empty($saccoMembers)) {

    $pdf->SetFont('Arial', 'I', 8);
    $pdf->safeCell(190, 6, 'No SACCO membership recorded for this period.', 1, 1);

} else {

    foreach ($saccoMembers as $member) {

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->safeCell(
            190,
            6,
            $member['sacco_name'] . ' - Account: ' . $member['sacco_account_no'],
            1,
            1
        );

   $pdf->SetFont('Arial', '', 8);
$pdf->safeCell(130, 6, 'Opening Contribution Balance', 1, 0);
$pdf->safeCell(60, 6, pdfMoney($member['opening_balance']), 1, 1, 'R');

$pdf->safeCell(130, 6, 'Monthly Contribution', 1, 0);
$pdf->safeCell(60, 6, pdfMoney($member['monthly_contribution']), 1, 1, 'R');

$pdf->SetFont('Arial', 'B', 8);
$pdf->safeCell(130, 6, 'Contribution Balance', 1, 0);
$pdf->safeCell(60, 6, pdfMoney($member['balance_after_contribution']), 1, 1, 'R');

$pdf->SetFont('Arial', '', 8);

        if (!empty($member['loans'])) {

            $pdf->SetFillColor(232, 239, 247);
            $pdf->SetFont('Arial', 'B', 7);

            $pdf->safeCell(50, 6, 'Loan Reference', 1, 0, 'L', true);
            $pdf->safeCell(45, 6, 'Loan Amount', 1, 0, 'R', true);
            $pdf->safeCell(45, 6, 'Paid This Month', 1, 0, 'R', true);
            $pdf->safeCell(50, 6, 'Pending Balance', 1, 1, 'R', true);

            $pdf->SetFont('Arial', '', 7);

            foreach ($member['loans'] as $loan) {

                $pdf->safeCell(50, 6, $loan['loan_reference'], 1, 0);
                $pdf->safeCell(45, 6, pdfMoney($loan['loan_amount']), 1, 0, 'R');
                $pdf->safeCell(45, 6, pdfMoney($loan['paid_this_month']), 1, 0, 'R');
                $pdf->safeCell(50, 6, pdfMoney($loan['pending_balance']), 1, 1, 'R');
            }

        } else {

            $pdf->SetFont('Arial', 'I', 7);
            $pdf->safeCell(190, 5, 'No SACCO loans recorded.', 1, 1);
        }

        $pdf->Ln(2);
    }

    $pdf->SetFont('Arial', 'I', 7);
    $pdf->safeCell(
        190,
        5,
        'SACCO contributions and loan repayments are tracked independently and are not included in payroll deductions or net pay.',
        0,
        1
    );
}

// --------------------------------------------------
// PAYROLL WORKFLOW & AUTHORIZATION
// --------------------------------------------------

pdfSection($pdf, 'PAYROLL WORKFLOW & AUTHORIZATION');

$workflowColumns = [
    [
        'title' => 'PREPARATION',
        'name' => $payroll['prepared_by'] ?? '',
        'date' => $payroll['date_prepared'] ?? '',
        'status' => $payroll['preparation_status'] ?? ''
    ],
    [
        'title' => 'APPROVAL',
        'name' => $payroll['approved_by'] ?? '',
        'date' => $payroll['approval_date'] ?? '',
        'status' => $payroll['approval_status'] ?? ''
    ],
    [
        'title' => 'PAYMENT',
        'name' => $payroll['accountant_name'] ?? '',
        'date' => $payroll['payment_date'] ?? '',
        'status' => $payroll['payment_status'] ?? ''
    ]
];

$columnWidths = [63, 63, 64];

foreach ($workflowColumns as $index => $column) {

    $pdf->SetFillColor(245, 247, 250);
    $pdf->SetFont('Arial', 'B', 7);

    $pdf->safeCell(
        $columnWidths[$index],
        6,
        $column['title'],
        1,
        $index === 2 ? 1 : 0,
        'C',
        true
    );
}

foreach (['name', 'date', 'status'] as $field) {

    foreach ($workflowColumns as $index => $column) {

        $labels = [
            'name' => 'Name: ',
            'date' => 'Date: ',
            'status' => 'Status: '
        ];

        $pdf->SetFont('Arial', '', 7);

        $pdf->safeCell(
            $columnWidths[$index],
            7,
            $labels[$field] . pdfStatus($column[$field]),
            1,
            $index === 2 ? 1 : 0
        );
    }
}

$pdf->Ln(2);
$pdf->SetFont('Arial', 'I', 7);

$pdf->safeCell(
    190,
    5,
    'This payslip is confidential and reflects the saved payroll snapshot for ' . $period . '.',
    0,
    1,
    'C'
);

// --------------------------------------------------
// OUTPUT PDF
// --------------------------------------------------

$safeName = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '_',
    (string)($payroll['full_name'] ?? 'Employee')
);

$filename = 'Payslip_' . $safeName . '_' . $selectedMonth . '.pdf';

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (isset($_GET['download']) && $_GET['download'] == 1) {
    $pdf->Output('D', $filename);
} else {
    $pdf->Output('I', $filename);
}

exit;
?>
