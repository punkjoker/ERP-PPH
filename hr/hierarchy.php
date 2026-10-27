
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not found. Check the path to db_con.php.');
}

$conn->set_charset('utf8mb4');

if (empty($_SESSION['hierarchy_csrf'])) {
    $_SESSION['hierarchy_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'success';

function escapeHtml($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function employeeName(array $employee): string
{
    return trim(
        ($employee['first_name'] ?? '') . ' ' .
        ($employee['last_name'] ?? '')
    );
}

/*
|--------------------------------------------------------------------------
| Save / Update / Remove reporting relationships
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['hierarchy_csrf'], $submittedToken)
    ) {
        $message = 'Invalid request token. Refresh the page and try again.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'save') {
                $employeeId = filter_var(
                    $_POST['employee_id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                $managerValue = $_POST['reports_to_employee_id'] ?? '';

                $managerId = null;

                if ($managerValue !== '') {
                    $managerId = filter_var(
                        $managerValue,
                        FILTER_VALIDATE_INT
                    );
                }

                if (!$employeeId || $employeeId < 1) {
                    throw new RuntimeException('Select a valid employee.');
                }

                if (
                    $managerValue !== '' &&
                    (!$managerId || $managerId < 1)
                ) {
                    throw new RuntimeException('Select a valid manager.');
                }

                if ($managerId !== null && $managerId === $employeeId) {
                    throw new RuntimeException(
                        'An employee cannot report to themselves.'
                    );
                }

                // Verify that the employee is active.
                $stmt = $conn->prepare(
                    "SELECT employee_id
                     FROM employees
                     WHERE employee_id = ?
                       AND LOWER(TRIM(status)) = 'active'
                     LIMIT 1"
                );
                $stmt->bind_param('i', $employeeId);
                $stmt->execute();
                $employeeResult = $stmt->get_result();
                $employeeExists = $employeeResult->num_rows > 0;
                $stmt->close();

                if (!$employeeExists) {
                    throw new RuntimeException(
                        'The selected employee is not active or does not exist.'
                    );
                }

                // Verify the selected manager.
                if ($managerId !== null) {
                    $stmt = $conn->prepare(
                        "SELECT employee_id
                         FROM employees
                         WHERE employee_id = ?
                           AND LOWER(TRIM(status)) = 'active'
                         LIMIT 1"
                    );
                    $stmt->bind_param('i', $managerId);
                    $stmt->execute();
                    $managerResult = $stmt->get_result();
                    $managerExists = $managerResult->num_rows > 0;
                    $stmt->close();

                    if (!$managerExists) {
                        throw new RuntimeException(
                            'The selected manager is not active or does not exist.'
                        );
                    }

                    /*
                     * Prevent circular reporting:
                     * Walk upward from the proposed manager. If the employee
                     * being edited appears in that chain, reject the change.
                     */
                    $currentManager = $managerId;
                    $visited = [];

                    while ($currentManager !== null) {
                        if ($currentManager === $employeeId) {
                            throw new RuntimeException(
                                'This assignment would create a reporting cycle.'
                            );
                        }

                        if (isset($visited[$currentManager])) {
                            throw new RuntimeException(
                                'An existing reporting cycle was detected. Correct it first.'
                            );
                        }

                        $visited[$currentManager] = true;

                        $stmt = $conn->prepare(
                            "SELECT reports_to_employee_id
                             FROM organization_hierarchy
                             WHERE employee_id = ?
                             LIMIT 1"
                        );
                        $stmt->bind_param('i', $currentManager);
                        $stmt->execute();
                        $stmt->bind_result($nextManager);
                        $found = $stmt->fetch();
                        $stmt->close();

                        if (!$found || $nextManager === null) {
                            $currentManager = null;
                        } else {
                            $currentManager = (int)$nextManager;
                        }
                    }
                }

                /*
                 * Save the reporting relationship.
                 * NULL manager means the employee is at the top level.
                 */
                if ($managerId === null) {
                    $stmt = $conn->prepare(
                        "INSERT INTO organization_hierarchy
                            (employee_id, reports_to_employee_id)
                         VALUES (?, NULL)
                         ON DUPLICATE KEY UPDATE
                            reports_to_employee_id = NULL"
                    );
                    $stmt->bind_param('i', $employeeId);
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO organization_hierarchy
                            (employee_id, reports_to_employee_id)
                         VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE
                            reports_to_employee_id = VALUES(reports_to_employee_id)"
                    );
                    $stmt->bind_param('ii', $employeeId, $managerId);
                }

                $stmt->execute();
                $stmt->close();

                $_SESSION['hierarchy_flash'] = [
                    'type' => 'success',
                    'text' => 'Reporting relationship saved successfully.'
                ];

                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            }

            if ($action === 'remove') {
                $employeeId = filter_var(
                    $_POST['employee_id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (!$employeeId || $employeeId < 1) {
                    throw new RuntimeException('Invalid employee selected.');
                }

                $stmt = $conn->prepare(
                    "DELETE FROM organization_hierarchy
                     WHERE employee_id = ?"
                );
                $stmt->bind_param('i', $employeeId);
                $stmt->execute();
                $stmt->close();

                $_SESSION['hierarchy_flash'] = [
                    'type' => 'success',
                    'text' => 'Reporting assignment removed. The employee record was not deleted.'
                ];

                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            }
        } catch (Throwable $e) {
            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to save the reporting relationship. Check the database table and try again.';

            $messageType = 'danger';
        }
    }
}

