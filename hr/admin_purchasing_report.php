
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");
date_default_timezone_set('Africa/Nairobi');

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| ADMIN ACCESS CONTROL
|--------------------------------------------------------------------------
| This verifies that the user is logged in.
| Add your existing administrator/HR role check here if required.
*/

if (empty($_SESSION['admin_purchasing_csrf'])) {
    $_SESSION['admin_purchasing_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_purchasing_csrf'];
$today = date('Y-m-d');

$statuses = [
    'Pending',
    'In Progress',
    'Completed',
    'On Hold',
    'Cancelled'
];

$activityTypes = ['Routine', 'Planned'];

$success = $_SESSION['admin_purchasing_success'] ?? '';
$error = $_SESSION['admin_purchasing_error'] ?? '';

unset(
    $_SESSION['admin_purchasing_success'],
    $_SESSION['admin_purchasing_error']
);

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function adminPurchasingH($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function adminPurchasingRedirect(
    string $message,
    string $type = 'success'
): void {
    $key = $type === 'error'
        ? 'admin_purchasing_error'
        : 'admin_purchasing_success';

    $_SESSION[$key] = $message;

    header("Location: admin_purchasing_report.php");
    exit;
}

function adminPurchasingValidDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed !== false
        && $parsed->format('Y-m-d') === $date;
}

function adminPurchasingGetReport(
    mysqli $conn,
    int $reportId
): ?array {
    $stmt = $conn->prepare("
        SELECT
            r.report_id,
            r.employee_id,
            r.report_date,
            r.created_at,
            r.updated_at,
            e.first_name,
            e.last_name,
            e.email,
            e.department,
            e.position
        FROM purchasing_daily_reports r
        INNER JOIN employees e
            ON e.employee_id = r.employee_id
        WHERE r.report_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $reportId);
    $stmt->execute();

    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $report ?: null;
}

function adminPurchasingGetActivities(
    mysqli $conn,
    int $reportId
): array {
    $stmt = $conn->prepare("
        SELECT
            activity_id,
            description,
            performance_indicators,
            outcome,
            activity_type,
            status
        FROM purchasing_daily_report_activities
        WHERE report_id = ?
        ORDER BY activity_id ASC
    ");

    $stmt->bind_param("i", $reportId);
    $stmt->execute();

    $result = $stmt->get_result();
    $activities = [];

    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }

    $stmt->close();

    return $activities;
}

/*
|--------------------------------------------------------------------------
| EXPORT PURCHASING REPORTS TO CSV / EXCEL
|--------------------------------------------------------------------------
*/

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $fromDate = trim($_GET['from_date'] ?? '');
    $toDate = trim($_GET['to_date'] ?? '');
    $exactDate = trim($_GET['report_date'] ?? '');

    if (
        ($fromDate !== '' && !adminPurchasingValidDate($fromDate)) ||
        ($toDate !== '' && !adminPurchasingValidDate($toDate)) ||
        ($exactDate !== '' && !adminPurchasingValidDate($exactDate))
    ) {
        http_response_code(400);
        exit('Invalid date filter.');
    }

    if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
        http_response_code(400);
        exit('The start date cannot be after the end date.');
    }

    $sql = "
        SELECT
            r.report_date,
            e.first_name,
            e.last_name,
            e.department,
            e.position,
            a.description,
            a.performance_indicators,
            a.outcome,
            a.activity_type,
            a.status
        FROM purchasing_daily_reports r
        INNER JOIN employees e
            ON e.employee_id = r.employee_id
        LEFT JOIN purchasing_daily_report_activities a
            ON a.report_id = r.report_id
        WHERE 1 = 1
    ";

    $types = '';
    $params = [];

    if ($exactDate !== '') {
        $sql .= " AND r.report_date = ?";
        $types .= 's';
        $params[] = $exactDate;
    } else {
        if ($fromDate !== '') {
            $sql .= " AND r.report_date >= ?";
            $types .= 's';
            $params[] = $fromDate;
        }

        if ($toDate !== '') {
            $sql .= " AND r.report_date <= ?";
            $types .= 's';
            $params[] = $toDate;
        }
    }

    $sql .= "
        ORDER BY
            r.report_date DESC,
            e.first_name ASC,
            r.report_id ASC,
            a.activity_id ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $filename = 'purchasing_daily_reports_' .
        date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM helps Excel display special characters correctly.
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    fputcsv($output, [
        'Reporting Date',
        'Staff Name',
        'Department',
        'Position',
        'Activity Description',
        'Performance Indicators',
        'Outcome',
        'Activity Type',
        'Activity Status'
    ]);

    $safeCell = static function ($value): string {
        $value = (string) ($value ?? '');

        // Prevent spreadsheet formula injection.
        if (preg_match('/^[\s]*[=+\-@]/u', $value)) {
            return "'" . $value;
        }

        return $value;
    };

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $safeCell($row['report_date']),
            $safeCell(trim(
                $row['first_name'] . ' ' . $row['last_name']
            )),
            $safeCell($row['department']),
            $safeCell($row['position']),
            $safeCell($row['description']),
            $safeCell($row['performance_indicators']),
            $safeCell($row['outcome']),
            $safeCell($row['activity_type']),
            $safeCell($row['status'])
        ]);
    }

    fclose($output);
    $stmt->close();
    exit;
}

