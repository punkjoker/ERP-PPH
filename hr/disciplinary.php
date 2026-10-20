
<?php
session_start();
date_default_timezone_set('Africa/Nairobi');

require_once 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection not found. Check db_con.php.');
}

$conn->set_charset('utf8mb4');

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

$selectedUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$selectedUserId) {
    $selectedUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
}

$caseDate = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| Save a new disciplinary case
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_case'])) {
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $caseDate = trim($_POST['case_date'] ?? '');
    $allegation = trim($_POST['allegation'] ?? '');
    $caseDetails = trim($_POST['case_details'] ?? '');
    $committeeComments = trim($_POST['committee_comments'] ?? '');
    $outcome = trim($_POST['outcome'] ?? 'Pending');
    $actionTaken = trim($_POST['action_taken'] ?? '');

    $allowedOutcomes = [
        'Pending',
        'No Action',
        'Verbal Warning',
        'Written Warning',
        'Final Warning',
        'Other'
    ];

    if (
        !$userId ||
        !$caseDate ||
        $allegation === '' ||
        $caseDetails === '' ||
        !in_array($outcome, $allowedOutcomes, true)
    ) {
        $error = 'Please complete all required fields correctly.';
    } else {
        try {
            // Confirm that the selected user belongs to the Staff group.
            $staffCheck = $conn->prepare("
                SELECT u.user_id
                FROM users u
                JOIN groups g ON u.group_id = g.group_id
                WHERE u.user_id = ?
                  AND LOWER(g.group_name) = 'staff'
                LIMIT 1
            ");
            $staffCheck->bind_param('i', $userId);
            $staffCheck->execute();
            $staffResult = $staffCheck->get_result();

            if ($staffResult->num_rows === 0) {
                throw new Exception('The selected user is not a valid staff member.');
            }

            // Record the current session username if available.
            $recordedBy = $_SESSION['username']
                ?? $_SESSION['full_name']
                ?? $_SESSION['admin_name']
                ?? 'HR Administrator';

            $insert = $conn->prepare("
                INSERT INTO disciplinary_cases
                (
                    user_id,
                    case_date,
                    allegation,
                    case_details,
                    committee_comments,
                    outcome,
                    action_taken,
                    recorded_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $insert->bind_param(
                'isssssss',
                $userId,
                $caseDate,
                $allegation,
                $caseDetails,
                $committeeComments,
                $outcome,
                $actionTaken,
                $recordedBy
            );

            $insert->execute();

            header('Location: disciplinary.php?user_id=' . $userId . '&saved=1');
            exit;
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
    }

    $selectedUserId = $userId;
}

/*
|--------------------------------------------------------------------------
| Success message
|--------------------------------------------------------------------------
*/
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $message = 'Disciplinary case saved successfully.';
}

/*
|--------------------------------------------------------------------------
| Load staff directory and disciplinary statistics
|--------------------------------------------------------------------------
*/
$staffSql = "
    SELECT
        u.user_id,
        u.full_name,
        COUNT(dc.case_id) AS total_cases,
        SUM(
            CASE
                WHEN dc.outcome IN (
                    'Verbal Warning',
                    'Written Warning',
                    'Final Warning'
                )
                THEN 1 ELSE 0
            END
        ) AS warnings_issued
    FROM users u
    JOIN groups g ON u.group_id = g.group_id
    LEFT JOIN disciplinary_cases dc ON dc.user_id = u.user_id
    WHERE LOWER(g.group_name) = 'staff'
    GROUP BY u.user_id, u.full_name
    ORDER BY u.full_name ASC
";

$staffResult = $conn->query($staffSql);
$staffMembers = [];

while ($row = $staffResult->fetch_assoc()) {
    $staffMembers[] = $row;
}

/*
|--------------------------------------------------------------------------
| Load selected staff member
|--------------------------------------------------------------------------
*/
$selectedStaff = null;
$cases = [];
$warningCount = 0;
$totalCases = 0;

if ($selectedUserId) {
    $staffStmt = $conn->prepare("
        SELECT u.user_id, u.full_name
        FROM users u
        JOIN groups g ON u.group_id = g.group_id
        WHERE u.user_id = ?
          AND LOWER(g.group_name) = 'staff'
        LIMIT 1
    ");

    $staffStmt->bind_param('i', $selectedUserId);
    $staffStmt->execute();
    $selectedStaff = $staffStmt->get_result()->fetch_assoc();

    if ($selectedStaff) {
        $caseStmt = $conn->prepare("
            SELECT
                case_id,
                case_date,
                allegation,
                case_details,
                committee_comments,
                outcome,
                action_taken,
                recorded_by,
                created_at
            FROM disciplinary_cases
            WHERE user_id = ?
            ORDER BY case_date DESC, case_id DESC
        ");

        $caseStmt->bind_param('i', $selectedUserId);
        $caseStmt->execute();
        $caseResult = $caseStmt->get_result();

        while ($case = $caseResult->fetch_assoc()) {
            $cases[] = $case;

            if (in_array($case['outcome'], [
                'Verbal Warning',
                'Written Warning',
                'Final Warning'
            ], true)) {
                $warningCount++;
            }
        }

        $totalCases = count($cases);
    } else {
        $error = 'The selected staff member could not be found.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Disciplinary Records</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>


<body class="bg-gray-100 min-h-screen">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                Staff Disciplinary Management
            </h1>
            <p class="text-gray-500 mt-1">
                Manage cases, committee comments and warning history.
            </p>
        </div>
        <div class="bg-white border rounded-xl px-4 py-3">
            <span class="text-sm text-gray-500">Staff Members</span>
            <p class="text-2xl font-bold">
                <?= count($staffMembers) ?>
            </p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-4 rounded-lg bg-green-100 text-green-800 p-4">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-4 rounded-lg bg-red-100 text-red-800 p-4">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($selectedStaff): ?>
        <!-- Selected staff member -->
        <div class="bg-white rounded-xl shadow-sm p-5 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <a href="disciplinary.php"
                       class="text-sm text-blue-600 hover:underline">
                        &larr; Back to staff list
                    </a>

                    <h2 class="text-2xl font-bold mt-2">
                        <?= e($selectedStaff['full_name']) ?>
                    </h2>
                    <p class="text-gray-500">
                        Staff ID: <?= (int)$selectedStaff['user_id'] ?>
                    </p>
                </div>

                <button
                    type="button"
                    onclick="document.getElementById('newCaseForm').classList.toggle('hidden')"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-lg font-semibold">
                    + Add New Case
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                    <p class="text-sm text-blue-700">Total Disciplinary Cases</p>
                    <p class="text-3xl font-bold text-blue-900">
                        <?= $totalCases ?>
                    </p>
                </div>

                <div class="bg-amber-50 border border-amber-100 rounded-xl p-4">
                    <p class="text-sm text-amber-700">Warnings Issued</p>
                    <p class="text-3xl font-bold text-amber-900">
                        <?= $warningCount ?>
                    </p>
                    <p class="text-xs text-amber-700 mt-1">
                        Counts verbal, written and final warnings.
                    </p>
                </div>
            </div>
        </div>

        <!-- New disciplinary case form -->
        <div id="newCaseForm"
             class="hidden bg-white rounded-xl shadow-sm p-5 mb-6">
            <h3 class="text-xl font-bold mb-4">Record New Disciplinary Case</h3>

            <form method="POST" action="disciplinary.php?user_id=<?= (int)$selectedStaff['user_id'] ?>"
                  class="space-y-4">
                <input type="hidden" name="user_id"
                       value="<?= (int)$selectedStaff['user_id'] ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Case Date *
                        </label>
                        <input type="date" name="case_date"
                               value="<?= e($caseDate) ?>"
                               required
                               class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-400">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Allegation / Case Title *
                        </label>
                        <input type="text" name="allegation"
                               maxlength="255"
                               required
                               placeholder="e.g. Repeated lateness"
                               class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-400">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">
                        Case Details *
                    </label>
                    <textarea name="case_details" rows="4" required
                              placeholder="Describe the incident and relevant facts..."
                              class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-400"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">
                        Committee Comments
                    </label>
                    <textarea name="committee_comments" rows="3"
                              placeholder="Enter committee observations or recommendations..."
                              class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-400"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Decision / Outcome *
                        </label>
                        <select name="outcome" required
                                class="w-full border rounded-lg p-3 bg-white">
                            <option value="Pending">Pending Committee Decision</option>
                            <option value="No Action">No Action</option>
                            <option value="Verbal Warning">Verbal Warning Issued</option>
                            <option value="Written Warning">Written Warning Issued</option>
                            <option value="Final Warning">Final Warning Issued</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-1">
                            Action Taken
                        </label>
                        <input type="text" name="action_taken"
                               maxlength="65535"
                               placeholder="e.g. Staff counselled by supervisor"
                               class="w-full border rounded-lg p-3">
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="save_case"
                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold">
                        Save Disciplinary Case
                    </button>

                    <button type="button"
                            onclick="document.getElementById('newCaseForm').classList.add('hidden')"
                            class="bg-gray-200 hover:bg-gray-300 px-6 py-3 rounded-lg">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Previous cases -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-5 border-b">
                <h3 class="text-xl font-bold">Previous Disciplinary Cases</h3>
                <p class="text-gray-500 text-sm mt-1">
                    Cases are displayed from newest to oldest.
                </p>
            </div>

            <?php if (empty($cases)): ?>
                <div class="p-8 text-center text-gray-500">
                    No disciplinary cases have been recorded for this staff member.
                </div>
            <?php else: ?>
                <div class="divide-y">
                    <?php foreach ($cases as $case): ?>
                        <article class="p-5">
                            <div class="flex flex-col md:flex-row md:justify-between gap-3">
                                <div>
                                    <p class="text-sm text-gray-500">
                                        Case #<?= (int)$case['case_id'] ?>
                                        &middot;
                                        <?= e(date('d M Y', strtotime($case['case_date']))) ?>
                                    </p>
                                    <h4 class="text-lg font-bold mt-1">
                                        <?= e($case['allegation']) ?>
                                    </h4>
                                </div>

                                <?php
                                $outcomeClass = 'bg-gray-100 text-gray-700';

                                if (in_array($case['outcome'], [
                                    'Verbal Warning',
                                    'Written Warning',
                                    'Final Warning'
                                ], true)) {
                                    $outcomeClass = 'bg-amber-100 text-amber-800';
                                } elseif ($case['outcome'] === 'No Action') {
                                    $outcomeClass = 'bg-green-100 text-green-800';
                                } elseif ($case['outcome'] === 'Pending') {
                                    $outcomeClass = 'bg-blue-100 text-blue-800';
                                }
                                ?>

                                <span class="inline-flex h-fit rounded-full px-3 py-1 text-sm font-semibold <?= e($outcomeClass) ?>">
                                    <?= e($case['outcome']) ?>
                                </span>
                            </div>

                            <div class="mt-4">
                                <h5 class="font-semibold text-gray-700">Case Details</h5>
                                <p class="mt-1 whitespace-pre-wrap text-gray-600"><?= e($case['case_details']) ?></p>
                            </div>

                            <div class="mt-4">
                                <h5 class="font-semibold text-gray-700">Committee Comments</h5>
                                <p class="mt-1 whitespace-pre-wrap text-gray-600"><?= $case['committee_comments'] !== null && trim($case['committee_comments']) !== '' ? e($case['committee_comments']) : 'No committee comments recorded.' ?></p>
                            </div>

                            <?php if (!empty($case['action_taken'])): ?>
                                <div class="mt-4">
                                    <h5 class="font-semibold text-gray-700">Action Taken</h5>
                                    <p class="mt-1 whitespace-pre-wrap text-gray-600"><?= e($case['action_taken']) ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="mt-4 text-xs text-gray-500">
                                Recorded by: <?= e($case['recorded_by'] ?: 'Not specified') ?>
                                &middot;
                                Saved: <?= e(date('d M Y H:i', strtotime($case['created_at']))) ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- Staff directory -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="p-5 border-b">
                <h2 class="text-xl font-bold">Staff Disciplinary Register</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Select a staff member to view their cases and add a new one.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 text-sm">
                        <tr>
                            <th class="p-4">#</th>
                            <th class="p-4">Staff Name</th>
                            <th class="p-4">Total Cases</th>
                            <th class="p-4">Warnings Issued</th>
                            <th class="p-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                    <?php if (empty($staffMembers)): ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-gray-500">
                                No staff members found in the Staff group.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffMembers as $index => $staff): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-4"><?= $index + 1 ?></td>
                                <td class="p-4 font-semibold"><?= e($staff['full_name']) ?></td>
                                <td class="p-4"><?= (int)$staff['total_cases'] ?></td>
                              <td class="p-4">
                                    <span class="inline-flex rounded-full bg-amber-100 text-amber-800 px-3 py-1 text-sm font-semibold">
                                        <?= (int)($staff['warnings_issued'] ?? 0) ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <a href="disciplinary.php?user_id=<?= (int)$staff['user_id'] ?>"
                                       class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                                        View Records
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
