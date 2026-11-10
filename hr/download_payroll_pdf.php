<?php
/** Lynntech payroll payslip + audit PDF. Requires the existing local fpdf.php. */
require 'db_con.php';
require 'fpdf.php';

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$selectedMonth = (string)($_GET['month'] ?? date('Y-m'));
if (!$user_id || !preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $selectedMonth, $matches)) {
    http_response_code(400); exit('Invalid employee or payroll month.');
}
$year = (int)$matches[1];
$monthText = date('F', mktime(0, 0, 0, (int)$matches[2], 1));
$period = $monthText . ' ' . $year;

$stmt = $conn->prepare('SELECT pr.*, u.email, u.national_id, u.status, es.bank_name, es.bank_account
    FROM payroll_records pr
    INNER JOIN users u ON pr.user_id = u.user_id
    LEFT JOIN employee_salary es ON pr.user_id = es.user_id
    WHERE pr.user_id = ? AND pr.month = ? AND pr.year = ? LIMIT 1');
$stmt->bind_param('isi', $user_id, $monthText, $year);
$stmt->execute();
// Bind results without requiring mysqli mysqlnd/get_result().
$meta = $stmt->result_metadata();
$fields = []; $refs = [];
while ($field = $meta->fetch_field()) { $fields[$field->name] = null; $refs[] = &$fields[$field->name]; }
call_user_func_array([$stmt, 'bind_result'], $refs);
$found = $stmt->fetch();
$payroll = $found ? array_map(function ($v) { return $v; }, $fields) : null;
$stmt->close();
if (!$payroll) { http_response_code(404); exit('No payroll record found for ' . htmlspecialchars($period, ENT_QUOTES, 'UTF-8')); }

$details = json_decode((string)($payroll['details'] ?? ''), true);
$details = is_array($details) ? $details : [];
$allowances = isset($details['allowances']) && is_array($details['allowances']) ? $details['allowances'] : [];
$deductions = isset($details['deductions']) && is_array($details['deductions']) ? $details['deductions'] : [];
function payrollPath($root, $paths) {
    foreach ($paths as $path) {
        $v = $root;
        foreach (explode('.', $path) as $key) {
            if (!is_array($v) || !array_key_exists($key, $v)) { $v = null; break; }
            $v = $v[$key];
        }
        if (is_scalar($v) && is_numeric($v)) return (float)$v;
    }
    return null;
}
function payrollLineAmount($line) {
    $value = $line['calculated_amount'] ?? $line['amount'] ?? null;
    if (is_numeric($value)) return (float)$value;
    if (is_string($value) && preg_match('/^\s*-?[\d,]+(?:\.\d+)?\s*$/', $value)) return (float)str_replace(',', '', $value);
    return null;
}
function payrollNamedDeduction($lines, $names) {
    foreach ($lines as $line) {
        if (!is_array($line)) continue;
        $name = strtoupper(trim((string)($line['name'] ?? '')));
        if (in_array($name, $names, true)) return payrollLineAmount($line);
    }
    return null;
}
function payrollFmt($n) { return $n === null ? 'Not recorded' : number_format((float)$n, 2); }
function payrollPdfText($text) {
    $text = (string)($text ?? '');
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) return $converted;
    }
    return preg_replace('/[^\x20-\x7E]/', '', $text);
}

