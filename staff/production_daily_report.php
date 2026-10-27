
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

if (empty($_SESSION['production_report_csrf'])) {
    $_SESSION['production_report_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['production_report_csrf'];

$success = '';
$error = '';
$today = date('Y-m-d');

$statuses = [
    'Pending',
    'In Progress',
    'Completed',
    'On Hold',
    'Cancelled'
];

$activityTypes = ['Routine', 'Planned'];

/*
|--------------------------------------------------------------------------
| Database helpers
|--------------------------------------------------------------------------
*/

function productionTableColumns(mysqli $conn, string $table): array
{
    // Only internal, hard-coded table names should be passed here.
    $allowedTables = ['users', 'employees'];

    if (!in_array($table, $allowedTables, true)) {
        throw new Exception("Invalid table.");
    }

    $result = $conn->query("SHOW COLUMNS FROM `$table`");
    $columns = [];

    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    return $columns;
}

function productionGetEmployeeId(
    mysqli $conn,
    int $userId
): int {
    $userColumns = productionTableColumns($conn, 'users');
    $employeeColumns = productionTableColumns($conn, 'employees');

    $userPk = null;

    foreach (['user_id', 'id'] as $candidate) {
        if (in_array($candidate, $userColumns, true)) {
            $userPk = $candidate;
            break;
        }
    }

    if ($userPk === null) {
        throw new Exception(
            "Could not identify the primary key in the users table."
        );
    }

    // Preferred relationship: users.employee_id -> employees.employee_id
    if (in_array('employee_id', $userColumns, true)) {
        $sql = "SELECT u.employee_id
                FROM users u
                INNER JOIN employees e
                    ON e.employee_id = u.employee_id
                WHERE u.`$userPk` = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['employee_id'])) {
            throw new Exception(
                "Your user account is not linked to an employee record. "
                . "Please contact the administrator."
            );
        }

        return (int) $row['employee_id'];
    }

    // Alternative relationship: match users.email to employees.email.
    if (
        in_array('email', $userColumns, true) &&
        in_array('email', $employeeColumns, true)
    ) {
        $sql = "SELECT e.employee_id
                FROM users u
                INNER JOIN employees e
                    ON LOWER(TRIM(e.email)) = LOWER(TRIM(u.email))
                WHERE u.`$userPk` = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception(
                "No employee record matches your login email."
            );
        }

        return (int) $row['employee_id'];
    }

    throw new Exception(
        "The employee relationship could not be identified. "
        . "Link users to employees using employee_id or a matching email."
    );
}

function productionValidDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed !== false
        && $parsed->format('Y-m-d') === $date;
}

function productionRedirect(string $message, string $type = 'success'): void
{
    $key = $type === 'error'
        ? 'production_report_error'
        : 'production_report_success';

    $_SESSION[$key] = $message;

    header("Location: production_daily_report.php");
    exit;
}

function productionGetOwnedReport(
    mysqli $conn,
    int $reportId,
    int $employeeId
): ?array {
    $stmt = $conn->prepare("
        SELECT r.report_id, r.employee_id, r.report_date,
               e.first_name, e.last_name
        FROM production_daily_reports r
        INNER JOIN employees e
            ON e.employee_id = r.employee_id
        WHERE r.report_id = ?
          AND r.employee_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $reportId, $employeeId);
    $stmt->execute();

    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $report ?: null;
}