if (!empty($_SESSION['hierarchy_flash'])) {
    $message = $_SESSION['hierarchy_flash']['text'];
    $messageType = $_SESSION['hierarchy_flash']['type'];
    unset($_SESSION['hierarchy_flash']);
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$editId = $editId && $editId > 0 ? $editId : null;

/*
|--------------------------------------------------------------------------
| Load active employees
|--------------------------------------------------------------------------
*/

$employees = [];

$sql = "
    SELECT
        employee_id,
        first_name,
        last_name,
        email,
        department,
        position,
        status
    FROM employees
    WHERE LOWER(TRIM(status)) = 'active'
    ORDER BY department ASC, first_name ASC, last_name ASC
";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $row['employee_id'] = (int)$row['employee_id'];
    $employees[$row['employee_id']] = $row;
}

/*
|--------------------------------------------------------------------------
| Load reporting relationships
|--------------------------------------------------------------------------
*/

$managerOf = [];
$reports = [];

$result = $conn->query(
    "SELECT employee_id, reports_to_employee_id
     FROM organization_hierarchy"
);

while ($row = $result->fetch_assoc()) {
    $employeeId = (int)$row['employee_id'];

    $managerId = $row['reports_to_employee_id'] !== null
        ? (int)$row['reports_to_employee_id']
        : null;

    $managerOf[$employeeId] = $managerId;

    if ($managerId !== null) {
        $reports[$managerId][] = $employeeId;
    }
}

/*
|--------------------------------------------------------------------------
| Search employees by name, department, position or ID
|--------------------------------------------------------------------------
*/

$filteredEmployees = [];

foreach ($employees as $id => $employee) {
    $name = employeeName($employee);

    $managerId = $managerOf[$id] ?? null;
    $managerName = (
        $managerId !== null &&
        isset($employees[$managerId])
    ) ? employeeName($employees[$managerId]) : '';

    $haystack = strtolower(
        $id . ' ' .
        $name . ' ' .
        ($employee['department'] ?? '') . ' ' .
        ($employee['position'] ?? '') . ' ' .
        ($employee['email'] ?? '') . ' ' .
        $managerName
    );

    if ($search === '' || strpos($haystack, strtolower($search)) !== false) {
        $filteredEmployees[$id] = $employee;
    }
}

$totalEmployees = count($employees);
$assignedEmployees = 0;

foreach ($employees as $id => $employee) {
    if (array_key_exists($id, $managerOf)) {
        $assignedEmployees++;
    }
}

$topLevelCount = 0;