/*
|--------------------------------------------------------------------------
| SAVE ADMIN EDITS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'update_report'
) {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken)
        || !hash_equals($csrfToken, $submittedToken)
    ) {
        adminPurchasingRedirect(
            "Invalid session token. Refresh the page and try again.",
            'error'
        );
    }

    $reportId = filter_var(
        $_POST['report_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $reportDate = trim($_POST['report_date'] ?? '');

    if (!$reportId || $reportId <= 0) {
        adminPurchasingRedirect("Invalid report ID.", 'error');
    }

    if (!adminPurchasingValidDate($reportDate)) {
        adminPurchasingRedirect(
            "Please select a valid report date.",
            'error'
        );
    }

    if ($reportDate > $today) {
        adminPurchasingRedirect(
            "The reporting date cannot be in the future.",
            'error'
        );
    }

    $existingReport = adminPurchasingGetReport(
        $conn,
        (int) $reportId
    );

    if (!$existingReport) {
        adminPurchasingRedirect("Purchasing report not found.", 'error');
    }

    $descriptions = $_POST['description'] ?? [];
    $indicators = $_POST['performance_indicators'] ?? [];
    $outcomes = $_POST['outcome'] ?? [];
    $types = $_POST['activity_type'] ?? [];
    $activityStatuses = $_POST['activity_status'] ?? [];

    if (
        !is_array($descriptions)
        || !is_array($indicators)
        || !is_array($outcomes)
        || !is_array($types)
        || !is_array($activityStatuses)
    ) {
        adminPurchasingRedirect(
            "Invalid activity information.",
            'error'
        );
    }

    $count = count($descriptions);

    if (
        $count < 1
        || $count > 100
        || count($indicators) !== $count
        || count($outcomes) !== $count
        || count($types) !== $count
        || count($activityStatuses) !== $count
    ) {
        adminPurchasingRedirect(
            "A report must contain between 1 and 100 valid activities.",
            'error'
        );
    }

    $activities = [];

    for ($i = 0; $i < $count; $i++) {
        foreach (
            [
                $descriptions[$i],
                $indicators[$i],
                $outcomes[$i],
                $types[$i],
                $activityStatuses[$i]
            ] as $value
        ) {
            if (!is_string($value)) {
                adminPurchasingRedirect(
                    "Invalid activity field.",
                    'error'
                );
            }
        }

        $description = trim($descriptions[$i]);
        $indicator = trim($indicators[$i]);
        $outcome = trim($outcomes[$i]);
        $type = trim($types[$i]);
        $status = trim($activityStatuses[$i]);

        if ($description === '') {
            adminPurchasingRedirect(
                "Every activity must have a description.",
                'error'
            );
        }

        if (
            !in_array($type, $activityTypes, true)
            || !in_array($status, $statuses, true)
        ) {
            adminPurchasingRedirect(
                "An activity has an invalid type or status.",
                'error'
            );
        }

        $activities[] = [
            'description' => $description,
            'indicators' => $indicator,
            'outcome' => $outcome,
            'type' => $type,
            'status' => $status
        ];
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("
            UPDATE purchasing_daily_reports
            SET report_date = ?
            WHERE report_id = ?
        ");

        $stmt->bind_param("si", $reportDate, $reportId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            DELETE FROM purchasing_daily_report_activities
            WHERE report_id = ?
        ");

        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO purchasing_daily_report_activities (
                report_id,
                description,
                performance_indicators,
                outcome,
                activity_type,
                status
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($activities as $activity) {
            $stmt->bind_param(
                "isssss",
                $reportId,
                $activity['description'],
                $activity['indicators'],
                $activity['outcome'],
                $activity['type'],
                $activity['status']
            );

            $stmt->execute();
        }

        $stmt->close();
        $conn->commit();

        adminPurchasingRedirect(
            "Purchasing report updated successfully."
        );

    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log(
                "Purchasing report rollback error: " .
                $rollbackError->getMessage()
            );
        }

        error_log(
            "Admin purchasing report update: " . $e->getMessage()
        );

        if (
            $e instanceof mysqli_sql_exception
            && (int) $e->getCode() === 1062
        ) {
            adminPurchasingRedirect(
                "Another report already exists for that staff member "
                . "and date. Choose a different date.",
                'error'
            );
        }

        adminPurchasingRedirect(
            "Unable to update the report. Please check the information "
            . "and try again.",
            'error'
        );
    }
}

/*
|--------------------------------------------------------------------------
| DATE FILTERS
|--------------------------------------------------------------------------
*/

