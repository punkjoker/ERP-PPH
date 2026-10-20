
<?php
session_start();
require_once 'db_con.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check db_con.php.");
}

mysqli_set_charset($conn, "utf8mb4");

if (empty($_SESSION['audit_csrf_token'])) {
    $_SESSION['audit_csrf_token'] = bin2hex(random_bytes(32));
}

function audit_escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function audit_redirect($url) {
    header("Location: " . $url);
    exit;
}

function audit_date_valid($date) {
    if ($date === '') {
        return false;
    }

    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

$errors = [];
$success = '';

$form = [
    'auditor_name' => '',
    'auditor_organization' => '',
    'auditor_email' => '',
    'auditor_phone' => '',
    'audit_type' => '',
    'department' => '',
    'audit_date' => date('Y-m-d'),
    'next_due_date' => '',
    'findings' => '',
    'recommendations' => '',
    'status' => 'Scheduled'
];

$editing = false;
$editId = 0;

/*
|--------------------------------------------------------------------------
| Handle form submissions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['audit_csrf_token'], $postedToken)) {
        $errors[] = "Your session token is invalid. Refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';

        /*
        |----------------------------------------------------------------------
        | Delete audit
        |----------------------------------------------------------------------
        */
        if ($action === 'delete') {
            $deleteId = filter_var(
                $_POST['audit_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$deleteId || $deleteId < 1) {
                $errors[] = "Invalid audit record.";
            } else {
                $stmt = $conn->prepare(
                    "DELETE FROM audit_logs WHERE audit_id = ?"
                );
                $stmt->bind_param("i", $deleteId);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $stmt->close();
                        audit_redirect("audit_log.php?deleted=1");
                    } else {
                        $errors[] = "Audit record was not found.";
                    }
                } else {
                    $errors[] = "Unable to delete the audit record.";
                }

                $stmt->close();
            }
        }

        /*
        |----------------------------------------------------------------------
        | Add or update audit
        |----------------------------------------------------------------------
        */
        elseif ($action === 'save') {
            $form['auditor_name'] = trim($_POST['auditor_name'] ?? '');
            $form['auditor_organization'] = trim(
                $_POST['auditor_organization'] ?? ''
            );
            $form['auditor_email'] = trim($_POST['auditor_email'] ?? '');
            $form['auditor_phone'] = trim($_POST['auditor_phone'] ?? '');
            $form['audit_type'] = trim($_POST['audit_type'] ?? '');
            $form['department'] = trim($_POST['department'] ?? '');
            $form['audit_date'] = trim($_POST['audit_date'] ?? '');
            $form['next_due_date'] = trim($_POST['next_due_date'] ?? '');
            $form['findings'] = trim($_POST['findings'] ?? '');
            $form['recommendations'] = trim(
                $_POST['recommendations'] ?? ''
            );
            $form['status'] = trim($_POST['status'] ?? 'Scheduled');

            $editId = filter_var(
                $_POST['audit_id'] ?? '0',
                FILTER_VALIDATE_INT
            ) ?: 0;

            $editing = $editId > 0;

            if ($form['auditor_name'] === '') {
                $errors[] = "Auditor name is required.";
            }

            if ($form['audit_type'] === '') {
                $errors[] = "Audit type is required.";
            }

            if (!audit_date_valid($form['audit_date'])) {
                $errors[] = "Enter a valid audit date.";
            }

            if (
                $form['next_due_date'] !== '' &&
                !audit_date_valid($form['next_due_date'])
            ) {
                $errors[] = "Enter a valid next due date.";
            }

            if (
                $form['next_due_date'] !== '' &&
                audit_date_valid($form['audit_date']) &&
                audit_date_valid($form['next_due_date']) &&
                $form['next_due_date'] < $form['audit_date']
            ) {
                $errors[] = "The next due date cannot be earlier than the audit date.";
            }

            if (
                $form['auditor_email'] !== '' &&
                !filter_var($form['auditor_email'], FILTER_VALIDATE_EMAIL)
            ) {
                $errors[] = "Enter a valid auditor email address.";
            }

            if (!in_array($form['status'], ['Scheduled', 'Completed'], true)) {
                $errors[] = "Choose a valid audit status.";
            }

            if ($editing && $editId < 1) {
                $errors[] = "Invalid audit record.";
            }

            if (empty($errors)) {
                $organization = $form['auditor_organization'] !== ''
                    ? $form['auditor_organization'] : null;

                $email = $form['auditor_email'] !== ''
                    ? $form['auditor_email'] : null;

                $phone = $form['auditor_phone'] !== ''
                    ? $form['auditor_phone'] : null;

                $department = $form['department'] !== ''
                    ? $form['department'] : null;

                $nextDueDate = $form['next_due_date'] !== ''
                    ? $form['next_due_date'] : null;

                $findings = $form['findings'] !== ''
                    ? $form['findings'] : null;

                $recommendations = $form['recommendations'] !== ''
                    ? $form['recommendations'] : null;

                if ($editing) {
                    $stmt = $conn->prepare(
                        "UPDATE audit_logs SET
                            auditor_name = ?,
                            auditor_organization = ?,
                            auditor_email = ?,
                            auditor_phone = ?,
                            audit_type = ?,
                            department = ?,
                            audit_date = ?,
                            next_due_date = ?,
                            findings = ?,
                            recommendations = ?,
                            status = ?
                         WHERE audit_id = ?"
                    );

                    $stmt->bind_param(
                        "sssssssssssi",
                        $form['auditor_name'],
                        $organization,
                        $email,
                        $phone,
                        $form['audit_type'],
                        $department,
                        $form['audit_date'],
                        $nextDueDate,
                        $findings,
                        $recommendations,
                        $form['status'],
                        $editId
                    );
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO audit_logs (
                            auditor_name,
                            auditor_organization,
                            auditor_email,
                            auditor_phone,
                            audit_type,
                            department,
                            audit_date,
                            next_due_date,
                            findings,
                            recommendations,
                            status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "sssssssssss",
                        $form['auditor_name'],
                        $organization,
                        $email,
                        $phone,
                        $form['audit_type'],
                        $department,
                        $form['audit_date'],
                        $nextDueDate,
                        $findings,
                        $recommendations,
                        $form['status']
                    );
                }

                if ($stmt->execute()) {
                    $stmt->close();

                    audit_redirect(
                        $editing
                            ? "audit_log.php?updated=1"
                            : "audit_log.php?added=1"
                    );
                } else {
                    $errors[] = "Unable to save the audit record. Please try again.";
                    $stmt->close();
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load record for editing
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['edit']) &&
    ctype_digit((string)$_GET['edit']) &&
    (int)$_GET['edit'] > 0
) {
    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT * FROM audit_logs WHERE audit_id = ?"
    );
    $stmt->bind_param("i", $editId);
    $stmt->execute();

    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();

    if ($record) {
        $editing = true;

        foreach ($form as $key => $value) {
            if (array_key_exists($key, $record)) {
                $form[$key] = $record[$key] ?? '';
            }
        }
    } else {
        $errors[] = "The audit record you selected was not found.";
    }
}

