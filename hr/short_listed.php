
<?php
session_start();
require_once 'db_con.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check db_con.php.");
}

mysqli_set_charset($conn, "utf8mb4");

if (empty($_SESSION['shortlist_csrf'])) {
    $_SESSION['shortlist_csrf'] = bin2hex(random_bytes(32));
}

function shortlist_escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function shortlist_valid_date($date) {
    if ($date === '') {
        return false;
    }

    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function shortlist_redirect($url) {
    header("Location: " . $url);
    exit;
}

$errors = [];
$success = '';

$form = [
    'full_name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'position_applied' => '',
    'interview_date' => '',
    'interview_time' => '',
    'interview_venue' => '',
    'interviewer' => '',
    'interview_status' => 'Pending',
    'qualifications' => '',
    'technical_skills' => '',
    'communication_skills' => '',
    'strengths' => '',
    'weaknesses' => '',
    'interview_notes' => '',
    'recommendation' => '',
    'next_steps' => ''
];

$editing = false;
$editId = 0;

/* -------------------------------------------------
   Handle form submissions
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['shortlist_csrf'], $postedToken)) {
        $errors[] = "Your session token is invalid. Refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';

        /* Delete applicant */
        if ($action === 'delete') {
            $deleteId = filter_var(
                $_POST['applicant_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$deleteId || $deleteId < 1) {
                $errors[] = "Invalid applicant record.";
            } else {
                $stmt = $conn->prepare(
                    "DELETE FROM shortlisted_applicants
                     WHERE applicant_id = ?"
                );

                $stmt->bind_param("i", $deleteId);

                if ($stmt->execute()) {
                    $affected = $stmt->affected_rows;
                    $stmt->close();

                    if ($affected > 0) {
                        shortlist_redirect("short_listed.php?deleted=1");
                    }

                    $errors[] = "Applicant record was not found.";
                } else {
                    $errors[] = "Unable to delete the applicant record.";
                    $stmt->close();
                }
            }
        }

        /* Add or update applicant */
        elseif ($action === 'save') {
            foreach ($form as $key => $value) {
                $form[$key] = trim((string)($_POST[$key] ?? ''));
            }

            $editId = filter_var(
                $_POST['applicant_id'] ?? '0',
                FILTER_VALIDATE_INT
            ) ?: 0;

            $editing = $editId > 0;

            if ($form['full_name'] === '') {
                $errors[] = "Applicant's full name is required.";
            }

            if ($form['phone'] === '') {
                $errors[] = "Phone number is required.";
            }

            if ($form['position_applied'] === '') {
                $errors[] = "Position applied for is required.";
            }

            if (
                $form['email'] !== '' &&
                !filter_var($form['email'], FILTER_VALIDATE_EMAIL)
            ) {
                $errors[] = "Enter a valid email address.";
            }

            if (
                $form['interview_date'] !== '' &&
                !shortlist_valid_date($form['interview_date'])
            ) {
                $errors[] = "Enter a valid interview date.";
            }

            if (
                $form['interview_date'] !== '' &&
                $form['interview_date'] < date('Y-m-d') &&
                !shortlist_valid_date($form['interview_date'])
            ) {
                $errors[] = "The interview date is invalid.";
            }

            if (
                $form['interview_time'] !== '' &&
                !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $form['interview_time'])
            ) {
                $errors[] = "Enter a valid interview time.";
            }

            $allowedStatuses = [
                'Pending',
                'Attended',
                'Absent',
                'Passed',
                'Failed'
            ];

            if (!in_array($form['interview_status'], $allowedStatuses, true)) {
                $errors[] = "Select a valid interview status.";
            }

            if (empty($errors)) {
                $email = $form['email'] !== ''
                    ? $form['email'] : null;

                $address = $form['address'] !== ''
                    ? $form['address'] : null;

                $interviewDate = $form['interview_date'] !== ''
                    ? $form['interview_date'] : null;

                $interviewTime = $form['interview_time'] !== ''
                    ? $form['interview_time'] : null;

                $venue = $form['interview_venue'] !== ''
                    ? $form['interview_venue'] : null;

                $interviewer = $form['interviewer'] !== ''
                    ? $form['interviewer'] : null;

                $qualifications = $form['qualifications'] !== ''
                    ? $form['qualifications'] : null;

                $technicalSkills = $form['technical_skills'] !== ''
                    ? $form['technical_skills'] : null;

                $communicationSkills = $form['communication_skills'] !== ''
                    ? $form['communication_skills'] : null;

                $strengths = $form['strengths'] !== ''
                    ? $form['strengths'] : null;

                $weaknesses = $form['weaknesses'] !== ''
                    ? $form['weaknesses'] : null;

                $interviewNotes = $form['interview_notes'] !== ''
                    ? $form['interview_notes'] : null;

                $recommendation = $form['recommendation'] !== ''
                    ? $form['recommendation'] : null;

                $nextSteps = $form['next_steps'] !== ''
                    ? $form['next_steps'] : null;

                if ($editing) {
                    $stmt = $conn->prepare(
                        "UPDATE shortlisted_applicants SET
                            full_name = ?,
                            phone = ?,
                            email = ?,
                            address = ?,
                            position_applied = ?,
                            interview_date = ?,
                            interview_time = ?,
                            interview_venue = ?,
                            interviewer = ?,
                            interview_status = ?,
                            qualifications = ?,
                            technical_skills = ?,
                            communication_skills = ?,
                            strengths = ?,
                            weaknesses = ?,
                            interview_notes = ?,
                            recommendation = ?,
                            next_steps = ?
                         WHERE applicant_id = ?"
                    );

                    $stmt->bind_param(
                        "ssssssssssssssssssi",
                        $form['full_name'],
                        $form['phone'],
                        $email,
                        $address,
                        $form['position_applied'],
                        $interviewDate,
                        $interviewTime,
                        $venue,
                        $interviewer,
                        $form['interview_status'],
                        $qualifications,
                        $technicalSkills,
                        $communicationSkills,
                        $strengths,
                        $weaknesses,
                        $interviewNotes,
                        $recommendation,
                        $nextSteps,
                        $editId
                    );
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO shortlisted_applicants (
                            full_name,
                            phone,
                            email,
                            address,
                            position_applied,
                            interview_date,
                            interview_time,
                            interview_venue,
                            interviewer,
                            interview_status,
                            qualifications,
                            technical_skills,
                            communication_skills,
                            strengths,
                            weaknesses,
                            interview_notes,
                            recommendation,
                            next_steps
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?, ?, ?
                        )"
                    );

                    $stmt->bind_param(
                        "ssssssssssssssssss",
                        $form['full_name'],
                        $form['phone'],
                        $email,
                        $address,
                        $form['position_applied'],
                        $interviewDate,
                        $interviewTime,
                        $venue,
                        $interviewer,
                        $form['interview_status'],
                        $qualifications,
                        $technicalSkills,
                        $communicationSkills,
                        $strengths,
                        $weaknesses,
                        $interviewNotes,
                        $recommendation,
                        $nextSteps
                    );
                }

                if ($stmt->execute()) {
                    $stmt->close();

                    shortlist_redirect(
                        $editing
                            ? "short_listed.php?updated=1"
                            : "short_listed.php?added=1"
                    );
                } else {
                    $errors[] = "Unable to save the applicant record. Please try again.";
                    $stmt->close();
                }
            }
        }
    }
}