$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');
$selectedDate = trim($_GET['report_date'] ?? '');

$filterError = '';

if ($fromDate !== '' && !adminPurchasingValidDate($fromDate)) {
    $filterError = "Invalid start date.";
    $fromDate = '';
}

if ($toDate !== '' && !adminPurchasingValidDate($toDate)) {
    $filterError = "Invalid end date.";
    $toDate = '';
}

if (
    $selectedDate !== ''
    && !adminPurchasingValidDate($selectedDate)
) {
    $filterError = "Invalid reporting date.";
    $selectedDate = '';
}

if (
    $fromDate !== ''
    && $toDate !== ''
    && $fromDate > $toDate
) {
    $filterError = "The start date cannot be after the end date.";
}

/*
|--------------------------------------------------------------------------
| VIEW OR EDIT SELECTION
|--------------------------------------------------------------------------
*/

$mode = $_GET['mode'] ?? 'list';

$reportId = filter_var(
    $_GET['report_id'] ?? '',
    FILTER_VALIDATE_INT
);

$selectedReport = null;
$selectedActivities = [];

if (
    in_array($mode, ['view', 'edit'], true)
    && $reportId
    && $reportId > 0
) {
    $selectedReport = adminPurchasingGetReport(
        $conn,
        (int) $reportId
    );

    if (!$selectedReport) {
        $error = "The selected purchasing report was not found.";
        $mode = 'list';
    } else {
        $selectedActivities = adminPurchasingGetActivities(
            $conn,
            (int) $reportId
        );
    }
} else {
    $mode = 'list';
}

/*
|--------------------------------------------------------------------------
| SUMMARY CARDS
|--------------------------------------------------------------------------
*/

$summary = [
    'reports' => 0,
    'activities' => 0,
    'staff' => 0,
    'completed' => 0
];

