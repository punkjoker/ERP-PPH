
<?php
session_start();
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// Ensure employee is logged in
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$loggedInUserId = (int)$_SESSION['user_id'];
$payroll_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Only allow employees to view their own payroll
$stmt = $conn->prepare("
    SELECT pr.*, u.full_name, u.email, u.national_id, u.status
    FROM payroll_records pr
    INNER JOIN users u ON pr.user_id = u.user_id
    WHERE pr.payroll_id = ?
      AND pr.user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $payroll_id, $loggedInUserId);
$stmt->execute();
$payroll = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payroll) {
    http_response_code(404);
    exit("<div style='margin:100px;text-align:center;color:red;font-weight:bold;'>
        Payroll record not found or access denied.
    </div>");
}

// Decode saved payroll snapshot
$details = json_decode($payroll['details'] ?? '', true);
$details = is_array($details) ? $details : [];

$manualAllowances = is_array($details['allowances'] ?? null)
    ? $details['allowances'] : [];

$manualDeductions = is_array($details['deductions'] ?? null)
    ? $details['deductions'] : [];

// Helpers
function salaryPath($root, $paths) {
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

function salaryLineAmount($line) {
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

function salaryNamedDeduction($lines, $names) {
    foreach ($lines as $line) {
        if (!is_array($line)) continue;

        $name = strtoupper(trim((string)($line['name'] ?? '')));

        if (in_array($name, $names, true)) {
            return salaryLineAmount($line);
        }
    }

    return null;
}

function money($amount) {
    return number_format((float)$amount, 2);
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Salary calculations from saved payroll record
$basicSalary = (float)$payroll['base_salary'];
$totalAllowances = (float)$payroll['total_allowances'];
$grossPay = round($basicSalary + $totalAllowances, 2);
$totalDeductions = (float)$payroll['total_deductions'];
$netPay = (float)$payroll['net_pay'];

// Pension
$pensionDetails = $details['pension'] ?? [];
$pensionEnabled = !empty($pensionDetails['enabled']);

$employeePension = $pensionEnabled
    ? (float)($pensionDetails['employee_amount'] ?? 0)
    : 0;

if ($pensionEnabled && !isset($pensionDetails['employee_amount'])) {
    $employeePension = (float)(
        salaryNamedDeduction(
            $manualDeductions,
            ['EMPLOYEE PENSION (6% GROSS)', 'EMPLOYEE PENSION']
        ) ?? 0
    );
}

$employerPensionRate = 6;
$employerPension = $pensionEnabled
    ? (isset($pensionDetails['employer_amount'])
        ? (float)$pensionDetails['employer_amount']
        : round($grossPay * $employerPensionRate / 100, 2))
    : 0;

// Statutory deductions
$nssf = salaryNamedDeduction($manualDeductions, ['NSSF']);
$shif = salaryNamedDeduction($manualDeductions, ['SHIF']);
$ahl = salaryNamedDeduction(
    $manualDeductions,
    ['AFFORDABLE HOUSING LEVY', 'AHL', 'HOUSING LEVY']
);
$paye = salaryNamedDeduction(
    $manualDeductions,
    ['PAYE', 'INCOME TAX (PAYE)']
);

$taxCharged = salaryPath($details, [
    'audit.tax_charged',
    'calculation.paye_before_relief',
    'calculation.tax_charged',
    'paye_before_relief'
]);

$taxRelief = salaryPath($details, [
    'audit.tax_relief',
    'calculation.relief_applied'
]);

if ($taxRelief === null && $taxCharged !== null && $paye !== null) {
    $taxRelief = max(0, round($taxCharged - $paye, 2));
}

$taxableGross = salaryPath($details, [
    'audit.taxable_pay',
    'audit.gross_taxable_pay',
    'calculation.gross_taxable_pay'
]);

if ($taxableGross === null &&
    ($details['calculation']['rule_set'] ?? '') === 'KE_STANDARD_2026_02_PENSION') {
    $taxableGross = $grossPay;
}

$netTaxable = salaryPath($details, [
    'audit.net_taxable_pay',
    'calculation.net_taxable_pay',
    'calculation.taxable_pay',
    'taxable_income',
    'taxable_pay'
]);

$qualifiedPension = ($pensionDetails['tax_qualified'] ?? false) === true;

$preTaxNssfPension = salaryPath($details, [
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

// SACCO information - separate from payroll deductions
$saccoMembers = [];
$saccoError = '';

$selectedMonth = date('Y-m', strtotime(
    ($payroll['year'] ?? date('Y')) . '-' .
    date('m', strtotime(($payroll['month'] ?? date('F')) . ' 1'))
));

$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
/*
|--------------------------------------------------------------------------
| EMPLOYEE PENSION TRACKING
|--------------------------------------------------------------------------
| Uses the same calculation as HR View Payroll.
| Only Paid contributions are included in the closing balance.
*/

$pensionTracking = [
    'opening_balance' => 0,
    'employee_amount' => 0,
    'employer_amount' => 0,
    'total_contribution' => 0,
    'remittance_status' => 'Not Recorded',
    'payment_date' => null,
    'payment_reference' => null,
    'cumulative_contributions' => 0,
    'closing_balance' => 0
];

$pensionTrackingError = '';
$pensionTrackingExists = false;
$monthlyPension = null;

try {
    $selectedYear = (int)$payroll['year'];

    // Get the selected month as a full month name.
    $selectedMonthText = date(
        'F',
        strtotime($selectedMonth . '-01')
    );

    $selectedMonthStart = date('Y-m-01', strtotime($selectedMonth . '-01'));
    $selectedMonthEnd = date('Y-m-t', strtotime($selectedMonthStart));

    // 1. Retrieve the employee's opening balance.
    $openingPensionStmt = $conn->prepare("
        SELECT opening_balance
        FROM employee_pension_opening_balances
        WHERE user_id = ?
          AND (
              effective_date IS NULL
              OR effective_date <= ?
          )
        LIMIT 1
    ");

    $openingPensionStmt->bind_param(
        "is",
        $loggedInUserId,
        $selectedMonthEnd
    );

    $openingPensionStmt->execute();
    $openingPension = $openingPensionStmt->get_result()->fetch_assoc();
    $openingPensionStmt->close();

    if ($openingPension) {
        $pensionTracking['opening_balance'] =
            (float)$openingPension['opening_balance'];

        $pensionTrackingExists = true;
    }

    // 2. Retrieve the selected month's contribution.
    $monthlyPensionStmt = $conn->prepare("
        SELECT
            employee_amount,
            employer_amount,
            total_contribution,
            remittance_status,
            payment_date,
            payment_reference
        FROM pension_contributions
        WHERE user_id = ?
          AND LOWER(TRIM(month)) = LOWER(TRIM(?))
          AND year = ?
        LIMIT 1
    ");

    $monthlyPensionStmt->bind_param(
        "isi",
        $loggedInUserId,
        $selectedMonthText,
        $selectedYear
    );

    $monthlyPensionStmt->execute();

    $monthlyPension = $monthlyPensionStmt
        ->get_result()
        ->fetch_assoc();

    $monthlyPensionStmt->close();

    if ($monthlyPension) {
        $pensionTrackingExists = true;

        $pensionTracking['employee_amount'] =
            (float)$monthlyPension['employee_amount'];

        $pensionTracking['employer_amount'] =
            (float)$monthlyPension['employer_amount'];

        // Calculate directly from employee and employer amounts.
        $pensionTracking['total_contribution'] =
            $pensionTracking['employee_amount'] +
            $pensionTracking['employer_amount'];

        $pensionTracking['remittance_status'] =
            $monthlyPension['remittance_status'];

        $pensionTracking['payment_date'] =
            $monthlyPension['payment_date'];

        $pensionTracking['payment_reference'] =
            $monthlyPension['payment_reference'];
    }

    // 3. Calculate cumulative contributions up to this payroll month.
    // Both Paid and Pending contributions are included in the
    // recorded pension balance.

    $monthExpression = "
        CASE LOWER(TRIM(month))
            WHEN 'january' THEN 1
            WHEN 'jan' THEN 1
            WHEN 'february' THEN 2
            WHEN 'feb' THEN 2
            WHEN 'march' THEN 3
            WHEN 'mar' THEN 3
            WHEN 'april' THEN 4
            WHEN 'apr' THEN 4
            WHEN 'may' THEN 5
            WHEN 'june' THEN 6
            WHEN 'jun' THEN 6
            WHEN 'july' THEN 7
            WHEN 'jul' THEN 7
            WHEN 'august' THEN 8
            WHEN 'aug' THEN 8
            WHEN 'september' THEN 9
            WHEN 'sep' THEN 9
            WHEN 'sept' THEN 9
            WHEN 'october' THEN 10
            WHEN 'oct' THEN 10
            WHEN 'november' THEN 11
            WHEN 'nov' THEN 11
            WHEN 'december' THEN 12
            WHEN 'dec' THEN 12
            ELSE
                CASE
                    WHEN TRIM(month) REGEXP '^[0-9]{1,2}$'
                    THEN CAST(TRIM(month) AS UNSIGNED)
                    WHEN TRIM(month) REGEXP '^[0-9]{4}-[0-9]{2}$'
                    THEN CAST(RIGHT(TRIM(month), 2) AS UNSIGNED)
                    ELSE 0
                END
        END
    ";

    $cumulativePensionStmt = $conn->prepare("
        SELECT
            COALESCE(
                SUM(employee_amount + employer_amount),
                0
            ) AS cumulative_contributions
        FROM pension_contributions
        WHERE user_id = ?
          AND (
                year < ?
                OR (
                    year = ?
                    AND ($monthExpression) <= ?
                )
          )
    ");

   $selectedMonthNumber = (int)date(
    'n',
    strtotime($selectedMonthStart)
);

$cumulativePensionStmt->bind_param(
    "iiii",
    $loggedInUserId,
    $selectedYear,
    $selectedYear,
    $selectedMonthNumber
);
    $cumulativePensionStmt->execute();

    $cumulativeResult = $cumulativePensionStmt
        ->get_result()
        ->fetch_assoc();

    $cumulativePensionStmt->close();

    $pensionTracking['cumulative_contributions'] =
        (float)$cumulativeResult['cumulative_contributions'];

    // 4. Calculate closing balance.
    $pensionTracking['closing_balance'] = round(
        $pensionTracking['opening_balance'] +
        $pensionTracking['cumulative_contributions'],
        2
    );

} catch (Throwable $e) {
    $pensionTrackingError = $e->getMessage();
}

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

    $saccoStmt->bind_param('is', $loggedInUserId, $monthEnd);
    $saccoStmt->execute();

    $members = $saccoStmt->get_result();

    while ($member = $members->fetch_assoc()) {
        $memberId = (int)$member['sacco_member_id'];

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
            $member['opening_balance'] + $member['monthly_contribution'];

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
            $loanAmount = (float)$loan['principal_amount']
                + (float)$loan['interest_amount'];

            $loan['loan_amount'] = $loanAmount;
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

$monthName = ($payroll['month'] ?? '') . ' ' . ($payroll['year'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Payslip - <?= h($payroll['full_name']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">My Payslip</h1>
            <p class="text-gray-500"><?= h($monthName) ?></p>
        </div>

        <div class="flex gap-2">
            <a href="my_payrolls.php"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                Back to Payroll
            </a>

            <a href="download_my_payroll_pdf.php?id=<?= $payroll_id ?>&download=1"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Download PDF
            </a>
        </div>
    </div>

    <!-- Employee information -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">
            Employee & Payroll Information
        </h2>

       
<!-- Pension running balance -->
<div class="bg-white rounded-xl shadow p-6 mb-6">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-700">
                Pension Contribution & Running Balance
            </h2>
            <p class="text-xs text-gray-500">
                Pension position as at <?= h($monthName) ?>
            </p>
        </div>

        <?php if ($pensionTrackingError === ''): ?>
            <span class="px-3 py-1 rounded-full text-xs font-semibold
                <?= strtolower($pensionTracking['remittance_status']) === 'paid'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-yellow-100 text-yellow-700' ?>">
                Remittance: <?= h($pensionTracking['remittance_status']) ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($pensionTrackingError !== ''): ?>

        <p class="text-red-600 text-sm">
            <?= h($pensionTrackingError) ?>
        </p>

    <?php elseif (!$pensionTrackingExists): ?>

        <p class="text-gray-500 italic">
            No pension tracking information is available for this payroll period.
        </p>

    <?php else: ?>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">

            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-xs text-gray-500">Opening Balance</p>
                <p class="text-lg font-bold text-gray-800">
                    Ksh <?= money($pensionTracking['opening_balance']) ?>
                </p>
            </div>

            <div class="bg-blue-50 p-4 rounded-lg">
                <p class="text-xs text-gray-600">Employee Contribution</p>
                <p class="text-lg font-bold text-blue-700">
                    Ksh <?= money($pensionTracking['employee_amount']) ?>
                </p>
            </div>

            <div class="bg-green-50 p-4 rounded-lg">
                <p class="text-xs text-gray-600">Employer Contribution</p>
                <p class="text-lg font-bold text-green-700">
                    Ksh <?= money($pensionTracking['employer_amount']) ?>
                </p>
            </div>

            <div class="bg-purple-50 p-4 rounded-lg">
                <p class="text-xs text-gray-600">Total This Month</p>
                <p class="text-lg font-bold text-purple-700">
                    Ksh <?= money($pensionTracking['total_contribution']) ?>
                </p>
            </div>

            <div class="bg-indigo-50 p-4 rounded-lg">
                <p class="text-xs text-gray-600">Closing Balance</p>
                <p class="text-lg font-bold text-indigo-700">
                    Ksh <?= money($pensionTracking['closing_balance']) ?>
                </p>
            </div>

        </div>

        <div class="mt-4 border-t pt-4">
            <div class="flex justify-between items-center">
                <span class="text-sm text-gray-600">
                    Cumulative Contributions Recorded
                </span>

                <span class="font-semibold text-gray-800">
                    Ksh <?= money($pensionTracking['cumulative_contributions']) ?>
                </span>
            </div>

            <div class="flex justify-between items-center mt-2">
                <span class="text-sm text-gray-600">
                    Total Pension Balance
                </span>

                <span class="text-xl font-bold text-indigo-700">
                    Ksh <?= money($pensionTracking['closing_balance']) ?>
                </span>
            </div>
        </div>

        <div class="mt-4 bg-blue-50 border border-blue-100 rounded-lg p-3">
            <p class="text-xs text-blue-800">
                The closing balance is calculated from the opening balance
                plus cumulative contributions recorded in the pension ledger.
                Contributions marked Pending are included in this recorded
                balance but have not yet been confirmed as remitted.
                Employer contributions are additional employer funding and
                are not deducted from your net salary.
            </p>
        </div>

    <?php endif; ?>

</div>


    <!-- Salary summary -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">Salary Summary</h2>

        <div class="divide-y text-sm">
            <?php
            $summaryRows = [
                ['Basic Salary', $basicSalary],
                ['Total Allowances', $totalAllowances],
                ['Gross Pay', $grossPay],
                ['Total Deductions', $totalDeductions],
                ['Employer Pension Contribution (6%)', $employerPension]
            ];
            foreach ($summaryRows as $row):
            ?>
                <div class="flex justify-between py-3">
                    <span><?= h($row[0]) ?></span>
                    <span class="font-medium">Ksh <?= money($row[1]) ?></span>
                </div>
            <?php endforeach; ?>

            <div class="flex justify-between py-4 text-lg font-bold text-blue-700">
                <span>NET PAY</span>
                <span>Ksh <?= money($netPay) ?></span>
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-2">
            Employer pension is funded by the employer. It is not deducted
            from your salary and does not reduce your net pay.
        </p>
    </div>

    <!-- Pension summary -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">
            Pension Contribution Summary
        </h2>

        <?php if ($pensionEnabled): ?>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Employee Contribution (6%)</p>
                    <p class="text-xl font-bold text-blue-700">
                        Ksh <?= money($employeePension) ?>
                    </p>
                </div>

                <div class="bg-green-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Employer Contribution (6%)</p>
                    <p class="text-xl font-bold text-green-700">
                        Ksh <?= money($employerPension) ?>
                    </p>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-600">Combined Contribution (12%)</p>
                    <p class="text-xl font-bold text-gray-800">
                        Ksh <?= money($employeePension + $employerPension) ?>
                    </p>
                </div>
            </div>

            <p class="text-xs text-gray-500 mt-3">
                Calculated using gross pay. The employer contribution is
                additional employer expenditure and is not deducted from
                employee net pay.
            </p>
        <?php else: ?>
            <p class="text-gray-500 italic">
                You were not enrolled in the pension scheme for this payroll period.
            </p>
        <?php endif; ?>
    </div>

    <!-- Tax and statutory audit -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">
            Payroll Tax & Statutory Breakdown
        </h2>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <tbody>
                <?php foreach ($taxLines as $index => $line): ?>
                    <tr class="border-b <?= in_array($index, [0,5,6,8,9], true) ? 'bg-gray-50 font-semibold' : '' ?>">
                        <td class="px-4 py-3"><?= h($line[0]) ?></td>
                        <td class="px-4 py-3 text-right">
                            <?= $line[1] === null
                                ? 'Not recorded'
                                : 'Ksh ' . money($line[1]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Allowances -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-green-700 mb-4">
            Allowances Breakdown
        </h2>

        <?php if ($manualAllowances): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-green-100">
                        <tr>
                            <th class="px-4 py-3 text-left">Allowance</th>
                            <th class="px-4 py-3 text-right">Amount (Ksh)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($manualAllowances as $allowance): ?>
                        <tr class="border-b">
                            <td class="px-4 py-3"><?= h($allowance['name'] ?? 'Allowance') ?></td>
                            <td class="px-4 py-3 text-right">
                                <?= money(salaryLineAmount($allowance) ?? 0) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 italic">No allowances recorded.</p>
        <?php endif; ?>
    </div>

    <!-- Deductions -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-red-700 mb-4">
            Deductions Breakdown
        </h2>

        <?php if ($manualDeductions): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-red-100">
                        <tr>
                            <th class="px-4 py-3 text-left">Deduction</th>
                            <th class="px-4 py-3 text-right">Rate</th>
                            <th class="px-4 py-3 text-right">Amount (Ksh)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($manualDeductions as $deduction):
                        $rate = '-';

                        if (($deduction['basis'] ?? '') === 'percent' &&
                            isset($deduction['value'])) {
                            $rate = money($deduction['value']) . '%';
                        } elseif (isset($deduction['rate']) &&
                            is_numeric($deduction['rate'])) {
                            $rate = money($deduction['rate']) . '%';
                        } elseif (is_string($deduction['amount'] ?? null) &&
                            strpos($deduction['amount'], '%') !== false) {
                            $rate = $deduction['amount'];
                        }

                        $amount = salaryLineAmount($deduction);
                    ?>
                        <tr class="border-b">
                            <td class="px-4 py-3"><?= h($deduction['name'] ?? 'Deduction') ?></td>
                            <td class="px-4 py-3 text-right"><?= h($rate) ?></td>
                            <td class="px-4 py-3 text-right">
                                <?= $amount === null ? 'Not recorded' : money($amount) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500 italic">No deductions recorded.</p>
        <?php endif; ?>
    </div>

    <!-- SACCO -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-purple-700 mb-4">
            SACCO Contributions & Loan Tracking
        </h2>

        <p class="text-xs text-gray-500 mb-4">
            SACCO transactions are tracked independently and are not included
            in payroll deductions or net pay.
        </p>

        <?php if ($saccoError): ?>
            <p class="text-red-600"><?= h($saccoError) ?></p>

        <?php elseif ($saccoMembers): ?>

            <?php foreach ($saccoMembers as $member): ?>
                <div class="border rounded-lg p-4 mb-4">
                    <h3 class="font-semibold text-gray-800 mb-3">
                        <?= h($member['sacco_name']) ?>
                        <span class="text-sm text-gray-500">
                            (<?= h($member['sacco_account_no']) ?>)
                        </span>
                    </h3>

                    <div class="grid md:grid-cols-3 gap-3 text-sm mb-4">
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-gray-500">Opening Balance</p>
                            <p class="font-semibold">Ksh <?= money($member['opening_balance']) ?></p>
                        </div>

                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-gray-500">Monthly Contribution</p>
                            <p class="font-semibold">Ksh <?= money($member['monthly_contribution']) ?></p>
                        </div>

                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-gray-500">Contribution Balance</p>
                            <p class="font-semibold">Ksh <?= money($member['balance_after_contribution']) ?></p>
                        </div>
                    </div>

                    <?php if (!empty($member['loans'])): ?>
                        <h4 class="font-semibold text-sm mb-2">SACCO Loans</h4>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-purple-100">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Loan Reference</th>
                                        <th class="px-3 py-2 text-right">Loan Amount</th>
                                        <th class="px-3 py-2 text-right">Paid This Month</th>
                                        <th class="px-3 py-2 text-right">Pending Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($member['loans'] as $loan): ?>
                                    <tr class="border-b">
                                        <td class="px-3 py-2"><?= h($loan['loan_reference']) ?></td>
                                        <td class="px-3 py-2 text-right"><?= money($loan['loan_amount']) ?></td>
                                        <td class="px-3 py-2 text-right"><?= money($loan['paid_this_month']) ?></td>
                                        <td class="px-3 py-2 text-right"><?= money($loan['pending_balance']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 italic">No SACCO loans recorded.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <p class="text-gray-500 italic">No SACCO membership recorded for this period.</p>
        <?php endif; ?>
    </div>

    <!-- Payroll workflow -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-700 mb-4">
            Payroll Workflow & Authorization
        </h2>

        <div class="grid md:grid-cols-3 gap-4 text-sm">
            <div class="border rounded-lg p-4">
                <h3 class="font-semibold mb-2">Preparation</h3>
                <p><strong>Prepared by:</strong> <?= h($payroll['prepared_by'] ?? 'Not recorded') ?></p>
                <p><strong>Date:</strong> <?= h($payroll['date_prepared'] ?? 'Not recorded') ?></p>
                <p><strong>Status:</strong> <?= h($payroll['preparation_status'] ?? 'Not recorded') ?></p>
            </div>

            <div class="border rounded-lg p-4">
                <h3 class="font-semibold mb-2">Approval</h3>
                <p><strong>Approved by:</strong> <?= h($payroll['approved_by'] ?? 'Not recorded') ?></p>
                <p><strong>Date:</strong> <?= h($payroll['approval_date'] ?? 'Not recorded') ?></p>
                <p><strong>Status:</strong> <?= h($payroll['approval_status'] ?? 'Not recorded') ?></p>
            </div>

            <div class="border rounded-lg p-4">
                <h3 class="font-semibold mb-2">Payment</h3>
                <p><strong>Paid by:</strong> <?= h($payroll['accountant_name'] ?? 'Not recorded') ?></p>
                <p><strong>Date:</strong> <?= h($payroll['payment_date'] ?? 'Not recorded') ?></p>
                <p><strong>Status:</strong> <?= h($payroll['payment_status'] ?? 'Not recorded') ?></p>
            </div>
        </div>
    </div>

    <div class="text-center text-xs text-gray-500 pb-6">
        Generated by Lynntech HR Manager.
        Figures reflect the saved payroll record for <?= h($monthName) ?>.
    </div>

</div>

</body>
</html>
