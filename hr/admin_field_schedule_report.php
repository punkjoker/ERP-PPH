
<?php
session_start();

require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// --------------------------------------------------
// ACCESS CHECK
// --------------------------------------------------
if (
    !isset($_SESSION['user_id']) &&
    !isset($_SESSION['admin_id'])
) {
    header("Location: ../login.php");
    exit;
}

// --------------------------------------------------
// HELPERS
// --------------------------------------------------
function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function reportKeySql(): string
{
    return "COALESCE(
        NULLIF(MAX(NULLIF(fs.report_group_id, '')), ''),
        CONCAT('legacy-', MIN(fs.schedule_id))
    )";
}

// --------------------------------------------------
// FILTERS
// --------------------------------------------------
$today = date('Y-m-d');

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
$search   = trim($_GET['search'] ?? '');

$errors = [];

// Validate dates
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $errors[] = "Invalid start date.";
    $dateFrom = '';
}

if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $errors[] = "Invalid end date.";
    $dateTo = '';
}

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $errors[] = "The start date cannot be after the end date.";
}

// --------------------------------------------------
// FETCH GROUPED FIELD REPORTS
// --------------------------------------------------
$reports = [];
$totalReports = 0;

try {
    $where = [];
    $params = [];
    $types = '';

    if ($dateFrom !== '') {
        $where[] = "fs.visit_date >= ?";
        $params[] = $dateFrom;
        $types .= 's';
    }

    if ($dateTo !== '') {
        $where[] = "fs.visit_date <= ?";
        $params[] = $dateTo;
        $types .= 's';
    }

    if ($search !== '') {
        $where[] = "(
            CONCAT_WS(' ', e.first_name, e.last_name) LIKE ?
            OR e.national_id LIKE ?
            OR CAST(e.employee_id AS CHAR) LIKE ?
            OR CAST(u.employee_id AS CHAR) LIKE ?
        )";

        $term = '%' . $search . '%';

        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;

        $types .= 'ssss';
    }

    $whereSql = $where
        ? 'WHERE ' . implode(' AND ', $where)
        : '';

    /*
     * A report group can contain several company rows.
     * Older records without report_group_id are treated
     * as individual reports.
     */
    $groupExpression = "
        CASE
            WHEN NULLIF(fs.report_group_id, '') IS NOT NULL
                THEN CONCAT('group:', fs.report_group_id)
            ELSE CONCAT('legacy:', fs.schedule_id)
        END
    ";

    $sql = "
        SELECT
            {$groupExpression} AS report_key,
            MAX(fs.report_group_id) AS report_group_id,
            MIN(fs.schedule_id) AS first_schedule_id,
            fs.user_id,
            MAX(fs.visit_date) AS visit_date,

            u.employee_id,
            e.first_name,
            e.last_name,
            e.national_id,

            MAX(fs.visit_purpose) AS visit_purpose,
            COUNT(DISTINCT fs.schedule_id) AS company_count,

            GROUP_CONCAT(
                DISTINCT NULLIF(fs.payment_status, '')
                ORDER BY fs.payment_status
                SEPARATOR ', '
            ) AS payment_statuses,

            MAX(fs.created_at) AS created_at

        FROM field_schedules fs

        INNER JOIN users u
            ON u.user_id = fs.user_id

        INNER JOIN employees e
            ON e.employee_id = u.employee_id

        {$whereSql}

        GROUP BY
            {$groupExpression},
            fs.user_id,
            u.employee_id,
            e.first_name,
            e.last_name,
            e.national_id

        ORDER BY MAX(fs.visit_date) DESC,
                 MAX(fs.created_at) DESC
    ";

    $stmt = $conn->prepare($sql);

    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }

    $totalReports = count($reports);

    $stmt->close();

} catch (Throwable $e) {
    error_log("Admin field schedule report error: " . $e->getMessage());
    $errors[] = "Unable to load field reports. Check the database relationships and try again.";
}