/*
|--------------------------------------------------------------------------
| Load view record
|--------------------------------------------------------------------------
*/

$viewRecord = null;

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['view']) &&
    ctype_digit((string)$_GET['view']) &&
    (int)$_GET['view'] > 0
) {
    $viewId = (int)$_GET['view'];

    $stmt = $conn->prepare(
        "SELECT *,
            CASE
                WHEN status <> 'Completed'
                    AND next_due_date IS NOT NULL
                    AND next_due_date < CURDATE()
                THEN 'Overdue'
                ELSE status
            END AS display_status
         FROM audit_logs
         WHERE audit_id = ?"
    );

    $stmt->bind_param("i", $viewId);
    $stmt->execute();

    $result = $stmt->get_result();
    $viewRecord = $result->fetch_assoc();
    $stmt->close();

    if (!$viewRecord) {
        $errors[] = "The audit record you selected was not found.";
    }
}

/*
|--------------------------------------------------------------------------
| Search and filter audit records
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$allowedFilters = ['Scheduled', 'Completed', 'Overdue'];

$sql = "
    SELECT *,
        CASE
            WHEN status <> 'Completed'
                AND next_due_date IS NOT NULL
                AND next_due_date < CURDATE()
            THEN 'Overdue'
            ELSE status
        END AS display_status
    FROM audit_logs
    WHERE 1 = 1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (
        auditor_name LIKE ?
        OR auditor_organization LIKE ?
        OR audit_type LIKE ?
        OR department LIKE ?
    )";

    $term = '%' . $search . '%';

    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;

    $types .= 'ssss';
}

if (in_array($statusFilter, $allowedFilters, true)) {
    if ($statusFilter === 'Overdue') {
        $sql .= " AND status <> 'Completed'
            AND next_due_date IS NOT NULL
            AND next_due_date < CURDATE()";
    } elseif ($statusFilter === 'Scheduled') {
        $sql .= " AND status = 'Scheduled'
            AND (next_due_date IS NULL OR next_due_date >= CURDATE())";
    } else {
        $sql .= " AND status = 'Completed'";
    }
}

$sql .= " ORDER BY audit_date DESC, audit_id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$audits = $stmt->get_result();

/*
|--------------------------------------------------------------------------
| Summary statistics
|--------------------------------------------------------------------------
*/