/* -------------------------------------------------
   Load applicant for editing
------------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['edit']) &&
    ctype_digit((string)$_GET['edit']) &&
    (int)$_GET['edit'] > 0
) {
    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT * FROM shortlisted_applicants
         WHERE applicant_id = ?"
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
        $errors[] = "The selected applicant was not found.";
    }
}

/* -------------------------------------------------
   View applicant
------------------------------------------------- */

$viewRecord = null;

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['view']) &&
    ctype_digit((string)$_GET['view']) &&
    (int)$_GET['view'] > 0
) {
    $viewId = (int)$_GET['view'];

    $stmt = $conn->prepare(
        "SELECT * FROM shortlisted_applicants
         WHERE applicant_id = ?"
    );

    $stmt->bind_param("i", $viewId);
    $stmt->execute();

    $viewRecord = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$viewRecord) {
        $errors[] = "The selected applicant was not found.";
    }
}

/* -------------------------------------------------
   Search and filter
------------------------------------------------- */

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$allowedStatuses = [
    'Pending',
    'Attended',
    'Absent',
    'Passed',
    'Failed'
];

$sql = "
    SELECT *
    FROM shortlisted_applicants
    WHERE 1 = 1
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (
        full_name LIKE ?
        OR phone LIKE ?
        OR email LIKE ?
        OR position_applied LIKE ?
        OR interviewer LIKE ?
    )";

    $term = '%' . $search . '%';

    $params = [$term, $term, $term, $term, $term];
    $types = 'sssss';
}

