
<?php
session_start();
require_once 'db_con.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check db_con.php.");
}

mysqli_set_charset($conn, "utf8mb4");

if (empty($_SESSION['issued_items_csrf'])) {
    $_SESSION['issued_items_csrf'] = bin2hex(random_bytes(32));
}

function issue_escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function issue_valid_date($date) {
    if ($date === '') {
        return false;
    }

    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

$errors = [];
$success = '';

$form = [
    'item_name' => '',
    'item_code' => '',
    'quantity' => '1',
    'department' => '',
    'issued_to_person' => '',
    'issue_date' => date('Y-m-d'),
    'due_date' => '',
    'return_date' => '',
    'status' => 'Issued',
    'issue_comments' => '',
    'return_comments' => ''
];

$editing = false;
$editId = 0;

/* Handle form submissions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['issued_items_csrf'], $postedToken)) {
        $errors[] = "Your session token is invalid. Refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';

        /* Delete record */
        if ($action === 'delete') {
            $deleteId = filter_var(
                $_POST['issue_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$deleteId || $deleteId < 1) {
                $errors[] = "Invalid issue record.";
            } else {
                $stmt = $conn->prepare(
                    "DELETE FROM issued_items WHERE issue_id = ?"
                );

                $stmt->bind_param("i", $deleteId);

                if ($stmt->execute()) {
                    $affected = $stmt->affected_rows;
                    $stmt->close();

                    if ($affected > 0) {
                        header("Location: issued_items.php?deleted=1");
                        exit;
                    }

                    $errors[] = "Issue record was not found.";
                } else {
                    $errors[] = "Unable to delete the issue record.";
                    $stmt->close();
                }
            }
        }

        /* Add or update record */
        elseif ($action === 'save') {
            foreach ($form as $key => $value) {
                $form[$key] = trim((string)($_POST[$key] ?? ''));
            }

            $editId = filter_var(
                $_POST['issue_id'] ?? '0',
                FILTER_VALIDATE_INT
            ) ?: 0;

            $editing = $editId > 0;

            if ($form['item_name'] === '') {
                $errors[] = "Item name is required.";
            }

            $quantity = filter_var(
                $form['quantity'],
                FILTER_VALIDATE_INT
            );

            if ($quantity === false || $quantity < 1) {
                $errors[] = "Quantity must be a whole number greater than zero.";
            }

            if (
                $form['department'] === '' &&
                $form['issued_to_person'] === ''
            ) {
                $errors[] = "Enter a department, an individual, or both.";
            }

            if (!issue_valid_date($form['issue_date'])) {
                $errors[] = "Enter a valid issue date.";
            }

            if (
                $form['due_date'] !== '' &&
                !issue_valid_date($form['due_date'])
            ) {
                $errors[] = "Enter a valid expected return date.";
            }

            if (
                $form['due_date'] !== '' &&
                issue_valid_date($form['issue_date']) &&
                issue_valid_date($form['due_date']) &&
                $form['due_date'] < $form['issue_date']
            ) {
                $errors[] = "The expected return date cannot be earlier than the issue date.";
            }

            if (!in_array($form['status'], ['Issued', 'Returned'], true)) {
                $errors[] = "Choose a valid issue status.";
            }

            if ($form['status'] === 'Returned') {
                if ($form['return_date'] === '') {
                    $form['return_date'] = date('Y-m-d');
                }

                if (!issue_valid_date($form['return_date'])) {
                    $errors[] = "Enter a valid return date.";
                } elseif (
                    issue_valid_date($form['issue_date']) &&
                    $form['return_date'] < $form['issue_date']
                ) {
                    $errors[] = "The return date cannot be earlier than the issue date.";
                }
            } else {
                // An item marked Issued must not have a return date.
                $form['return_date'] = '';
            }

            if (empty($errors)) {
                $itemCode = $form['item_code'] !== ''
                    ? $form['item_code'] : null;

                $department = $form['department'] !== ''
                    ? $form['department'] : null;

                $person = $form['issued_to_person'] !== ''
                    ? $form['issued_to_person'] : null;

                $dueDate = $form['due_date'] !== ''
                    ? $form['due_date'] : null;

                $returnDate = $form['return_date'] !== ''
                    ? $form['return_date'] : null;

                $issueComments = $form['issue_comments'] !== ''
                    ? $form['issue_comments'] : null;

                $returnComments = $form['return_comments'] !== ''
                    ? $form['return_comments'] : null;

                if ($editing) {
                    $stmt = $conn->prepare(
                        "UPDATE issued_items SET
                            item_name = ?,
                            item_code = ?,
                            quantity = ?,
                            department = ?,
                            issued_to_person = ?,
                            issue_date = ?,
                            due_date = ?,
                            return_date = ?,
                            status = ?,
                            issue_comments = ?,
                            return_comments = ?
                         WHERE issue_id = ?"
                    );

                    $stmt->bind_param(
                        "ssissssssssi",
                        $form['item_name'],
                        $itemCode,
                        $quantity,
                        $department,
                        $person,
                        $form['issue_date'],
                        $dueDate,
                        $returnDate,
                        $form['status'],
                        $issueComments,
                        $returnComments,
                        $editId
                    );
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO issued_items (
                            item_name,
                            item_code,
                            quantity,
                            department,
                            issued_to_person,
                            issue_date,
                            due_date,
                            return_date,
                            status,
                            issue_comments,
                            return_comments
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "ssissssssss",
                        $form['item_name'],
                        $itemCode,
                        $quantity,
                        $department,
                        $person,
                        $form['issue_date'],
                        $dueDate,
                        $returnDate,
                        $form['status'],
                        $issueComments,
                        $returnComments
                    );
                }

                if ($stmt->execute()) {
                    $stmt->close();

                    header(
                        "Location: issued_items.php?" .
                        ($editing ? 'updated=1' : 'added=1')
                    );
                    exit;
                }

                $errors[] = "Unable to save the issue record. Please try again.";
                $stmt->close();
            }
        }
    }
}