$summaryResult = $conn->query("
    SELECT
        COUNT(DISTINCT r.report_id) AS total_reports,
        COUNT(a.activity_id) AS total_activities,
        COUNT(DISTINCT r.employee_id) AS total_staff,
        COALESCE(SUM(a.status = 'Completed'), 0) AS total_completed
    FROM purchasing_daily_reports r
    LEFT JOIN purchasing_daily_report_activities a
        ON a.report_id = r.report_id
");

$summaryRow = $summaryResult->fetch_assoc();

$summary = [
    'reports' => (int) ($summaryRow['total_reports'] ?? 0),
    'activities' => (int) ($summaryRow['total_activities'] ?? 0),
    'staff' => (int) ($summaryRow['total_staff'] ?? 0),
    'completed' => (int) ($summaryRow['total_completed'] ?? 0)
];

/*
|--------------------------------------------------------------------------
| FETCH PURCHASING REPORT LIST
|--------------------------------------------------------------------------
*/

$reports = [];

if ($filterError === '') {
    $sql = "
        SELECT
            r.report_id,
            r.report_date,
            r.created_at,
            r.updated_at,
            e.employee_id,
            e.first_name,
            e.last_name,
            e.department,
            COUNT(a.activity_id) AS activity_count,
            COALESCE(SUM(a.status = 'Completed'), 0) AS completed_count,
            COALESCE(SUM(a.status = 'Pending'), 0) AS pending_count,
            COALESCE(SUM(a.status = 'In Progress'), 0) AS progress_count
        FROM purchasing_daily_reports r
        INNER JOIN employees e
            ON e.employee_id = r.employee_id
        LEFT JOIN purchasing_daily_report_activities a
            ON a.report_id = r.report_id
        WHERE 1 = 1
    ";

    $params = [];
    $types = '';

    if ($fromDate !== '') {
        $sql .= " AND r.report_date >= ?";
        $params[] = $fromDate;
        $types .= 's';
    }

    if ($toDate !== '') {
        $sql .= " AND r.report_date <= ?";
        $params[] = $toDate;
        $types .= 's';
    }

    if ($selectedDate !== '') {
        $sql .= " AND r.report_date = ?";
        $params[] = $selectedDate;
        $types .= 's';
    }

    $sql .= "
        GROUP BY
            r.report_id,
            r.report_date,
            r.created_at,
            r.updated_at,
            e.employee_id,
            e.first_name,
            e.last_name,
            e.department
        ORDER BY
            r.report_date DESC,
            e.first_name ASC,
            e.last_name ASC
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

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Purchasing Reports | Lynntech Manager</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .report-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .report-input {
            width: 100%;
            padding: 9px 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background: white;
        }

        .report-input:focus {
            outline: 2px solid #d4a017;
            outline-offset: 1px;
        }

        .gold-button {
            background: #b8860b;
            color: white;
        }

        .gold-button:hover {
            background: #966f09;
        }

        .stat-number {
            font-size: 25px;
            font-weight: 700;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-col md:flex-row md:items-center
                md:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                Admin Purchasing Reports
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Review and manage purchasing reports submitted by staff.
            </p>
        </div>

        <a href="admin_purchasing_report.php"
           class="gold-button px-4 py-2 rounded-lg text-center font-semibold">
            All Reports
        </a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200
                    text-green-800 p-4">
            <?= adminPurchasingH($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200
                    text-red-800 p-4">
            <?= adminPurchasingH($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($filterError !== ''): ?>
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200
                    text-red-800 p-4">
            <?= adminPurchasingH($filterError) ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard summary -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

        <div class="report-card p-5">
            <p class="text-sm text-gray-500">Total Reports</p>
            <p class="stat-number text-gray-900">
                <?= $summary['reports'] ?>
            </p>
        </div>

        <div class="report-card p-5">
            <p class="text-sm text-gray-500">Total Activities</p>
            <p class="stat-number text-blue-700">
                <?= $summary['activities'] ?>
            </p>
        </div>

        <div class="report-card p-5">
            <p class="text-sm text-gray-500">Staff Who Submitted</p>
            <p class="stat-number text-purple-700">
                <?= $summary['staff'] ?>
            </p>
        </div>

        <div class="report-card p-5">
            <p class="text-sm text-gray-500">Completed Activities</p>
            <p class="stat-number text-green-700">
                <?= $summary['completed'] ?>
            </p>
        </div>

    </div>

    <?php if ($mode === 'view' && $selectedReport): ?>

        <!-- View purchasing report -->
        <div class="report-card p-5 mb-6">

            <div class="flex flex-col sm:flex-row sm:justify-between
                        sm:items-start gap-4 mb-5">
                <div>
                    <h2 class="text-xl font-bold">
                        Purchasing Report Details
                    </h2>

                    <p class="text-sm text-gray-500 mt-2">
                        Staff:
                        <strong>
                            <?= adminPurchasingH(
                                $selectedReport['first_name'] . ' '
                                . $selectedReport['last_name']
                            ) ?>
                        </strong>
                    </p>

                    <p class="text-sm text-gray-500">
                        Date:
                        <strong>
                            <?= adminPurchasingH(
                                $selectedReport['report_date']
                            ) ?>
                        </strong>
                    </p>

                    <?php if (!empty($selectedReport['department'])): ?>
                        <p class="text-sm text-gray-500">
                            Department:
                            <?= adminPurchasingH(
                                $selectedReport['department']
                            ) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <a href="admin_purchasing_report.php?mode=edit&amp;report_id=<?= (int) $selectedReport['report_id'] ?>"
                   class="bg-blue-600 hover:bg-blue-700 text-white
                          px-4 py-2 rounded-lg text-center">
                    Edit Report
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm border-collapse">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border p-3 text-left">#</th>
                            <th class="border p-3 text-left">Description</th>
                            <th class="border p-3 text-left">
                                Indicators of Performance
                            </th>
                            <th class="border p-3 text-left">Outcome</th>
                            <th class="border p-3 text-left">Routine / Planned</th>
                            <th class="border p-3 text-left">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$selectedActivities): ?>
                            <tr>
                                <td colspan="6"
                                    class="border p-5 text-center text-gray-500">
                                    No activities recorded for this report.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($selectedActivities as $i => $activity): ?>
                                <tr class="align-top hover:bg-gray-50">
                                    <td class="border p-3">
                                        <?= $i + 1 ?>
                                    </td>

                                    <td class="border p-3 whitespace-pre-wrap"><?= adminPurchasingH(
                                        $activity['description']
                                    ) ?></td>

                                    <td class="border p-3 whitespace-pre-wrap"><?= adminPurchasingH(
                                        $activity['performance_indicators']
                                    ) ?></td>

                                    <td class="border p-3 whitespace-pre-wrap"><?= adminPurchasingH(
                                        $activity['outcome']
                                    ) ?></td>

                                    <td class="border p-3"><?= adminPurchasingH(
                                        $activity['activity_type']
                                    ) ?></td>

                                    <td class="border p-3"><?= adminPurchasingH(
                                        $activity['status']
                                    ) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($mode === 'edit' && $selectedReport): ?>

        <!-- Edit purchasing report -->
        <div class="report-card p-5 mb-6">

            <h2 class="text-xl font-bold mb-2">
                Edit Staff Purchasing Report
            </h2>

            <p class="text-sm text-gray-500 mb-5">
                Editing report submitted by
                <strong>
                    <?= adminPurchasingH(
                        $selectedReport['first_name'] . ' '
                        . $selectedReport['last_name']
                    ) ?>
                </strong>
            </p>

            <form method="POST"
                  action="admin_purchasing_report.php"
                  id="adminEditForm">

                <input type="hidden" name="action" value="update_report">

                <input type="hidden" name="csrf_token"
                       value="<?= adminPurchasingH($csrfToken) ?>">

                <input type="hidden" name="report_id"
                       value="<?= (int) $selectedReport['report_id'] ?>">

                <div class="max-w-sm mb-6">
                    <label class="block text-sm font-semibold mb-1">
                        Reporting Date *
                    </label>

                    <input type="date"
                           name="report_date"
                           class="report-input"
                           max="<?= adminPurchasingH($today) ?>"
                           required
                           value="<?= adminPurchasingH(
                               $selectedReport['report_date']
                           ) ?>">
                </div>

                <div class="flex justify-between items-center gap-3 mb-4">
                    <h3 class="font-bold">Activities</h3>

                    <button type="button"
                            id="adminAddActivity"
                            class="border border-green-600 text-green-700
                                   hover:bg-green-50 px-4 py-2 rounded-lg">
                        + Add Activity
                    </button>
                </div>

                <div id="adminActivities" class="space-y-4">

                    <?php
                    $editActivities = $selectedActivities ?: [[
                        'description' => '',
                        'performance_indicators' => '',
                        'outcome' => '',
                        'activity_type' => 'Routine',
                        'status' => 'Pending'
                    ]];
                    ?>

                    <?php foreach ($editActivities as $i => $activity): ?>
                        <div class="border border-gray-200 rounded-lg
                                    p-4 bg-gray-50 admin-activity">

                            <div class="flex justify-between items-center mb-4">
                                <h4 class="font-semibold admin-activity-title">
                                    Activity <?= $i + 1 ?>
                                </h4>

                                <button type="button"
                                        class="admin-remove-activity text-red-600
                                               font-semibold text-sm">
                                    Remove
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                <div>
                                    <label class="block text-sm font-semibold mb-1">
                                        Description *
                                    </label>

                                    <textarea name="description[]"
                                              rows="3"
                                              required
                                              class="report-input"><?= adminPurchasingH(
                                                  $activity['description']
                                              ) ?></textarea>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold mb-1">
                                        Indicators of Performance
                                    </label>

                                    <textarea name="performance_indicators[]"
                                              rows="3"
                                              class="report-input"><?= adminPurchasingH(
                                                  $activity['performance_indicators']
                                              ) ?></textarea>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold mb-1">
                                        Outcome
                                    </label>

                                    <textarea name="outcome[]"
                                              rows="3"
                                              class="report-input"><?= adminPurchasingH(
                                                  $activity['outcome']
                                              ) ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                                    <div>
                                        <label class="block text-sm font-semibold mb-1">
                                            Routine / Planned *
                                        </label>

                                        <select name="activity_type[]"
                                                required
                                                class="report-input">
                                            <?php foreach ($activityTypes as $type): ?>
                                                <option
                                                    value="<?= adminPurchasingH($type) ?>"
                                                    <?= $activity['activity_type'] === $type
                                                        ? 'selected' : '' ?>>
                                                    <?= adminPurchasingH($type) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold mb-1">
                                            Status *
                                        </label>

                                        <select name="activity_status[]"
                                                required
                                                class="report-input">
                                            <?php foreach ($statuses as $status): ?>
                                                <option
                                                    value="<?= adminPurchasingH($status) ?>"
                                                    <?= $activity['status'] === $status
                                                        ? 'selected' : '' ?>>
                                                    <?= adminPurchasingH($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

                <div class="flex flex-wrap gap-3 mt-6 pt-5 border-t">

                    <button type="submit"
                            class="gold-button px-6 py-2.5 rounded-lg font-semibold">
                        Save Changes
                    </button>

                    <a href="admin_purchasing_report.php"
                       class="border border-gray-300 bg-white px-6 py-2.5
                              rounded-lg">
                        Cancel
                    </a>

                </div>
            </form>
        </div>

    <?php endif; ?>

    <!-- Date filter -->
    <div class="report-card p-5 mb-6">

        <h2 class="text-lg font-bold mb-4">
            Filter Purchasing Reports by Date
        </h2>

        <form method="GET"
              action="admin_purchasing_report.php"
              class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4
                     gap-4 items-end">

            <div>
                <label class="block text-sm font-semibold mb-1">
                    From Date
                </label>

                <input type="date"
                       name="from_date"
                       class="report-input"
                       value="<?= adminPurchasingH($fromDate) ?>">
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">
                    To Date
                </label>

                <input type="date"
                       name="to_date"
                       class="report-input"
                       value="<?= adminPurchasingH($toDate) ?>">
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">
                    Exact Reporting Date
                </label>

                <input type="date"
                       name="report_date"
                       class="report-input"
                       value="<?= adminPurchasingH($selectedDate) ?>">
            </div>

            <div class="flex flex-wrap gap-2">

                <button type="submit"
                        class="gold-button px-5 py-2.5 rounded-lg font-semibold">
                    Filter
                </button>

                <a href="admin_purchasing_report.php"
                   class="border border-gray-300 bg-white px-5 py-2.5
                          rounded-lg text-center">
                    Reset
                </a>

                <a
                    href="admin_purchasing_report.php?export=excel&amp;from_date=<?= urlencode($fromDate) ?>&amp;to_date=<?= urlencode($toDate) ?>&amp;report_date=<?= urlencode($selectedDate) ?>"
                    class="inline-flex items-center gap-2 bg-green-600
                           hover:bg-green-700 text-white px-4 py-2
                           rounded-lg font-semibold">
                    Download Excel
                </a>

            </div>
        </form>

        <p class="text-xs text-gray-500 mt-3">
            Use the date range to find purchasing reports over a period,
            or select an exact date to show only reports submitted for that day.
        </p>
    </div>

    <!-- Purchasing reports table -->
    <div class="report-card p-5">

        <div class="flex flex-col sm:flex-row sm:justify-between
                    sm:items-center gap-3 mb-5">

            <div>
                <h2 class="text-lg font-bold">
                    Staff Purchasing Reports
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    <?= count($reports) ?> report(s) found for the selected filters.
                </p>
            </div>

            <input type="search"
                   id="adminReportSearch"
                   class="report-input sm:max-w-xs"
                   placeholder="Search staff or department...">
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border-collapse">

                <thead class="bg-gray-100">
                    <tr>
                        <th class="border p-3 text-left">#</th>
                        <th class="border p-3 text-left">Staff Name</th>
                        <th class="border p-3 text-left">Department</th>
                        <th class="border p-3 text-left">Reporting Date</th>
                        <th class="border p-3 text-center">Activities</th>
                        <th class="border p-3 text-center">Completed</th>
                        <th class="border p-3 text-left">Submitted / Updated</th>
                        <th class="border p-3 text-center">Actions</th>
                    </tr>
                </thead>

                <tbody id="adminReportsBody">

                    <?php if (!$reports): ?>
                        <tr>
                            <td colspan="8"
                                class="border p-6 text-center text-gray-500">
                                No purchasing reports found for the selected date.
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($reports as $i => $report): ?>
                            <tr class="admin-report-row hover:bg-gray-50"
                                data-search="<?= adminPurchasingH(
                                    $report['first_name'] . ' '
                                    . $report['last_name'] . ' '
                                    . ($report['department'] ?? '') . ' '
                                    . $report['report_date']
                                ) ?>">

                                <td class="border p-3">
                                    <?= $i + 1 ?>
                                </td>

                                <td class="border p-3 font-semibold">
                                    <?= adminPurchasingH(
                                        $report['first_name'] . ' '
                                        . $report['last_name']
                                    ) ?>
                                </td>

                                <td class="border p-3">
                                    <?= adminPurchasingH(
                                        $report['department'] ?? '—'
                                    ) ?>
                                </td>

                                <td class="border p-3">
                                    <?= adminPurchasingH($report['report_date']) ?>
                                </td>

                                <td class="border p-3 text-center">
                                    <?= (int) $report['activity_count'] ?>
                                </td>

                                <td class="border p-3 text-center text-green-700">
                                    <?= (int) $report['completed_count'] ?>
                                </td>

                                <td class="border p-3">
                                    <?= adminPurchasingH(
                                        $report['updated_at']
                                    ) ?>
                                </td>

                                <td class="border p-3">
                                    <div class="flex justify-center gap-3">

                                        <a
                                            href="admin_purchasing_report.php?mode=view&amp;report_id=<?= (int) $report['report_id'] ?>"
                                            class="text-blue-700 hover:underline font-semibold">
                                            View
                                        </a>

                                        <a
                                            href="admin_purchasing_report.php?mode=edit&amp;report_id=<?= (int) $report['report_id'] ?>"
                                            class="text-amber-700 hover:underline font-semibold">
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
    </div>

    <p class="text-xs text-gray-500 mt-5">
        Lynntech Manager | Purchasing Reports
    </p>

</div>

<script>
(function () {
    const search = document.getElementById('adminReportSearch');

    if (search) {
        search.addEventListener('input', function () {
            const query = search.value.toLowerCase().trim();

            document.querySelectorAll('.admin-report-row').forEach(function (row) {
                const content = (
                    (row.dataset.search || '') + ' ' + row.textContent
                ).toLowerCase();

                row.style.display = content.includes(query) ? '' : 'none';
            });
        });
    }

    const activities = document.getElementById('adminActivities');
    const addButton = document.getElementById('adminAddActivity');
    const maxActivities = 100;

    function updateActivityButtons() {
        if (!activities) return;

        const rows = activities.querySelectorAll('.admin-activity');

        rows.forEach(function (row, index) {
            const title = row.querySelector('.admin-activity-title');
            const remove = row.querySelector('.admin-remove-activity');

            if (title) {
                title.textContent = 'Activity ' + (index + 1);
            }

            if (remove) {
                remove.disabled = rows.length === 1;
                remove.classList.toggle('opacity-40', rows.length === 1);
            }
        });

        if (addButton) {
            addButton.disabled = rows.length >= maxActivities;
            addButton.classList.toggle(
                'opacity-40',
                rows.length >= maxActivities
            );
        }
    }

    function createActivity() {
        const row = document.createElement('div');

        row.className =
            'border border-gray-200 rounded-lg p-4 bg-gray-50 admin-activity';

        row.innerHTML = `
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-semibold admin-activity-title">Activity</h4>

                <button type="button"
                    class="admin-remove-activity text-red-600 font-semibold text-sm">
                    Remove
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">
                        Description *
                    </label>

                    <textarea name="description[]" rows="3" required
                        class="report-input"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">
                        Indicators of Performance
                    </label>

                    <textarea name="performance_indicators[]" rows="3"
                        class="report-input"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">
                        Outcome
                    </label>

                    <textarea name="outcome[]" rows="3"
                        class="report-input"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Routine / Planned *
                        </label>

                        <select name="activity_type[]" required class="report-input">
                            <option value="Routine">Routine</option>
                            <option value="Planned">Planned</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Status *
                        </label>

                        <select name="activity_status[]" required class="report-input">
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="On Hold">On Hold</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>
        `;

        return row;
    }

    if (activities && addButton) {
        addButton.addEventListener('click', function () {
            if (
                activities.querySelectorAll('.admin-activity').length
                >= maxActivities
            ) {
                alert('A report can contain a maximum of 100 activities.');
                return;
            }

            activities.appendChild(createActivity());
            updateActivityButtons();

            const description = activities.lastElementChild.querySelector(
                '[name="description[]"]'
            );

            if (description) {
                description.focus();
            }
        });

        activities.addEventListener('click', function (event) {
            const remove = event.target.closest('.admin-remove-activity');

            if (!remove || remove.disabled) return;

            if (
                activities.querySelectorAll('.admin-activity').length <= 1
            ) {
                return;
            }

            remove.closest('.admin-activity').remove();
            updateActivityButtons();
        });

        updateActivityButtons();
    }
})();
</script>

</body>
</html>