foreach ($employees as $id => $employee) {
    $managerId = $managerOf[$id] ?? null;

    if (
        $managerId === null ||
        !isset($employees[$managerId])
    ) {
        $topLevelCount++;
    }
}

$editEmployee = $editId && isset($employees[$editId])
    ? $employees[$editId]
    : null;

$editManager = $editId
    ? ($managerOf[$editId] ?? null)
    : null;

/*
|--------------------------------------------------------------------------
| Render the organization chart
|--------------------------------------------------------------------------
*/

function renderEmployeeNode(
    int $employeeId,
    array $employees,
    array $reports,
    array $managerOf,
    array $searchMatches,
    array &$visited
): void {
    if (!isset($employees[$employeeId]) || isset($visited[$employeeId])) {
        return;
    }

    $visited[$employeeId] = true;
    $employee = $employees[$employeeId];
    $name = employeeName($employee);

    $department = trim($employee['department'] ?? '');
    $position = trim($employee['position'] ?? '');
    $children = $reports[$employeeId] ?? [];

    $matchesSearch = isset($searchMatches[$employeeId]);
    ?>
    <li class="org-node">
        <div class="org-card <?= $matchesSearch ? 'search-match' : '' ?>">
            <div class="org-avatar">
                <?= escapeHtml(
                    strtoupper(
                        substr($employee['first_name'] ?? '?', 0, 1) .
                        substr($employee['last_name'] ?? '', 0, 1)
                    )
                ) ?>
            </div>

            <div class="org-name"><?= escapeHtml($name) ?></div>

            <div class="org-position">
                <?= escapeHtml($position ?: 'Position not specified') ?>
            </div>

            <div class="org-department">
                <?= escapeHtml($department ?: 'Department not specified') ?>
            </div>

            <div class="org-meta">
                Employee ID: <?= (int)$employeeId ?>
            </div>

            <div class="org-actions">
                <a
                    href="?edit=<?= (int)$employeeId ?>"
                    class="btn-small btn-edit"
                >Manage</a>
            </div>
        </div>

        <?php if (!empty($children)): ?>
            <ul class="org-children">
                <?php
                foreach ($children as $childId) {
                    renderEmployeeNode(
                        (int)$childId,
                        $employees,
                        $reports,
                        $managerOf,
                        $searchMatches,
                        $visited
                    );
                }
                ?>
            </ul>
        <?php endif; ?>
    </li>
    <?php
}
?>

<?php
// Keep the existing HR navigation.
$navbarPath = __DIR__ . '/navbar.php';