$basic = (float)$payroll['base_salary'];
$allowanceTotal = (float)$payroll['total_allowances'];
$gross = round($basic + $allowanceTotal, 2);
$totalDeductions = (float)$payroll['total_deductions'];
$netPay = (float)$payroll['net_pay'];
$netTaxable = payrollPath($details, ['audit.net_taxable_pay','calculation.net_taxable_pay','calculation.taxable_pay','taxable_income','taxable_pay']);
$taxableGross = payrollPath($details, ['audit.taxable_pay','audit.gross_taxable_pay','calculation.gross_taxable_pay']);
// Original calculator used calculation.taxable_pay to mean NET taxable pay.
if ($taxableGross === null && ($details['calculation']['rule_set'] ?? '') === 'KE_STANDARD_2026_02_PENSION') $taxableGross = $gross;
$taxCharged = payrollPath($details, ['audit.tax_charged','calculation.paye_before_relief','calculation.tax_charged','paye_before_relief']);
$paye = payrollPath($details, ['audit.paye_deducted','calculation.paye_deducted','calculation.paye']);
if ($paye === null) $paye = payrollNamedDeduction($deductions, ['PAYE','INCOME TAX (PAYE)']);
$relief = payrollPath($details, ['audit.tax_relief','calculation.relief_applied']);
if ($relief === null && $taxCharged !== null && $paye !== null) $relief = max(0, round($taxCharged - $paye, 2));
// The old personal_relief field is an entitlement, not necessarily the relief applied.
$nssf = payrollNamedDeduction($deductions, ['NSSF']);
$shif = payrollNamedDeduction($deductions, ['SHIF']);
$ahl = payrollNamedDeduction($deductions, ['AFFORDABLE HOUSING LEVY','AHL','HOUSING LEVY']);
$pension = payrollPath($details, ['pension.employee_amount']);

if ($pension === null) {
    $pension = payrollNamedDeduction(
        $deductions,
        ['EMPLOYEE PENSION (6% GROSS)', 'EMPLOYEE PENSION']
    );
}

$qualified = ($details['pension']['tax_qualified'] ?? false) === true;

/*
|--------------------------------------------------------------------------
| EMPLOYER PENSION CONTRIBUTION
|--------------------------------------------------------------------------
| Employer contributes 6% of gross pay when the employee is enrolled.
| This is an employer expense, NOT an employee payroll deduction.
*/

$pensionEnabled = !empty($details['pension']['enabled']);

$employeePension = $pensionEnabled
    ? (float)($pension ?? 0)
    : 0;

$employerPensionRate = 6;

$employerPension = $pensionEnabled
    ? round($gross * ($employerPensionRate / 100), 2)
    : 0;

$totalPensionContribution = $employeePension + $employerPension;
$preTaxNssfPension = payrollPath($details, ['audit.pension_tax_deduction','pension.tax_deduction_including_nssf','calculation.pension_tax_deduction']);
$taxLines = [
    ['Gross taxable earnings', $taxableGross],
    ['Less: NSSF (employee)', $nssf === null ? null : -$nssf],
    ['Less: SHIF', $shif === null ? null : -$shif],
    ['Less: Housing Levy', $ahl === null ? null : -$ahl],
    ['Less: qualifying pension', $qualified ? ($pension === null ? null : -$pension) : 0.0],
    ['Net taxable pay', $netTaxable],
    ['Tax charged (before relief)', $taxCharged],
    ['Tax relief applied', $relief === null ? null : -$relief],
    ['PAYE deducted', $paye],
    ['Net pay', $netPay],
];
// For the original calculator, NSSF and pension share a cap. Show the cap when relevant.
if ($preTaxNssfPension !== null && $nssf !== null && $qualified && $pension !== null && $preTaxNssfPension + .01 < $nssf + $pension) {
    $taxLines[4][1] = null; // Avoid presenting the uncapped pension as deductible.
    $taxLines[4][0] = 'Pension (shared cap; see saved rules)';
}

/*
|--------------------------------------------------------------------------
| SACCO CONTRIBUTION AND LOAN DATA FOR PDF
|--------------------------------------------------------------------------
| Historical figures as at the selected payroll month.
| Display only: no payroll calculations are changed.
*/

$saccoPdfMembers = [];
$saccoPdfError = '';

$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

