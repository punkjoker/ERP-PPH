
<?php
session_start();
date_default_timezone_set('Africa/Nairobi');

/*
|--------------------------------------------------------------------------
| 1. AUTHENTICATION
|--------------------------------------------------------------------------
| IMPORTANT:
| Match these session variables and role names to the authentication
| guard used by your existing Lynntech HR pages.
*/

$loggedInUserId = $_SESSION['user_id']
    ?? $_SESSION['admin_id']
    ?? null;

$userRole = strtolower(trim((string)(
    $_SESSION['user_role']
    ?? $_SESSION['role']
    ?? $_SESSION['group_name']
    ?? ''
)));

$allowedRoles = [
    'admin',
    'superadmin',
    'hr',
    'hr manager',
    'hr_manager',
    'human resource manager'
];

if (!$loggedInUserId || !in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    exit('Access denied. Please log in using an authorized HR account.');
}

/*
|--------------------------------------------------------------------------
| 2. DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

require_once __DIR__ . 'db_con.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('Database connection is not available. Check db_con.php.');
}

$conn->set_charset('utf8mb4');

/*
|--------------------------------------------------------------------------
| 3. HELPERS
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirectPage(): void
{
    header('Location: job_adverts.php');
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['job_advert_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function generateJobToken(): string
{
    return bin2hex(random_bytes(32));
}

function makeApplicationLink(string $token): string
{
    /*
     * Works on localhost and on a hosted domain.
     * The public_jobs folder is expected to be at the project root.
     */
    $isHttps = (
        (!empty($_SERVER['HTTPS']) &&
            strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );

    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Reject unexpected host-header values.
    if (!preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
        throw new RuntimeException('Invalid application host.');
    }

    $projectRoot = dirname(dirname($_SERVER['SCRIPT_NAME']));
    $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');

    return $scheme . '://' . $host
        . $projectRoot
        . '/public_jobs/apply.php?token='
        . rawurlencode($token);
}