if (file_exists($navbarPath)) {
    require_once $navbarPath;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Hierarchy | Lynntech</title>

    <style>
        :root {
            --navy: #172b4d;
            --gold: #c99a32;
            --green: #198754;
            --background: #f4f6f9;
            --border: #e1e6ed;
            --text: #273449;
            --muted: #718096;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        .hierarchy-container {
            max-width: 1600px;
            margin: 25px auto;
            padding: 0 22px 35px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .page-header h1 {
            color: var(--navy);
            margin: 0 0 8px;
            font-size: 27px;
        }

        .page-header p {
            color: var(--muted);
            margin: 0;
        }

        .header-icon {
            background: #fff3d2;
            color: #896313;
            padding: 13px 17px;
            border-radius: 12px;
            font-size: 25px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(23, 43, 77, .04);
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--navy);
        }

        .panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 23px;
            margin-bottom: 24px;
            box-shadow: 0 3px 12px rgba(23, 43, 77, .04);
        }

        .panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .panel-heading h2 {
            margin: 0;
            color: var(--navy);
            font-size: 19px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #40516a;
        }

        input, select {
            width: 100%;
            min-width: 0;
            padding: 11px 12px;
            border: 1px solid #ccd4df;
            border-radius: 7px;
            font-size: 14px;
            background: #fff;
            color: var(--text);
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201, 154, 50, .13);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            border: 0;
            border-radius: 7px;
            padding: 11px 16px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--navy);
            color: white;
        }

        .btn-primary:hover {
            background: #243f68;
        }

        .btn-gold {
            background: var(--gold);
            color: #1f2937;
        }

        .btn-secondary {
            background: #edf1f6;
            color: var(--navy);
        }

        .btn-danger {
            background: #b42332;
            color: white;
        }

        .alert {
            padding: 14px 17px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert.success {
            background: #dff5e7;
            color: #17643a;
        }

        .alert.danger {
            background: #fde5e5;
            color: #9c2525;
        }

        .search-row {
            display: flex;
            gap: 10px;
            width: 100%;
            max-width: 650px;
        }

        .search-row input {
            flex: 1;
        }

        .chart-scroll {
    width: 100%;
    overflow: auto;
    padding: 8px 2px 20px;
    min-height: 0;
}

.org-tree {
    width: max-content;
    min-width: 100%;
    padding: 8px 2px;
    text-align: center;
}

        .org-tree ul {
    position: relative;
    display: flex;
    justify-content: center;
    padding: 18px 0 0;
    margin: 0;
}

.org-tree li {
    list-style: none;
    position: relative;
    padding: 16px 4px 0;
}

        .org-tree li::before,
.org-tree li::after {
    content: "";
    position: absolute;
    top: 0;
    right: 50%;
    width: 50%;
    height: 16px;
    border-top: 1px solid #cbd4e0;
}

.org-tree li::after {
    right: auto;
    left: 50%;
    border-left: 1px solid #cbd4e0;
}

.org-tree ul ul::before {
    content: "";
    position: absolute;
    top: 0;
    left: 50%;
    height: 18px;
    border-left: 1px solid #cbd4e0;
}
        .org-tree > ul {
            padding-top: 0;
        }

        .org-tree > ul > li {
            padding-top: 0;
        }

        .org-tree > ul > li::before,
        .org-tree > ul > li::after {
            display: none;
        }

        .org-card {
    display: inline-flex;
    flex-direction: column;
    align-items: center;

    width: 155px;
    min-height: 0;
    vertical-align: top;

    padding: 10px 8px;

    background: #fff;
    border: 1px solid #dce3ed;
    border-top: 3px solid var(--gold);
    border-radius: 8px;

    box-shadow: 0 3px 8px rgba(23, 43, 77, .07);
    transition: transform .2s, box-shadow .2s;

    overflow-wrap: anywhere;
}

        .org-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(23, 43, 77, .13);
        }

        .org-card.search-match {
            border: 2px solid var(--green);
            border-top: 4px solid var(--green);
        }

        .org-avatar {
    display: flex;
    justify-content: center;
    align-items: center;

    width: 34px;
    height: 34px;
    margin-bottom: 6px;

    background: #edf1f8;
    color: var(--navy);
    border-radius: 50%;

    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
}

.org-name {
    font-size: 12px;
    font-weight: 700;
    line-height: 1.3;
    color: var(--navy);
    overflow-wrap: anywhere;
}

.org-position {
    margin-top: 4px;
    font-size: 10px;
    line-height: 1.3;
    color: #53647c;
}

.org-department {
    margin-top: 5px;
    padding: 4px 6px;

    background: #fff3d2;
    border-radius: 12px;

    font-size: 9px;
    line-height: 1.3;
    color: #745512;
    overflow-wrap: anywhere;
}

.org-meta {
    margin-top: 5px;
    font-size: 9px;
    color: var(--muted);
}

.org-actions {
    margin-top: 7px;
}