/* Load record for editing */
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['edit']) &&
    ctype_digit((string)$_GET['edit']) &&
    (int)$_GET['edit'] > 0
) {
    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT * FROM issued_items WHERE issue_id = ?"
    );

    $stmt->bind_param("i", $editId);
    $stmt->execute();

    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {
        $editing = true;

        foreach ($form as $key => $value) {
            if (array_key_exists($key, $record)) {
                $form[$key] = $record[$key] ?? '';
            }
        }
    } else {
        $errors[] = "The issue record you selected was not found.";
    }
}

/* Search and filters */
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$allowedStatuses = ['Issued', 'Returned', 'Overdue'];

$sql = "
    SELECT *,
        CASE
            WHEN status = 'Returned' THEN 'Returned'
            WHEN due_date IS NOT NULL
                 AND due_date < CURDATE() THEN 'Overdue'
            ELSE 'Issued'
        END AS display_status
    FROM issued_items
    WHERE 1 = 1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (
        item_name LIKE ?
        OR item_code LIKE ?
        OR department LIKE ?
        OR issued_to_person LIKE ?
    )";

    $term = '%' . $search . '%';

    $params = [$term, $term, $term, $term];
    $types = 'ssss';
}

if (in_array($statusFilter, $allowedStatuses, true)) {
    if ($statusFilter === 'Overdue') {
        $sql .= " AND status = 'Issued'
                  AND due_date IS NOT NULL
                  AND due_date < CURDATE()";
    } elseif ($statusFilter === 'Issued') {
        $sql .= " AND status = 'Issued'
                  AND (due_date IS NULL OR due_date >= CURDATE())";
    } else {
        $sql .= " AND status = 'Returned'";
    }
}

$sql .= " ORDER BY issue_date DESC, issue_id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$items = $stmt->get_result();

