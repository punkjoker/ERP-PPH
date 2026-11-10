
<?php
session_start();
require_once __DIR__ . '/db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

function p9_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function p9_number($value): ?float {
    if ($value === null || $value === '' || !is_scalar($value)) {
        return null;
    }

    if (is_string($value)) {
        $value = str_replace(',', '', trim($value));
    }

    return is_numeric($value) ? (float)$value : null;
}

function p9_path_value(array $source, array $paths): ?float {
    foreach ($paths as $path) {
        $value = $source;

        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                $value = null;
                break;
            }

            $value = $value[$part];
        }

        $number = p9_number($value);

        if ($number !== null) {
            return $number;
        }
    }

    return null;
}

function p9_line_amount(array $line): ?float {
    return p9_number(
        $line['amount']
        ?? $line['calculated_amount']
        ?? null
    );
}

function p9_find_deduction(array $lines, array $needles): float {
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
                $amount = p9_line_amount($line);

                if ($amount !== null) {
                    $total += $amount;
                }

                break;
            }
        }
    }

    return $total;
}

function p9_month_number($month): int {
    $month = trim((string)$month);

    if (ctype_digit($month)) {
        $number = (int)$month;
        return ($number >= 1 && $number <= 12) ? $number : 0;
    }

    $timestamp = strtotime($month);

    return $timestamp ? (int)date('n', $timestamp) : 0;
}

function p9_month_name(int $month): string {
    return date('F', mktime(0, 0, 0, $month, 1));
}