if (in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= " AND interview_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql .= " ORDER BY
    CASE WHEN interview_status = 'Pending' THEN 0 ELSE 1 END,
    interview_date ASC,
    applicant_id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$applicants = $stmt->get_result();

/* -------------------------------------------------
   Dashboard statistics
------------------------------------------------- */

$statsResult = $conn->query("
    SELECT
        COUNT(*) AS total_applicants,

        COALESCE(SUM(
            interview_status = 'Pending'
        ), 0) AS pending_applicants,

        COALESCE(SUM(
            interview_status = 'Attended'
        ), 0) AS attended_interviews,

        COALESCE(SUM(
            interview_status = 'Passed'
        ), 0) AS passed_applicants,

        COALESCE(SUM(
            interview_status = 'Absent'
        ), 0) AS absent_applicants

    FROM shortlisted_applicants
");

$stats = $statsResult->fetch_assoc();

/* Upcoming interviews: pending applicants with today's
   or a future interview date. */
$upcomingResult = $conn->query("
    SELECT COUNT(*) AS upcoming_interviews
    FROM shortlisted_applicants
    WHERE interview_status = 'Pending'
      AND interview_date >= CURDATE()
");

$upcoming = $upcomingResult->fetch_assoc();

if (isset($_GET['added'])) {
    $success = "Applicant shortlisted successfully.";
} elseif (isset($_GET['updated'])) {
    $success = "Applicant and interview details updated successfully.";
} elseif (isset($_GET['deleted'])) {
    $success = "Applicant record deleted successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shortlisted Applicants | Lynntech ERP</title>
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
                Shortlisted Applicants
            </h1>
            <p class="text-gray-600 mt-2">
                Manage shortlisted candidates, schedule interviews,
                and record interview assessments.
            </p>
        </div>

        <a href="short_listed.php#applicant-form"
           class="inline-flex items-center justify-center
                  bg-yellow-600 hover:bg-yellow-700 text-white
                  font-semibold px-5 py-3 rounded-lg shadow">
            + Add Applicant
        </a>
    </div>

    <!-- Notifications -->
    <?php if ($success !== ''): ?>
        <div class="mb-6 rounded-lg border border-green-200
                    bg-green-50 text-green-800 px-4 py-3">
            <?= shortlist_escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 rounded-lg border border-red-200
                    bg-red-50 text-red-800 px-4 py-3">
            <p class="font-semibold mb-2">Please check the following:</p>
            <ul class="list-disc pl-5 space-y-1">
                <?php foreach ($errors as $error): ?>
                    <li><?= shortlist_escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Dashboard cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4
                gap-5 mb-8">

        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-5">
            <p class="text-sm text-gray-500">Total Shortlisted</p>
            <p class="text-3xl font-bold text-gray-800 mt-2">
                <?= (int)$stats['total_applicants'] ?>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-5">
            <p class="text-sm text-gray-500">Upcoming Interviews</p>
            <p class="text-3xl font-bold text-blue-600 mt-2">
                <?= (int)$upcoming['upcoming_interviews'] ?>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-5">
            <p class="text-sm text-gray-500">Interviews Attended</p>
            <p class="text-3xl font-bold text-indigo-600 mt-2">
                <?= (int)$stats['attended_interviews'] ?>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-5">
            <p class="text-sm text-gray-500">Passed Interview</p>
            <p class="text-3xl font-bold text-green-600 mt-2">
                <?= (int)$stats['passed_applicants'] ?>
            </p>
        </div>

    </div>

    <!-- Applicant form -->
    <div id="applicant-form"
         class="bg-white rounded-xl shadow-sm border
                border-gray-200 p-6 mb-8">

        <div class="flex flex-wrap items-center
                    justify-between gap-3 mb-6">
            <h2 class="text-xl font-bold text-gray-800">
                <?= $editing
                    ? 'Edit Applicant / Interview Details'
                    : 'Register Shortlisted Applicant' ?>
            </h2>

            <?php if ($editing): ?>
                <a href="short_listed.php"
                   class="text-sm text-gray-600 underline">
                    Cancel editing
                </a>
            <?php endif; ?>
        </div>

        <form method="POST" action="short_listed.php#applicant-form"
              class="space-y-6">

            <input type="hidden" name="csrf_token"
                   value="<?= shortlist_escape($_SESSION['shortlist_csrf']) ?>">

            <input type="hidden" name="action" value="save">

            <input type="hidden" name="applicant_id"
                   value="<?= (int)$editId ?>">

            <!-- Applicant information -->
            <div>
                <h3 class="text-sm font-bold uppercase tracking-wide
                           text-gray-500 mb-4">
                    Applicant Contact Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2
                            xl:grid-cols-3 gap-5">

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Full Name *
                        </label>
                        <input type="text" name="full_name" required
                               maxlength="150"
                               value="<?= shortlist_escape($form['full_name']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Phone Number *
                        </label>
                        <input type="tel" name="phone" required
                               maxlength="30"
                               value="<?= shortlist_escape($form['phone']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Email Address
                        </label>
                        <input type="email" name="email" maxlength="150"
                               value="<?= shortlist_escape($form['email']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Position Applied For *
                        </label>
                        <input type="text" name="position_applied" required
                               maxlength="150"
                               value="<?= shortlist_escape($form['position_applied']) ?>"
                               placeholder="e.g. Sales Representative"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Address / Location
                        </label>
                        <input type="text" name="address" maxlength="255"
                               value="<?= shortlist_escape($form['address']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                </div>
            </div>

            <!-- Interview scheduling -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-sm font-bold uppercase tracking-wide
                           text-gray-500 mb-4">
                    Interview Scheduling
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2
                            xl:grid-cols-4 gap-5">

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Interview Date
                        </label>
                        <input type="date" name="interview_date"
                               value="<?= shortlist_escape($form['interview_date']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Interview Time
                        </label>
                        <input type="time" name="interview_time"
                               value="<?= shortlist_escape($form['interview_time']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Interview Venue
                        </label>
                        <input type="text" name="interview_venue"
                               maxlength="200"
                               value="<?= shortlist_escape($form['interview_venue']) ?>"
                               placeholder="Office / Meeting Room"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Interviewer
                        </label>
                        <input type="text" name="interviewer"
                               maxlength="150"
                               value="<?= shortlist_escape($form['interviewer']) ?>"
                               class="w-full rounded-lg border border-gray-300
                                      px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                      focus:outline-none">
                    </div>

                </div>

                <div class="mt-5 max-w-md">
                    <label class="block text-sm font-semibold
                                  text-gray-700 mb-2">
                        Interview Status
                    </label>

                    <select name="interview_status"
                            class="w-full rounded-lg border border-gray-300
                                   px-4 py-3 bg-white focus:ring-2
                                   focus:ring-yellow-500 focus:outline-none">

                        <option value="Pending"
                            <?= $form['interview_status'] === 'Pending'
                                ? 'selected' : '' ?>>
                            Pending / Awaiting Interview
                        </option>

                        <option value="Attended"
                            <?= $form['interview_status'] === 'Attended'
                                ? 'selected' : '' ?>>
                            Attended - Assessment Pending
                        </option>

                        <option value="Absent"
                            <?= $form['interview_status'] === 'Absent'
                                ? 'selected' : '' ?>>
                            Absent
                        </option>

                        <option value="Passed"
                            <?= $form['interview_status'] === 'Passed'
                                ? 'selected' : '' ?>>
                            Passed
                        </option>

                        <option value="Failed"
                            <?= $form['interview_status'] === 'Failed'
                                ? 'selected' : '' ?>>
                            Failed
                        </option>
                    </select>
                </div>
            </div>

            <!-- Interview assessment -->
            <div class="border-t border-gray-200 pt-6">
                <h3 class="text-sm font-bold uppercase tracking-wide
                           text-gray-500 mb-2">
                    Interview Assessment Form
                </h3>

                <p class="text-sm text-gray-500 mb-4">
                    Complete these fields during or after the interview.
                    They can remain blank when first registering an applicant.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Qualifications and Experience
                        </label>
                        <textarea name="qualifications" rows="4"
                                  placeholder="Education, certificates, previous experience..."
                                  class="w-full rounded-lg border border-gray-300
                                         px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                         focus:outline-none"><?= shortlist_escape($form['qualifications']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Technical Skills Assessment
                        </label>
                        <textarea name="technical_skills" rows="4"
                                  placeholder="Technical knowledge, practical test results, relevant skills..."
                                  class="w-full rounded-lg border border-gray-300
                                         px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                         focus:outline-none"><?= shortlist_escape($form['technical_skills']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Communication and Presentation
                        </label>
                        <textarea name="communication_skills" rows="4"
                                  placeholder="Communication, confidence, clarity, and professionalism..."
                                  class="w-full rounded-lg border border-gray-300
                                         px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                         focus:outline-none"><?= shortlist_escape($form['communication_skills']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Strengths
                        </label>
                        <textarea name="strengths" rows="4"
                                  placeholder="Key strengths demonstrated by the candidate..."
                                  class="w-full rounded-lg border border-gray-300
                                         px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                         focus:outline-none"><?= shortlist_escape($form['strengths']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Weaknesses / Areas to Improve
                        </label>
                        <textarea name="weaknesses" rows="4"
                                  placeholder="Skills gaps or areas requiring improvement..."
                                  class="w-full rounded-lg border border-gray-300
                                         px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                         focus:outline-none"><?= shortlist_escape($form['weaknesses']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Interview Notes and Questions
                        </label>
                        <textarea name="interview_notes" rows="4"
                                  placeholder="Questions asked, candidate responses, observations..."
                                  class="w-full rounded-lg border border-gray-300
                                         px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                         focus:outline-none"><?= shortlist_escape($form['interview_notes']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Interviewer's Recommendation
                        </label>
                        <textarea name="recommendation" rows="4"
                                  placeholder="Recommend hiring, further assessment, or not proceeding..."
                                  class="w-full rounded-lg border border-gray-300
                                         px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                         focus:outline-none"><?= shortlist_escape($form['recommendation']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold
                                      text-gray-700 mb-2">
                            Next Steps / Follow-up
                        </label>
                        <textarea name="next_steps" rows="4"
                                  placeholder="Second interview, reference checks, offer, or other follow-up..."
                                  class="w-full rounded-lg border border-gray-300
                                         px-4 py-3 focus:ring-2 focus:ring-yellow-500
                                         focus:outline-none"><?= shortlist_escape($form['next_steps']) ?></textarea>
                    </div>

                </div>
            </div>

            <!-- Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                <button type="submit"
                        class="bg-yellow-600 hover:bg-yellow-700
                               text-white font-semibold px-6 py-3
                               rounded-lg shadow">
                    <?= $editing ? 'Update Applicant / Interview' : 'Save Applicant' ?>
                </button>

                <?php if ($editing): ?>
                    <a href="short_listed.php"
                       class="text-center bg-gray-200 hover:bg-gray-300
                              text-gray-800 font-semibold px-6 py-3 rounded-lg">
                        Cancel
                    </a>
                <?php else: ?>
                    <button type="reset"
                            class="bg-gray-200 hover:bg-gray-300
                                   text-gray-800 font-semibold px-6 py-3 rounded-lg">
                        Clear Form
                    </button>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <!-- View applicant details -->
    <?php if ($viewRecord): ?>
        <div class="bg-white rounded-xl shadow-sm border
                    border-gray-200 p-6 mb-8">

            <div class="flex flex-wrap items-center justify-between
                        gap-3 mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    Applicant and Interview Details
                </h2>

                <div class="flex gap-3">
                    <a href="short_listed.php?edit=<?= (int)$viewRecord['applicant_id'] ?>#applicant-form"
                       class="bg-yellow-600 hover:bg-yellow-700 text-white
                              px-4 py-2 rounded-lg font-semibold">
                        Edit
                    </a>

                    <a href="short_listed.php"
                       class="bg-gray-200 hover:bg-gray-300 text-gray-800
                              px-4 py-2 rounded-lg font-semibold">
                        Close
                    </a>
                </div>
            </div>

            <?php
            $viewFields = [
                'Full Name' => $viewRecord['full_name'],
                'Phone' => $viewRecord['phone'],
                'Email' => $viewRecord['email'],
                'Address' => $viewRecord['address'],
                'Position Applied For' => $viewRecord['position_applied'],
                'Interview Date' => $viewRecord['interview_date'],
                'Interview Time' => $viewRecord['interview_time'],
                'Venue' => $viewRecord['interview_venue'],
                'Interviewer' => $viewRecord['interviewer'],
                'Status' => $viewRecord['interview_status'],
                'Qualifications' => $viewRecord['qualifications'],
                'Technical Skills' => $viewRecord['technical_skills'],
                'Communication Skills' => $viewRecord['communication_skills'],
                'Strengths' => $viewRecord['strengths'],
                'Weaknesses' => $viewRecord['weaknesses'],
                'Interview Notes' => $viewRecord['interview_notes'],
                'Recommendation' => $viewRecord['recommendation'],
                'Next Steps' => $viewRecord['next_steps']
            ];
            ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($viewFields as $label => $value): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm text-gray-500 mb-1">
                            <?= shortlist_escape($label) ?>
                        </p>
                        <p class="font-semibold text-gray-800 whitespace-pre-wrap break-words">
                            <?= shortlist_escape(
                                ($value !== null && $value !== '')
                                    ? $value : 'Not provided'
                            ) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search and filter -->
    <div class="bg-white rounded-xl shadow-sm border
                border-gray-200 p-5 mb-6">

        <form method="GET" action="short_listed.php"
              class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold
                              text-gray-700 mb-2">
                    Search Applicants
                </label>
                <input type="text" name="search"
                       value="<?= shortlist_escape($search) ?>"
                       placeholder="Search name, phone, email, position, interviewer..."
                       class="w-full rounded-lg border border-gray-300
                              px-4 py-3 focus:ring-2 focus:ring-yellow-500
                              focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-semibold
                              text-gray-700 mb-2">
                    Filter by Status
                </label>
                <select name="status"
                        class="w-full rounded-lg border border-gray-300
                               bg-white px-4 py-3">
                    <option value="">All statuses</option>

                    <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= shortlist_escape($status) ?>"
                            <?= $statusFilter === $status ? 'selected' : '' ?>>
                            <?= shortlist_escape($status) ?>
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

                <a href="short_listed.php"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-800
                          font-semibold px-5 py-3 rounded-lg">
                    Reset
                </a>
            </div>

        </form>
    </div>

    <!-- Applicants table -->
    <div class="bg-white rounded-xl shadow-sm border
                border-gray-200 overflow-hidden">

        <div class="p-5 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">
                Shortlisted Applicants Register
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                Manage candidate contact details, interviews, and outcomes.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">

                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Applicant
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Contact
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Position
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Interview Date
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Time
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Interviewer
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Status
                        </th>
                        <th class="px-5 py-4 text-left text-xs font-semibold uppercase">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">

                    <?php if ($applicants->num_rows > 0): ?>
                        <?php while ($applicant = $applicants->fetch_assoc()): ?>

                            <?php
                            $status = $applicant['interview_status'];

                            $statusClass = 'bg-yellow-100 text-yellow-800';

                            if ($status === 'Passed') {
                                $statusClass = 'bg-green-100 text-green-800';
                            } elseif ($status === 'Failed') {
                                $statusClass = 'bg-red-100 text-red-800';
                            } elseif ($status === 'Attended') {
                                $statusClass = 'bg-blue-100 text-blue-800';
                            } elseif ($status === 'Absent') {
                                $statusClass = 'bg-gray-200 text-gray-800';
                            }
                            ?>

                            <tr class="hover:bg-gray-50">

                                <td class="px-5 py-4">
                                    <p class="font-semibold text-gray-800">
                                        <?= shortlist_escape($applicant['full_name']) ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?= shortlist_escape($applicant['address'] ?: 'No address') ?>
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <p><?= shortlist_escape($applicant['phone']) ?></p>
                                    <p class="text-gray-500 mt-1">
                                        <?= shortlist_escape($applicant['email'] ?: 'No email') ?>
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <?= shortlist_escape($applicant['position_applied']) ?>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700 whitespace-nowrap">
                                    <?= shortlist_escape($applicant['interview_date'] ?: 'Not scheduled') ?>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700 whitespace-nowrap">
                                    <?= shortlist_escape(
                                        $applicant['interview_time']
                                            ? date('h:i A', strtotime($applicant['interview_time']))
                                            : '—'
                                    ) ?>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <?= shortlist_escape($applicant['interviewer'] ?: 'Not assigned') ?>
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1
                                                 text-xs font-semibold <?= $statusClass ?>">
                                        <?= shortlist_escape($status) ?>
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex flex-col items-start gap-3">

                                        <a href="short_listed.php?view=<?= (int)$applicant['applicant_id'] ?>"
                                           class="text-blue-700 hover:text-blue-900
                                                  font-semibold text-sm">
                                            View Details
                                        </a>

                                        <a href="short_listed.php?edit=<?= (int)$applicant['applicant_id'] ?>#applicant-form"
                                           class="text-yellow-700 hover:text-yellow-900
                                                  font-semibold text-sm">
                                            Edit / Interview Form
                                        </a>

                                        <form method="POST" action="short_listed.php"
                                              onsubmit="return confirm('Are you sure you want to delete this applicant?');">

                                            <input type="hidden" name="csrf_token"
                                                   value="<?= shortlist_escape($_SESSION['shortlist_csrf']) ?>">

                                            <input type="hidden" name="action" value="delete">

                                            <input type="hidden" name="applicant_id"
                                                   value="<?= (int)$applicant['applicant_id'] ?>">

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
                            <td colspan="8"
                                class="px-5 py-12 text-center text-gray-500">
                                <p class="text-lg font-semibold">
                                    No shortlisted applicants found
                                </p>
                                <p class="text-sm mt-2">
                                    Add an applicant or change your search filters.
                                </p>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

    <p class="text-sm text-gray-500 mt-4 text-right">
        Shortlisted Applicants Management
    </p>

</div>

</body>
</html>