/*
|--------------------------------------------------------------------------
| 4. CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['job_adverts_csrf'])) {
    $_SESSION['job_adverts_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['job_adverts_csrf'];

/*
|--------------------------------------------------------------------------
| 5. PROCESS FORM SUBMISSIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $submittedCsrf)) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        /*
        |--------------------------------------------------------------
        | CREATE OR UPDATE ADVERT
        |--------------------------------------------------------------
        */

        if (in_array($action, ['save_draft', 'publish'], true)) {
            $id = filter_var(
                $_POST['advert_id'] ?? 0,
                FILTER_VALIDATE_INT
            );

            $id = $id ? (int)$id : 0;

            $title = trim((string)($_POST['title'] ?? ''));
            $department = trim((string)($_POST['department'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $employmentType = trim(
                (string)($_POST['employment_type'] ?? 'Full-time')
            );
            $description = trim(
                (string)($_POST['description'] ?? '')
            );
            $responsibilities = trim(
                (string)($_POST['responsibilities'] ?? '')
            );
            $qualifications = trim(
                (string)($_POST['qualifications'] ?? '')
            );
            $deadline = trim(
                (string)($_POST['application_deadline'] ?? '')
            );

            $validTypes = [
                'Full-time',
                'Part-time',
                'Contract',
                'Internship',
                'Temporary'
            ];

            if (
                $title === '' ||
                $department === '' ||
                $location === '' ||
                $description === '' ||
                $qualifications === '' ||
                $deadline === ''
            ) {
                throw new RuntimeException(
                    'Complete all required fields.'
                );
            }

            if (
                mb_strlen($title) > 180 ||
                mb_strlen($department) > 120 ||
                mb_strlen($location) > 180 ||
                mb_strlen($description) > 50000 ||
                mb_strlen($responsibilities) > 30000 ||
                mb_strlen($qualifications) > 30000
            ) {
                throw new RuntimeException(
                    'One or more fields exceed the permitted length.'
                );
            }

            if (!in_array($employmentType, $validTypes, true)) {
                throw new RuntimeException(
                    'Select a valid employment type.'
                );
            }

            $dateObject = DateTime::createFromFormat('!Y-m-d', $deadline);
            $dateErrors = DateTime::getLastErrors();

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d') !== $deadline ||
                ($dateErrors !== false &&
                    ($dateErrors['warning_count'] > 0 ||
                     $dateErrors['error_count'] > 0))
            ) {
                throw new RuntimeException(
                    'Enter a valid application deadline.'
                );
            }

            if (
                $action === 'publish' &&
                $deadline < date('Y-m-d')
            ) {
                throw new RuntimeException(
                    'The application deadline cannot be in the past.'
                );
            }

            if ($id > 0) {
                // Confirm that the advert exists before updating.
                $check = $conn->prepare(
                    'SELECT id FROM job_adverts WHERE id = ? LIMIT 1'
                );
                $check->bind_param('i', $id);
                $check->execute();
                $existing = $check->get_result()->fetch_assoc();
                $check->close();

                if (!$existing) {
                    throw new RuntimeException(
                        'The selected job advert was not found.'
                    );
                }

                if ($action === 'save_draft') {
                    // Saving a draft immediately disables its public link.
                    $status = 'Draft';
                    $linkActive = 0;

                    $stmt = $conn->prepare("
                        UPDATE job_adverts
                        SET title = ?,
                            department = ?,
                            location = ?,
                            employment_type = ?,
                            description = ?,
                            responsibilities = ?,
                            qualifications = ?,
                            application_deadline = ?,
                            status = ?,
                            link_active = ?,
                            published_at = NULL
                        WHERE id = ?
                    ");

                    $stmt->bind_param(
                        'sssssssssii',
                        $title,
                        $department,
                        $location,
                        $employmentType,
                        $description,
                        $responsibilities,
                        $qualifications,
                        $deadline,
                        $status,
                        $linkActive,
                        $id
                    );

                    $stmt->execute();
                    $stmt->close();

                    setFlash(
                        'success',
                        'Job advert saved as a draft. Its public link is disabled.'
                    );
                    redirectPage();
                }

                /*
                 * Publishing always creates a fresh token.
                 * The raw token is never stored in the database.
                 */
                $token = generateJobToken();
                $tokenHash = hash('sha256', $token);
                $status = 'Published';
                $linkActive = 1;

                $stmt = $conn->prepare("
                    UPDATE job_adverts
                    SET title = ?,
                        department = ?,
                        location = ?,
                        employment_type = ?,
                        description = ?,
                        responsibilities = ?,
                        qualifications = ?,
                        application_deadline = ?,
                        status = ?,
                        link_token_hash = ?,
                        link_active = ?,
                        published_at = NOW()
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    'ssssssssssii',
                    $title,
                    $department,
                    $location,
                    $employmentType,
                    $description,
                    $responsibilities,
                    $qualifications,
                    $deadline,
                    $status,
                    $tokenHash,
                    $linkActive,
                    $id
                );

                $stmt->execute();
                $stmt->close();

                $applicationLink = makeApplicationLink($token);

                // Show the raw link once, after publishing.
                $_SESSION['job_advert_new_link'] = [
                    'id' => $id,
                    'title' => $title,
                    'url' => $applicationLink
                ];

                setFlash(
                    'success',
                    'Job advert published. Copy the new application link below.'
                );

                redirectPage();
            } else {
                /*
                 * New adverts are always created as drafts.
                 * Publishing is a separate action after creation.
                 */
                $status = 'Draft';
                $linkActive = 0;
                $createdBy = (int)$loggedInUserId;

                $stmt = $conn->prepare("
                    INSERT INTO job_adverts (
                        title,
                        department,
                        location,
                        employment_type,
                        description,
                        responsibilities,
                        qualifications,
                        application_deadline,
                        status,
                        link_active,
                        created_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    'sssssssssii',
                    $title,
                    $department,
                    $location,
                    $employmentType,
                    $description,
                    $responsibilities,
                    $qualifications,
                    $deadline,
                    $status,
                    $linkActive,
                    $createdBy
                );

                $stmt->execute();
                $stmt->close();

                setFlash(
                    'success',
                    'Job advert created as a draft. Open it to publish and generate its link.'
                );
                redirectPage();
            }
        }

        /*
        |--------------------------------------------------------------
        | CLOSE ADVERT
        |--------------------------------------------------------------
        */

        if ($action === 'close') {
            $id = filter_var(
                $_POST['advert_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid advert ID.');
            }

            $stmt = $conn->prepare("
                UPDATE job_adverts
                SET status = 'Closed',
                    link_active = 0
                WHERE id = ?
            ");

            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows < 0) {
                $stmt->close();
                throw new RuntimeException('Could not close the advert.');
            }

            $stmt->close();

            setFlash(
                'success',
                'Advert closed. New applications must be rejected by the public submission endpoint.'
            );
            redirectPage();
        }

        /*
        |--------------------------------------------------------------
        | REVOKE ADVERT LINK
        |--------------------------------------------------------------
        */

        if ($action === 'revoke') {
            $id = filter_var(
                $_POST['advert_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid advert ID.');
            }

            $stmt = $conn->prepare("
                UPDATE job_adverts
                SET status = 'Revoked',
                    link_active = 0,
                    link_token_hash = NULL
                WHERE id = ?
            ");

            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            setFlash(
                'success',
                'The application link has been revoked. Publish the advert again to create a new link.'
            );
            redirectPage();
        }

        /*
        |--------------------------------------------------------------
        | EDIT ADVERT
        |--------------------------------------------------------------
        */

        if ($action === 'edit') {
            $id = filter_var(
                $_POST['advert_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid advert ID.');
            }

            $_SESSION['job_advert_edit_id'] = (int)$id;
            redirectPage();
        }

        if ($action === 'cancel_edit') {
            unset($_SESSION['job_advert_edit_id']);
            redirectPage();
        }

        throw new RuntimeException('Unknown action.');

    } catch (Throwable $e) {
        setFlash('error', $e->getMessage());
        redirectPage();
    }
}

/*
|--------------------------------------------------------------------------
| 6. LOAD FLASH MESSAGES AND ONE-TIME LINK
|--------------------------------------------------------------------------
*/

$flash = $_SESSION['job_advert_flash'] ?? null;
unset($_SESSION['job_advert_flash']);

$newLink = $_SESSION['job_advert_new_link'] ?? null;
unset($_SESSION['job_advert_new_link']);

/*
|--------------------------------------------------------------------------
| 7. LOAD EDIT FORM DATA
|--------------------------------------------------------------------------
*/

$editAdvert = null;
$editId = (int)($_GET['edit'] ?? $_SESSION['job_advert_edit_id'] ?? 0);
unset($_SESSION['job_advert_edit_id']);

if ($editId > 0) {
    $stmt = $conn->prepare("
        SELECT *
        FROM job_adverts
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editAdvert = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editAdvert) {
        $flash = [
            'type' => 'error',
            'message' => 'The advert selected for editing was not found.'
        ];
    }
}

/*
|--------------------------------------------------------------------------
| 8. LOAD ADVERTS AND APPLICATION COUNTS
|--------------------------------------------------------------------------
*/

$adverts = [];

$result = $conn->query("
    SELECT
        j.*,
        (
            SELECT COUNT(*)
            FROM job_applications a
            WHERE a.job_advert_id = j.id
        ) AS application_count
    FROM job_adverts j
    ORDER BY j.created_at DESC
");

while ($row = $result->fetch_assoc()) {
    $adverts[] = $row;
}

$result->free();

$totalAdverts = count($adverts);
$publishedCount = 0;
$totalApplications = 0;

foreach ($adverts as $advert) {
    if ($advert['status'] === 'Published' && (int)$advert['link_active'] === 1) {
        $publishedCount++;
    }

    $totalApplications += (int)$advert['application_count'];
}

/*
|--------------------------------------------------------------------------
| 9. PAGE HEADER
|--------------------------------------------------------------------------
*/

include_once __DIR__ . '/navbar.php';

$old = $editAdvert ?? [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Job Advertisements | Lynntech HR</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
        }

        .gold {
            background: #b8860b;
        }

        .gold:hover {
            background: #967006;
        }

        .panel {
            background: white;
            border-radius: 14px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, .06);
        }

        .field {
            width: 100%;
            border: 1px solid #d1d5db;
            padding: 10px 12px;
            border-radius: 8px;
            margin-top: 6px;
            outline: none;
        }

        .field:focus {
            border-color: #b8860b;
            box-shadow: 0 0 0 2px rgba(184, 134, 11, .15);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 13px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 0;
        }

        .btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-Published { background: #dcfce7; color: #166534; }
        .status-Draft { background: #e5e7eb; color: #374151; }
        .status-Closed { background: #fef3c7; color: #92400e; }
        .status-Revoked { background: #fee2e2; color: #991b1b; }

        textarea.field {
            min-height: 110px;
            resize: vertical;
        }
    </style>
</head>

<body>
<main class="max-w-7xl mx-auto p-4 md:p-8">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-7">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                Job Advertisements
            </h1>
            <p class="text-gray-500 mt-2">
                Create vacancies, share application links and manage recruitment.
            </p>
        </div>

        <a href="#advert-form" class="btn gold text-white">
            + Create Job Advert
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="mb-6 p-4 rounded-lg
            <?= $flash['type'] === 'error'
                ? 'bg-red-100 text-red-800'
                : 'bg-green-100 text-green-800' ?>">
            <?= h($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($newLink): ?>
        <section class="panel p-5 mb-7 border-l-4 border-green-600">
            <h2 class="text-lg font-bold text-gray-800">
                Application link generated
            </h2>

            <p class="text-sm text-gray-600 mt-1">
                <?= h($newLink['title']) ?>
            </p>

            <div class="flex flex-col md:flex-row gap-3 mt-4">
                <input
                    id="newApplicationLink"
                    class="field mt-0"
                    readonly
                    value="<?= h($newLink['url']) ?>"
                >

                <button
                    type="button"
                    onclick="copyApplicationLink()"
                    class="btn bg-green-700 hover:bg-green-800 text-white"
                >
                    Copy Link
                </button>

                <a
                    class="btn bg-gray-800 hover:bg-gray-900 text-white"
                    target="_blank"
                    rel="noopener noreferrer"
                    href="<?= h($newLink['url']) ?>"
                >
                    Test Link
                </a>
            </div>

            <p class="text-xs text-amber-700 mt-3">
                Copy and save this link now. The raw token is shown only once.
                If you lose it, publish again to generate a new link.
            </p>
        </section>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->

    <section class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="panel p-5">
            <p class="text-sm text-gray-500">Total Advertisements</p>
            <p class="text-3xl font-bold text-gray-800 mt-2">
                <?= $totalAdverts ?>
            </p>
        </div>

        <div class="panel p-5">
            <p class="text-sm text-gray-500">Active Published Links</p>
            <p class="text-3xl font-bold text-green-700 mt-2">
                <?= $publishedCount ?>
            </p>
        </div>

        <div class="panel p-5">
            <p class="text-sm text-gray-500">Applications Received</p>
            <p class="text-3xl font-bold text-blue-700 mt-2">
                <?= $totalApplications ?>
            </p>
        </div>
    </section>

    <!-- CREATE / EDIT FORM -->

    <section id="advert-form" class="panel p-5 md:p-7 mb-8">
        <div class="flex items-center justify-between gap-3 mb-6">
            <h2 class="text-xl font-bold text-gray-800">
                <?= $editAdvert ? 'Edit Job Advertisement' : 'Create Job Advertisement' ?>
            </h2>

            <?php if ($editAdvert): ?>
                <a href="job_adverts.php" class="text-sm text-gray-600 underline">
                    Cancel Editing
                </a>
            <?php endif; ?>
        </div>

        <form method="POST" action="job_adverts.php" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="advert_id" value="<?= (int)($old['id'] ?? 0) ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="font-semibold text-sm">Job Title *</label>
                    <input
                        class="field"
                        type="text"
                        name="title"
                        maxlength="180"
                        required
                        value="<?= h($old['title'] ?? '') ?>"
                        placeholder="e.g. Procurement Officer"
                    >
                </div>

                <div>
                    <label class="font-semibold text-sm">Department *</label>
                    <input
                        class="field"
                        type="text"
                        name="department"
                        maxlength="120"
                        required
                        value="<?= h($old['department'] ?? '') ?>"
                        placeholder="e.g. Procurement"
                    >
                </div>

                <div>
                    <label class="font-semibold text-sm">Job Location *</label>
                    <input
                        class="field"
                        type="text"
                        name="location"
                        maxlength="180"
                        required
                        value="<?= h($old['location'] ?? '') ?>"
                        placeholder="e.g. Kisaju, off Namanga Road"
                    >
                </div>

                <div>
                    <label class="font-semibold text-sm">Employment Type *</label>
                    <select class="field" name="employment_type" required>
                        <?php
                        $types = [
                            'Full-time',
                            'Part-time',
                            'Contract',
                            'Internship',
                            'Temporary'
                        ];

                        $selectedType = $old['employment_type'] ?? 'Full-time';

                        foreach ($types as $type):
                        ?>
                            <option
                                value="<?= h($type) ?>"
                                <?= $selectedType === $type ? 'selected' : '' ?>
                            >
                                <?= h($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="font-semibold text-sm">Application Deadline *</label>
                    <input
                        class="field"
                        type="date"
                        name="application_deadline"
                        min="<?= date('Y-m-d') ?>"
                        required
                        value="<?= h($old['application_deadline'] ?? '') ?>"
                    >
                </div>
            </div>

            <div>
                <label class="font-semibold text-sm">Job Description *</label>
                <textarea
                    class="field"
                    name="description"
                    maxlength="50000"
                    required
                    placeholder="Explain the purpose of the position..."
                ><?= h($old['description'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="font-semibold text-sm">Responsibilities</label>
                <textarea
                    class="field"
                    name="responsibilities"
                    maxlength="30000"
                    placeholder="List the main duties and responsibilities..."
                ><?= h($old['responsibilities'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="font-semibold text-sm">Qualifications and Requirements *</label>
                <textarea
                    class="field"
                    name="qualifications"
                    maxlength="30000"
                    required
                    placeholder="Education, experience, skills and other requirements..."
                ><?= h($old['qualifications'] ?? '') ?></textarea>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button
                    class="btn bg-gray-700 hover:bg-gray-800 text-white"
                    type="submit"
                    name="action"
                    value="save_draft"
                >
                    Save as Draft
                </button>

                <button
                    class="btn gold text-white"
                    type="submit"
                    name="action"
                    value="publish"
                    onclick="return confirm('Publish this advert and generate a new application link?');"
                >
                    Publish & Generate Link
                </button>
            </div>

            <p class="text-xs text-gray-500">
                Publishing creates a new secure link. Republishing an advert
                replaces its previous token, so previously shared links stop working.
            </p>
        </form>
    </section>

    <!-- ADVERTS TABLE -->

    <section class="panel p-5 md:p-7">
        <h2 class="text-xl font-bold text-gray-800 mb-5">
            Manage Existing Advertisements
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 text-gray-600">
                    <tr>
                        <th class="p-3">Job Title</th>
                        <th class="p-3">Department</th>
                        <th class="p-3">Deadline</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Applications</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                <?php if (!$adverts): ?>
                    <tr>
                        <td colspan="6" class="p-6 text-center text-gray-500">
                            No job advertisements yet. Create your first advert above.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($adverts as $advert): ?>
                    <tr>
                        <td class="p-3 font-semibold text-gray-800">
                            <?= h($advert['title']) ?>
                            <div class="text-xs font-normal text-gray-500 mt-1">
                                <?= h($advert['location']) ?>
                            </div>
                        </td>

                        <td class="p-3"><?= h($advert['department']) ?></td>

                        <td class="p-3 whitespace-nowrap">
                            <?= h($advert['application_deadline']) ?>
                        </td>

                        <td class="p-3">
                            <span class="status status-<?= h($advert['status']) ?>">
                                <?= h($advert['status']) ?>
                            </span>

                            <?php if (
                                (int)$advert['link_active'] === 1 &&
                                $advert['status'] === 'Published'
                            ): ?>
                                <div class="text-xs text-green-700 mt-1">
                                    Link active
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-gray-500 mt-1">
                                    Link inactive
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="p-3 font-semibold">
                            <?= (int)$advert['application_count'] ?>
                        </td>

                        <td class="p-3">
                            <div class="flex flex-wrap gap-2 min-w-48">
                                <a
                                    class="btn bg-blue-600 hover:bg-blue-700 text-white"
                                    href="job_adverts.php?edit=<?= (int)$advert['id'] ?>#advert-form"
                                >
                                    Edit
                                </a>

                                <?php if ($advert['status'] !== 'Revoked'): ?>
                                    <form method="POST" action="job_adverts.php"
                                          onsubmit="return confirm('Close this advert and stop new submissions?');">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="advert_id" value="<?= (int)$advert['id'] ?>">
                                        <button class="btn bg-amber-600 hover:bg-amber-700 text-white"
                                                type="submit" name="action" value="close">
                                            Close
                                        </button>
                                    </form>

                                    <form method="POST" action="job_adverts.php"
                                          onsubmit="return confirm('Permanently revoke this link? Previously shared links will stop working.');">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="advert_id" value="<?= (int)$advert['id'] ?>">
                                        <button class="btn bg-red-600 hover:bg-red-700 text-white"
                                                type="submit" name="action" value="revoke">
                                            Revoke
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<script>
async function copyApplicationLink() {
    const input = document.getElementById('newApplicationLink');

    try {
        await navigator.clipboard.writeText(input.value);
        alert('Application link copied.');
    } catch (error) {
        input.focus();
        input.select();

        const copied = document.execCommand('copy');

        alert(
            copied
                ? 'Application link copied.'
                : 'Please copy the selected link manually.'
        );
    }
}
</script>

</body>
</html>