function productionGetActivities(
    mysqli $conn,
    int $reportId
): array {
    $stmt = $conn->prepare("
        SELECT activity_id, description,
               performance_indicators, outcome,
               activity_type, status
        FROM production_daily_report_activities
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

function productionExcelCell(string $value): string
{
    // Prevent spreadsheet formula injection in exported text.
    if (preg_match('/^[\s]*[=+\-@]/u', $value)) {
        $value = "'" . $value;
    }

    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Resolve logged-in staff member
|--------------------------------------------------------------------------
*/

try {
    $employeeId = productionGetEmployeeId($conn, $userId);
} catch (Throwable $e) {
    error_log("Production report employee lookup: " . $e->getMessage());

    http_response_code(403);
    exit(
        "Your login account could not be linked to an employee record. "
        . "Please contact the administrator to check the users/employees "
        . "relationship."
    );
}

/*
|--------------------------------------------------------------------------
| Process requests
|--------------------------------------------------------------------------
*/

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/*
|--------------------------------------------------------------------------
| Excel export
|--------------------------------------------------------------------------
*/

if ($action === 'export') {
    $reportId = filter_var(
        $_GET['report_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    if (!$reportId || $reportId <= 0) {
        http_response_code(400);
        exit("Invalid report.");
    }

    $report = productionGetOwnedReport(
        $conn,
        $reportId,
        $employeeId
    );

    if (!$report) {
        http_response_code(404);
        exit("Report not found.");
    }

    $activities = productionGetActivities($conn, $reportId);

    $filename = 'Production_Daily_Report_'
        . $report['report_date'] . '.xls';

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header(
        'Content-Disposition: attachment; filename="'
        . $filename . '"'
    );
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");

    echo "\xEF\xBB\xBF";
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            table {
                border-collapse: collapse;
                width: 100%;
            }
            th, td {
                border: 1px solid #555;
                padding: 8px;
                vertical-align: top;
            }
            th {
                background: #d9e8d5;
                font-weight: bold;
            }
            .heading {
                font-size: 16px;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <table>
            <tr>
                <td colspan="6" class="heading">
                    LYNNTECH MANAGER - PRODUCTION DAILY REPORT
                </td>
            </tr>
            <tr>
                <td colspan="2"><strong>Reporting Date</strong></td>
                <td colspan="4">
                    <?= productionExcelCell($report['report_date']) ?>
                </td>
            </tr>
            <tr>
                <td colspan="2"><strong>Staff Member</strong></td>
                <td colspan="4">
                    <?= productionExcelCell(
                        $report['first_name'] . ' ' . $report['last_name']
                    ) ?>
                </td>
            </tr>
            <tr>
                <th>No.</th>
                <th>Description</th>
                <th>Indicators of Performance</th>
                <th>Outcome</th>
                <th>Routine / Planned</th>
                <th>Status</th>
            </tr>
            <?php foreach ($activities as $index => $activity): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= nl2br(productionExcelCell(
                        $activity['description']
                    )) ?></td>
                    <td><?= nl2br(productionExcelCell(
                        $activity['performance_indicators'] ?? ''
                    )) ?></td>
                    <td><?= nl2br(productionExcelCell(
                        $activity['outcome'] ?? ''
                    )) ?></td>
                    <td><?= productionExcelCell(
                        $activity['activity_type']
                    ) ?></td>
                    <td><?= productionExcelCell(
                        $activity['status']
                    ) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </body>
    </html>
    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| Save new report or update existing report
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        productionRedirect(
            "Your session token is invalid. Refresh the page and try again.",
            'error'
        );
    }

    $reportDate = trim($_POST['report_date'] ?? '');

    $reportId = filter_var(
        $_POST['report_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $reportId = $reportId ? (int) $reportId : 0;

    if (!productionValidDate($reportDate)) {
        productionRedirect("Please select a valid reporting date.", 'error');
    }

    if ($reportDate > $today) {
        productionRedirect(
            "The reporting date cannot be in the future.",
            'error'
        );
    }

    $descriptions = $_POST['description'] ?? [];
    $indicators = $_POST['performance_indicators'] ?? [];
    $outcomes = $_POST['outcome'] ?? [];
    $types = $_POST['activity_type'] ?? [];
    $activityStatuses = $_POST['activity_status'] ?? [];

    if (
        !is_array($descriptions) ||
        !is_array($indicators) ||
        !is_array($outcomes) ||
        !is_array($types) ||
        !is_array($activityStatuses)
    ) {
        productionRedirect("Invalid activity information.", 'error');
    }

    $activities = [];

    $rowCount = count($descriptions);

    if (
        $rowCount < 1 ||
        $rowCount > 100 ||
        count($indicators) !== $rowCount ||
        count($outcomes) !== $rowCount ||
        count($types) !== $rowCount ||
        count($activityStatuses) !== $rowCount
    ) {
        productionRedirect(
            "Add at least one activity. A maximum of 100 activities "
            . "can be saved in one report.",
            'error'
        );
    }

    for ($i = 0; $i < $rowCount; $i++) {
        if (
            !is_string($descriptions[$i]) ||
            !is_string($indicators[$i]) ||
            !is_string($outcomes[$i]) ||
            !is_string($types[$i]) ||
            !is_string($activityStatuses[$i])
        ) {
            productionRedirect("Invalid activity fields.", 'error');
        }

        $description = trim($descriptions[$i]);
        $indicator = trim($indicators[$i]);
        $outcome = trim($outcomes[$i]);
        $type = trim($types[$i]);
        $activityStatus = trim($activityStatuses[$i]);

        if ($description === '') {
            productionRedirect(
                "Every activity must have a description.",
                'error'
            );
        }

        if (
            !in_array($type, $activityTypes, true) ||
            !in_array($activityStatus, $statuses, true)
        ) {
            productionRedirect(
                "An activity has an invalid type or status.",
                'error'
            );
        }

        $activities[] = [
            'description' => $description,
            'indicators' => $indicator,
            'outcome' => $outcome,
            'type' => $type,
            'status' => $activityStatus
        ];
    }

    try {
        $conn->begin_transaction();

        if ($reportId > 0) {
            $existingReport = productionGetOwnedReport(
                $conn,
                $reportId,
                $employeeId
            );

            if (!$existingReport) {
                throw new Exception(
                    "Report not found or you do not have permission to edit it."
                );
            }

            // Update date while respecting the one-report-per-day rule.
            $stmt = $conn->prepare("
                UPDATE production_daily_reports
                SET report_date = ?
                WHERE report_id = ?
                  AND employee_id = ?
            ");

            $stmt->bind_param(
                "sii",
                $reportDate,
                $reportId,
                $employeeId
            );

            $stmt->execute();
            $stmt->close();

        } else {
            // A new report must not duplicate an employee's reporting date.
            $stmt = $conn->prepare("
                INSERT INTO production_daily_reports
                    (employee_id, report_date)
                VALUES (?, ?)
            ");

            $stmt->bind_param("is", $employeeId, $reportDate);
            $stmt->execute();

            $reportId = (int) $conn->insert_id;
            $stmt->close();
        }

        // Editing replaces the previous activity list with the submitted list.
        if (isset($existingReport)) {
            $stmt = $conn->prepare("
                DELETE FROM production_daily_report_activities
                WHERE report_id = ?
            ");

            $stmt->bind_param("i", $reportId);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("
            INSERT INTO production_daily_report_activities
                (
                    report_id,
                    description,
                    performance_indicators,
                    outcome,
                    activity_type,
                    status
                )
            VALUES (?, ?, ?, ?, ?, ?)
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

        productionRedirect(
            $existingReport
                ? "Daily production report updated successfully."
                : "Daily production report saved successfully."
        );

    } catch (Throwable $e) {
        $conn->rollback();

        error_log("Production daily report save: " . $e->getMessage());

        if (
            $e instanceof mysqli_sql_exception &&
            (int) $e->getCode() === 1062
        ) {
            productionRedirect(
                "A report already exists for this date. Use Edit to update it.",
                'error'
            );
        }

        productionRedirect(
            $e instanceof Exception
                ? $e->getMessage()
                : "Unable to save the report. Please try again.",
            'error'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Load edit or view selection
|--------------------------------------------------------------------------
*/

$selectedReport = null;
$selectedActivities = [];
$mode = $_GET['mode'] ?? 'form';

$requestedReportId = filter_var(
    $_GET['report_id'] ?? '',
    FILTER_VALIDATE_INT
);

if ($requestedReportId && $requestedReportId > 0) {
    $selectedReport = productionGetOwnedReport(
        $conn,
        (int) $requestedReportId,
        $employeeId
    );

    if (!$selectedReport) {
        productionRedirect(
            "Report not found or you do not have permission to access it.",
            'error'
        );
    }

    $selectedActivities = productionGetActivities(
        $conn,
        (int) $requestedReportId
    );

    if ($mode !== 'view') {
        $mode = 'edit';
    }
}

if (isset($_SESSION['production_report_success'])) {
    $success = $_SESSION['production_report_success'];
    unset($_SESSION['production_report_success']);
}

if (isset($_SESSION['production_report_error'])) {
    $error = $_SESSION['production_report_error'];
    unset($_SESSION['production_report_error']);
}

$defaultDate = $selectedReport['report_date'] ?? $today;

/*
|--------------------------------------------------------------------------
| Load grouped reports for the logged-in employee
|--------------------------------------------------------------------------
*/

$reports = [];

$stmt = $conn->prepare("
    SELECT
        r.report_id,
        r.report_date,
        r.created_at,
        r.updated_at,
        COUNT(a.activity_id) AS activity_count,
        SUM(a.status = 'Completed') AS completed_count,
        SUM(a.status = 'Pending') AS pending_count,
        SUM(a.status = 'In Progress') AS in_progress_count
    FROM production_daily_reports r
    LEFT JOIN production_daily_report_activities a
        ON a.report_id = r.report_id
    WHERE r.employee_id = ?
    GROUP BY
        r.report_id,
        r.report_date,
        r.created_at,
        r.updated_at
    ORDER BY r.report_date DESC
");

$stmt->bind_param("i", $employeeId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $reports[] = $row;
}

$stmt->close();

function productionH($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Daily Report | Lynntech Manager</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .production-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .production-input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            padding: 9px 11px;
            background: #fff;
        }

        .production-input:focus {
            outline: 2px solid #d4a017;
            outline-offset: 1px;
        }

        .production-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        .btn-gold {
            background: #b8860b;
            color: white;
        }

        .btn-gold:hover {
            background: #966f09;
        }

        .activity-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
            background: #fafafa;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-col md:flex-row md:items-center
                md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                Production Daily Report
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Record, manage and export your daily production activities.
            </p>
        </div>

        <a href="production_daily_report.php"
           class="btn-gold px-4 py-2 rounded-lg text-center font-semibold">
            + New Daily Report
        </a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50
                    text-green-800 p-4">
            <?= productionH($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50
                    text-red-800 p-4">
            <?= productionH($error) ?>
        </div>
    <?php endif; ?>

    <!-- Report form -->
    <?php if ($mode !== 'view'): ?>
        <div class="production-card p-5 mb-8">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
                <h2 class="text-lg font-bold">
                    <?= $mode === 'edit'
                        ? 'Edit Daily Production Report'
                        : 'Enter Daily Production Report' ?>
                </h2>

                <?php if ($selectedReport): ?>
                    <span class="text-sm text-gray-500">
                        Report #<?= (int) $selectedReport['report_id'] ?>
                    </span>
                <?php endif; ?>
            </div>

            <form method="POST" action="production_daily_report.php"
                  id="productionReportForm">

                <input type="hidden" name="action" value="save">

                <input type="hidden" name="csrf_token"
                       value="<?= productionH($csrfToken) ?>">

                <input type="hidden" name="report_id"
                       value="<?= (int) ($selectedReport['report_id'] ?? 0) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="production-label">Reporting Date *</label>
                        <input type="date"
                               name="report_date"
                               class="production-input"
                               value="<?= productionH($defaultDate) ?>"
                               max="<?= productionH($today) ?>"
                               required>
                    </div>

                    <div>
                        <label class="production-label">Staff Member</label>
                        <input type="text"
                               class="production-input bg-gray-100"
                               value="<?= productionH(
                                   trim(
                                       ($_SESSION['first_name'] ?? '') . ' ' .
                                       ($_SESSION['last_name'] ?? '')
                                   )
                               ) ?>"
                               placeholder="Logged-in staff member"
                               readonly>
                        <p class="text-xs text-gray-500 mt-1">
                            The report is linked to your employee account.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center
                            justify-between gap-3 mb-4">
                    <h3 class="font-bold text-gray-800">Activities</h3>

                    <button type="button"
                            id="addActivityBtn"
                            class="border border-green-600 text-green-700
                                   hover:bg-green-50 px-4 py-2 rounded-lg
                                   font-semibold">
                        + Add Activity
                    </button>
                </div>

                <div id="activitiesContainer" class="space-y-4">
                    <?php
                    $formActivities = $selectedActivities ?: [[
                        'description' => '',
                        'performance_indicators' => '',
                        'outcome' => '',
                        'activity_type' => 'Routine',
                        'status' => 'Pending'
                    ]];
                    ?>

                    <?php foreach ($formActivities as $index => $activity): ?>
                        <div class="activity-card activity-row">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="font-semibold activity-heading">
                                    Activity <?= $index + 1 ?>
                                </h4>

                                <button type="button"
                                        class="remove-activity text-red-600
                                               hover:text-red-800 text-sm
                                               font-semibold"
                                        <?= count($formActivities) === 1
                                            ? 'disabled' : '' ?>>
                                    Remove
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2
                                        gap-4 mb-4">
                                <div>
                                    <label class="production-label">
                                        Description *
                                    </label>
                                    <textarea name="description[]"
                                              rows="3"
                                              class="production-input"
                                              required
                                              placeholder="Describe the activity performed..."><?= productionH(
                                                  $activity['description']
                                              ) ?></textarea>
                                </div>

                                <div>
                                    <label class="production-label">
                                        Indicators of Performance
                                    </label>
                                    <textarea name="performance_indicators[]"
                                              rows="3"
                                              class="production-input"
                                              placeholder="Targets, quantities, KPIs or measures..."><?= productionH(
                                                  $activity['performance_indicators']
                                              ) ?></textarea>
                                </div>

                                <div>
                                    <label class="production-label">Outcome</label>
                                    <textarea name="outcome[]"
                                              rows="3"
                                              class="production-input"
                                              placeholder="Results or deliverables achieved..."><?= productionH(
                                                  $activity['outcome']
                                              ) ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="production-label">
                                            Routine / Planned *
                                        </label>

                                        <select name="activity_type[]"
                                                class="production-input"
                                                required>
                                            <?php foreach ($activityTypes as $type): ?>
                                                <option
                                                    value="<?= productionH($type) ?>"
                                                    <?= $activity['activity_type'] === $type
                                                        ? 'selected' : '' ?>>
                                                    <?= productionH($type) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="production-label">
                                            Status *
                                        </label>

                                        <select name="activity_status[]"
                                                class="production-input"
                                                required>
                                            <?php foreach ($statuses as $status): ?>
                                                <option
                                                    value="<?= productionH($status) ?>"
                                                    <?= $activity['status'] === $status
                                                        ? 'selected' : '' ?>>
                                                    <?= productionH($status) ?>
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
                            class="btn-gold px-6 py-2.5 rounded-lg
                                   font-semibold">
                        <?= $mode === 'edit'
                            ? 'Update Report'
                            : 'Save Daily Report' ?>
                    </button>

                    <a href="production_daily_report.php"
                       class="border border-gray-300 bg-white px-6 py-2.5
                              rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- View selected report -->
    <?php if ($mode === 'view' && $selectedReport): ?>
        <div class="production-card p-5 mb-8">
            <div class="flex flex-col sm:flex-row sm:items-start
                        sm:justify-between gap-4 mb-5">
                <div>
                    <h2 class="text-xl font-bold">Daily Report Details</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Reporting date:
                        <strong><?= productionH(
                            $selectedReport['report_date']
                        ) ?></strong>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="production_daily_report.php?mode=edit&amp;report_id=<?= (int) $selectedReport['report_id'] ?>"
                       class="bg-blue-600 hover:bg-blue-700 text-white
                              px-4 py-2 rounded-lg">
                        Edit
                    </a>

                    <a href="production_daily_report.php?action=export&amp;report_id=<?= (int) $selectedReport['report_id'] ?>"
                       class="bg-green-700 hover:bg-green-800 text-white
                              px-4 py-2 rounded-lg">
                        Download Excel
                    </a>
                </div>
            </div>

            <p class="text-sm mb-4">
                Staff member:
                <strong><?= productionH(
                    trim(
                        $selectedReport['first_name'] . ' ' .
                        $selectedReport['last_name']
                    )
                ) ?></strong>
            </p>

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
                            <th class="border p-3 text-left">
                                Routine / Planned
                            </th>
                            <th class="border p-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$selectedActivities): ?>
                            <tr>
                                <td colspan="6"
                                    class="border p-4 text-center text-gray-500">
                                    No activities found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($selectedActivities as $index => $activity): ?>
                                <tr class="hover:bg-gray-50 align-top">
                                    <td class="border p-3">
                                        <?= $index + 1 ?>
                                    </td>
                                    <td class="border p-3 whitespace-pre-wrap"><?= productionH(
                                        $activity['description']
                                    ) ?></td>
                                    <td class="border p-3 whitespace-pre-wrap"><?= productionH(
                                        $activity['performance_indicators']
                                    ) ?></td>
                                    <td class="border p-3 whitespace-pre-wrap"><?= productionH(
                                        $activity['outcome']
                                    ) ?></td>
                                    <td class="border p-3">
                                        <?= productionH(
                                            $activity['activity_type']
                                        ) ?>
                                    </td>
                                    <td class="border p-3">
                                        <?= productionH($activity['status']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Grouped report history -->
    <div class="production-card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center
                    sm:justify-between gap-3 mb-5">
            <div>
                <h2 class="text-lg font-bold">My Daily Reports</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Reports are grouped by date, with activity summaries.
                </p>
            </div>

            <input type="search"
                   id="reportSearch"
                   class="production-input sm:max-w-xs"
                   placeholder="Search by date or status...">
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border-collapse">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border p-3 text-left">Reporting Date</th>
                        <th class="border p-3 text-center">Activities</th>
                        <th class="border p-3 text-center">Completed</th>
                        <th class="border p-3 text-center">Pending</th>
                        <th class="border p-3 text-center">In Progress</th>
                        <th class="border p-3 text-left">Last Updated</th>
                        <th class="border p-3 text-center">Actions</th>
                    </tr>
                </thead>

                <tbody id="reportsTableBody">
                    <?php if (!$reports): ?>
                        <tr>
                            <td colspan="7"
                                class="border p-6 text-center text-gray-500">
                                No daily reports have been recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr class="report-row hover:bg-gray-50"
                                data-search="<?= productionH(
                                    $report['report_date']
                                ) ?>">
                                <td class="border p-3 font-medium">
                                    <?= productionH($report['report_date']) ?>
                                </td>

                                <td class="border p-3 text-center">
                                    <?= (int) $report['activity_count'] ?>
                                </td>

                                <td class="border p-3 text-center text-green-700">
                                    <?= (int) ($report['completed_count'] ?? 0) ?>
                                </td>

                                <td class="border p-3 text-center text-amber-700">
                                    <?= (int) ($report['pending_count'] ?? 0) ?>
                                </td>

                                <td class="border p-3 text-center text-blue-700">
                                    <?= (int) ($report['in_progress_count'] ?? 0) ?>
                                </td>

                                <td class="border p-3">
                                    <?= productionH(
                                        $report['updated_at']
                                    ) ?>
                                </td>

                                <td class="border p-3">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <a href="production_daily_report.php?mode=view&amp;report_id=<?= (int) $report['report_id'] ?>"
                                           class="text-blue-700 hover:underline font-semibold">
                                            View
                                        </a>

                                        <a href="production_daily_report.php?mode=edit&amp;report_id=<?= (int) $report['report_id'] ?>"
                                           class="text-amber-700 hover:underline font-semibold">
                                            Edit
                                        </a>

                                        <a href="production_daily_report.php?action=export&amp;report_id=<?= (int) $report['report_id'] ?>"
                                           class="text-green-700 hover:underline font-semibold">
                                            Excel
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
        Lynntech Manager | Production Daily Reporting
    </p>

</div>

<script>
(function () {
    const container = document.getElementById('activitiesContainer');
    const addButton = document.getElementById('addActivityBtn');
    const form = document.getElementById('productionReportForm');

    const maxActivities = 100;

    function updateActivityRows() {
        if (!container) return;

        const rows = container.querySelectorAll('.activity-row');

        rows.forEach(function (row, index) {
            const heading = row.querySelector('.activity-heading');
            const removeButton = row.querySelector('.remove-activity');

            if (heading) {
                heading.textContent = 'Activity ' + (index + 1);
            }

            if (removeButton) {
                removeButton.disabled = rows.length === 1;
                removeButton.classList.toggle(
                    'opacity-40',
                    rows.length === 1
                );
                removeButton.classList.toggle(
                    'cursor-not-allowed',
                    rows.length === 1
                );
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

    function createActivityRow() {
        const row = document.createElement('div');
        row.className = 'activity-card activity-row';

        row.innerHTML = `
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-semibold activity-heading">Activity</h4>
                <button type="button"
                    class="remove-activity text-red-600 hover:text-red-800
                           text-sm font-semibold">
                    Remove
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="production-label">Description *</label>
                    <textarea name="description[]" rows="3"
                        class="production-input" required
                        placeholder="Describe the activity performed..."></textarea>
                </div>

                <div>
                    <label class="production-label">
                        Indicators of Performance
                    </label>
                    <textarea name="performance_indicators[]" rows="3"
                        class="production-input"
                        placeholder="Targets, quantities, KPIs or measures..."></textarea>
                </div>

                <div>
                    <label class="production-label">Outcome</label>
                    <textarea name="outcome[]" rows="3"
                        class="production-input"
                        placeholder="Results or deliverables achieved..."></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="production-label">
                            Routine / Planned *
                        </label>
                        <select name="activity_type[]"
                            class="production-input" required>
                            <option value="Routine">Routine</option>
                            <option value="Planned">Planned</option>
                        </select>
                    </div>

                    <div>
                        <label class="production-label">Status *</label>
                        <select name="activity_status[]"
                            class="production-input" required>
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

    if (addButton && container) {
        addButton.addEventListener('click', function () {
            if (container.querySelectorAll('.activity-row').length >= maxActivities) {
                alert('You can add up to 100 activities per report.');
                return;
            }

            container.appendChild(createActivityRow());
            updateActivityRows();

            const newRow = container.lastElementChild;
            const description = newRow.querySelector('[name="description[]"]');

            if (description) {
                description.focus();
            }
        });
    }

    if (container) {
        container.addEventListener('click', function (event) {
            const removeButton = event.target.closest('.remove-activity');

            if (!removeButton || removeButton.disabled) return;

            const rows = container.querySelectorAll('.activity-row');

            if (rows.length <= 1) return;

            removeButton.closest('.activity-row').remove();
            updateActivityRows();
        });

        updateActivityRows();
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            const rows = container.querySelectorAll('.activity-row');

            if (rows.length < 1) {
                event.preventDefault();
                alert('Please add at least one activity.');
                return;
            }

            let missingDescription = false;

            rows.forEach(function (row) {
                const description = row.querySelector('[name="description[]"]');

                if (!description || !description.value.trim()) {
                    missingDescription = true;
                }
            });

            if (missingDescription) {
                event.preventDefault();
                alert('Please enter a description for every activity.');
            }
        });
    }

    // Filter report history.
    const searchInput = document.getElementById('reportSearch');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = searchInput.value.toLowerCase().trim();

            document.querySelectorAll('.report-row').forEach(function (row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
})();
</script>

</body>
</html>
