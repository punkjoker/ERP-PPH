<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_con.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");
date_default_timezone_set('Africa/Nairobi');

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

if (
    !isset($_SESSION['purchasing_report_csrf']) ||
    !is_string($_SESSION['purchasing_report_csrf']) ||
    strlen($_SESSION['purchasing_report_csrf']) !== 64
) {
    $_SESSION['purchasing_report_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['purchasing_report_csrf'];

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
| Database helper functions
|--------------------------------------------------------------------------
*/

function purchasingTableColumns(mysqli $conn, string $table): array
{
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

function purchasingGetEmployeeId(mysqli $conn, int $userId): int
{
    $userColumns = purchasingTableColumns($conn, 'users');
    $employeeColumns = purchasingTableColumns($conn, 'employees');

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
        $stmt = $conn->prepare("
            SELECT e.employee_id
            FROM users u
            INNER JOIN employees e
                ON e.employee_id = u.employee_id
            WHERE u.`$userPk` = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['employee_id'])) {
            throw new Exception(
                "Your login account is not linked to an employee record. "
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
        $stmt = $conn->prepare("
            SELECT e.employee_id
            FROM users u
            INNER JOIN employees e
                ON LOWER(TRIM(e.email)) = LOWER(TRIM(u.email))
            WHERE u.`$userPk` = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['employee_id'])) {
            throw new Exception(
                "No employee record matches your login email."
            );
        }

        return (int) $row['employee_id'];
    }

    throw new Exception(
        "Could not identify the employee relationship. "
        . "Link users to employees using employee_id or a matching email."
    );
}

function purchasingValidDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function purchasingRedirect(
    string $message,
    string $type = 'success',
    string $url = 'purchasing_daily_report.php'
): void {
    $key = $type === 'error'
        ? 'purchasing_report_error'
        : 'purchasing_report_success';

    $_SESSION[$key] = $message;

    header("Location: " . $url);
    exit;
}
function purchasingGetOwnedReport(
    mysqli $conn,
    int $reportId,
    int $employeeId
): ?array {
    $stmt = $conn->prepare("
        SELECT
            r.report_id,
            r.employee_id,
            r.report_date,
            e.first_name,
            e.last_name
        FROM purchasing_daily_reports r
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

function purchasingGetActivities(mysqli $conn, int $reportId): array
{
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

function purchasingH($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function purchasingExcelCell(string $value): string
{
    // Protect exported spreadsheet cells against formula injection.
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
| Excel import helper functions
|--------------------------------------------------------------------------
*/

function purchasingNormalizeHeading(string $heading): string
{
    $heading = strtoupper(trim($heading));

    // Correct common spelling mistakes.
    $heading = str_replace('PERFOMANCE', 'PERFORMANCE', $heading);

    // Remove spaces, underscores and hyphens for consistent matching.
    $heading = preg_replace('/[\s_\-]+/', '', $heading);

    // Normalize punctuation.
    $heading = str_replace(['\\', ':', '.'], '', $heading);

    return $heading;
}

function purchasingReadExcelActivities(string $filePath): array
{
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    $highestRow = $sheet->getHighestDataRow();
    $highestColumn = $sheet->getHighestDataColumn();

    $highestColumnIndex = Coordinate::columnIndexFromString(
        $highestColumn
    );

    $headingAliases = [
        'DESCRIPTION' => [
            'DESCRIPTION',
            'ACTIVITYDESCRIPTION',
            'ACTIVITIES',
            'TASKDESCRIPTION'
        ],

        'INDICATORS OF PERFORMANCE' => [
            'INDICATORSOFPERFORMANCE',
            'PERFORMANCEINDICATORS',
            'INDICATOROFPERFORMANCE',
            'INDICATORS',
            'KPI',
            'KPIS'
        ],

        'OUTCOME' => [
            'OUTCOME',
            'EXPECTEDOUTCOME',
            'ACTUALOUTCOME',
            'RESULT',
            'RESULTS'
        ],

        'ROUTINE/PLANNED' => [
            'ROUTINEPLANNED',
            'ROUTINE',
            'PLANNED',
            'ACTIVITYTYPE',
            'TYPE'
        ],

        'STATUS' => [
            'STATUS',
            'ACTIVITYSTATUS',
            'PROGRESSSTATUS'
        ]
    ];

    $headingRow = null;
    $columnMap = [];

    // Locate the heading row within the first 20 rows.
    for ($row = 1; $row <= min(20, $highestRow); $row++) {

        $found = [];

        for ($col = 1; $col <= $highestColumnIndex; $col++) {

            $cell = $sheet->getCell(
                Coordinate::stringFromColumnIndex($col) . $row
            );

            if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                continue;
            }

            $value = $cell->getValue();

            if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $value = $value->getPlainText();
            }

            $normalized = purchasingNormalizeHeading((string) $value);

            if ($normalized === '') {
                continue;
            }

            foreach ($headingAliases as $heading => $aliases) {

                foreach ($aliases as $alias) {

                    if (
                        $normalized ===
                        purchasingNormalizeHeading($alias)
                    ) {
                        $found[$heading] = $col;
                        break 2;
                    }
                }
            }
        }

        // Description is the only mandatory heading.
        if (isset($found['DESCRIPTION'])) {
            $headingRow = $row;
            $columnMap = $found;
            break;
        }
    }

    if ($headingRow === null) {
        throw new Exception(
            "The Excel file must contain a DESCRIPTION heading."
        );
    }

    $activities = [];

    for ($row = $headingRow + 1; $row <= $highestRow; $row++) {

        $values = [];

        foreach ($headingAliases as $heading => $aliases) {

            $values[$heading] = '';

            // Missing optional columns are allowed.
            if (!isset($columnMap[$heading])) {
                continue;
            }

            $cell = $sheet->getCell(
                Coordinate::stringFromColumnIndex(
                    $columnMap[$heading]
                ) . $row
            );

            if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                throw new Exception(
                    "Formula detected in Excel row {$row}. "
                    . "Please replace formulas with normal text."
                );
            }

            $value = $cell->getValue();

            if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                $value = $value->getPlainText();
            }

            $values[$heading] = trim((string) ($value ?? ''));
        }

        // Ignore completely empty rows.
        if (implode('', $values) === '') {
            continue;
        }

        if ($values['DESCRIPTION'] === '') {
            throw new Exception(
                "Description is missing in Excel row {$row}."
            );
        }

        // Activity type.
        $typeValue = strtolower(
            trim($values['ROUTINE/PLANNED'])
        );

        $type = ($typeValue === 'planned')
            ? 'Planned'
            : 'Routine';

        if (
            $typeValue !== '' &&
            !in_array($typeValue, ['routine', 'planned'], true)
        ) {
            throw new Exception(
                "Invalid Routine/Planned value in Excel row {$row}. "
                . "Use Routine or Planned."
            );
        }

        // Accept the status exactly as entered in Excel.
        $status = trim($values['STATUS']);

        if ($status === '') {
            $status = 'Pending';
        }

        $activities[] = [
            'description' => $values['DESCRIPTION'],
            'performance_indicators' =>
                $values['INDICATORS OF PERFORMANCE'],
            'outcome' => $values['OUTCOME'],
            'activity_type' => $type,
            'status' => $status
        ];

        if (count($activities) > 100) {
            throw new Exception(
                "The Excel file contains more than 100 activities. "
                . "Please import a maximum of 100 activities at a time."
            );
        }
    }

    if (empty($activities)) {
        throw new Exception(
            "No activities were found in the Excel file."
        );
    }

    return $activities;
}
/*
|--------------------------------------------------------------------------
| Resolve logged-in employee
|--------------------------------------------------------------------------
*/

try {
    $employeeId = purchasingGetEmployeeId($conn, $userId);
} catch (Throwable $e) {
    error_log("Purchasing report employee lookup: " . $e->getMessage());

    http_response_code(403);

    exit(
        "Your login account could not be linked to an employee record. "
        . "Please contact the administrator to check the users/employees "
        . "relationship."
    );
}

/*
|--------------------------------------------------------------------------
| Identify requested action
|--------------------------------------------------------------------------
*/

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/*
|--------------------------------------------------------------------------
| Download Excel import template
|--------------------------------------------------------------------------
*/

if ($action === 'template') {

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setTitle('Purchasing Activities');

    $headers = [
        'DESCRIPTION',
        'INDICATORS OF PERFORMANCE',
        'OUTCOME',
        'ROUTINE/PLANNED',
        'STATUS'
    ];

    $sheet->fromArray($headers, null, 'A1');

    $sheet->getStyle('A1:E1')->getFont()->setBold(true);

    $sheet->getStyle('A1:E1')
        ->getFill()
        ->setFillType(
            \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID
        )
        ->getStartColor()
        ->setRGB('D9E8D5');

    foreach (range('A', 'E') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $filename = 'Purchasing_Daily_Report_Template.xlsx';

    header(
        'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    header(
        'Content-Disposition: attachment; filename="' . $filename . '"'
    );

    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    exit;
}

/*
|--------------------------------------------------------------------------
| Export report to Excel
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

    $report = purchasingGetOwnedReport(
        $conn,
        (int) $reportId,
        $employeeId
    );

    if (!$report) {
        http_response_code(404);
        exit("Report not found.");
    }

    $activities = purchasingGetActivities($conn, (int) $reportId);

    $filename = 'Purchasing_Daily_Report_' .
        $report['report_date'] . '.xls';

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");

    header(
        'Content-Disposition: attachment; filename="' . $filename . '"'
    );

    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");

    echo "\xEF\xBB\xBF";
    ?>
    <!DOCTYPE html>
    <html lang="en">
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
                    LYNNTECH MANAGER - PURCHASING DAILY REPORT
                </td>
            </tr>

            <tr>
                <td colspan="2"><strong>Reporting Date</strong></td>
                <td colspan="4">
                    <?= purchasingExcelCell($report['report_date']) ?>
                </td>
            </tr>

            <tr>
                <td colspan="2"><strong>Staff Member</strong></td>
                <td colspan="4">
                    <?= purchasingExcelCell(
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

                    <td><?= nl2br(purchasingExcelCell(
                        $activity['description']
                    )) ?></td>

                    <td><?= nl2br(purchasingExcelCell(
                        $activity['performance_indicators'] ?? ''
                    )) ?></td>

                    <td><?= nl2br(purchasingExcelCell(
                        $activity['outcome'] ?? ''
                    )) ?></td>

                    <td><?= purchasingExcelCell(
                        $activity['activity_type']
                    ) ?></td>

                    <td><?= purchasingExcelCell(
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
| Import activities from Excel
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'import') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['purchasing_report_csrf'], $submittedToken)
    ) {
        purchasingRedirect(
            "Your session token is invalid. Please reload the page and try again.",
            'error'
        );
    }

    $reportDate = trim((string) ($_POST['report_date'] ?? ''));

    $reportIdValue = filter_var(
        $_POST['report_id'] ?? '0',
        FILTER_VALIDATE_INT
    );

    $reportId = ($reportIdValue && $reportIdValue > 0)
        ? (int) $reportIdValue
        : 0;

    if (!purchasingValidDate($reportDate) || $reportDate > $today) {
        purchasingRedirect(
            "Please select a valid reporting date before importing.",
            'error'
        );
    }

    if ($reportId > 0) {

        $ownedReport = purchasingGetOwnedReport(
            $conn,
            $reportId,
            $employeeId
        );

        if (!$ownedReport) {
            purchasingRedirect(
                "Report not found or you do not have permission to edit it.",
                'error'
            );
        }
    }

    if (
        !isset($_FILES['excel_file']) ||
        $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK
    ) {
        purchasingRedirect(
            "Please select a valid Excel file to import.",
            'error'
        );
    }

    $file = $_FILES['excel_file'];

    if ($file['size'] > 5 * 1024 * 1024) {
        purchasingRedirect(
            "The Excel file must not exceed 5 MB.",
            'error'
        );
    }

    $extension = strtolower(
        pathinfo($file['name'], PATHINFO_EXTENSION)
    );

    if (!in_array($extension, ['xlsx', 'xls'], true)) {
        purchasingRedirect(
            "Only Excel .xlsx and .xls files are supported.",
            'error'
        );
    }

    try {

        $importedActivities = purchasingReadExcelActivities(
            $file['tmp_name']
        );

        $_SESSION['purchasing_imported_activities'] = $importedActivities;
        $_SESSION['purchasing_imported_date'] = $reportDate;
        $_SESSION['purchasing_imported_report_id'] = $reportId;

        $_SESSION['purchasing_report_success'] =
            count($importedActivities)
            . " activities imported successfully. Review them before saving.";

        $redirectUrl = 'purchasing_daily_report.php';

if ($reportId > 0) {
    $redirectUrl .= '?mode=edit&report_id=' . $reportId;
}
        header("Location: " . $redirectUrl);
        exit;

    } catch (Throwable $e) {

        error_log("Purchasing Excel import: " . $e->getMessage());

        purchasingRedirect(
            $e instanceof Exception
                ? $e->getMessage()
                : "Unable to read the Excel file. Please check its format.",
            'error'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Save a new report or update an existing report
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['purchasing_report_csrf'], $submittedToken)
    ) {
        purchasingRedirect(
            "Your session token is invalid. Please reload the page and try again.",
            'error'
        );
    }

    $reportDate = trim((string) ($_POST['report_date'] ?? ''));

    $reportIdValue = filter_var(
        $_POST['report_id'] ?? '0',
        FILTER_VALIDATE_INT
    );

    $reportId = ($reportIdValue && $reportIdValue > 0)
        ? (int) $reportIdValue
        : 0;

    if (!purchasingValidDate($reportDate)) {
        purchasingRedirect("Please select a valid reporting date.", 'error');
    }

    if ($reportDate > $today) {
        purchasingRedirect(
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
        purchasingRedirect("Invalid activity information.", 'error');
    }

    $rowCount = count($descriptions);

    if (
        $rowCount < 1 ||
        $rowCount > 100 ||
        count($indicators) !== $rowCount ||
        count($outcomes) !== $rowCount ||
        count($types) !== $rowCount ||
        count($activityStatuses) !== $rowCount
    ) {
        purchasingRedirect(
            "Add at least one activity. A maximum of 100 activities "
            . "can be saved in one report.",
            'error'
        );
    }

    $activities = [];

    for ($i = 0; $i < $rowCount; $i++) {

        if (
            !is_string($descriptions[$i]) ||
            !is_string($indicators[$i]) ||
            !is_string($outcomes[$i]) ||
            !is_string($types[$i]) ||
            !is_string($activityStatuses[$i])
        ) {
            purchasingRedirect("Invalid activity fields.", 'error');
        }

        $description = trim($descriptions[$i]);
        $indicator = trim($indicators[$i]);
        $outcome = trim($outcomes[$i]);
        $type = trim($types[$i]);
        $activityStatus = trim($activityStatuses[$i]);

        if ($description === '') {
            purchasingRedirect(
                "Every activity must have a description.",
                'error'
            );
        }

        if (
            !in_array($type, $activityTypes, true) ||
            !in_array($activityStatus, $statuses, true)
        ) {
            purchasingRedirect(
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

    $isEditing = $reportId > 0;
    $existingReport = null;
    $transactionStarted = false;

    try {

        $conn->begin_transaction();
        $transactionStarted = true;

        if ($isEditing) {

            $existingReport = purchasingGetOwnedReport(
                $conn,
                $reportId,
                $employeeId
            );

            if (!$existingReport) {
                throw new Exception(
                    "Report not found or you do not have permission to edit it."
                );
            }

            $stmt = $conn->prepare("
                UPDATE purchasing_daily_reports
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

            $stmt = $conn->prepare("
                DELETE FROM purchasing_daily_report_activities
                WHERE report_id = ?
            ");

            $stmt->bind_param("i", $reportId);
            $stmt->execute();
            $stmt->close();

        } else {

            $stmt = $conn->prepare("
                INSERT INTO purchasing_daily_reports
                    (employee_id, report_date)
                VALUES (?, ?)
            ");

            $stmt->bind_param("is", $employeeId, $reportDate);
            $stmt->execute();

            $reportId = (int) $conn->insert_id;
            $stmt->close();
        }

        $stmt = $conn->prepare("
            INSERT INTO purchasing_daily_report_activities
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
        $transactionStarted = false;

        purchasingRedirect(
    $isEditing
        ? "Daily purchasing report updated successfully."
        : "Daily purchasing report saved successfully.",
    'success',
    'purchasing_daily_report.php?mode=edit&report_id=' . $reportId
);
    } catch (Throwable $e) {

        if ($transactionStarted) {
            $conn->rollback();
        }

        error_log("Purchasing daily report save: " . $e->getMessage());

        if (
            $e instanceof mysqli_sql_exception &&
            (int) $e->getCode() === 1062
        ) {
            purchasingRedirect(
                "A report already exists for this date. Use Edit to update it.",
                'error'
            );
        }

        purchasingRedirect(
            $e instanceof Exception
                ? $e->getMessage()
                : "Unable to save the report. Please try again.",
            'error'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Load selected report for viewing or editing
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

    $selectedReport = purchasingGetOwnedReport(
        $conn,
        (int) $requestedReportId,
        $employeeId
    );

    if (!$selectedReport) {
        purchasingRedirect(
            "Report not found or you do not have permission to access it.",
            'error'
        );
    }

    $selectedActivities = purchasingGetActivities(
        $conn,
        (int) $requestedReportId
    );

    if ($mode !== 'view') {
        $mode = 'edit';
    }
}

/*
|--------------------------------------------------------------------------
| Load session messages
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['purchasing_report_success'])) {
    $success = $_SESSION['purchasing_report_success'];
    unset($_SESSION['purchasing_report_success']);
}

if (isset($_SESSION['purchasing_report_error'])) {
    $error = $_SESSION['purchasing_report_error'];
    unset($_SESSION['purchasing_report_error']);
}

/*
|--------------------------------------------------------------------------
| Restore temporarily imported Excel activities
|--------------------------------------------------------------------------
*/

$importedActivities = [];
$importedDate = null;
$importedReportId = 0;

if (isset($_SESSION['purchasing_imported_activities'])) {

    $importedActivities = $_SESSION['purchasing_imported_activities'];

    $importedDate = $_SESSION['purchasing_imported_date'] ?? $today;

    $importedReportId = (int) (
        $_SESSION['purchasing_imported_report_id'] ?? 0
    );

    unset(
        $_SESSION['purchasing_imported_activities'],
        $_SESSION['purchasing_imported_date'],
        $_SESSION['purchasing_imported_report_id']
    );
}

$defaultDate = $importedDate ?? (
    $selectedReport['report_date'] ?? $today
);

/*
|--------------------------------------------------------------------------
| Prepare activity rows for the form
|--------------------------------------------------------------------------
*/

$formActivities = !empty($importedActivities)
    ? $importedActivities
    : ($selectedActivities ?: [[
        'description' => '',
        'performance_indicators' => '',
        'outcome' => '',
        'activity_type' => 'Routine',
        'status' => 'Pending'
    ]]);

/*
|--------------------------------------------------------------------------
| Load report history for this employee
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
        COALESCE(SUM(a.status = 'Completed'), 0) AS completed_count,
        COALESCE(SUM(a.status = 'Pending'), 0) AS pending_count,
        COALESCE(SUM(a.status = 'In Progress'), 0) AS in_progress_count
    FROM purchasing_daily_reports r
    LEFT JOIN purchasing_daily_report_activities a
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

$staffName = trim(
    (string) ($_SESSION['first_name'] ?? '') . ' ' .
    (string) ($_SESSION['last_name'] ?? '')
);

if ($staffName === '' && $selectedReport) {
    $staffName = trim(
        $selectedReport['first_name'] . ' ' .
        $selectedReport['last_name']
    );
}

if ($staffName === '') {
    $staffName = 'Logged-in staff member';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Purchasing Daily Report | Lynntech Manager</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .purchasing-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .purchasing-input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            padding: 9px 11px;
            background: #fff;
        }

        .purchasing-input:focus {
            outline: 2px solid #d4a017;
            outline-offset: 1px;
        }

        .purchasing-label {
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

        @media (max-width: 767px) {
            .page-content {
                margin-left: 0 !important;
                padding: 16px !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800">

<?php include 'navbar.php'; ?>

<div class="page-content ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-col md:flex-row md:items-center
                md:justify-between gap-4 mb-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                Purchasing Daily Report
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Record, manage and export your daily purchasing activities.
            </p>
        </div>

        <a href="purchasing_daily_report.php"
           class="btn-gold px-4 py-2 rounded-lg text-center font-semibold">
            + New Daily Report
        </a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50
                    text-green-800 p-4">
            <?= purchasingH($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50
                    text-red-800 p-4">
            <?= purchasingH($error) ?>
        </div>
    <?php endif; ?>

    <!-- New or edit report form -->
    <?php if ($mode !== 'view'): ?>

        <div class="purchasing-card p-5 mb-8">

            <div class="flex items-center justify-between flex-wrap
                        gap-3 mb-5">

                <h2 class="text-lg font-bold">
                    <?= $mode === 'edit'
                        ? 'Edit Daily Purchasing Report'
                        : 'Enter Daily Purchasing Report' ?>
                </h2>

                <?php if ($selectedReport): ?>
                    <span class="text-sm text-gray-500">
                        Report #<?= (int) $selectedReport['report_id'] ?>
                    </span>
                <?php endif; ?>

            </div>
<!-- EXCEL IMPORT SECTION -->
<div class="mb-6 p-5 rounded-xl border-2 border-dashed border-green-300
            bg-green-50">

    <div class="flex flex-col md:flex-row md:items-center
                md:justify-between gap-3 mb-4">

        <div>
            <h3 class="text-lg font-bold text-green-900">
                <i class="fas fa-file-excel mr-2"></i>
                Import Activities from Excel
            </h3>

            <p class="text-sm text-gray-600 mt-1">
                Upload an Excel file to automatically populate your
                purchasing activities.
            </p>
        </div>

        <a href="purchasing_daily_report.php?action=template"
           class="inline-flex items-center justify-center gap-2
                  bg-green-700 hover:bg-green-800 text-white
                  px-4 py-2 rounded-lg text-sm font-semibold">

            <i class="fas fa-download"></i>
            Download Excel Template

        </a>

    </div>

    <form method="POST"
          action="purchasing_daily_report.php"
          enctype="multipart/form-data"
          class="space-y-4">

        <input type="hidden" name="action" value="import">

        <input type="hidden"
               name="csrf_token"
               value="<?= purchasingH($csrfToken) ?>">

        <input type="hidden"
               name="report_id"
               value="<?= (int) ($selectedReport['report_id'] ?? 0) ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div>
                <label class="purchasing-label">
                    Reporting Date *
                </label>

                <input type="date"
                       name="report_date"
                       class="purchasing-input"
                       value="<?= purchasingH($defaultDate) ?>"
                       max="<?= purchasingH($today) ?>"
                       required>
            </div>

            <div>
                <label class="purchasing-label">
                    Select Excel File *
                </label>

                <input type="file"
                       name="excel_file"
                       accept=".xlsx,.xls"
                       class="purchasing-input bg-white"
                       required>
            </div>

        </div>

        <div class="flex flex-wrap items-center gap-3">

            <button type="submit"
                    class="bg-green-700 hover:bg-green-800
                           text-white px-6 py-2.5 rounded-lg
                           font-semibold">

                <i class="fas fa-upload mr-2"></i>
                Import Excel Activities

            </button>

            <span class="text-xs text-gray-500">
                Supported formats: XLSX and XLS. Maximum size: 5 MB.
            </span>

        </div>

    </form>

</div>
<!-- END EXCEL IMPORT SECTION -->
            <form method="POST"
                  action="purchasing_daily_report.php"
                  id="purchasingReportForm">

                <input type="hidden" name="action" value="save">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= purchasingH($csrfToken) ?>">

                <input type="hidden"
                       name="report_id"
                       value="<?= (int) ($selectedReport['report_id'] ?? 0) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

                    <div>
                        <label class="purchasing-label">
                            Reporting Date *
                        </label>

                        <input type="date"
                               name="report_date"
                               class="purchasing-input"
                               value="<?= purchasingH($defaultDate) ?>"
                               max="<?= purchasingH($today) ?>"
                               required>
                    </div>

                    <div>
                        <label class="purchasing-label">
                            Staff Member
                        </label>

                        <input type="text"
                               class="purchasing-input bg-gray-100"
                               value="<?= purchasingH($staffName) ?>"
                               readonly>

                        <p class="text-xs text-gray-500 mt-1">
                            The report is linked to your employee account.
                        </p>
                    </div>

                </div>

                <div class="flex flex-col sm:flex-row sm:items-center
                            justify-between gap-3 mb-4">

                    <h3 class="font-bold text-gray-800">
                        Activities
                    </h3>

                    <button type="button"
                            id="addActivityBtn"
                            class="border border-green-600 text-green-700
                                   hover:bg-green-50 px-4 py-2 rounded-lg
                                   font-semibold">
                        + Add Activity
                    </button>

                </div>

                <div id="activitiesContainer" class="space-y-4">

                    <?php foreach ($formActivities as $index => $activity): ?>

                        <div class="activity-card activity-row">

                            <div class="flex justify-between items-center mb-4">

                                <h4 class="font-semibold activity-heading">
                                    Activity <?= $index + 1 ?>
                                </h4>

                                <button type="button"
                                        class="remove-activity text-red-600
                                               hover:text-red-800 text-sm
                                               font-semibold">
                                    Remove
                                </button>

                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2
                                        gap-4 mb-4">

                                <div>
                                    <label class="purchasing-label">
                                        Description *
                                    </label>

                                    <textarea name="description[]"
                                              rows="3"
                                              class="purchasing-input"
                                              required
                                              placeholder="Describe the purchasing activity..."><?= purchasingH(
                                                  $activity['description']
                                              ) ?></textarea>
                                </div>

                                <div>
                                    <label class="purchasing-label">
                                        Indicators of Performance
                                    </label>

                                    <textarea name="performance_indicators[]"
                                              rows="3"
                                              class="purchasing-input"
                                              placeholder="Purchase targets, quantities, KPIs or measures..."><?= purchasingH(
                                                  $activity['performance_indicators'] ?? ''
                                              ) ?></textarea>
                                </div>

                                <div>
                                    <label class="purchasing-label">
                                        Outcome
                                    </label>

                                    <textarea name="outcome[]"
                                              rows="3"
                                              class="purchasing-input"
                                              placeholder="Items procured, deliveries received, cost savings or results..."><?= purchasingH(
                                                  $activity['outcome'] ?? ''
                                              ) ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                                    <div>
                                        <label class="purchasing-label">
                                            Routine / Planned *
                                        </label>

                                        <select name="activity_type[]"
                                                class="purchasing-input"
                                                required>

                                            <?php foreach ($activityTypes as $type): ?>
                                                <option
                                                    value="<?= purchasingH($type) ?>"
                                                    <?= ($activity['activity_type'] ?? 'Routine') === $type
                                                        ? 'selected' : '' ?>>
                                                    <?= purchasingH($type) ?>
                                                </option>
                                            <?php endforeach; ?>

                                        </select>
                                    </div>

                                    <div>
                                        <label class="purchasing-label">
                                            Status *
                                        </label>

                                        <select name="activity_status[]"
                                                class="purchasing-input"
                                                required>

                                            <?php foreach ($statuses as $status): ?>
                                                <option
                                                    value="<?= purchasingH($status) ?>"
                                                    <?= ($activity['status'] ?? 'Pending') === $status
                                                        ? 'selected' : '' ?>>
                                                    <?= purchasingH($status) ?>
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
                            class="btn-gold px-6 py-2.5 rounded-lg font-semibold">
                        <?= $mode === 'edit'
                            ? 'Update Report'
                            : 'Save Daily Report' ?>
                    </button>

                    <a href="purchasing_daily_report.php"
                       class="border border-gray-300 bg-white px-6 py-2.5
                              rounded-lg hover:bg-gray-50">
                        Cancel
                    </a>

                </div>

            </form>
        </div>

    <?php endif; ?>

    <!-- View a selected report -->
    <?php if ($mode === 'view' && $selectedReport): ?>

        <div class="purchasing-card p-5 mb-8">

            <div class="flex flex-col sm:flex-row sm:items-start
                        sm:justify-between gap-4 mb-5">

                <div>
                    <h2 class="text-xl font-bold">
                        Purchasing Report Details
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Reporting date:
                        <strong><?= purchasingH(
                            $selectedReport['report_date']
                        ) ?></strong>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">

                    <a href="purchasing_daily_report.php?mode=edit&amp;report_id=<?= (int) $selectedReport['report_id'] ?>"
                       class="bg-blue-600 hover:bg-blue-700 text-white
                              px-4 py-2 rounded-lg">
                        Edit
                    </a>

                    <a href="purchasing_daily_report.php?action=export&amp;report_id=<?= (int) $selectedReport['report_id'] ?>"
                       class="bg-green-700 hover:bg-green-800 text-white
                              px-4 py-2 rounded-lg">
                        Download Excel
                    </a>

                </div>
            </div>

            <p class="text-sm mb-4">
                Staff member:
                <strong><?= purchasingH(
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

                                    <td class="border p-3 whitespace-pre-wrap"><?= purchasingH(
                                        $activity['description']
                                    ) ?></td>

                                    <td class="border p-3 whitespace-pre-wrap"><?= purchasingH(
                                        $activity['performance_indicators']
                                    ) ?></td>

                                    <td class="border p-3 whitespace-pre-wrap"><?= purchasingH(
                                        $activity['outcome']
                                    ) ?></td>

                                    <td class="border p-3">
                                        <?= purchasingH(
                                            $activity['activity_type']
                                        ) ?>
                                    </td>

                                    <td class="border p-3">
                                        <?= purchasingH($activity['status']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

    <!-- Report history -->
    <div class="purchasing-card p-5">

        <div class="flex flex-col sm:flex-row sm:items-center
                    sm:justify-between gap-3 mb-5">

            <div>
                <h2 class="text-lg font-bold">
                    My Purchasing Daily Reports
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    Reports are grouped by date with activity summaries.
                </p>
            </div>

            <input type="search"
                   id="reportSearch"
                   class="purchasing-input sm:max-w-xs"
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
                                No purchasing daily reports have been recorded yet.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($reports as $report): ?>

                            <tr class="report-row hover:bg-gray-50">

                                <td class="border p-3 font-medium">
                                    <?= purchasingH($report['report_date']) ?>
                                </td>

                                <td class="border p-3 text-center">
                                    <?= (int) $report['activity_count'] ?>
                                </td>

                                <td class="border p-3 text-center text-green-700">
                                    <?= (int) $report['completed_count'] ?>
                                </td>

                                <td class="border p-3 text-center text-amber-700">
                                    <?= (int) $report['pending_count'] ?>
                                </td>

                                <td class="border p-3 text-center text-blue-700">
                                    <?= (int) $report['in_progress_count'] ?>
                                </td>

                                <td class="border p-3">
                                    <?= purchasingH($report['updated_at']) ?>
                                </td>

                                <td class="border p-3">

                                    <div class="flex flex-wrap justify-center gap-2">

                                        <a href="purchasing_daily_report.php?mode=view&amp;report_id=<?= (int) $report['report_id'] ?>"
                                           class="text-blue-700 hover:underline font-semibold">
                                            View
                                        </a>

                                        <a href="purchasing_daily_report.php?mode=edit&amp;report_id=<?= (int) $report['report_id'] ?>"
                                           class="text-amber-700 hover:underline font-semibold">
                                            Edit
                                        </a>

                                        <a href="purchasing_daily_report.php?action=export&amp;report_id=<?= (int) $report['report_id'] ?>"
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
        Lynntech Manager | Purchasing Daily Reporting
    </p>

</div>

<script>
(function () {

    const container = document.getElementById('activitiesContainer');
    const addButton = document.getElementById('addActivityBtn');
    const form = document.getElementById('purchasingReportForm');

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
                    <label class="purchasing-label">Description *</label>

                    <textarea name="description[]" rows="3"
                        class="purchasing-input" required
                        placeholder="Describe the purchasing activity..."></textarea>
                </div>

                <div>
                    <label class="purchasing-label">
                        Indicators of Performance
                    </label>

                    <textarea name="performance_indicators[]" rows="3"
                        class="purchasing-input"
                        placeholder="Purchase targets, quantities, KPIs or measures..."></textarea>
                </div>

                <div>
                    <label class="purchasing-label">Outcome</label>

                    <textarea name="outcome[]" rows="3"
                        class="purchasing-input"
                        placeholder="Items procured, deliveries received, cost savings or results..."></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div>
                        <label class="purchasing-label">
                            Routine / Planned *
                        </label>

                        <select name="activity_type[]"
                            class="purchasing-input" required>

                            <option value="Routine">Routine</option>
                            <option value="Planned">Planned</option>

                        </select>
                    </div>

                    <div>
                        <label class="purchasing-label">Status *</label>

                        <select name="activity_status[]"
                            class="purchasing-input" required>

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

            if (
                container.querySelectorAll('.activity-row').length >=
                maxActivities
            ) {
                alert('You can add up to 100 activities per report.');
                return;
            }

            container.appendChild(createActivityRow());

            updateActivityRows();

            const newRow = container.lastElementChild;

            const description = newRow.querySelector(
                '[name="description[]"]'
            );

            if (description) {
                description.focus();
            }
        });
    }

    if (container) {

        container.addEventListener('click', function (event) {

            const target = event.target;

            if (!(target instanceof Element)) return;

            const removeButton = target.closest('.remove-activity');

            if (!removeButton || removeButton.disabled) return;

            const rows = container.querySelectorAll('.activity-row');

            if (rows.length <= 1) return;

            removeButton.closest('.activity-row').remove();

            updateActivityRows();
        });

        updateActivityRows();
    }

    if (form && container) {

        form.addEventListener('submit', function (event) {

            const rows = container.querySelectorAll('.activity-row');

            if (rows.length < 1) {
                event.preventDefault();
                alert('Please add at least one activity.');
                return;
            }

            let missingDescription = false;

            rows.forEach(function (row) {

                const description = row.querySelector(
                    '[name="description[]"]'
                );

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