.btn-small {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 10px;
    font-weight: 700;
}
        .btn-small {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-edit {
            background: #e8edf5;
            color: var(--navy);
        }

        .empty-state {
            text-align: center;
            padding: 45px 20px;
            color: var(--muted);
        }

        .empty-state strong {
            display: block;
            color: var(--navy);
            font-size: 16px;
            margin-bottom: 8px;
        }

        .hint {
            font-size: 12px;
            color: var(--muted);
            margin-top: 7px;
            line-height: 1.5;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            text-align: left;
            padding: 13px 12px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        th {
            background: #f7f9fc;
            color: #52627a;
        }

        td {
            color: #34445b;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: #e0f4e7;
            color: #187342;
        }

        .table-actions {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .inline-form {
            display: inline;
            margin: 0;
        }

        @media (max-width: 800px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .hierarchy-container {
                padding: 0 12px 25px;
            }

            .panel {
                padding: 16px;
            }

            .search-row {
                max-width: 100%;
            }
        }

        @media print {
            body {
                background: #fff;
            }

            .no-print,
            .org-actions,
            .header-icon {
                display: none !important;
            }

            .hierarchy-container {
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .panel,
            .stat-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .chart-scroll {
                overflow: visible;
            }

            .org-card {
                box-shadow: none;
                break-inside: avoid;
            }

            .org-tree {
                transform-origin: top left;
            }
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
    <div class="page-header">
        <div>
            <h1>Organization Hierarchy</h1>
            <p>Manage employees, reporting lines and the company structure.</p>
        </div>

        <div class="header-icon">🏢</div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert <?= escapeHtml($messageType) ?>">
            <?= escapeHtml($message) ?>
        </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-label">Active Employees</div>
            <div class="stat-value"><?= $totalEmployees ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Employees With Hierarchy Records</div>
            <div class="stat-value"><?= $assignedEmployees ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Top-Level Employees</div>
            <div class="stat-value"><?= $topLevelCount ?></div>
        </div>
    </div>

    <div class="panel no-print" id="assignment-form">
        <div class="panel-heading">
            <h2>
                <?= $editEmployee ? 'Update Reporting Relationship' : 'Assign Reporting Relationship' ?>
            </h2>

            <?php if ($editEmployee): ?>
                <a href="hierarchy.php" class="btn btn-secondary">Cancel Edit</a>
            <?php endif; ?>
        </div>

        <?php if ($totalEmployees === 0): ?>
            <div class="empty-state">
                <strong>No active employees found.</strong>
                Add or activate employees before creating reporting relationships.
            </div>
        <?php else: ?>
            <form method="POST" action="hierarchy.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapeHtml($_SESSION['hierarchy_csrf']) ?>"
                >

                <input type="hidden" name="action" value="save">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="employee_id">Employee *</label>

                        <select
                            name="employee_id"
                            id="employee_id"
                            required
                        >
                            <option value="">Select employee</option>

                            <?php foreach ($employees as $id => $employee): ?>
                                <option
                                    value="<?= $id ?>"
                                    <?= $editId === $id ? 'selected' : '' ?>
                                >
                                    <?= escapeHtml(employeeName($employee)) ?>
                                    — ID <?= $id ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="hint">
                            Select the employee whose reporting line you want to manage.
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reports_to_employee_id">Reports To</label>

                        <select
                            name="reports_to_employee_id"
                            id="reports_to_employee_id"
                        >
                            <option value="">No manager / Top level</option>

                            <?php foreach ($employees as $id => $employee): ?>
                                <?php if ($editId !== $id): ?>
                                    <option
                                        value="<?= $id ?>"
                                        <?= $editManager === $id ? 'selected' : '' ?>
                                    >
                                        <?= escapeHtml(employeeName($employee)) ?>
                                        — <?= escapeHtml($employee['position'] ?? 'Position not specified') ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>

                        <div class="hint">
                            Choose the employee's direct supervisor or manager.
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="department_preview">Employee Department</label>

                        <input
                            type="text"
                            id="department_preview"
                            value="<?= escapeHtml($editEmployee['department'] ?? '') ?>"
                            placeholder="Automatically shown when editing"
                            readonly
                        >

                        <div class="hint">
                            Department and position are read from the employee record.
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $editEmployee ? 'Update Relationship' : 'Save Relationship' ?>
                    </button>

                    <a href="hierarchy.php" class="btn btn-secondary">
                        Clear Form
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-heading">
            <h2>Organization Chart</h2>

            <button
                type="button"
                class="btn btn-gold no-print"
                onclick="window.print()"
            >
                Print Chart
            </button>
        </div>

        <p class="hint">
            Each card represents an active employee. Use Manage to edit that
            employee's reporting relationship. Employees without a manager
            appear at the top level.
        </p>

        <div class="chart-scroll">
            <?php if ($totalEmployees === 0): ?>
                <div class="empty-state">
                    <strong>No employees to display.</strong>
                    Active employee records will appear here.
                </div>
            <?php else: ?>
                <div class="org-tree">
                    <ul>
                        <?php
                        $visited = [];

                        // Show employees with no manager, or whose manager
                        // is not currently active, as top-level nodes.
                        foreach ($employees as $id => $employee) {
                            $managerId = $managerOf[$id] ?? null;

                            if (
                                $managerId === null ||
                                !isset($employees[$managerId])
                            ) {
                                renderEmployeeNode(
                                    (int)$id,
                                    $employees,
                                    $reports,
                                    $managerOf,
                                    $filteredEmployees,
                                    $visited
                                );
                            }
                        }

                        // Avoid hiding employees if old/corrupt data contains
                        // a reporting cycle.
                        foreach ($employees as $id => $employee) {
                            if (!isset($visited[$id])) {
                                renderEmployeeNode(
                                    (int)$id,
                                    $employees,
                                    $reports,
                                    $managerOf,
                                    $filteredEmployees,
                                    $visited
                                );
                            }
                        }
                        ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel no-print">
        <div class="panel-heading">
            <h2>Employee Reporting Lines</h2>
        </div>

        <form method="GET" action="hierarchy.php" class="search-row">
            <input
                type="search"
                name="search"
                placeholder="Search name, employee ID, department or position..."
                value="<?= escapeHtml($search) ?>"
            >

            <button type="submit" class="btn btn-primary">Search</button>

            <a href="hierarchy.php" class="btn btn-secondary">Clear</a>
        </form>

        <div style="height: 18px;"></div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Employee Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Reports To</th>
                        <th>Assignment</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($filteredEmployees)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:25px;">
                                No employees match your search.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredEmployees as $id => $employee): ?>
                            <?php
                            $managerId = $managerOf[$id] ?? null;

                            $managerName = (
                                $managerId !== null &&
                                isset($employees[$managerId])
                            )
                                ? employeeName($employees[$managerId])
                                : 'Top level / Not assigned';
                            ?>

                            <tr>
                                <td><?= $id ?></td>

                                <td>
                                    <strong>
                                        <?= escapeHtml(employeeName($employee)) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= escapeHtml($employee['department'] ?: '—') ?>
                                </td>

                                <td>
                                    <?= escapeHtml($employee['position'] ?: '—') ?>
                                </td>

                                <td><?= escapeHtml($managerName) ?></td>

                                <td>
                                    <?php if (array_key_exists($id, $managerOf)): ?>
                                        <span class="badge">Configured</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#fff0cf;color:#78540c;">
                                            Not configured
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            href="hierarchy.php?edit=<?= $id ?>#assignment-form"
                                            class="btn-small btn-edit"
                                        >
                                            Manage
                                        </a>

                                        <?php if (array_key_exists($id, $managerOf)): ?>
                                            <form
                                                method="POST"
                                                action="hierarchy.php"
                                                class="inline-form"
                                                onsubmit="return confirm('Remove this reporting assignment? The employee record will remain unchanged.');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= escapeHtml($_SESSION['hierarchy_csrf']) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="remove"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="employee_id"
                                                    value="<?= $id ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn-small btn-danger"
                                                >
                                                    Remove
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    const employeeSelect = document.getElementById('employee_id');
    const departmentPreview = document.getElementById('department_preview');

    const employeeDetails = <?= json_encode(
        array_map(
            static function ($employee) {
                return [
                    'department' => $employee['department'] ?? ''
                ];
            },
            $employees
        ),
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    ) ?>;

    if (employeeSelect && departmentPreview) {
        employeeSelect.addEventListener('change', function () {
            const selected = employeeDetails[this.value];

            departmentPreview.value = selected
                ? selected.department
                : '';
        });
    }
</script>

</body>
</html>