try {

    // Find SACCO memberships that existed by the selected payroll month.
    $saccoStmt = $conn->prepare("
        SELECT
            m.sacco_member_id,
            m.sacco_account_no,
            s.sacco_name
        FROM hr_sacco_members m
        INNER JOIN hr_saccos s
            ON s.sacco_id = m.sacco_id
        WHERE m.user_id = ?
          AND m.membership_date <= ?
        ORDER BY s.sacco_name
    ");

    $saccoStmt->bind_param('is', $user_id, $monthEnd);
    $saccoStmt->execute();

    $saccoResult = $saccoStmt->get_result();

    while ($member = $saccoResult->fetch_assoc()) {

        $memberId = (int)$member['sacco_member_id'];

        // Opening contribution balance before selected month.
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

        // Contributions belonging to the selected payroll month.
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

        // Fetch every loan issued by the end of the selected month.
        $loanStmt = $conn->prepare("
            SELECT
                l.loan_id,
                l.loan_reference,
                l.principal_amount,
                l.interest_amount,
                l.issue_date,

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

        $member['loans'] = [];

        $loanResult = $loanStmt->get_result();

        while ($loan = $loanResult->fetch_assoc()) {

            $loanAmount =
                (float)$loan['principal_amount'] +
                (float)$loan['interest_amount'];

            $loan['loan_amount'] = $loanAmount;
            $loan['paid_this_month'] = (float)$loan['paid_this_month'];

            $loan['pending_balance'] = max(
                0,
                $loanAmount - (float)$loan['total_repaid']
            );

            $member['loans'][] = $loan;
        }

        $loanStmt->close();

        $saccoPdfMembers[] = $member;
    }

    $saccoStmt->close();

} catch (Throwable $e) {

    $saccoPdfError = 'SACCO information could not be loaded.';
}

/*
|--------------------------------------------------------------------------
| PENSION TRACKING DATA FOR PDF
|--------------------------------------------------------------------------
*/

$pensionPdf = [
    'opening_balance' => 0,
    'employee_amount' => 0,
    'employer_amount' => 0,
    'total_contribution' => 0,
    'cumulative_contributions' => 0,
    'closing_balance' => 0,
    'remittance_status' => 'Not Recorded'
];

$pensionPdfError = '';

try {
    // Opening pension balance
    $pensionOpeningStmt = $conn->prepare("
        SELECT opening_balance
        FROM employee_pension_opening_balances
        WHERE user_id = ?
          AND (effective_date IS NULL OR effective_date <= ?)
        LIMIT 1
    ");

    $pensionOpeningStmt->bind_param(
        'is',
        $user_id,
        $monthEnd
    );

    $pensionOpeningStmt->execute();
    $openingRow = $pensionOpeningStmt->get_result()->fetch_assoc();

    if ($openingRow) {
        $pensionPdf['opening_balance'] =
            (float)$openingRow['opening_balance'];
    }

    $pensionOpeningStmt->close();


    // Selected month's pension contribution
    $pensionMonthStmt = $conn->prepare("
        SELECT
            employee_amount,
            employer_amount,
            total_contribution,
            remittance_status
        FROM pension_contributions
        WHERE user_id = ?
          AND month = ?
          AND year = ?
        LIMIT 1
    ");

    $pensionMonthStmt->bind_param(
        'isi',
        $user_id,
        $monthText,
        $year
    );

    $pensionMonthStmt->execute();
    $pensionMonthRow = $pensionMonthStmt->get_result()->fetch_assoc();

    if ($pensionMonthRow) {

    $pensionPdf['employee_amount'] =
        (float)$pensionMonthRow['employee_amount'];

    $pensionPdf['employer_amount'] =
        (float)$pensionMonthRow['employer_amount'];

    $pensionPdf['total_contribution'] =
        (float)$pensionMonthRow['total_contribution'];

    $pensionPdf['remittance_status'] =
        $pensionMonthRow['remittance_status'];

} else {

    // Fallback to the saved payroll calculation.
    $pensionPdf['employee_amount'] = $employeePension;
    $pensionPdf['employer_amount'] = $employerPension;

    $pensionPdf['total_contribution'] =
        $employeePension + $employerPension;

    $pensionPdf['remittance_status'] = 'Not Recorded';
}

    $pensionMonthStmt->close();

// Total accrued pension contributions up to the selected payroll month.
// Include both Paid and Pending contributions in the closing balance.
$pensionPaidStmt = $conn->prepare("
    SELECT COALESCE(SUM(total_contribution), 0) AS cumulative_contributions
    FROM pension_contributions
    WHERE user_id = ?
      AND STR_TO_DATE(
            CONCAT('01 ', month, ' ', year),
            '%d %M %Y'
          ) <= ?
");

$pensionPaidStmt->bind_param(
    'is',
    $user_id,
    $monthStart
);

$pensionPaidStmt->execute();
$paidRow = $pensionPaidStmt->get_result()->fetch_assoc();

$pensionPdf['cumulative_contributions'] =
    (float)$paidRow['cumulative_contributions'];

$pensionPaidStmt->close();

// Closing balance = Opening balance + all accrued contributions.
$pensionPdf['closing_balance'] =
    $pensionPdf['opening_balance'] +
    $pensionPdf['cumulative_contributions'];

} catch (Throwable $e) {
    $pensionPdfError = 'Pension information could not be loaded.';
}

class LynnPayrollPDF extends FPDF {
    public function safeCell($w, $h, $value, $border = 0, $ln = 0, $align = 'L', $fill = false, $max = 0) {
        $value = payrollPdfText($value);
        if ($max > 0) {
            while ($this->GetStringWidth($value) > $max && strlen($value) > 3) $value = substr($value, 0, -4) . '...';
        }
        $this->Cell($w, $h, $value, $border, $ln, $align, $fill);
    }
}
$pdf = new LynnPayrollPDF('P','mm','A4');
$pdf->SetMargins(10, 8, 10);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();
$W = 190;
// SIMPLE PAYSLIP HEADER - EMPLOYEE NAME ONLY
$pdf->SetTextColor(28,43,61);
$pdf->SetFont('Arial','B',14);
$pdf->SetY(9);

$pdf->SetFont('Arial','',9);
$pdf->safeCell(190,5,' LYNNTECH CHEMICALS $ EQUIPMENT LTD PAYSLIP | '.$period,0,1,'C');

$pdf->SetDrawColor(180,190,200);
$pdf->Line(10,24,200,24);
function sectionTitle($pdf, $text, $h = 6) {
    $pdf->SetFillColor(232,239,247);
    $pdf->SetFont('Arial','B',9);
    $pdf->safeCell(190,$h,$text,0,1,'L',true);
}
function detailPair($pdf,$left,$right,$h=5.5) {
    $pdf->SetFont('Arial','',8);
    $pdf->safeCell(95,$h,$left,1,0,'L',false,91);
    $pdf->safeCell(95,$h,$right,1,1,'L',false,91);
}
$pdf->SetY(27);
sectionTitle($pdf,'EMPLOYEE & PAYROLL INFORMATION');
detailPair($pdf,'Name: '.($payroll['full_name'] ?? '-'),'Payroll #: '.($payroll['payroll_no'] ?: '-'));
detailPair($pdf,'Email: '.($payroll['email'] ?? '-'),'National ID: '.($payroll['national_id'] ?? '-'));
detailPair($pdf,'Bank: '.($payroll['bank_name'] ?? '-'),'Account: '.($payroll['bank_account'] ?? '-'));
detailPair($pdf,'Status: '.($payroll['status'] ?? '-'),'Period: '.$period);
$pdf->Ln(2);
sectionTitle($pdf,'SALARY SUMMARY');
$pdf->SetFont('Arial','',8);
foreach ([
    ['Basic salary', $basic],
    ['Allowances', $allowanceTotal],
    ['Gross pay', $gross],
    ['Total deductions', $totalDeductions],
    ['Employer Pension Contribution (6%)', $employerPension]
] as $row) {
    $pdf->safeCell(120,5,$row[0],1,0);
    $pdf->safeCell(70,5,'Ksh '.payrollFmt($row[1]),1,1,'R');
}
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(225,239,253);

$pdf->safeCell(120,6,'NET PAY',1,0,'L',true);
$pdf->safeCell(70,6,'Ksh '.payrollFmt($netPay),1,1,'R',true);

$pdf->Ln(2);

/*
|--------------------------------------------------------------------------
| PENSION CONTRIBUTION SUMMARY
|--------------------------------------------------------------------------
*/


$pdf->Ln(2);

sectionTitle($pdf,'PAYROLL TAX & AUDIT BREAKDOWN');
$pdf->SetFont('Arial','',8);
foreach ($taxLines as $i=>$line) {
    $bold = in_array($i,[0,5,6,8,9],true);
    $pdf->SetFont('Arial',$bold?'B':'',7.8);
    if ($bold) $pdf->SetFillColor(246,249,253);
    $pdf->safeCell(120,5,$line[0],1,0,'L',$bold);
    $pdf->safeCell(70,5,$line[1] === null ? 'Not recorded' : 'Ksh '.payrollFmt($line[1]),1,1,'R',$bold);
}
$pdf->SetFont('Arial','',7);
$pdf->safeCell(190,4,'Reconciliation: gross - deductions = Ksh '.payrollFmt($gross-$totalDeductions).(abs($gross-$totalDeductions-$netPay) <= .02 ? '  [MATCH]' : '  [MISMATCH]'),0,1);
$pdf->Ln(2);

// Keep all line items on one A4 page. Reduce row height/font for long item lists.
$allowCount = max(1,count($allowances));
$dedCount = max(1,count($deductions));
// Reserve about 39 mm for workflow/footer, 10 mm for section headings.

// Reserve space for the SACCO section before calculating payroll row heights.


/*
|--------------------------------------------------------------------------
| ONE-PAGE A4 SPACE CALCULATION
|--------------------------------------------------------------------------
*/

// Each section now contains only one consolidated data row.
$saccoReserve = !empty($saccoPdfMembers) ? 18 : 0;
$pensionReserve = 18;

$workflowReserve = 39;
$breakdownReserve = 24;

$available = 278
    - $pdf->GetY()
    - $workflowReserve
    - $breakdownReserve
    - $saccoReserve
    - $pensionReserve;

$totalRows = $allowCount + $dedCount;

$lineHeight = min(5.0, $available / $totalRows);

if ($lineHeight < 2.6) {
    http_response_code(422);
    exit('Too many payroll items to fit legibly on one A4 page.');
}

$lineFont = $lineHeight < 3.5
    ? 6.1
    : ($lineHeight < 4.3 ? 6.8 : 7.2);


function breakdown($pdf, $title, $items, $h, $font, $isDeduction) {
    sectionTitle($pdf,$title,5.5);
    $pdf->SetFillColor(245,247,250); $pdf->SetFont('Arial','B',7.5);
    if ($isDeduction) {
        $pdf->safeCell(108,4.5,'Deduction',1,0,'L',true);
        $pdf->safeCell(25,4.5,'Rate',1,0,'C',true);
        $pdf->safeCell(57,4.5,'Amount (Ksh)',1,1,'R',true);
    } else {
        $pdf->safeCell(133,4.5,'Allowance',1,0,'L',true);
        $pdf->safeCell(57,4.5,'Amount (Ksh)',1,1,'R',true);
    }
    $pdf->SetFont('Arial','',$font);
    if (!$items) {
        $pdf->safeCell(190,$h,'None recorded',1,1);
        return;
    }
    foreach ($items as $line) {
        if (!is_array($line)) continue;
        $name = $line['name'] ?? ($isDeduction?'Deduction':'Allowance');
        $amount = $isDeduction ? payrollLineAmount($line) : payrollLineAmount($line);
        if ($isDeduction) {
            $rate = '-';
            if (($line['basis'] ?? '') === 'percent' && isset($line['value'])) $rate = payrollFmt($line['value']).'%';
            elseif (isset($line['rate']) && is_numeric($line['rate'])) $rate = payrollFmt($line['rate']).'%';
            elseif (is_string($line['amount'] ?? null) && strpos($line['amount'],'%') !== false) $rate = $line['amount'];
            $pdf->safeCell(108,$h,$name,1,0,'L',false,105);
            $pdf->safeCell(25,$h,$rate,1,0,'C',false,23);
            $pdf->safeCell(57,$h,payrollFmt($amount),1,1,'R');
        } else {
            $pdf->safeCell(133,$h,$name,1,0,'L',false,130);
            $pdf->safeCell(57,$h,payrollFmt($amount),1,1,'R');
        }
    }
}

breakdown($pdf,'ALLOWANCES BREAKDOWN',$allowances,$lineHeight,$lineFont,false);
$pdf->Ln(2);

breakdown($pdf,'DEDUCTIONS BREAKDOWN',$deductions,$lineHeight,$lineFont,true);
$pdf->Ln(2);

/*
|--------------------------------------------------------------------------
| SACCO TRACKING SECTION
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| SACCO MONTHLY SUMMARY - ONE ROW
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| SACCO CONTRIBUTION AND LOAN SUMMARY
|--------------------------------------------------------------------------
*/

$pdf->Ln(1);
sectionTitle($pdf, 'SACCO CONTRIBUTION SUMMARY', 5);

if ($saccoPdfError !== '') {

    $pdf->SetFont('Arial', '', 7);
    $pdf->safeCell(190, 5, $saccoPdfError, 1, 1);

} elseif (empty($saccoPdfMembers)) {

    $pdf->SetFont('Arial', '', 7);
    $pdf->safeCell(190, 5, 'No SACCO membership recorded for this employee.', 1, 1);

} else {

    // Individual SACCO contribution balances
    $pdf->SetFillColor(232,239,247);
    $pdf->SetFont('Arial', 'B', 6.5);

    $pdf->safeCell(60, 4.5, 'SACCO', 1, 0, 'L', true);
    $pdf->safeCell(43, 4.5, 'OPENING BALANCE', 1, 0, 'C', true);
    $pdf->safeCell(43, 4.5, 'THIS MONTH', 1, 0, 'C', true);
    $pdf->safeCell(44, 4.5, 'CURRENT BALANCE', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 7);

    foreach ($saccoPdfMembers as $member) {

        $saccoName = $member['sacco_name'] ?? 'SACCO';

        if (!empty($member['sacco_account_no'])) {
            $saccoName .= ' (' . $member['sacco_account_no'] . ')';
        }

        $pdf->safeCell(60, 4.5, $saccoName, 1, 0, 'L', false, 57);

        $pdf->safeCell(
            43, 4.5,
            number_format((float)$member['opening_balance'], 2),
            1, 0, 'R'
        );

        $pdf->safeCell(
            43, 4.5,
            number_format((float)$member['monthly_contribution'], 2),
            1, 0, 'R'
        );

        $pdf->SetFont('Arial', 'B', 7);

        $pdf->safeCell(
            44, 4.5,
            number_format((float)$member['balance_after_contribution'], 2),
            1, 1, 'R'
        );

        $pdf->SetFont('Arial', '', 7);
    }

    // Individual SACCO loans
    $hasLoans = false;

    foreach ($saccoPdfMembers as $member) {
        if (!empty($member['loans'])) {
            $hasLoans = true;
            break;
        }
    }

    if ($hasLoans) {

        $pdf->Ln(1);
        sectionTitle($pdf, 'SACCO LOAN SUMMARY', 5);

        $pdf->SetFillColor(232,239,247);
        $pdf->SetFont('Arial', 'B', 6.5);

        $pdf->safeCell(42, 4.5, 'SACCO / LOAN', 1, 0, 'L', true);
        $pdf->safeCell(35, 4.5, 'LOAN AMOUNT', 1, 0, 'C', true);
        $pdf->safeCell(35, 4.5, 'PAID THIS MONTH', 1, 0, 'C', true);
        $pdf->safeCell(35, 4.5, 'OUTSTANDING', 1, 0, 'C', true);
        $pdf->safeCell(43, 4.5, 'LOAN REFERENCE', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 7);

        foreach ($saccoPdfMembers as $member) {

            if (empty($member['loans'])) {
                continue;
            }

            foreach ($member['loans'] as $loan) {

                $saccoName = $member['sacco_name'] ?? 'SACCO';

                $pdf->safeCell(
                    42, 4.5, $saccoName, 1, 0, 'L', false, 39
                );

                $pdf->safeCell(
                    35, 4.5,
                    number_format((float)$loan['loan_amount'], 2),
                    1, 0, 'R'
                );

                $pdf->safeCell(
                    35, 4.5,
                    number_format((float)$loan['paid_this_month'], 2),
                    1, 0, 'R'
                );

                $pdf->safeCell(
                    35, 4.5,
                    number_format((float)$loan['pending_balance'], 2),
                    1, 0, 'R'
                );

                $pdf->safeCell(
                    43, 4.5,
                    $loan['loan_reference'] ?? ('Loan #' . $loan['loan_id']),
                    1, 1, 'L', false, 40
                );
            }
        }
    }
}

$pdf->Ln(1);


sectionTitle($pdf, 'PENSION CONTRIBUTION SUMMARY', 5);

if ($pensionPdfError !== '') {

    $pdf->SetFont('Arial', '', 7);
    $pdf->safeCell(190, 5, $pensionPdfError, 1, 1);

} else {

    $pdf->SetFillColor(232,239,247);
    $pdf->SetFont('Arial','B',6.2);

    $pdf->safeCell(38,4.5,'OPENING BALANCE',1,0,'C',true);
    $pdf->safeCell(38,4.5,'EMPLOYEE (6%)',1,0,'C',true);
    $pdf->safeCell(38,4.5,'EMPLOYER (6%)',1,0,'C',true);
    $pdf->safeCell(38,4.5,'TOTAL THIS MONTH',1,0,'C',true);
    $pdf->safeCell(38,4.5,'CLOSING BALANCE',1,1,'C',true);

    $pdf->SetFont('Arial','',7);

    $pdf->safeCell(
        38, 5,
        payrollFmt($pensionPdf['opening_balance']),
        1, 0, 'R'
    );

    $pdf->safeCell(
        38, 5,
        payrollFmt($pensionPdf['employee_amount']),
        1, 0, 'R'
    );

    $pdf->safeCell(
        38, 5,
        payrollFmt($pensionPdf['employer_amount']),
        1, 0, 'R'
    );

    $pdf->safeCell(
        38, 5,
        payrollFmt($pensionPdf['total_contribution']),
        1, 0, 'R'
    );

    $pdf->safeCell(
        38, 5,
        payrollFmt($pensionPdf['closing_balance']),
        1, 1, 'R'
    );

    $pdf->SetFont('Arial','',7);

    $pdf->safeCell(
        190, 5,
        'Remittance status: '.$pensionPdf['remittance_status'],
        1, 1, 'L'
    );
}

$pdf->Ln(1);

$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetFillColor(245,247,250);

$columns = [
    ['prepared_by', 63],
    ['approved_by', 63],
    ['accountant_name', 64]
];

// Column headings
foreach ([
    ['PREPARED BY', 63],
    ['APPROVED BY', 63],
    ['PAID BY', 64]
] as $i => $col) {
    $pdf->safeCell(
        $col[1], 5, $col[0],
        1, $i === 2 ? 1 : 0, 'C', true
    );
}

// Names only
$pdf->SetFont('Arial', '', 7);

foreach ($columns as $i => $col) {

    $name = trim((string)($payroll[$col[0]] ?? ''));

    $pdf->safeCell(
        $col[1],
        9,
        $name !== '' ? $name : 'Not recorded',
        1,
        $i === 2 ? 1 : 0,
        'C',
        false,
        $col[1] - 4
    );
}

$pdf->SetFont('Arial', 'I', 7);
$pdf->safeCell(
    190,
    4,
    'Generated by Lynntech HR Manager on ' . date('d M Y') . '. Amounts reflect the saved payroll snapshot.',
    0,
    1,
    'C'
);

if ($pdf->GetY() > 285) {
    http_response_code(422);
    exit('Payslip exceeds one A4 page.');
}

while (ob_get_level() > 0) ob_end_clean();

$safeName = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '_',
    (string)($payroll['full_name'] ?? 'Employee')
);

$pdf->Output('I', 'Payslip_' . $safeName . '_' . $selectedMonth . '.pdf');
exit;