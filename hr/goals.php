
<?php
session_start();

date_default_timezone_set('Africa/Nairobi');

require_once 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* =========================================================
   HELPER FUNCTIONS
========================================================= */
function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   CSRF TOKEN
========================================================= */
if (empty($_SESSION['goals_csrf_token'])) {
    $_SESSION['goals_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['goals_csrf_token'];
$successMessage = '';
$errorMessage = '';

/* =========================================================
   FLASH MESSAGES
========================================================= */
if (!empty($_SESSION['goals_flash'])) {
    $successMessage = $_SESSION['goals_flash'];
    unset($_SESSION['goals_flash']);
}

/* =========================================================
   LOAD EMPLOYEES
   Uses the same users/groups structure as disciplinary.php.
========================================================= */
$employees = [];

$employeeQuery = $conn->query("
    SELECT u.user_id, u.full_name
    FROM users u
    INNER JOIN `groups` g ON u.group_id = g.group_id
    WHERE LOWER(g.group_name) = 'staff'
    ORDER BY u.full_name ASC
");

while ($employee = $employeeQuery->fetch_assoc()) {
    $employees[] = $employee;
}

/* =========================================================
   HANDLE ADD AND UPDATE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedEmployeeIds = [];

    try {
        if (
            empty($_POST['csrf_token']) ||
            !hash_equals(
                $_SESSION['goals_csrf_token'],
                $_POST['csrf_token']
            )
        ) {
            throw new RuntimeException(
                'Your session token is invalid. Refresh the page and try again.'
            );
        }

        $action = $_POST['action'] ?? '';

        $goalTitle = trim($_POST['goal_title'] ?? '');
        $goalDescription = trim($_POST['goal_description'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $tasksDone = trim($_POST['tasks_done'] ?? '');
        $overseenBy = trim($_POST['overseen_by'] ?? '');
        $status = $_POST['status'] ?? 'Pending';
        $comments = trim($_POST['comments'] ?? '');

        $rawEmployeeIds = $_POST['employee_ids'] ?? [];

        if (!is_array($rawEmployeeIds)) {
            throw new RuntimeException(
                'Please select employees using the employee list.'
            );
        }

        foreach ($rawEmployeeIds as $rawId) {
            $id = filter_var($rawId, FILTER_VALIDATE_INT);

            if (!$id || $id < 1) {
                throw new RuntimeException(
                    'One or more selected employee IDs are invalid.'
                );
            }

            $submittedEmployeeIds[] = (int)$id;
        }

        $submittedEmployeeIds = array_values(
            array_unique($submittedEmployeeIds)
        );

        if (
            $goalTitle === '' ||
            $goalDescription === '' ||
            $department === '' ||
            $overseenBy === ''
        ) {
            throw new RuntimeException(
                'Please fill in the goal title, description, department, and overseen-by fields.'
            );
        }

        if (
            mb_strlen($goalTitle) > 255 ||
            mb_strlen($department) > 150 ||
            mb_strlen($overseenBy) > 150
        ) {
            throw new RuntimeException(
                'The goal title, department, or overseen-by field is too long.'
            );
        }

        if (!in_array($status, ['Pending', 'Completed'], true)) {
            throw new RuntimeException('Invalid task status selected.');
        }

        if (count($submittedEmployeeIds) === 0) {
            throw new RuntimeException(
                'Please select at least one employee responsible for this task.'
            );
        }

        /*
         * Confirm all selected employees exist and belong to the
         * staff group before saving the assignments.
         */
        $validEmployeeIds = [];

        $employeeCheck = $conn->prepare("
            SELECT u.user_id
            FROM users u
            INNER JOIN `groups` g ON u.group_id = g.group_id
            WHERE LOWER(g.group_name) = 'staff'
              AND u.user_id = ?
        ");

        foreach ($submittedEmployeeIds as $employeeId) {
            $employeeCheck->bind_param('i', $employeeId);
            $employeeCheck->execute();

            $employeeResult = $employeeCheck->get_result();

            if ($employeeResult->num_rows === 1) {
                $validEmployeeIds[] = $employeeId;
            }
        }

        $employeeCheck->close();

        if (count($validEmployeeIds) !== count($submittedEmployeeIds)) {
            throw new RuntimeException(
                'One or more selected employees are no longer available. Refresh and try again.'
            );
        }

        $conn->begin_transaction();

        if ($action === 'add') {
            $stmt = $conn->prepare("
                INSERT INTO goals
                (
                    goal_title,
                    goal_description,
                    department,
                    tasks_done,
                    overseen_by,
                    status,
                    comments
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                'sssssss',
                $goalTitle,
                $goalDescription,
                $department,
                $tasksDone,
                $overseenBy,
                $status,
                $comments
            );

            $stmt->execute();
            $goalId = (int)$conn->insert_id;
            $stmt->close();

        } elseif ($action === 'update') {
            $goalId = filter_var(
                $_POST['goal_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$goalId || $goalId < 1) {
                throw new RuntimeException('Invalid goal ID.');
            }

            $checkGoal = $conn->prepare("
                SELECT goal_id
                FROM goals
                WHERE goal_id = ?
            ");

            $checkGoal->bind_param('i', $goalId);
            $checkGoal->execute();

            if ($checkGoal->get_result()->num_rows === 0) {
                $checkGoal->close();
                throw new RuntimeException(
                    'The goal you are trying to edit was not found.'
                );
            }

            $checkGoal->close();

            $stmt = $conn->prepare("
                UPDATE goals
                SET
                    goal_title = ?,
                    goal_description = ?,
                    department = ?,
                    tasks_done = ?,
                    overseen_by = ?,
                    status = ?,
                    comments = ?
                WHERE goal_id = ?
            ");

            $stmt->bind_param(
                'sssssssi',
                $goalTitle,
                $goalDescription,
                $department,
                $tasksDone,
                $overseenBy,
                $status,
                $comments,
                $goalId
            );

            $stmt->execute();
            $stmt->close();

            // Replace the employee assignments with the updated selection.
            $deleteAssignments = $conn->prepare("
                DELETE FROM goal_employees WHERE goal_id = ?
            ");

            $deleteAssignments->bind_param('i', $goalId);
            $deleteAssignments->execute();
            $deleteAssignments->close();

        } else {
            throw new RuntimeException('Invalid form action.');
        }

        // Save each selected employee against this goal.
        $insertEmployee = $conn->prepare("
            INSERT INTO goal_employees (goal_id, user_id)
            VALUES (?, ?)
        ");

        foreach ($validEmployeeIds as $employeeId) {
            $insertEmployee->bind_param('ii', $goalId, $employeeId);
            $insertEmployee->execute();
        }

        $insertEmployee->close();

        $conn->commit();

        $_SESSION['goals_flash'] = $action === 'update'
            ? 'Goal/task updated successfully.'
            : 'Goal/task added successfully.';

        header('Location: goals.php');
        exit;

    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
            // No active transaction to roll back.
        }

        $errorMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to save the goal/task. Check your database structure and try again.';
    }
}

/* =========================================================
   SEARCH AND PAGE MODE
========================================================= */
$search = trim($_GET['q'] ?? '');

$viewId = filter_var($_GET['view'] ?? null, FILTER_VALIDATE_INT);
$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);

$pageMode = 'list';
$selectedGoal = null;

if ($viewId && $viewId > 0) {
    $pageMode = 'view';
    $selectedId = $viewId;
} elseif ($editId && $editId > 0) {
    $pageMode = 'edit';
    $selectedId = $editId;
} elseif (isset($_GET['add'])) {
    $pageMode = 'add';
    $selectedId = null;
} else {
    $selectedId = null;
}

/* =========================================================
   LOAD SELECTED GOAL
========================================================= */
if ($selectedId !== null) {
    $stmt = $conn->prepare("
        SELECT *
        FROM goals
        WHERE goal_id = ?
    ");

    $stmt->bind_param('i', $selectedId);
    $stmt->execute();

    $selectedGoal = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$selectedGoal) {
        $pageMode = 'list';
        $errorMessage = 'The requested goal/task was not found.';
    }
}

/* =========================================================
   LOAD ASSIGNED EMPLOYEES FOR VIEW / EDIT
========================================================= */
$selectedEmployeeIds = [];

if ($selectedGoal) {
    $stmt = $conn->prepare("
        SELECT user_id
        FROM goal_employees
        WHERE goal_id = ?
    ");

    $stmt->bind_param('i', $selectedGoal['goal_id']);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $selectedEmployeeIds[] = (int)$row['user_id'];
    }

    $stmt->close();
}

/* =========================================================
   FORM VALUES
========================================================= */
$formData = [
    'goal_id' => $selectedGoal['goal_id'] ?? '',
    'goal_title' => $selectedGoal['goal_title'] ?? '',
    'goal_description' => $selectedGoal['goal_description'] ?? '',
    'department' => $selectedGoal['department'] ?? '',
    'tasks_done' => $selectedGoal['tasks_done'] ?? '',
    'overseen_by' => $selectedGoal['overseen_by'] ?? '',
    'status' => $selectedGoal['status'] ?? 'Pending',
    'comments' => $selectedGoal['comments'] ?? ''
];

$formEmployeeIds = $selectedEmployeeIds;

/* Retain submitted values after a validation/database error. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage !== '') {
    $formData = [
        'goal_id' => $_POST['goal_id'] ?? '',
        'goal_title' => $_POST['goal_title'] ?? '',
        'goal_description' => $_POST['goal_description'] ?? '',
        'department' => $_POST['department'] ?? '',
        'tasks_done' => $_POST['tasks_done'] ?? '',
        'overseen_by' => $_POST['overseen_by'] ?? '',
        'status' => $_POST['status'] ?? 'Pending',
        'comments' => $_POST['comments'] ?? ''
    ];

    $formEmployeeIds = $submittedEmployeeIds;

    $pageMode = ($_POST['action'] ?? '') === 'update'
        ? 'edit'
        : 'add';
}

/* =========================================================
   LOAD GOAL LIST
========================================================= */
if ($search !== '') {
    $likeSearch = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT
            g.*,
            (
                SELECT COUNT(*)
                FROM goal_employees ge
                WHERE ge.goal_id = g.goal_id
            ) AS employee_count
        FROM goals g
        WHERE g.goal_title LIKE ?
           OR g.department LIKE ?
           OR g.overseen_by LIKE ?
           OR g.status LIKE ?
        ORDER BY g.created_at DESC
    ");

    $stmt->bind_param(
        'ssss',
        $likeSearch,
        $likeSearch,
        $likeSearch,
        $likeSearch
    );
} else {
    $stmt = $conn->prepare("
        SELECT
            g.*,
            (
                SELECT COUNT(*)
                FROM goal_employees ge
                WHERE ge.goal_id = g.goal_id
            ) AS employee_count
        FROM goals g
        ORDER BY g.created_at DESC
    ");
}

$stmt->execute();
$goals = $stmt->get_result();
$totalGoals = $goals->num_rows;

/* =========================================================
   SUMMARY COUNTS
========================================================= */
$summaryResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(status = 'Completed'), 0) AS completed
    FROM goals
");

$summary = $summaryResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Goals & Tasks | HR Management</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
        }

        .page-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
        }

        .field-label {
            display: block;
            margin-bottom: 7px;
            color: #374151;
            font-size: 14px;
            font-weight: 600;
        }

        .field-input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            outline: none;
            font-size: 14px;
        }

        .field-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 15px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: #b8860b;
            color: white;
        }

        .btn-primary:hover {
            background: #946b08;
        }

        .btn-view {
            background: #2563eb;
            color: white;
        }

        .btn-edit {
            background: #059669;
            color: white;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .table-cell {
            padding: 14px 16px;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }

        .table-heading {
            padding: 14px 16px;
            background: #f9fafb;
            color: #4b5563;
            font-size: 11px;
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .description-text {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            line-height: 1.7;
        }

        .status-pending {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-completed {
            display: inline-block;
            background: #d1fae5;
            color: #065f46;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">

        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                Goals & Tasks
            </h1>

            <p class="text-sm text-gray-500 mt-2">
                Assign departmental goals, track progress and monitor completion.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="goals.php" class="btn btn-secondary">
                All Goals
            </a>

            <a href="goals.php?add=1" class="btn btn-primary">
                + Add New Goal / Task
            </a>
        </div>
    </div>

    <!-- MESSAGES -->
    <?php if ($successMessage !== ''): ?>
        <div class="mb-5 rounded-lg border border-green-200
                    bg-green-50 px-4 py-3 text-sm text-green-800">
            <?= h($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="mb-5 rounded-lg border border-red-200
                    bg-red-50 px-4 py-3 text-sm text-red-800">
            <?= h($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

        <div class="page-card p-5">
            <p class="text-sm text-gray-500">Total Goals / Tasks</p>
            <p class="text-3xl font-bold mt-2">
                <?= number_format((int)$summary['total']) ?>
            </p>
        </div>

        <div class="page-card p-5">
            <p class="text-sm text-gray-500">Pending</p>
            <p class="text-3xl font-bold text-amber-600 mt-2">
                <?= number_format((int)$summary['pending']) ?>
            </p>
        </div>

        <div class="page-card p-5">
            <p class="text-sm text-gray-500">Completed</p>
            <p class="text-3xl font-bold text-green-600 mt-2">
                <?= number_format((int)$summary['completed']) ?>
            </p>
        </div>

    </div>

    <!-- ADD / EDIT FORM -->
    <?php if ($pageMode === 'add' || $pageMode === 'edit'): ?>

        <div class="page-card p-5 md:p-7 mb-7">

            <div class="border-b border-gray-200 pb-4 mb-6">
                <h2 class="text-xl font-bold">
                    <?= $pageMode === 'edit'
                        ? 'Edit Goal / Task'
                        : 'Add New Goal / Task' ?>
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    Complete the details below and assign the responsible employees.
                </p>
            </div>

            <form method="POST" action="goals.php" class="space-y-5">

                <input type="hidden" name="csrf_token"
                       value="<?= h($csrfToken) ?>">

                <input type="hidden" name="action"
                       value="<?= $pageMode === 'edit' ? 'update' : 'add' ?>">

                <?php if ($pageMode === 'edit'): ?>
                    <input type="hidden" name="goal_id"
                           value="<?= h($formData['goal_id']) ?>">
                <?php endif; ?>

                <div>
                    <label for="goal_title" class="field-label">
                        Goal / Task Title <span class="text-red-600">*</span>
                    </label>

                    <input
                        type="text"
                        id="goal_title"
                        name="goal_title"
                        class="field-input"
                        maxlength="255"
                        placeholder="e.g. Complete monthly stock audit"
                        value="<?= h($formData['goal_title']) ?>"
                        required
                    >
                </div>

                <div>
                    <label for="goal_description" class="field-label">
                        Goal / Task Description
                        <span class="text-red-600">*</span>
                    </label>

                    <textarea
                        id="goal_description"
                        name="goal_description"
                        rows="4"
                        class="field-input"
                        placeholder="Describe the goal or work that needs to be done..."
                        required
                    ><?= h($formData['goal_description']) ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div>
                        <label for="department" class="field-label">
                            Department Responsible
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="text"
                            id="department"
                            name="department"
                            class="field-input"
                            maxlength="150"
                            placeholder="e.g. Finance"
                            value="<?= h($formData['department']) ?>"
                            required
                        >
                    </div>

                    <div>
                        <label for="overseen_by" class="field-label">
                            Overseen By <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="text"
                            id="overseen_by"
                            name="overseen_by"
                            class="field-input"
                            maxlength="150"
                            placeholder="Supervisor / manager name"
                            value="<?= h($formData['overseen_by']) ?>"
                            required
                        >
                    </div>

                </div>

                <div>
                    <label for="employee_ids" class="field-label">
                        Employees Responsible
                        <span class="text-red-600">*</span>
                    </label>

                    <p class="text-xs text-gray-500 mb-2">
                        Select one or more staff members. Hold Ctrl on Windows
                        (or Command on Mac) to select multiple names.
                    </p>

                    <select
                        id="employee_ids"
                        name="employee_ids[]"
                        class="field-input"
                        multiple
                        size="6"
                        required
                    >
                        <?php foreach ($employees as $employee): ?>
                            <option
                                value="<?= (int)$employee['user_id'] ?>"
                                <?= in_array(
                                    (int)$employee['user_id'],
                                    array_map('intval', $formEmployeeIds),
                                    true
                                ) ? 'selected' : '' ?>
                            >
                                <?= h($employee['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (count($employees) === 0): ?>
                        <p class="text-sm text-red-600 mt-2">
                            No staff members were found in the users/groups tables.
                            Add staff records or check the employee query.
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="tasks_done" class="field-label">
                        Tasks Done / Progress
                    </label>

                    <textarea
                        id="tasks_done"
                        name="tasks_done"
                        rows="4"
                        class="field-input"
                        placeholder="Record work completed so far, progress made, or leave blank until work begins..."
                    ><?= h($formData['tasks_done']) ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div>
                        <label for="status" class="field-label">
                            Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="field-input"
                            required
                        >
                            <option value="Pending"
                                <?= $formData['status'] === 'Pending'
                                    ? 'selected' : '' ?>>
                                Pending
                            </option>

                            <option value="Completed"
                                <?= $formData['status'] === 'Completed'
                                    ? 'selected' : '' ?>>
                                Completed
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="comments" class="field-label">
                            Comments / Remarks
                        </label>

                        <textarea
                            id="comments"
                            name="comments"
                            rows="3"
                            class="field-input"
                            placeholder="Add any comments or remarks..."
                        ><?= h($formData['comments']) ?></textarea>
                    </div>

                </div>

                <div class="flex flex-col sm:flex-row gap-3
                            pt-4 border-t border-gray-200">

                    <button type="submit" class="btn btn-primary">
                        <?= $pageMode === 'edit'
                            ? 'Save Changes'
                            : 'Save Goal / Task' ?>
                    </button>

                    <a href="goals.php" class="btn btn-secondary">
                        Cancel
                    </a>

                </div>

            </form>
        </div>

    <?php endif; ?>

    <!-- VIEW GOAL / TASK -->
    <?php if ($pageMode === 'view' && $selectedGoal): ?>

        <?php
        $viewEmployeeNames = [];

        $stmt = $conn->prepare("
            SELECT u.full_name
            FROM goal_employees ge
            INNER JOIN users u ON u.user_id = ge.user_id
            WHERE ge.goal_id = ?
            ORDER BY u.full_name ASC
        ");

        $stmt->bind_param('i', $selectedGoal['goal_id']);
        $stmt->execute();

        $viewEmployeeResult = $stmt->get_result();

        while ($employee = $viewEmployeeResult->fetch_assoc()) {
            $viewEmployeeNames[] = $employee['full_name'];
        }

        $stmt->close();
        ?>

        <div class="page-card p-5 md:p-7 mb-7">

            <div class="flex flex-col sm:flex-row sm:items-start
                        sm:justify-between gap-4 border-b border-gray-200
                        pb-5 mb-6">

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider
                              text-blue-700">
                        <?= h($selectedGoal['department']) ?>
                    </p>

                    <h2 class="text-2xl font-bold text-gray-800 mt-2">
                        <?= h($selectedGoal['goal_title']) ?>
                    </h2>

                    <p class="text-sm text-gray-500 mt-2">
                        Overseen by: <?= h($selectedGoal['overseen_by']) ?>
                    </p>

                    <div class="mt-3">
                        <span class="<?= $selectedGoal['status'] === 'Completed'
                            ? 'status-completed'
                            : 'status-pending' ?>">
                            <?= h($selectedGoal['status']) ?>
                        </span>
                    </div>
                </div>

                <a
                    href="goals.php?edit=<?= (int)$selectedGoal['goal_id'] ?>"
                    class="btn btn-edit"
                >
                    Edit Goal / Task
                </a>

            </div>

            <div class="space-y-6">

                <section>
                    <h3 class="font-bold text-gray-800 mb-2">
                        Goal / Task Description
                    </h3>

                    <div class="description-text text-sm text-gray-600">
                        <?= h($selectedGoal['goal_description']) ?>
                    </div>
                </section>

                <section>
                    <h3 class="font-bold text-gray-800 mb-2">
                        Employees Responsible
                    </h3>

                    <?php if (count($viewEmployeeNames) > 0): ?>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($viewEmployeeNames as $employeeName): ?>
                                <span class="rounded-full bg-blue-50
                                             text-blue-700 px-3 py-2 text-sm">
                                    <?= h($employeeName) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">
                            No employees assigned.
                        </p>
                    <?php endif; ?>
                </section>

                <section>
                    <h3 class="font-bold text-gray-800 mb-2">
                        Tasks Done / Progress
                    </h3>

                    <div class="description-text text-sm text-gray-600">
                        <?= $selectedGoal['tasks_done'] !== ''
                            ? h($selectedGoal['tasks_done'])
                            : 'No progress recorded yet.' ?>
                    </div>
                </section>

                <section>
                    <h3 class="font-bold text-gray-800 mb-2">
                        Comments / Remarks
                    </h3>

                    <div class="description-text text-sm text-gray-600">
                        <?= $selectedGoal['comments'] !== ''
                            ? h($selectedGoal['comments'])
                            : 'No comments recorded.' ?>
                    </div>
                </section>

                <section class="border-t border-gray-200 pt-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4
                                text-xs text-gray-500">
                        <p>
                            Created:
                            <?= h($selectedGoal['created_at']) ?>
                        </p>

                        <p>
                            Last Updated:
                            <?= h($selectedGoal['updated_at']) ?>
                        </p>
                    </div>
                </section>

            </div>

            <div class="mt-7">
                <a href="goals.php" class="btn btn-secondary">
                    Back to Goals
                </a>
            </div>

        </div>

    <?php endif; ?>

    <!-- GOALS LIST -->
    <?php if (in_array($pageMode, ['list', 'add', 'edit'], true)): ?>

        <div class="page-card overflow-hidden">

            <div class="p-5 border-b border-gray-200
                        flex flex-col md:flex-row md:items-center
                        md:justify-between gap-4">

                <div>
                    <h2 class="text-lg font-bold text-gray-800">
                        Goals & Tasks List
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Review assigned work, progress and status.
                    </p>
                </div>

                <form method="GET" action="goals.php"
                      class="flex flex-col sm:flex-row gap-2">

                    <input
                        type="text"
                        name="q"
                        class="field-input"
                        placeholder="Search title, department, status..."
                        value="<?= h($search) ?>"
                    >

                    <button type="submit" class="btn btn-view">
                        Search
                    </button>

                    <?php if ($search !== ''): ?>
                        <a href="goals.php" class="btn btn-secondary">
                            Clear
                        </a>
                    <?php endif; ?>

                </form>

            </div>

            <div class="overflow-x-auto">

                <table class="min-w-full divide-y divide-gray-200">

                    <thead>
                        <tr>
                            <th class="table-heading">#</th>
                            <th class="table-heading">Goal / Task</th>
                            <th class="table-heading">Department</th>
                            <th class="table-heading">Employees</th>
                            <th class="table-heading">Overseen By</th>
                            <th class="table-heading">Status</th>
                            <th class="table-heading">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white">

                        <?php if ($totalGoals > 0): ?>

                            <?php $counter = 1; ?>

                            <?php while ($goal = $goals->fetch_assoc()): ?>

                                <tr class="hover:bg-gray-50">

                                    <td class="table-cell text-gray-500">
                                        <?= $counter++ ?>
                                    </td>

                                    <td class="table-cell">
                                        <p class="font-semibold text-gray-800">
                                            <?= h($goal['goal_title']) ?>
                                        </p>

                                        <p class="text-xs text-gray-500 mt-1">
                                            Added:
                                            <?= h(date(
                                                'd M Y',
                                                strtotime($goal['created_at'])
                                            )) ?>
                                        </p>
                                    </td>

                                    <td class="table-cell">
                                        <?= h($goal['department']) ?>
                                    </td>

                                    <td class="table-cell">
                                        <?= (int)$goal['employee_count'] ?>
                                        <?= (int)$goal['employee_count'] === 1
                                            ? 'employee'
                                            : 'employees' ?>
                                    </td>

                                    <td class="table-cell">
                                        <?= h($goal['overseen_by']) ?>
                                    </td>

                                    <td class="table-cell">
                                        <span class="<?= $goal['status'] === 'Completed'
                                            ? 'status-completed'
                                            : 'status-pending' ?>">
                                            <?= h($goal['status']) ?>
                                        </span>
                                    </td>

                                    <td class="table-cell">
                                        <div class="flex flex-wrap gap-2">

                                            <a
                                                href="goals.php?view=<?= (int)$goal['goal_id'] ?>"
                                                class="btn btn-view"
                                            >
                                                View
                                            </a>

                                            <a
                                                href="goals.php?edit=<?= (int)$goal['goal_id'] ?>"
                                                class="btn btn-edit"
                                            >
                                                Edit
                                            </a>

                                        </div>
                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">

                                    <div class="text-gray-400 text-4xl mb-3">
                                        &#128221;
                                    </div>

                                    <p class="font-semibold text-gray-700">
                                        No goals or tasks found
                                    </p>

                                    <p class="text-sm text-gray-500 mt-2">
                                        <?= $search !== ''
                                            ? 'Try a different search term.'
                                            : 'Click Add New Goal / Task to get started.' ?>
                                    </p>

                                    <?php if ($search === ''): ?>
                                        <a href="goals.php?add=1"
                                           class="btn btn-primary mt-4">
                                            + Add First Goal / Task
                                        </a>
                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div class="px-5 py-4 border-t border-gray-200
                        bg-gray-50 text-xs text-gray-500">
                Showing <?= number_format($totalGoals) ?>
                <?= $totalGoals === 1 ? 'goal/task' : 'goals/tasks' ?>.
            </div>

        </div>

    <?php endif; ?>

    <div class="text-center text-xs text-gray-400 mt-8">
        HR Management System &mdash; Goals & Tasks
    </div>

</div>

</body>
</html>

