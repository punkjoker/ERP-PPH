
<?php
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
| Enforce your existing HR authentication and authorization rules here.
| This page should only be accessible to authorized HR staff.
*/

$months = [
    'January', 'February', 'March', 'April',
    'May', 'June', 'July', 'August',
    'September', 'October', 'November', 'December'
];

$currentYear = (int)date('Y');
$success = '';
$error = '';

if (empty($_SESSION['pension_csrf'])) {
    $_SESSION['pension_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['pension_csrf'];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function pensionMoney($amount): string {
    return 'KSh ' . number_format((float)$amount, 2);
}

function pensionDateValid($date): bool {
    if (!$date) return false;

    $d = DateTime::createFromFormat('!Y-m-d', $date);

    return $d && $d->format('Y-m-d') === $date;
}

/*
|--------------------------------------------------------------------------
| HANDLE FORMS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($csrfToken, (string)$_POST['csrf_token'])
        ) {
            throw new RuntimeException('Invalid request token. Refresh the page and try again.');
        }

        $action = $_POST['action'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | SAVE EMPLOYEE OPENING BALANCE
        |--------------------------------------------------------------------------
        */

        if ($action === 'save_opening_balance') {

            $userId = filter_var(
                $_POST['user_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $openingBalance = $_POST['opening_balance'] ?? null;
            $effectiveDate = trim((string)($_POST['effective_date'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            if (!$userId || !is_numeric($openingBalance)) {
                throw new InvalidArgumentException('Select an employee and enter a valid opening balance.');
            }

            $openingBalance = round((float)$openingBalance, 2);

            if ($openingBalance < 0) {
                throw new InvalidArgumentException('Opening balance cannot be negative.');
            }

            if ($effectiveDate !== '' && !pensionDateValid($effectiveDate)) {
                throw new InvalidArgumentException('Invalid effective date.');
            }

            if (strlen($notes) > 5000) {
                throw new InvalidArgumentException('Notes are too long.');
            }

            $stmt = $conn->prepare("
                SELECT user_id
                FROM users
                WHERE user_id = ?
                LIMIT 1
            ");

            $stmt->bind_param('i', $userId);
            $stmt->execute();

            $employee = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$employee) {
                throw new InvalidArgumentException('Employee not found.');
            }

            $effectiveDateValue = $effectiveDate !== ''
                ? $effectiveDate
                : null;

            $stmt = $conn->prepare("
                INSERT INTO employee_pension_opening_balances
                    (user_id, opening_balance, effective_date, notes)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    opening_balance = VALUES(opening_balance),
                    effective_date = VALUES(effective_date),
                    notes = VALUES(notes)
            ");

            $stmt->bind_param(
                'idss',
                $userId,
                $openingBalance,
                $effectiveDateValue,
                $notes
            );

            $stmt->execute();
            $stmt->close();

            $success = 'Employee pension opening balance saved successfully.';
        }

        /*
        |--------------------------------------------------------------------------
        | MARK PENSION CONTRIBUTION AS REMITTED
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'mark_remitted') {

            $contributionId = filter_var(
                $_POST['contribution_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $paymentDate = trim((string)($_POST['payment_date'] ?? ''));
            $paymentReference = trim((string)($_POST['payment_reference'] ?? ''));

            if (!$contributionId) {
                throw new InvalidArgumentException('Invalid pension contribution.');
            }

            if (!pensionDateValid($paymentDate)) {
                throw new InvalidArgumentException('Enter a valid remittance date.');
            }

            if ($paymentReference === '') {
                throw new InvalidArgumentException('Enter the payment reference.');
            }

            if (strlen($paymentReference) > 150) {
                throw new InvalidArgumentException('Payment reference is too long.');
            }

            $stmt = $conn->prepare("
                UPDATE pension_contributions
                SET remittance_status = 'Paid',
                    payment_date = ?,
                    payment_reference = ?
                WHERE id = ?
                  AND remittance_status = 'Pending'
            ");

            $stmt->bind_param(
                'ssi',
                $paymentDate,
                $paymentReference,
                $contributionId
            );

            $stmt->execute();

            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected === 0) {
                throw new InvalidArgumentException(
                    'Contribution not found or it has already been remitted.'
                );
            }

            $success = 'Pension contribution successfully marked as remitted.';
        }

        else {
            throw new InvalidArgumentException('Invalid action.');
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$selectedMonth = $_GET['month'] ?? '';
$selectedYear = filter_var(
    $_GET['year'] ?? $currentYear,
    FILTER_VALIDATE_INT
);

$selectedStatus = $_GET['status'] ?? '';

if (!in_array($selectedMonth, $months, true)) {
    $selectedMonth = '';
}

if (!$selectedYear || $selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = $currentYear;
}

if (!in_array($selectedStatus, ['', 'Pending', 'Paid'], true)) {
    $selectedStatus = '';
}

/*
|--------------------------------------------------------------------------
| BUILD REPORT FILTER
|--------------------------------------------------------------------------
*/

$where = [];
$where[] = '1=1';

if ($selectedMonth !== '') {
    $safeMonth = $conn->real_escape_string($selectedMonth);
    $where[] = "pc.month = '$safeMonth'";
}

$safeYear = (int)$selectedYear;
$where[] = "pc.year = $safeYear";

if ($selectedStatus !== '') {
    $safeStatus = $conn->real_escape_string($selectedStatus);
    $where[] = "pc.remittance_status = '$safeStatus'";
}

$whereSql = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| FETCH EMPLOYEES
|--------------------------------------------------------------------------
*/

$employees = [];

$employeeResult = $conn->query("
    SELECT user_id, full_name
    FROM users
    WHERE group_id = 9
    ORDER BY full_name ASC
");

while ($row = $employeeResult->fetch_assoc()) {
    $employees[] = $row;
}

/*
|--------------------------------------------------------------------------
| OPENING BALANCES
|--------------------------------------------------------------------------
*/

$openingBalances = [];

$openingResult = $conn->query("
    SELECT
        ob.*,
        u.full_name
    FROM employee_pension_opening_balances ob
    INNER JOIN users u ON ob.user_id = u.user_id
    ORDER BY u.full_name ASC
");

while ($row = $openingResult->fetch_assoc()) {
    $openingBalances[] = $row;
}

/*
|--------------------------------------------------------------------------
| SUMMARY TOTALS
|--------------------------------------------------------------------------
*/

$summarySql = "
    SELECT
        COUNT(*) AS contribution_count,

        COALESCE(SUM(employee_amount), 0) AS employee_total,

        COALESCE(SUM(employer_amount), 0) AS employer_total,

        COALESCE(SUM(total_contribution), 0) AS accrued_total,

        COALESCE(SUM(
            CASE
                WHEN remittance_status = 'Pending'
                THEN total_contribution
                ELSE 0
            END
        ), 0) AS pending_total,

        COALESCE(SUM(
            CASE
                WHEN remittance_status = 'Paid'
                THEN total_contribution
                ELSE 0
            END
        ), 0) AS paid_total

    FROM pension_contributions pc
    WHERE $whereSql
";

$summaryResult = $conn->query($summarySql);
$summary = $summaryResult->fetch_assoc();

/*
|--------------------------------------------------------------------------
| FETCH CONTRIBUTION HISTORY
|--------------------------------------------------------------------------
*/

$contributions = [];

$contributionSql = "
    SELECT
        pc.*,
        u.full_name
    FROM pension_contributions pc
    INNER JOIN users u ON pc.user_id = u.user_id
    WHERE $whereSql
    ORDER BY pc.year DESC,
             FIELD(
                pc.month,
                'January','February','March','April',
                'May','June','July','August',
                'September','October','November','December'
             ) DESC,
             u.full_name ASC
";

$contributionResult = $conn->query($contributionSql);

while ($row = $contributionResult->fetch_assoc()) {
    $contributions[] = $row;
}

/*
|--------------------------------------------------------------------------
| OPENING BALANCE TOTAL
|--------------------------------------------------------------------------
*/

$openingTotalResult = $conn->query("
    SELECT COALESCE(SUM(opening_balance), 0) AS total
    FROM employee_pension_opening_balances
");

$openingTotal = (float)$openingTotalResult->fetch_assoc()['total'];

/*
|--------------------------------------------------------------------------
| CSV EXPORT
|--------------------------------------------------------------------------
*/

if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename=pension_report_' .
        $selectedYear . '.csv'
    );

    $output = fopen('php://output', 'w');

    // Excel UTF-8 support.
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, [
        'Employee',
        'Month',
        'Year',
        'Gross Pay',
        'Employee Rate',
        'Employee Contribution',
        'Employer Rate',
        'Employer Contribution',
        'Total Contribution',
        'Remittance Status',
        'Payment Date',
        'Payment Reference'
    ]);

    foreach ($contributions as $row) {

        fputcsv($output, [
            $row['full_name'],
            $row['month'],
            $row['year'],
            $row['gross_pay'],
            $row['employee_rate'],
            $row['employee_amount'],
            $row['employer_rate'],
            $row['employer_amount'],
            $row['total_contribution'],
            $row['remittance_status'],
            $row['payment_date'],
            $row['payment_reference']
        ]);
    }

    fclose($output);
    exit;
}

/*
|--------------------------------------------------------------------------
| INCLUDE NAVIGATION
|--------------------------------------------------------------------------
*/

require 'navbar.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pension Management | Lynntech Manager</title>

    <script src="https://cdn.tailwindcss.com/2.2.19"></script>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>
        body {
            background: #f3f4f6;
        }

        .main-content {
            margin-left: 16rem;
            padding: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }

        .table-wrap {
            overflow-x: auto;
        }

        .form-control {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            padding: 9px 11px;
            background: white;
            outline: none;
        }

        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37,99,235,.12);
        }

        .btn {
            padding: 9px 14px;
            border-radius: 7px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            justify-content: center;
        }

        .btn-primary {
            background: #1e40af;
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a8a;
        }

        .btn-success {
            background: #15803d;
            color: white;
        }

        .btn-success:hover {
            background: #166534;
        }

        .btn-dark {
            background: #1f2937;
            color: white;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-paid {
            background: #dcfce7;
            color: #166534;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            margin-bottom: 24px;
        }

        @media(max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<div class="main-content">

    <!-- PAGE HEADER -->

    <div class="flex flex-wrap justify-between items-center gap-4 mb-7">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-piggy-bank text-blue-800 mr-2"></i>
                Pension Management
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Manage employee pension balances, contributions and remittances.
            </p>
        </div>

        <div class="flex gap-2 flex-wrap">

            <a
                href="?month=<?= urlencode($selectedMonth) ?>&year=<?= $selectedYear ?>&status=<?= urlencode($selectedStatus) ?>&export=csv"
                class="btn btn-success"
            >
                <i class="fas fa-file-excel"></i>
                Export Report
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-dark"
            >
                <i class="fas fa-print"></i>
                Print
            </button>

        </div>

    </div>

    <!-- ALERTS -->

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg mb-5">
            <i class="fas fa-check-circle mr-2"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-lg mb-5">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 mb-7">

        <div class="stat-card">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm text-gray-500">Employee Contributions</p>
                    <h2 class="text-2xl font-bold text-blue-800 mt-2">
                        <?= pensionMoney($summary['employee_total']) ?>
                    </h2>
                </div>
                <i class="fas fa-user text-2xl text-blue-400"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm text-gray-500">Employer Contributions</p>
                    <h2 class="text-2xl font-bold text-indigo-800 mt-2">
                        <?= pensionMoney($summary['employer_total']) ?>
                    </h2>
                </div>
                <i class="fas fa-building text-2xl text-indigo-400"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Contributions Accrued</p>
                    <h2 class="text-2xl font-bold text-gray-800 mt-2">
                        <?= pensionMoney($summary['accrued_total']) ?>
                    </h2>
                </div>
                <i class="fas fa-chart-line text-2xl text-gray-400"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending Remittance</p>
                    <h2 class="text-2xl font-bold text-amber-700 mt-2">
                        <?= pensionMoney($summary['pending_total']) ?>
                    </h2>
                </div>
                <i class="fas fa-clock text-2xl text-amber-400"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Remitted</p>
                    <h2 class="text-2xl font-bold text-green-700 mt-2">
                        <?= pensionMoney($summary['paid_total']) ?>
                    </h2>
                </div>
                <i class="fas fa-check-circle text-2xl text-green-400"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm text-gray-500">Opening Balances Recorded</p>
                    <h2 class="text-2xl font-bold text-gray-800 mt-2">
                        <?= pensionMoney($openingTotal) ?>
                    </h2>
                </div>
                <i class="fas fa-wallet text-2xl text-gray-400"></i>
            </div>
            <p class="text-xs text-gray-400 mt-2">
                Across all employees; not limited to the selected reporting year.
            </p>
        </div>

    </div>

    <!-- FILTERS -->

    <div class="section-card">

        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">

            <h2 class="text-lg font-bold text-gray-800">
                <i class="fas fa-filter text-blue-700 mr-2"></i>
                Contribution Report Filters
            </h2>

            <span class="text-sm text-gray-500">
                <?= (int)$summary['contribution_count'] ?> records found
            </span>

        </div>

        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">

            <div>
                <label class="block text-sm font-semibold mb-1">
                    Month
                </label>

                <select name="month" class="form-control">
                    <option value="">All Months</option>

                    <?php foreach ($months as $month): ?>
                        <option
                            value="<?= htmlspecialchars($month) ?>"
                            <?= $selectedMonth === $month ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($month) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">
                    Year
                </label>

                <input
                    type="number"
                    name="year"
                    min="2000"
                    max="2100"
                    value="<?= $selectedYear ?>"
                    class="form-control"
                >
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">
                    Remittance Status
                </label>

                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?= $selectedStatus === 'Pending' ? 'selected' : '' ?>>
                        Pending
                    </option>
                    <option value="Paid" <?= $selectedStatus === 'Paid' ? 'selected' : '' ?>>
                        Paid
                    </option>
                </select>
            </div>

            <div class="flex items-end gap-2">

                <button type="submit" class="btn btn-primary flex-1">
                    <i class="fas fa-search"></i>
                    Filter
                </button>

                <a href="pension_management.php" class="btn bg-gray-200 text-gray-800">
                    Reset
                </a>

            </div>

        </form>

    </div>

    <!-- OPENING BALANCE FORM -->

    <div class="section-card">

        <h2 class="text-lg font-bold text-gray-800 mb-2">
            <i class="fas fa-wallet text-blue-700 mr-2"></i>
            Employee Opening Pension Balance
        </h2>

        <p class="text-sm text-gray-500 mb-5">
            Enter the pension balance an employee had before using this system.
            Saving again for the same employee updates their opening balance.
        </p>

        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="save_opening_balance">

            <div>
                <label class="block text-sm font-semibold mb-1">
                    Employee
                </label>

                <select name="user_id" class="form-control" required>
                    <option value="">Select Employee</option>

                    <?php foreach ($employees as $employee): ?>
                        <option value="<?= (int)$employee['user_id'] ?>">
                            <?= htmlspecialchars($employee['full_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">
                    Opening Balance (KSh)
                </label>

                <input
                    type="number"
                    name="opening_balance"
                    min="0"
                    step="0.01"
                    class="form-control"
                    placeholder="0.00"
                    required
                >
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">
                    Effective Date
                </label>

                <input
                    type="date"
                    name="effective_date"
                    class="form-control"
                >
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">
                    Notes
                </label>

                <input
                    type="text"
                    name="notes"
                    class="form-control"
                    placeholder="Optional notes"
                >
            </div>

            <div class="md:col-span-2 xl:col-span-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Save Opening Balance
                </button>
            </div>

        </form>

    </div>

    <!-- OPENING BALANCES TABLE -->

    <div class="section-card">

        <h2 class="text-lg font-bold text-gray-800 mb-4">
            <i class="fas fa-users text-blue-700 mr-2"></i>
            Employee Opening Balances
        </h2>

        <div class="table-wrap">

            <table class="w-full text-sm text-left">

                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="p-3">#</th>
                        <th class="p-3">Employee</th>
                        <th class="p-3">Opening Balance</th>
                        <th class="p-3">Effective Date</th>
                        <th class="p-3">Notes</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (!$openingBalances): ?>

                        <tr>
                            <td colspan="5" class="p-5 text-center text-gray-500">
                                No opening balances have been recorded.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($openingBalances as $index => $balance): ?>

                            <tr class="border-b hover:bg-gray-50">

                                <td class="p-3">
                                    <?= $index + 1 ?>
                                </td>

                                <td class="p-3 font-semibold">
                                    <?= htmlspecialchars($balance['full_name']) ?>
                                </td>

                                <td class="p-3 font-semibold text-blue-800">
                                    <?= pensionMoney($balance['opening_balance']) ?>
                                </td>

                                <td class="p-3">
                                    <?= htmlspecialchars($balance['effective_date'] ?: '-') ?>
                                </td>

                                <td class="p-3">
                                    <?= htmlspecialchars($balance['notes'] ?: '-') ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- CONTRIBUTIONS TABLE -->

    <div class="section-card">

        <div class="flex flex-wrap justify-between items-center gap-3 mb-5">

            <div>
                <h2 class="text-lg font-bold text-gray-800">
                    <i class="fas fa-money-check-alt text-blue-700 mr-2"></i>
                    Monthly Pension Contributions
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    Contributions generated from saved payroll records.
                </p>
            </div>

        </div>

        <div class="table-wrap">

            <table class="w-full text-sm text-left">

                <thead class="bg-gray-100 text-gray-700">

                    <tr>
                        <th class="p-3">Employee</th>
                        <th class="p-3">Period</th>
                        <th class="p-3">Gross Pay</th>
                        <th class="p-3">Employee 6%</th>
                        <th class="p-3">Employer 6%</th>
                        <th class="p-3">Total</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Remittance</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (!$contributions): ?>

                        <tr>
                            <td colspan="8" class="p-6 text-center text-gray-500">
                                No pension contributions found for the selected filters.
                                Save an eligible payroll to generate contributions.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($contributions as $row): ?>

                            <tr class="border-b hover:bg-gray-50">

                                <td class="p-3 font-semibold">
                                    <?= htmlspecialchars($row['full_name']) ?>
                                </td>

                                <td class="p-3">
                                    <?= htmlspecialchars($row['month']) ?>
                                    <?= (int)$row['year'] ?>
                                </td>

                                <td class="p-3">
                                    <?= number_format((float)$row['gross_pay'], 2) ?>
                                </td>

                                <td class="p-3">
                                    <?= number_format((float)$row['employee_amount'], 2) ?>
                                </td>

                                <td class="p-3">
                                    <?= number_format((float)$row['employer_amount'], 2) ?>
                                </td>

                                <td class="p-3 font-bold text-blue-800">
                                    <?= number_format((float)$row['total_contribution'], 2) ?>
                                </td>

                                <td class="p-3">

                                    <?php if ($row['remittance_status'] === 'Paid'): ?>

                                        <span class="badge-paid px-3 py-1 rounded-full text-xs font-semibold">
                                            Paid
                                        </span>

                                    <?php else: ?>

                                        <span class="badge-pending px-3 py-1 rounded-full text-xs font-semibold">
                                            Pending
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="p-3">

                                    <?php if ($row['remittance_status'] === 'Paid'): ?>

                                        <div class="text-xs text-green-700 font-semibold">
                                            <?= htmlspecialchars($row['payment_date'] ?: '-') ?>
                                        </div>

                                        <div class="text-xs text-gray-500">
                                            Ref: <?= htmlspecialchars($row['payment_reference'] ?: '-') ?>
                                        </div>

                                    <?php else: ?>

                                        <button
                                            type="button"
                                            class="btn btn-success text-xs"
                                            onclick="openRemittanceModal(
                                                <?= (int)$row['id'] ?>,
                                                '<?= htmlspecialchars(addslashes($row['full_name']), ENT_QUOTES) ?>',
                                                '<?= number_format((float)$row['total_contribution'], 2, '.', '') ?>'
                                            )"
                                        >
                                            <i class="fas fa-check"></i>
                                            Mark Paid
                                        </button>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

                <?php if ($contributions): ?>

                    <tfoot class="bg-gray-100 font-bold">

                        <tr>
                            <td colspan="3" class="p-3 text-right">
                                Filtered Totals
                            </td>

                            <td class="p-3">
                                <?= number_format((float)$summary['employee_total'], 2) ?>
                            </td>

                            <td class="p-3">
                                <?= number_format((float)$summary['employer_total'], 2) ?>
                            </td>

                            <td class="p-3 text-blue-800">
                                <?= number_format((float)$summary['accrued_total'], 2) ?>
                            </td>

                            <td colspan="2"></td>
                        </tr>

                    </tfoot>

                <?php endif; ?>

            </table>

        </div>

    </div>

    <!-- REMITTANCE MODAL -->

    <div
        id="remittanceModal"
        class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4"
        style="z-index: 9999;"
    >

        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">

            <div class="flex justify-between items-center mb-4">

                <h3 class="text-lg font-bold text-gray-800">
                    Record Pension Remittance
                </h3>

                <button
                    type="button"
                    onclick="closeRemittanceModal()"
                    class="text-gray-500 hover:text-red-600 text-xl"
                >
                    &times;
                </button>

            </div>

            <form method="POST">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="mark_remitted">

                <input
                    type="hidden"
                    name="contribution_id"
                    id="modalContributionId"
                >

                <div class="bg-blue-50 p-3 rounded-lg mb-4">

                    <p class="text-xs text-gray-500">Employee</p>
                    <p id="modalEmployee" class="font-bold text-gray-800"></p>

                    <p class="text-xs text-gray-500 mt-3">Total Contribution</p>
                    <p id="modalAmount" class="font-bold text-blue-800 text-xl"></p>

                </div>

                <div class="mb-4">

                    <label class="block text-sm font-semibold mb-1">
                        Remittance Date
                    </label>

                    <input
                        type="date"
                        name="payment_date"
                        value="<?= date('Y-m-d') ?>"
                        class="form-control"
                        required
                    >

                </div>

                <div class="mb-5">

                    <label class="block text-sm font-semibold mb-1">
                        Payment Reference
                    </label>

                    <input
                        type="text"
                        name="payment_reference"
                        class="form-control"
                        placeholder="Bank reference / transaction code"
                        maxlength="150"
                        required
                    >

                </div>

                <div class="flex justify-end gap-2">

                    <button
                        type="button"
                        onclick="closeRemittanceModal()"
                        class="btn bg-gray-200 text-gray-800"
                    >
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i>
                        Confirm Remittance
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script>

function openRemittanceModal(id, employee, amount) {

    document.getElementById('modalContributionId').value = id;

    document.getElementById('modalEmployee').textContent = employee;

    document.getElementById('modalAmount').textContent =
        'KSh ' + Number(amount).toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

    const modal = document.getElementById('remittanceModal');

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeRemittanceModal() {

    const modal = document.getElementById('remittanceModal');

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

</script>

</body>
</html>