function p9_build_month(array $payroll, int $year, int $month): array {
    $details = json_decode((string)($payroll['details'] ?? ''), true);

    if (!is_array($details)) {
        $details = [];
    }

    $deductions = is_array($details['deductions'] ?? null)
        ? $details['deductions']
        : [];

    $basic = (float)($payroll['base_salary'] ?? 0);
    $allowances = (float)($payroll['total_allowances'] ?? 0);

    // These are separate P9A fields. They are zero unless explicitly
    // saved by the payroll calculator.
    $nonCashBenefits = p9_path_value($details, [
        'p9.non_cash_benefits',
        'p9.benefits_non_cash',
        'audit.non_cash_benefits',
        'benefits.non_cash'
    ]) ?? 0.0;

    $quarters = p9_path_value($details, [
        'p9.value_of_quarters',
        'p9.quarters_value',
        'audit.value_of_quarters',
        'benefits.value_of_quarters'
    ]) ?? 0.0;

    // Gross pay includes basic salary, cash allowances and separately
    // recorded non-cash benefits and quarters.
    $gross = $basic + $allowances + $nonCashBenefits + $quarters;

    // Actual employee pension contribution.
    $pensionActual = p9_path_value($details, [
        'pension.employee_amount',
        'p9.pension_actual',
        'audit.employee_pension'
    ]);

    if ($pensionActual === null) {
        $pensionActual = p9_find_deduction(
            $deductions,
            ['employee pension', 'pension contribution']
        );
    }

    // Pension deduction limits shown on the P9A.
    $pension30Percent = $basic * 0.30;
    $pensionMonthlyLimit = (
        $year < 2024 || ($year === 2024 && $month < 12)
    ) ? 20000.00 : 30000.00;

    $pensionTaxQualified = !(
        isset($details['pension']['tax_qualified']) &&
        !$details['pension']['tax_qualified']
    );

    $pensionAllowable = 0.0;

    if ($pensionTaxQualified && $pensionActual !== null) {
        $pensionAllowable = max(0, min(
            $pension30Percent,
            $pensionActual,
            $pensionMonthlyLimit
        ));
    }

    $ahl = p9_path_value($details, [
        'p9.ahl',
        'p9.affordable_housing_levy',
        'audit.ahl'
    ]);

    if ($ahl === null) {
        $ahl = p9_find_deduction(
            $deductions,
            ['affordable housing levy', 'housing levy', 'ahl']
        );
    }

    $shif = p9_path_value($details, [
        'p9.shif',
        'p9.social_health_insurance',
        'audit.shif'
    ]);

    if ($shif === null) {
        $shif = p9_find_deduction(
            $deductions,
            ['shif', 'social health insurance']
        );
    }

    $prmf = p9_path_value($details, [
        'p9.prmf',
        'p9.post_retirement_medical_fund',
        'audit.prmf'
    ]);

    if ($prmf === null) {
        $prmf = p9_find_deduction(
            $deductions,
            ['post retirement medical', 'prmf']
        );
    }

    $ownerInterest = p9_path_value($details, [
        'p9.owner_occupied_interest',
        'p9.owner_occupier_interest',
        'audit.owner_occupied_interest'
    ]);

    if ($ownerInterest === null) {
        $ownerInterest = p9_find_deduction(
            $deductions,
            ['owner occupied interest', 'owner occupier interest']
        );
    }

    $ahl = $ahl ?? 0.0;
    $shif = $shif ?? 0.0;
    $prmf = $prmf ?? 0.0;
    $ownerInterest = $ownerInterest ?? 0.0;

    $totalDeductions = (
        $pensionAllowable +
        $ahl +
        $shif +
        $prmf +
        $ownerInterest
    );

    $chargeablePay = $gross - $totalDeductions;

    // Use the historical tax values saved in the payroll record.
    $taxCharged = p9_path_value($details, [
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

    $paye = p9_path_value($details, [
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
        $paye = p9_find_deduction(
            $deductions,
            ['income tax (paye)', 'paye']
        );
    }

    $totalRelief = p9_path_value($details, [
        'audit.tax_relief',
        'calculation.tax_relief',
        'calculation.relief_applied',
        'tax.tax_relief',
        'tax_relief',
        'relief_applied'
    ]);

    // Match the payslip's historical audit approach:
    // derive total relief from saved tax charged and saved PAYE.
    if ($taxCharged !== null && $paye !== null) {
        $totalRelief = max(0, $taxCharged - $paye);
    }

    $personalRelief = p9_path_value($details, [
        'p9.personal_relief',
        'audit.personal_relief',
        'calculation.personal_relief',
        'tax.personal_relief'
    ]);

    $insuranceRelief = p9_path_value($details, [
        'p9.insurance_relief',
        'audit.insurance_relief',
        'calculation.insurance_relief',
        'tax.insurance_relief'
    ]);

    // If the saved payroll only contains combined relief, split it
    // using the P9A personal-relief amount, up to Ksh 2,400 monthly.
    // An explicitly saved personal or insurance relief takes priority.
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
        'month' => $month,
        'month_name' => p9_month_name($month),
        'has_record' => true,
        'basic' => $basic,
        'benefits' => $nonCashBenefits,
        'quarters' => $quarters,
        'gross' => $gross,
        'pension_e1' => $pension30Percent,
        'pension_e2' => $pensionActual,
        'pension_e3' => $pensionMonthlyLimit,
        'pension_allowed' => $pensionAllowable,
        'ahl' => $ahl,
        'shif' => $shif,
        'prmf' => $prmf,
        'owner_interest' => $ownerInterest,
        'total_deductions' => $totalDeductions,
        'chargeable' => $chargeablePay,
        'tax_charged' => $taxCharged,
        'personal_relief' => $personalRelief,
        'insurance_relief' => $insuranceRelief,
        'paye' => $paye
    ];
}

function p9_money($value): string {
    return $value === null ? '—' : number_format((float)$value, 2);
}

function p9_total(array $rows, string $key): ?float {
    $found = false;
    $total = 0.0;

    foreach ($rows as $row) {
        if (!$row['has_record'] || !isset($row[$key]) || $row[$key] === null) {
            continue;
        }

        $found = true;
        $total += (float)$row[$key];
    }

    return $found ? $total : null;
}

// Employee profile and KRA PIN.
$stmt = $conn->prepare("
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.national_id,
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
    die("Your employee profile could not be found.");
}

// Available tax years for this employee.
$stmt = $conn->prepare("
    SELECT DISTINCT year
    FROM payroll_records
    WHERE user_id = ?
      AND year IS NOT NULL
    ORDER BY year DESC
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$yearResult = $stmt->get_result();

$availableYears = [];

while ($yearRow = $yearResult->fetch_assoc()) {
    $availableYears[] = (int)$yearRow['year'];
}

$stmt->close();

$selectedYear = isset($_GET['year']) && ctype_digit((string)$_GET['year'])
    ? (int)$_GET['year']
    : ($availableYears[0] ?? (int)date('Y'));

if ($selectedYear < 2000 || $selectedYear > ((int)date('Y') + 1)) {
    $selectedYear = $availableYears[0] ?? (int)date('Y');
}

// Fetch only this employee's payroll records for the selected year.
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

$stmt->bind_param("ii", $userId, $selectedYear);
$stmt->execute();
$payrollResult = $stmt->get_result();

$monthlyPayroll = [];

while ($payroll = $payrollResult->fetch_assoc()) {
    $monthNumber = p9_month_number($payroll['month']);

    if ($monthNumber >= 1 && $monthNumber <= 12) {
        // If duplicate payroll records exist for a month, the latest
        // payroll_id is used.
        $monthlyPayroll[$monthNumber] = $payroll;
    }
}

$stmt->close();

// Build all twelve monthly rows.
$rows = [];

for ($month = 1; $month <= 12; $month++) {
    if (isset($monthlyPayroll[$month])) {
        $rows[] = p9_build_month(
            $monthlyPayroll[$month],
            $selectedYear,
            $month
        );
    } else {
        $rows[] = [
            'month' => $month,
            'month_name' => p9_month_name($month),
            'has_record' => false
        ];
    }
}

// Employer profile.
$employer = null;

$stmt = $conn->prepare("
    SELECT employer_name, kra_pin, postal_address, town, phone, email
    FROM employer_tax_details
    WHERE id = 1
    LIMIT 1
");

$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$monthsWithPayroll = count($monthlyPayroll);

$summaryKeys = [
    'basic' => 'Basic Salary',
    'benefits' => 'Benefits - Non-Cash',
    'quarters' => 'Value of Quarters',
    'gross' => 'Total Gross Pay',
    'pension_allowed' => 'Allowable Pension',
    'ahl' => 'Affordable Housing Levy',
    'shif' => 'Social Health Insurance Fund',
    'prmf' => 'Post Retirement Medical Fund',
    'owner_interest' => 'Owner-Occupied Interest',
    'total_deductions' => 'Total P9A Deductions',
    'chargeable' => 'Chargeable Pay',
    'tax_charged' => 'Tax Charged',
    'personal_relief' => 'Personal Relief',
    'insurance_relief' => 'Insurance Relief',
    'paye' => 'PAYE Tax'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My P9 Form | Lynntech Manager</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body { background: #f8fafc; }
        .p9-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
        .p9-table th, .p9-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .p9-table th {
            background: #f1f5f9;
            color: #334155;
            font-size: 12px;
            text-transform: uppercase;
        }
    </style>
</head>

<body class="bg-slate-50">

<?php require_once __DIR__ . '/navbar.php'; ?>

<main class="ml-64 p-6">

    <div class="max-w-7xl mx-auto">

        <div class="flex flex-col md:flex-row md:items-center
                    md:justify-between gap-4 mb-6">

            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    <i class="fas fa-file-invoice-dollar text-amber-600 mr-2"></i>
                    My P9 Form
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    View your annual KRA tax deduction card and download
                    your P9A PDF.
                </p>
            </div>

            <form method="GET" class="flex items-center gap-3">
                <label for="year" class="text-sm font-semibold text-slate-600">
                    Tax Year
                </label>

                <select name="year" id="year"
                        onchange="this.form.submit()"
                        class="border border-slate-300 rounded-lg px-4 py-2
                               bg-white text-sm focus:ring-2 focus:ring-amber-500">

                    <?php if (!$availableYears): ?>
                        <option value="<?= (int)$selectedYear ?>">
                            <?= (int)$selectedYear ?>
                        </option>
                    <?php else: ?>
                        <?php foreach ($availableYears as $year): ?>
                            <option value="<?= $year ?>"
                                <?= $selectedYear === $year ? 'selected' : '' ?>>
                                <?= $year ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </select>
            </form>

        </div>

        <?php if (!$employer): ?>
            <div class="mb-5 p-4 rounded-lg bg-yellow-50
                        border border-yellow-200 text-yellow-800 text-sm">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Employer tax details have not been configured by HR.
                Please contact HR before downloading your P9A.
            </div>
        <?php endif; ?>

        <?php if (empty($employee['kra_pin'])): ?>
            <div class="mb-5 p-4 rounded-lg bg-yellow-50
                        border border-yellow-200 text-yellow-800 text-sm">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Your employee KRA PIN is missing from your employee profile.
                Please contact HR to update it.
            </div>
        <?php endif; ?>

        <!-- Employee information -->
        <section class="p9-card shadow-sm p-5 mb-6">

            <div class="flex flex-col md:flex-row md:items-center
                        md:justify-between gap-4">

                <div>
                    <h2 class="font-semibold text-slate-800 mb-3">
                        Employee Information
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-2 text-sm">

                        <p>
                            <span class="text-slate-500">Full Name:</span>
                            <strong><?= p9_h($employee['full_name'] ?? '') ?></strong>
                        </p>

                        <p>
                            <span class="text-slate-500">Employee KRA PIN:</span>
                            <strong><?= p9_h($employee['kra_pin'] ?: 'Not recorded') ?></strong>
                        </p>

                        <p>
                            <span class="text-slate-500">Email:</span>
                            <?= p9_h($employee['email'] ?? '-') ?>
                        </p>

                        <p>
                            <span class="text-slate-500">National ID:</span>
                            <?= p9_h($employee['national_id'] ?? '-') ?>
                        </p>

                    </div>
                </div>

                <div class="text-left md:text-right">
                    <p class="text-xs uppercase text-slate-500 font-semibold">
                        Selected Tax Year
                    </p>

                    <p class="text-3xl font-bold text-amber-700">
                        <?= (int)$selectedYear ?>
                    </p>
                </div>

            </div>

        </section>

        <!-- Annual summary -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

            <div class="p9-card p-4">
                <p class="text-xs text-slate-500 uppercase font-semibold">
                    Months Processed
                </p>
                <p class="text-2xl font-bold text-slate-800 mt-2">
                    <?= $monthsWithPayroll ?> / 12
                </p>
            </div>

            <div class="p9-card p-4">
                <p class="text-xs text-slate-500 uppercase font-semibold">
                    Annual Gross Pay
                </p>
                <p class="text-xl font-bold text-blue-700 mt-2">
                    KSh <?= number_format(p9_total($rows, 'gross') ?? 0, 2) ?>
                </p>
            </div>

            <div class="p9-card p-4">
                <p class="text-xs text-slate-500 uppercase font-semibold">
                    Chargeable Pay
                </p>
                <p class="text-xl font-bold text-indigo-700 mt-2">
                    KSh <?= number_format(p9_total($rows, 'chargeable') ?? 0, 2) ?>
                </p>
            </div>

            <div class="p9-card p-4">
                <p class="text-xs text-slate-500 uppercase font-semibold">
                    Total PAYE
                </p>
                <p class="text-xl font-bold text-green-700 mt-2">
                    KSh <?= number_format(p9_total($rows, 'paye') ?? 0, 2) ?>
                </p>
            </div>

        </section>

        <!-- Monthly P9 breakdown -->
        <section class="p9-card shadow-sm overflow-hidden mb-6">

            <div class="px-5 py-4 border-b border-slate-200
                        flex flex-col sm:flex-row sm:items-center
                        sm:justify-between gap-3">

                <div>
                    <h2 class="font-semibold text-slate-800">
                        Monthly P9A Breakdown
                    </h2>

                    <p class="text-xs text-slate-500 mt-1">
                        Payroll information for January to December <?= (int)$selectedYear ?>
                    </p>
                </div>

                <?php if ($employer && !empty($employee['kra_pin']) && $monthsWithPayroll > 0): ?>
                    <a href="download_my_p9.php?year=<?= (int)$selectedYear ?>"
                       class="inline-flex items-center justify-center gap-2
                              bg-amber-600 hover:bg-amber-700 text-white
                              px-5 py-2.5 rounded-lg text-sm font-semibold">

                        <i class="fas fa-file-pdf"></i>
                        Download P9A PDF
                    </a>
                <?php else: ?>
                    <span class="text-xs text-slate-400">
                        PDF download unavailable
                    </span>
                <?php endif; ?>

            </div>

            <div class="overflow-x-auto">

                <table class="w-full text-sm p9-table">

                    <thead>
                        <tr>
                            <th class="text-left">Month</th>
                            <th class="text-right">Basic Salary</th>
                            <th class="text-right">Benefits</th>
                            <th class="text-right">Quarters</th>
                            <th class="text-right">Gross Pay</th>
                            <th class="text-right">Pension Allowed</th>
                            <th class="text-right">AHL</th>
                            <th class="text-right">SHIF</th>
                            <th class="text-right">PRMF</th>
                            <th class="text-right">Owner Interest</th>
                            <th class="text-right">Total Deductions</th>
                            <th class="text-right">Chargeable Pay</th>
                            <th class="text-right">Tax Charged</th>
                            <th class="text-right">Personal Relief</th>
                            <th class="text-right">Insurance Relief</th>
                            <th class="text-right">PAYE</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($rows as $row): ?>

                        <tr class="<?= !$row['has_record'] ? 'bg-slate-50 text-slate-400' : '' ?>">

                            <td class="font-semibold">
                                <?= p9_h($row['month_name']) ?>

                                <?php if (!$row['has_record']): ?>
                                    <span class="block text-xs font-normal">
                                        No payroll
                                    </span>
                                <?php endif; ?>
                            </td>

                            <?php
                            $displayKeys = [
                                'basic',
                                'benefits',
                                'quarters',
                                'gross',
                                'pension_allowed',
                                'ahl',
                                'shif',
                                'prmf',
                                'owner_interest',
                                'total_deductions',
                                'chargeable',
                                'tax_charged',
                                'personal_relief',
                                'insurance_relief',
                                'paye'
                            ];
                            ?>

                            <?php foreach ($displayKeys as $key): ?>
                                <td class="text-right">
                                    <?= $row['has_record']
                                        ? p9_money($row[$key] ?? null)
                                        : '—' ?>
                                </td>
                            <?php endforeach; ?>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                    <tfoot>
                        <tr class="bg-slate-100 font-bold">
                            <td>TOTAL</td>

                            <?php foreach ([
                                'basic',
                                'benefits',
                                'quarters',
                                'gross',
                                'pension_allowed',
                                'ahl',
                                'shif',
                                'prmf',
                                'owner_interest',
                                'total_deductions',
                                'chargeable',
                                'tax_charged',
                                'personal_relief',
                                'insurance_relief',
                                'paye'
                            ] as $key): ?>

                                <td class="text-right">
                                    <?= p9_money(p9_total($rows, $key)) ?>
                                </td>

                            <?php endforeach; ?>
                        </tr>
                    </tfoot>

                </table>

            </div>

        </section>

        <div class="p-4 rounded-lg bg-blue-50 border border-blue-100
                    text-sm text-blue-800">

            <i class="fas fa-info-circle mr-2"></i>

            This P9A is based on payroll records saved for the selected year.
            Benefits, quarters, retirement medical fund, owner-occupied
            interest and separate relief values will only be populated when
            those values are recorded in the payroll details.
        </div>

    </div>

</main>

</body>
</html>