// --------------------------------------------------
// STATUS DISPLAY
// --------------------------------------------------
function paymentBadgeClass($status): string
{
    $status = strtolower(trim((string)$status));

    if ($status === '') {
        return 'bg-gray-100 text-gray-700';
    }

    if (strpos($status, 'denied') !== false) {
        return 'bg-red-100 text-red-700';
    }

    if (strpos($status, 'paid') !== false) {
        return 'bg-green-100 text-green-700';
    }

    if (strpos($status, 'pending') !== false) {
        return 'bg-amber-100 text-amber-800';
    }

    return 'bg-blue-100 text-blue-700';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Field Schedule Reports</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: #f4f6f9;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .report-table th,
        .report-table td {
            padding: 13px 15px;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .report-table th {
            background: #f8fafc;
            color: #334155;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .report-table tbody tr:hover {
            background: #f8fafc;
        }
    </style>
</head>

<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-full mx-auto">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                Staff Field Schedule Reports
            </h1>

            <p class="text-gray-500 mt-1">
                View staff field reports, search employees, and filter reports by date.
            </p>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl px-5 py-3 shadow-sm">
            <p class="text-sm text-gray-500">Reports found</p>
            <p class="text-2xl font-bold text-gray-900">
                <?= number_format($totalReports) ?>
            </p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
            <?php foreach ($errors as $error): ?>
                <p><?= h($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- FILTERS -->
    <section class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6">

        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">
                    Search staff
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?= h($search) ?>"
                    placeholder="Name, national ID or employee ID"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
            </div>

            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">
                    From date
                </label>

                <input
                    type="date"
                    id="date_from"
                    name="date_from"
                    value="<?= h($dateFrom) ?>"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
            </div>

            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">
                    To date
                </label>

                <input
                    type="date"
                    id="date_to"
                    name="date_to"
                    value="<?= h($dateTo) ?>"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
            </div>

            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    class="flex-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 transition"
                >
                    Search
                </button>

                <a
                    href="admin_field_schedule_report.php"
                    class="rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-2.5 transition"
                >
                    Reset
                </a>
            </div>

        </form>
    </section>

    <!-- REPORT TABLE -->
    <section class="bg-white rounded-xl shadow-sm border border-gray-200">

        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="font-semibold text-gray-900">Field Reports</h2>
            <p class="text-sm text-gray-500 mt-1">
                Each row represents one daily report, even when several companies were visited.
            </p>
        </div>

        <div class="table-wrap">
            <table class="report-table w-full">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Staff Name</th>
                        
                        <th>National ID</th>
                        <th>Visit Date</th>
                        <th>Purpose</th>
                        <th>Companies</th>
                        <th>Payment Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($reports)): ?>

                    <tr>
                        <td colspan="9" class="text-center py-12 text-gray-500">
                            <div class="text-4xl mb-3">📋</div>
                            <p class="font-semibold">No field reports found</p>
                            <p class="text-sm mt-1">Try changing the date range or search term.</p>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($reports as $index => $report): ?>

                        <?php
                            $fullName = trim(
                                ($report['first_name'] ?? '') . ' ' .
                                ($report['last_name'] ?? '')
                            );

                            $rawGroupId = trim((string)($report['report_group_id'] ?? ''));

                            // Keep the legacy key format understood by the report pages.
                            $reportKey = $rawGroupId !== ''
                                ? $rawGroupId
                                : 'legacy-' . (int)$report['first_schedule_id'];

                            $paymentStatus = trim((string)($report['payment_statuses'] ?? ''));

                            if ($paymentStatus === '') {
                                $paymentStatus = 'Not set';
                            }
                        ?>

                        <tr class="border-t border-gray-100">

                            <td class="text-gray-500">
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <div class="font-semibold text-gray-900">
                                    <?= h($fullName !== '' ? $fullName : 'Unknown employee') ?>
                                </div>

                                <div class="text-xs text-gray-500 mt-1">
                                    User ID: <?= (int)$report['user_id'] ?>
                                </div>
                            </td>

                            

                            <td>
                                <?= h($report['national_id'] ?? '-') ?>
                            </td>

                            <td>
                                <?= h(
                                    !empty($report['visit_date'])
                                        ? date('d M Y', strtotime($report['visit_date']))
                                        : '-'
                                ) ?>
                            </td>

                            <td class="max-w-xs">
                                <div class="whitespace-normal min-w-[150px]">
                                    <?= h($report['visit_purpose'] ?? '-') ?>
                                </div>
                            </td>

                            <td>
                                <span class="inline-flex items-center rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-sm font-semibold">
                                    <?= (int)$report['company_count'] ?>
                                    <?= (int)$report['company_count'] === 1 ? 'company' : 'companies' ?>
                                </span>
                            </td>

                            <td>
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?= h(paymentBadgeClass($paymentStatus)) ?>">
                                    <?= h($paymentStatus) ?>
                                </span>
                            </td>

                            <td>
                                <div class="flex items-center gap-2">

                                    <a
                                        href="admin_view_field_report.php?group=<?= urlencode($reportKey) ?>&user_id=<?= (int)$report['user_id'] ?>"
                                        class="inline-flex items-center rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 text-sm font-semibold transition"
                                    >
                                        View
                                    </a>

                                    <a
                                        href="admin_edit_field_report.php?group=<?= urlencode($reportKey) ?>&user_id=<?= (int)$report['user_id'] ?>"
                                        class="inline-flex items-center rounded-lg bg-amber-500 hover:bg-amber-600 text-white px-3 py-2 text-sm font-semibold transition"
                                    >
                                        Edit
                                    </a>

                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>
        </div>

        <div class="border-t border-gray-200 px-5 py-4 text-sm text-gray-500">
            Showing <?= number_format($totalReports) ?> report<?= $totalReports === 1 ? '' : 's' ?>.
        </div>

    </section>

</main>

</body>
</html>