$statsResult = $conn->query("
    SELECT
        COUNT(*) AS total_audits,
        SUM(status = 'Completed') AS completed_audits,
        SUM(
            status <> 'Completed'
            AND next_due_date IS NOT NULL
            AND next_due_date < CURDATE()
        ) AS overdue_audits,
        SUM(
            status <> 'Completed'
            AND (
                next_due_date IS NULL
                OR next_due_date >= CURDATE()
            )
        ) AS upcoming_audits
    FROM audit_logs
");

$stats = $statsResult
    ? $statsResult->fetch_assoc()
    : [
        'total_audits' => 0,
        'completed_audits' => 0,
        'overdue_audits' => 0,
        'upcoming_audits' => 0
    ];

if (isset($_GET['added'])) {
    $success = "Audit record added successfully.";
} elseif (isset($_GET['updated'])) {
    $success = "Audit record updated successfully.";
} elseif (isset($_GET['deleted'])) {
    $success = "Audit record deleted successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Audit Log Management</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">

<?php include 'navbar.php'; ?>
        <!-- Page heading -->
      
<div class="ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">
                    Audit Log Management
                </h1>
                <p class="text-gray-600 mt-2">
                    Record auditors, track audit activities, and monitor
                    upcoming audit due dates.
                </p>
            </div>

            <a href="audit_log.php#audit-form"
               class="inline-flex items-center justify-center
                      bg-yellow-600 hover:bg-yellow-700 text-white
                      font-semibold px-5 py-3 rounded-lg shadow">
                + Add Audit Record
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($success !== ''): ?>
            <div class="mb-6 rounded-lg border border-green-200
                        bg-green-50 text-green-800 px-4 py-3">
                <?= audit_escape($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="mb-6 rounded-lg border border-red-200
                        bg-red-50 text-red-800 px-4 py-3">
                <p class="font-semibold mb-2">
                    Please check the following:
                </p>
                <ul class="list-disc pl-5 space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?= audit_escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4
                    gap-5 mb-8">

            <div class="bg-white rounded-xl shadow-sm border
                        border-gray-200 p-5">
                <p class="text-sm text-gray-500">Total Audits</p>
                <p class="text-3xl font-bold text-gray-800 mt-2">
                    <?= (int)$stats['total_audits'] ?>
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border
                        border-gray-200 p-5">
                <p class="text-sm text-gray-500">Completed Audits</p>
                <p class="text-3xl font-bold text-green-600 mt-2">
                    <?= (int)$stats['completed_audits'] ?>
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border
                        border-gray-200 p-5">
                <p class="text-sm text-gray-500">Upcoming / Not Overdue</p>
                <p class="text-3xl font-bold text-blue-600 mt-2">
                    <?= (int)$stats['upcoming_audits'] ?>
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border
                        border-gray-200 p-5">
                <p class="text-sm text-gray-500">Overdue Audits</p>
                <p class="text-3xl font-bold text-red-600 mt-2">
                    <?= (int)$stats['overdue_audits'] ?>
                </p>
            </div>

        </div>

        <!-- Add / Edit form -->
        <div id="audit-form"
             class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-6 mb-8">

            <div class="flex items-center justify-between gap-3 mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    <?= $editing ? 'Edit Audit Record' : 'Add New Audit' ?>
                </h2>

                <?php if ($editing): ?>
                    <a href="audit_log.php"
                       class="text-sm text-gray-600 hover:text-gray-900
                              underline">
                        Cancel editing
                    </a>
                <?php endif; ?>
            </div>

            <form method="POST" action="audit_log.php# audit-form"
                  class="space-y-6">
                <input type="hidden" name="csrf_token"
                       value="<?= audit_escape($_SESSION['audit_csrf_token']) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="audit_id"
                       value="<?= (int)$editId ?>">

                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wide
                               text-gray-500 mb-4">
                        Auditor Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2
                                xl:grid-cols-4 gap-5">

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Auditor Name *
                            </label>
                            <input type="text" name="auditor_name"
                                   required maxlength="150"
                                   value="<?= audit_escape($form['auditor_name']) ?>"
                                   class="w-full rounded-lg border border-gray-300
                                          px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                          focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Organization / Company
                            </label>
                            <input type="text" name="auditor_organization"
                                   maxlength="200"
                                   value="<?= audit_escape($form['auditor_organization']) ?>"
                                   class="w-full rounded-lg border border-gray-300
                                          px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                          focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Auditor Email
                            </label>
                            <input type="email" name="auditor_email"
                                   maxlength="150"
                                   value="<?= audit_escape($form['auditor_email']) ?>"
                                   class="w-full rounded-lg border border-gray-300
                                          px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                          focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Auditor Phone
                            </label>
                            <input type="text" name="auditor_phone"
                                   maxlength="30"
                                   value="<?= audit_escape($form['auditor_phone']) ?>"
                                   class="w-full rounded-lg border border-gray-300
                                          px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                          focus:outline-none">
                        </div>

                    </div>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-bold uppercase tracking-wide
                               text-gray-500 mb-4">
                        Audit Details
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2
                                xl:grid-cols-4 gap-5">

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Audit Type *
                            </label>
                            <select name="audit_type" required
                                    class="w-full rounded-lg border border-gray-300
                                           px-4 py-3 bg-white focus:ring-2
                                           focus:ring-yellow-500 focus:outline-none">
                                <option value="">Select audit type</option>
                                <?php
                                $auditTypes = [
                                    'Internal Audit',
                                    'External Audit',
                                    'Financial Audit',
                                    'Tax Audit',
                                    'Compliance Audit',
                                    'Health and Safety Audit',
                                    'Quality Audit',
                                    'Environmental Audit',
                                    'IT / Systems Audit',
                                    'Risk Audit',
                                    'Other'
                                ];
                                ?>
                                <?php foreach ($auditTypes as $type): ?>
                                    <option value="<?= audit_escape($type) ?>"
                                        <?= $form['audit_type'] === $type
                                            ? 'selected' : '' ?>>
                                        <?= audit_escape($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Department / Area
                            </label>
                            <input type="text" name="department"
                                   maxlength="150"
                                   placeholder="e.g. Finance, HR, Operations"
                                   value="<?= audit_escape($form['department']) ?>"
                                   class="w-full rounded-lg border border-gray-300
                                          px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                          focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Date Audited *
                            </label>
                            <input type="date" name="audit_date" required
                                   value="<?= audit_escape($form['audit_date']) ?>"
                                   class="w-full rounded-lg border border-gray-300
                                          px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                          focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Next Due Audit Date
                            </label>
                            <input type="date" name="next_due_date"
                                   value="<?= audit_escape($form['next_due_date']) ?>"
                                   class="w-full rounded-lg border border-gray-300
                                          px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                          focus:outline-none">
                            <p class="text-xs text-gray-500 mt-1">
                                Leave blank if not yet scheduled.
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Audit Status
                            </label>
                            <select name="status"
                                    class="w-full rounded-lg border border-gray-300
                                           px-4 py-3 bg-white focus:ring-2
                                           focus:ring-yellow-500 focus:outline-none">
                                <option value="Scheduled"
                                    <?= $form['status'] === 'Scheduled'
                                        ? 'selected' : '' ?>>
                                    Scheduled
                                </option>
                                <option value="Completed"
                                    <?= $form['status'] === 'Completed'
                                        ? 'selected' : '' ?>>
                                    Completed
                                </option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                Overdue is calculated from the due date.
                            </p>
                        </div>

                    </div>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-bold uppercase tracking-wide
                               text-gray-500 mb-4">
                        Findings and Recommendations
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Audit Findings
                            </label>
                            <textarea name="findings" rows="4"
                                      placeholder="Record observations, issues, or non-compliance..."
                                      class="w-full rounded-lg border border-gray-300
                                             px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                             focus:outline-none"><?= audit_escape($form['findings']) ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold
                                          text-gray-700 mb-2">
                                Recommendations / Actions Required
                            </label>
                            <textarea name="recommendations" rows="4"
                                      placeholder="Record corrective actions or recommendations..."
                                      class="w-full rounded-lg border border-gray-300
                                             px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                             focus:outline-none"><?= audit_escape($form['recommendations']) ?></textarea>
                        </div>

                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit"
                            class="bg-yellow-600 hover:bg-yellow-700
                                   text-white font-semibold px-6 py-3
                                   rounded-lg shadow">
                        <?= $editing ? 'Update Audit Record' : 'Save Audit Record' ?>
                    </button>

                    <?php if ($editing): ?>
                        <a href="audit_log.php"
                           class="text-center bg-gray-200 hover:bg-gray-300
                                  text-gray-800 font-semibold px-6 py-3 rounded-lg">
                            Cancel
                        </a>
                    <?php else: ?>
                        <button type="reset"
                                class="bg-gray-200 hover:bg-gray-300
                                       text-gray-800 font-semibold px-6 py-3
                                       rounded-lg">
                            Clear Form
                        </button>
                    <?php endif; ?>
                </div>

            </form>
        </div>

        <!-- View selected audit -->
        <?php if ($viewRecord): ?>
            <div class="bg-white rounded-xl shadow-sm border
                        border-gray-200 p-6 mb-8">

                <div class="flex flex-col sm:flex-row sm:items-center
                            sm:justify-between gap-3 mb-6">
                    <h2 class="text-xl font-bold text-gray-800">
                        Audit Record Details
                    </h2>

                    <div class="flex gap-3">
                        <a href="audit_log.php?edit=<?= (int)$viewRecord['audit_id'] ?>#audit-form"
                           class="bg-yellow-600 hover:bg-yellow-700 text-white
                                  px-4 py-2 rounded-lg font-semibold">
                            Edit
                        </a>
                        <a href="audit_log.php"
                           class="bg-gray-200 hover:bg-gray-300 text-gray-800
                                  px-4 py-2 rounded-lg font-semibold">
                            Close
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php
                    $details = [
                        'Auditor Name' => $viewRecord['auditor_name'],
                        'Organization' => $viewRecord['auditor_organization'],
                        'Email' => $viewRecord['auditor_email'],
                        'Phone' => $viewRecord['auditor_phone'],
                        'Audit Type' => $viewRecord['audit_type'],
                        'Department / Area' => $viewRecord['department'],
                        'Date Audited' => $viewRecord['audit_date'],
                        'Next Due Date' => $viewRecord['next_due_date'],
                        'Status' => $viewRecord['display_status']
                    ];
                    ?>

                    <?php foreach ($details as $label => $value): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm text-gray-500 mb-1">
                                <?= audit_escape($label) ?>
                            </p>
                            <p class="font-semibold text-gray-800 break-words">
                                <?= audit_escape(
                                    ($value !== null && $value !== '')
                                        ? $value : 'Not provided'
                                ) ?>
                            </p>
                        </div>
                    <?php endforeach; ?>

                    <div class="bg-gray-50 rounded-lg p-4 sm:col-span-2 lg:col-span-3">
                        <p class="text-sm text-gray-500 mb-1">Audit Findings</p>
                        <p class="text-gray-800 whitespace-pre-wrap"><?= audit_escape($viewRecord['findings'] ?: 'No findings recorded.') ?></p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4 sm:col-span-2 lg:col-span-3">
                        <p class="text-sm text-gray-500 mb-1">Recommendations</p>
                        <p class="text-gray-800 whitespace-pre-wrap"><?= audit_escape($viewRecord['recommendations'] ?: 'No recommendations recorded.') ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Search and filter -->
        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-5 mb-6">
            <form method="GET" action="audit_log.php"
                  class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold
                                  text-gray-700 mb-2">
                        Search audits
                    </label>
                    <input type="text" name="search"
                           value="<?= audit_escape($search) ?>"
                           placeholder="Search auditor, organization, audit type, department..."
                           class="w-full rounded-lg border border-gray-300
                                  px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                  focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold
                                  text-gray-700 mb-2">
                        Filter by status
                    </label>
                    <select name="status"
                            class="w-full rounded-lg border border-gray-300
                                   bg-white px-4 py-3">
                        <option value="">All statuses</option>
                        <?php foreach ($allowedFilters as $filter): ?>
                            <option value="<?= audit_escape($filter) ?>"
                                <?= $statusFilter === $filter
                                    ? 'selected' : '' ?>>
                                <?= audit_escape($filter) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-3 flex flex-wrap gap-3">
                    <button type="submit"
                            class="bg-gray-800 hover:bg-gray-900 text-white
                                   font-semibold px-5 py-3 rounded-lg">
                        Search / Filter
                    </button>
                    <a href="audit_log.php"
                       class="bg-gray-200 hover:bg-gray-300 text-gray-800
                              font-semibold px-5 py-3 rounded-lg">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Audit records table -->
        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 overflow-hidden">

            <div class="p-5 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800">
                    Audit Records
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Review and manage recorded audits.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-5 py-4 text-left text-xs
                                       font-semibold uppercase">
                                Auditor
                            </th>
                            <th class="px-5 py-4 text-left text-xs
                                       font-semibold uppercase">
                                Audit Type
                            </th>
                            <th class="px-5 py-4 text-left text-xs
                                       font-semibold uppercase">
                                Department
                            </th>
                            <th class="px-5 py-4 text-left text-xs
                                       font-semibold uppercase">
                                Date Audited
                            </th>
                            <th class="px-5 py-4 text-left text-xs
                                       font-semibold uppercase">
                                Next Due
                            </th>
                            <th class="px-5 py-4 text-left text-xs
                                       font-semibold uppercase">
                                Status
                            </th>
                            <th class="px-5 py-4 text-left text-xs
                                       font-semibold uppercase">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <?php if ($audits->num_rows > 0): ?>
                            <?php while ($audit = $audits->fetch_assoc()): ?>
                                <?php
                                $displayStatus = $audit['display_status'];

                                $statusClass = 'bg-blue-100 text-blue-800';

                                if ($displayStatus === 'Completed') {
                                    $statusClass = 'bg-green-100 text-green-800';
                                } elseif ($displayStatus === 'Overdue') {
                                    $statusClass = 'bg-red-100 text-red-800';
                                }
                                ?>

                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-gray-800">
                                            <?= audit_escape($audit['auditor_name']) ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <?= audit_escape($audit['auditor_organization'] ?: 'No organization') ?>
                                        </p>
                                    </td>

                                    <td class="px-5 py-4 text-sm text-gray-700">
                                        <?= audit_escape($audit['audit_type']) ?>
                                    </td>

                                    <td class="px-5 py-4 text-sm text-gray-700">
                                        <?= audit_escape($audit['department'] ?: '—') ?>
                                    </td>

                                    <td class="px-5 py-4 text-sm text-gray-700 whitespace-nowrap">
                                        <?= audit_escape($audit['audit_date']) ?>
                                    </td>

                                    <td class="px-5 py-4 text-sm text-gray-700 whitespace-nowrap">
                                        <?= audit_escape($audit['next_due_date'] ?: 'Not set') ?>
                                    </td>

                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full
                                                     px-3 py-1 text-xs font-semibold
                                                     <?= $statusClass ?>">
                                            <?= audit_escape($displayStatus) ?>
                                        </span>
                                    </td>

                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap gap-2">
                                            <a href="audit_log.php?view=<?= (int)$audit['audit_id'] ?>"
                                               class="text-blue-700 hover:text-blue-900
                                                      font-semibold text-sm">
                                                View
                                            </a>

                                            <a href="audit_log.php?edit=<?= (int)$audit['audit_id'] ?>#audit-form"
                                               class="text-yellow-700 hover:text-yellow-900
                                                      font-semibold text-sm">
                                                Edit
                                            </a>

                                            <form method="POST" action="audit_log.php"
                                                  onsubmit="return confirm('Are you sure you want to delete this audit record?');">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?= audit_escape($_SESSION['audit_csrf_token']) ?>">
                                                <input type="hidden" name="action"
                                                       value="delete">
                                                <input type="hidden" name="audit_id"
                                                       value="<?= (int)$audit['audit_id'] ?>">

                                                <button type="submit"
                                                        class="text-red-600 hover:text-red-800
                                                               font-semibold text-sm">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7"
                                    class="px-5 py-12 text-center text-gray-500">
                                    <p class="text-lg font-semibold">
                                        No audit records found
                                    </p>
                                    <p class="text-sm mt-2">
                                        Add a new audit record or change your search filters.
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-sm text-gray-500 mt-4 text-right">
            Audit Log Management
        </p>

    </div>
</div>

</body>
</html>