/* Dashboard statistics */
$statsResult = $conn->query("
    SELECT
        COUNT(*) AS total_records,
        COALESCE(SUM(status = 'Issued'), 0) AS currently_issued,
        COALESCE(SUM(status = 'Returned'), 0) AS returned_items,
        COALESCE(SUM(
            status = 'Issued'
            AND due_date IS NOT NULL
            AND due_date < CURDATE()
        ), 0) AS overdue_items
    FROM issued_items
");

$stats = $statsResult->fetch_assoc();

if (isset($_GET['added'])) {
    $success = "Item issue recorded successfully.";
} elseif (isset($_GET['updated'])) {
    $success = "Issue record updated successfully.";
} elseif (isset($_GET['deleted'])) {
    $success = "Issue record deleted successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issued Items Management</title>

    <!-- Use the same Tailwind setup as your other ERP pages. -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <!-- Page heading -->
    <div class="flex flex-col md:flex-row md:items-center
                md:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                Issued Items Management
            </h1>
            <p class="text-gray-600 mt-2">
                Track items issued to departments and employees,
                monitor returns, and record comments.
            </p>
        </div>

        <a href="issued_items.php#issue-form"
           class="inline-flex items-center justify-center
                  bg-yellow-600 hover:bg-yellow-700 text-white
                  font-semibold px-5 py-3 rounded-lg shadow">
            + Issue New Item
        </a>
    </div>

    <!-- Notifications -->
    <?php if ($success !== ''): ?>
        <div class="mb-6 rounded-lg border border-green-200
                    bg-green-50 text-green-800 px-4 py-3">
            <?= issue_escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 rounded-lg border border-red-200
                    bg-red-50 text-red-800 px-4 py-3">
            <p class="font-semibold mb-2">Please check the following:</p>
            <ul class="list-disc pl-5 space-y-1">
                <?php foreach ($errors as $error): ?>
                    <li><?= issue_escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Summary cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4
                gap-5 mb-8">

        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-5">
            <p class="text-sm text-gray-500">Total Issue Records</p>
            <p class="text-3xl font-bold text-gray-800 mt-2">
                <?= (int)$stats['total_records'] ?>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-5">
            <p class="text-sm text-gray-500">Currently Issued</p>
            <p class="text-3xl font-bold text-blue-600 mt-2">
                <?= (int)$stats['currently_issued'] ?>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-5">
            <p class="text-sm text-gray-500">Returned</p>
            <p class="text-3xl font-bold text-green-600 mt-2">
                <?= (int)$stats['returned_items'] ?>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-5">
            <p class="text-sm text-gray-500">Overdue Returns</p>
            <p class="text-3xl font-bold text-red-600 mt-2">
                <?= (int)$stats['overdue_items'] ?>
            </p>
        </div>

    </div>

    <!-- Add / Edit form -->
    <div id="issue-form"
         class="bg-white rounded-xl shadow-sm border
                border-gray-200 p-6 mb-8">

        <div class="flex items-center justify-between gap-3 mb-6">
            <h2 class="text-xl font-bold text-gray-800">
                <?= $editing ? 'Edit Issued Item' : 'Issue New Item' ?>
            </h2>

            <?php if ($editing): ?>
                <a href="issued_items.php"
                   class="text-sm text-gray-600 hover:text-gray-900 underline">
                    Cancel editing
                </a>
            <?php endif; ?>
        </div>

        <form method="POST" action="issued_items.php#issue-form"
              class="space-y-6">

            <input type="hidden" name="csrf_token"
                   value="<?= issue_escape($_SESSION['issued_items_csrf']) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="issue_id"
                   value="<?= (int)$editId ?>">

            <!-- Item details -->
            <div>
                <h3 class="text-sm font-bold uppercase tracking-wide
                           text-gray-500 mb-4">
                    Item Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2
                            xl:grid-cols-3 gap-5">

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Item Name *
                        </label>
                        <input type="text" name="item_name" required
                               maxlength="200"
                               value="<?= issue_escape($form['item_name']) ?>"
                               placeholder="e.g. Laptop, Safety Boots"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Item Code / Serial Number
                        </label>
                        <input type="text" name="item_code" maxlength="100"
                               value="<?= issue_escape($form['item_code']) ?>"
                               placeholder="e.g. LAP-001"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Quantity *
                        </label>
                        <input type="number" name="quantity" required
                               min="1" step="1"
                               value="<?= issue_escape($form['quantity']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                </div>
            </div>

            <!-- Recipient -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-sm font-bold uppercase tracking-wide
                           text-gray-500 mb-4">
                    Recipient Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Department
                        </label>
                        <input type="text" name="department" maxlength="150"
                               value="<?= issue_escape($form['department']) ?>"
                               placeholder="e.g. Engineering, HR, Finance"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Issued To (Individual)
                        </label>
                        <input type="text" name="issued_to_person"
                               maxlength="150"
                               value="<?= issue_escape($form['issued_to_person']) ?>"
                               placeholder="Employee name"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                </div>

                <p class="text-xs text-gray-500 mt-2">
                    Enter a department, an individual, or both.
                </p>
            </div>

            <!-- Issue and return tracking -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-sm font-bold uppercase tracking-wide
                           text-gray-500 mb-4">
                    Issue and Return Tracking
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2
                            xl:grid-cols-4 gap-5">

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Date Issued *
                        </label>
                        <input type="date" name="issue_date" required
                               value="<?= issue_escape($form['issue_date']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Expected Return Date
                        </label>
                        <input type="date" name="due_date"
                               value="<?= issue_escape($form['due_date']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Return Date
                        </label>
                        <input type="date" name="return_date"
                               value="<?= issue_escape($form['return_date']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Item Status *
                        </label>
                        <select name="status" required
                                class="w-full rounded-lg border border-gray-300
                                       px-4 py-3 bg-white focus:ring-2
                                       focus:ring-yellow-500 focus:outline-none">
                            <option value="Issued"
                                <?= $form['status'] === 'Issued'
                                    ? 'selected' : '' ?>>
                                Issued / Not Returned
                            </option>
                            <option value="Returned"
                                <?= $form['status'] === 'Returned'
                                    ? 'selected' : '' ?>>
                                Returned
                            </option>
                        </select>
                    </div>

                </div>

                <p class="text-xs text-gray-500 mt-2">
                    When marking an item as Returned, enter its return date.
                    If left blank, today's date will be used.
                </p>
            </div>

            <!-- Comments -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-sm font-bold uppercase tracking-wide
                           text-gray-500 mb-4">
                    Comments and Observations
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Issue Comments
                        </label>
                        <textarea name="issue_comments" rows="4"
                                  placeholder="Purpose of issue, condition before issue, accessories supplied..."
                                  class="w-full rounded-lg border border-gray-300
                                         px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                         focus:outline-none"><?= issue_escape($form['issue_comments']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Return Comments
                        </label>
                        <textarea name="return_comments" rows="4"
                                  placeholder="Condition on return, missing parts, damages, or other observations..."
                                  class="w-full rounded-lg border border-gray-300
                                         px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                         focus:outline-none"><?= issue_escape($form['return_comments']) ?></textarea>
                    </div>

                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <button type="submit"
                        class="bg-yellow-600 hover:bg-yellow-700
                               text-white font-semibold px-6 py-3
                               rounded-lg shadow">
                    <?= $editing ? 'Update Issue Record' : 'Save Issue Record' ?>
                </button>

                <?php if ($editing): ?>
                    <a href="issued_items.php"
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

    <!-- Search and filter -->
    <div class="bg-white rounded-xl shadow-sm border
                border-gray-200 p-5 mb-6">

        <form method="GET" action="issued_items.php"
              class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold
                              text-gray-700 mb-2">
                    Search issued items
                </label>
                <input type="text" name="search"
                       value="<?= issue_escape($search) ?>"
                       placeholder="Search item, code, department, or employee..."
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
                    <?php foreach ($allowedStatuses as $filter): ?>
                        <option value="<?= issue_escape($filter) ?>"
                            <?= $statusFilter === $filter ? 'selected' : '' ?>>
                            <?= issue_escape($filter) ?>
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

                <a href="issued_items.php"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-800
                          font-semibold px-5 py-3 rounded-lg">
                    Reset
                </a>
            </div>

        </form>
    </div>

    <!-- Issued items table -->
    <div class="bg-white rounded-xl shadow-sm border
                border-gray-200 overflow-hidden">

        <div class="p-5 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">
                Issued Items Register
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                Review issued items, recipients, return dates, and comments.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">

                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Item
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Recipient
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Quantity
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Date Issued
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Due Date
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Return Date
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Status
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Comments
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">

                    <?php if ($items->num_rows > 0): ?>
                        <?php while ($item = $items->fetch_assoc()): ?>

                            <?php
                            $displayStatus = $item['display_status'];

                            $statusClass = 'bg-blue-100 text-blue-800';

                            if ($displayStatus === 'Returned') {
                                $statusClass = 'bg-green-100 text-green-800';
                            } elseif ($displayStatus === 'Overdue') {
                                $statusClass = 'bg-red-100 text-red-800';
                            }
                            ?>

                            <tr class="hover:bg-gray-50">

                                <td class="px-5 py-4">
                                    <p class="font-semibold text-gray-800">
                                        <?= issue_escape($item['item_name']) ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        Code:
                                        <?= issue_escape($item['item_code'] ?: 'Not provided') ?>
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <p>
                                        <span class="font-semibold">Department:</span>
                                        <?= issue_escape($item['department'] ?: '—') ?>
                                    </p>
                                    <p class="mt-1">
                                        <span class="font-semibold">Person:</span>
                                        <?= issue_escape($item['issued_to_person'] ?: '—') ?>
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <?= (int)$item['quantity'] ?>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700 whitespace-nowrap">
                                    <?= issue_escape($item['issue_date']) ?>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700 whitespace-nowrap">
                                    <?= issue_escape($item['due_date'] ?: 'Not set') ?>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700 whitespace-nowrap">
                                    <?= issue_escape($item['return_date'] ?: 'Not returned') ?>
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1
                                                 text-xs font-semibold <?= $statusClass ?>">
                                        <?= issue_escape($displayStatus) ?>
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <p>
                                        <span class="font-semibold">Issue:</span>
                                        <?= issue_escape($item['issue_comments'] ?: '—') ?>
                                    </p>
                                    <p class="mt-2">
                                        <span class="font-semibold">Return:</span>
                                        <?= issue_escape($item['return_comments'] ?: '—') ?>
                                    </p>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex flex-col items-start gap-3">

                                        <a href="issued_items.php?edit=<?= (int)$item['issue_id'] ?>#issue-form"
                                           class="text-yellow-700 hover:text-yellow-900
                                                  font-semibold text-sm">
                                            Edit / Update Return
                                        </a>

                                        <form method="POST"
                                              action="issued_items.php"
                                              onsubmit="return confirm('Are you sure you want to delete this issue record?');">

                                            <input type="hidden" name="csrf_token"
                                                   value="<?= issue_escape($_SESSION['issued_items_csrf']) ?>">

                                            <input type="hidden" name="action" value="delete">

                                            <input type="hidden" name="issue_id"
                                                   value="<?= (int)$item['issue_id'] ?>">

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
                            <td colspan="9"
                                class="px-5 py-12 text-center text-gray-500">
                                <p class="text-lg font-semibold">
                                    No issued items found
                                </p>
                                <p class="text-sm mt-2">
                                    Issue a new item or change your search filters.
                                </p>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

    <p class="text-sm text-gray-500 mt-4 text-right">
        Issued Items Management
    </p>

</div>

</body>
</html>
