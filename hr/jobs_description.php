
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

function redirectTo($url)
{
    header("Location: " . $url);
    exit;
}

/* =========================================================
   CSRF TOKEN
========================================================= */
if (empty($_SESSION['jobs_csrf_token'])) {
    $_SESSION['jobs_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['jobs_csrf_token'];
$successMessage = '';
$errorMessage = '';

/* =========================================================
   HANDLE ADD AND UPDATE
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            empty($_POST['csrf_token']) ||
            !hash_equals($_SESSION['jobs_csrf_token'], $_POST['csrf_token'])
        ) {
            throw new RuntimeException(
                'Your session token is invalid. Refresh the page and try again.'
            );
        }

        $action = $_POST['action'] ?? '';

        $department = trim($_POST['department'] ?? '');
        $jobRole = trim($_POST['job_role'] ?? '');
        $positionTitle = trim($_POST['position_title'] ?? '');
        $requirements = trim($_POST['requirements'] ?? '');
        $skillsRequired = trim($_POST['skills_required'] ?? '');
        $responsibilities = trim($_POST['responsibilities'] ?? '');

        if (
            $department === '' ||
            $jobRole === '' ||
            $positionTitle === '' ||
            $requirements === '' ||
            $skillsRequired === '' ||
            $responsibilities === ''
        ) {
            throw new RuntimeException(
                'Please fill in all the required fields.'
            );
        }

        if (
            mb_strlen($department) > 150 ||
            mb_strlen($jobRole) > 150 ||
            mb_strlen($positionTitle) > 200
        ) {
            throw new RuntimeException(
                'The department, job role, or position title is too long.'
            );
        }

        if ($action === 'add') {
            $stmt = $conn->prepare("
                INSERT INTO job_descriptions
                (
                    department,
                    job_role,
                    position_title,
                    requirements,
                    skills_required,
                    responsibilities
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssssss",
                $department,
                $jobRole,
                $positionTitle,
                $requirements,
                $skillsRequired,
                $responsibilities
            );

            $stmt->execute();
            $stmt->close();

            $_SESSION['jobs_flash'] = [
                'type' => 'success',
                'message' => 'Job description added successfully.'
            ];

            redirectTo('jobs_description.php');
        }

        if ($action === 'update') {
            $jobId = filter_var(
                $_POST['job_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$jobId || $jobId < 1) {
                throw new RuntimeException('Invalid job description ID.');
            }

            $stmt = $conn->prepare("
                UPDATE job_descriptions
                SET
                    department = ?,
                    job_role = ?,
                    position_title = ?,
                    requirements = ?,
                    skills_required = ?,
                    responsibilities = ?
                WHERE job_id = ?
            ");

            $stmt->bind_param(
                "ssssssi",
                $department,
                $jobRole,
                $positionTitle,
                $requirements,
                $skillsRequired,
                $responsibilities,
                $jobId
            );

            $stmt->execute();
            $stmt->close();

            $_SESSION['jobs_flash'] = [
                'type' => 'success',
                'message' => 'Job description updated successfully.'
            ];

            redirectTo('jobs_description.php');
        }

        throw new RuntimeException('Invalid form action.');

    } catch (Throwable $e) {
        $errorMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to save the job description. Please check your database connection and table.';
    }
}

/* =========================================================
   FLASH MESSAGES
========================================================= */
if (!empty($_SESSION['jobs_flash'])) {
    $successMessage = $_SESSION['jobs_flash']['message'];
    unset($_SESSION['jobs_flash']);
}

/* =========================================================
   SEARCH
========================================================= */
$search = trim($_GET['q'] ?? '');

/* =========================================================
   VIEW OR EDIT RECORD
========================================================= */
$viewId = filter_var(
    $_GET['view'] ?? null,
    FILTER_VALIDATE_INT
);

$editId = filter_var(
    $_GET['edit'] ?? null,
    FILTER_VALIDATE_INT
);

$selectedJob = null;
$pageMode = 'list';

if ($viewId && $viewId > 0) {
    $stmt = $conn->prepare("
        SELECT *
        FROM job_descriptions
        WHERE job_id = ?
    ");

    $stmt->bind_param("i", $viewId);
    $stmt->execute();

    $selectedJob = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selectedJob) {
        $pageMode = 'view';
    } else {
        $errorMessage = 'The requested job description was not found.';
    }
} elseif ($editId && $editId > 0) {
    $stmt = $conn->prepare("
        SELECT *
        FROM job_descriptions
        WHERE job_id = ?
    ");

    $stmt->bind_param("i", $editId);
    $stmt->execute();

    $selectedJob = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selectedJob) {
        $pageMode = 'edit';
    } else {
        $errorMessage = 'The job description you want to edit was not found.';
    }
} elseif (isset($_GET['add'])) {
    $pageMode = 'add';
}

/* =========================================================
   LOAD JOB DESCRIPTIONS
========================================================= */
if ($search !== '') {
    $likeSearch = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT *
        FROM job_descriptions
        WHERE department LIKE ?
           OR job_role LIKE ?
           OR position_title LIKE ?
        ORDER BY department ASC, position_title ASC
    ");

    $stmt->bind_param(
        "sss",
        $likeSearch,
        $likeSearch,
        $likeSearch
    );
} else {
    $stmt = $conn->prepare("
        SELECT *
        FROM job_descriptions
        ORDER BY department ASC, position_title ASC
    ");
}

$stmt->execute();
$jobs = $stmt->get_result();
$totalJobs = $jobs->num_rows;

/* =========================================================
   FORM DEFAULT VALUES
========================================================= */
$formData = [
    'job_id' => $selectedJob['job_id'] ?? '',
    'department' => $selectedJob['department'] ?? '',
    'job_role' => $selectedJob['job_role'] ?? '',
    'position_title' => $selectedJob['position_title'] ?? '',
    'requirements' => $selectedJob['requirements'] ?? '',
    'skills_required' => $selectedJob['skills_required'] ?? '',
    'responsibilities' => $selectedJob['responsibilities'] ?? ''
];

/*
 If saving fails, retain the submitted form values.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage !== '') {
    $formData = [
        'job_id' => $_POST['job_id'] ?? '',
        'department' => $_POST['department'] ?? '',
        'job_role' => $_POST['job_role'] ?? '',
        'position_title' => $_POST['position_title'] ?? '',
        'requirements' => $_POST['requirements'] ?? '',
        'skills_required' => $_POST['skills_required'] ?? '',
        'responsibilities' => $_POST['responsibilities'] ?? ''
    ];

    $pageMode = ($_POST['action'] ?? '') === 'update'
        ? 'edit'
        : 'add';

    if ($pageMode === 'edit' && empty($selectedJob)) {
        $selectedJob = $formData;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Job Descriptions | HR Management</title>

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
            transition: 0.2s;
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

        .btn-view:hover {
            background: #1d4ed8;
        }

        .btn-edit {
            background: #059669;
            color: white;
        }

        .btn-edit:hover {
            background: #047857;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #d1d5db;
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
    </style>
</head>

<body class="bg-gray-100 min-h-screen">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">

        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                Job Descriptions
            </h1>

            <p class="text-sm text-gray-500 mt-2">
                Manage departments, job roles, position requirements,
                skills and responsibilities.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="jobs_description.php" class="btn btn-secondary">
                All Positions
            </a>

            <a href="jobs_description.php?add=1" class="btn btn-primary">
                <span class="text-lg leading-none">+</span>
                Add Job Description
            </a>
        </div>
    </div>

    <!-- ALERT MESSAGES -->
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

    <!-- SUMMARY -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

        <div class="page-card p-5">
            <p class="text-sm text-gray-500">Positions Listed</p>

            <p class="text-3xl font-bold text-gray-800 mt-2">
                <?= number_format($totalJobs) ?>
            </p>

            <p class="text-xs text-gray-400 mt-1">
                <?= $search !== '' ? 'Matching your search' : 'All job descriptions' ?>
            </p>
        </div>

        <div class="page-card p-5">
            <p class="text-sm text-gray-500">Current Section</p>

            <p class="text-xl font-bold text-gray-800 mt-3">
                <?php
                if ($pageMode === 'add') {
                    echo 'Add Position';
                } elseif ($pageMode === 'edit') {
                    echo 'Edit Position';
                } elseif ($pageMode === 'view') {
                    echo 'View Position';
                } else {
                    echo 'Position Directory';
                }
                ?>
            </p>

            <p class="text-xs text-gray-400 mt-2">
                Human Resources Management
            </p>
        </div>

    </div>

    <!-- ADD / EDIT FORM -->
    <?php if ($pageMode === 'add' || $pageMode === 'edit'): ?>

        <div class="page-card p-5 md:p-7 mb-7">

            <div class="border-b border-gray-200 pb-4 mb-6">
                <h2 class="text-xl font-bold text-gray-800">
                    <?= $pageMode === 'edit'
                        ? 'Edit Job Description'
                        : 'Add New Job Description' ?>
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    Enter the details of the department and position below.
                </p>
            </div>

            <form method="POST" action="jobs_description.php" class="space-y-5">

                <input type="hidden" name="csrf_token"
                       value="<?= h($csrfToken) ?>">

                <input type="hidden" name="action"
                       value="<?= $pageMode === 'edit' ? 'update' : 'add' ?>">

                <?php if ($pageMode === 'edit'): ?>
                    <input type="hidden" name="job_id"
                           value="<?= h($formData['job_id']) ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div>
                        <label for="department" class="field-label">
                            Department <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="text"
                            id="department"
                            name="department"
                            class="field-input"
                            placeholder="e.g. Human Resources"
                            maxlength="150"
                            value="<?= h($formData['department']) ?>"
                            required
                        >
                    </div>

                    <div>
                        <label for="job_role" class="field-label">
                            Job Role <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="text"
                            id="job_role"
                            name="job_role"
                            class="field-input"
                            placeholder="e.g. HR Officer"
                            maxlength="150"
                            value="<?= h($formData['job_role']) ?>"
                            required
                        >
                    </div>

                    <div class="md:col-span-2">
                        <label for="position_title" class="field-label">
                            Position Title <span class="text-red-600">*</span>
                        </label>

                        <input
                            type="text"
                            id="position_title"
                            name="position_title"
                            class="field-input"
                            placeholder="e.g. Human Resources Officer - Grade II"
                            maxlength="200"
                            value="<?= h($formData['position_title']) ?>"
                            required
                        >
                    </div>

                </div>

                <div>
                    <label for="requirements" class="field-label">
                        Job Requirements <span class="text-red-600">*</span>
                    </label>

                    <textarea
                        id="requirements"
                        name="requirements"
                        rows="4"
                        class="field-input"
                        placeholder="Enter education, qualifications, experience and other requirements..."
                        required
                    ><?= h($formData['requirements']) ?></textarea>
                </div>

                <div>
                    <label for="skills_required" class="field-label">
                        Skills Required <span class="text-red-600">*</span>
                    </label>

                    <textarea
                        id="skills_required"
                        name="skills_required"
                        rows="4"
                        class="field-input"
                        placeholder="Enter technical skills, communication, leadership and other relevant skills..."
                        required
                    ><?= h($formData['skills_required']) ?></textarea>
                </div>

                <div>
                    <label for="responsibilities" class="field-label">
                        Responsibilities of the Position
                        <span class="text-red-600">*</span>
                    </label>

                    <textarea
                        id="responsibilities"
                        name="responsibilities"
                        rows="5"
                        class="field-input"
                        placeholder="Describe the duties, tasks and responsibilities of this position..."
                        required
                    ><?= h($formData['responsibilities']) ?></textarea>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-3
                            border-t border-gray-200">

                    <button type="submit" class="btn btn-primary">
                        <?= $pageMode === 'edit'
                            ? 'Save Changes'
                            : 'Save Job Description' ?>
                    </button>

                    <a href="jobs_description.php" class="btn btn-secondary">
                        Cancel
                    </a>

                </div>

            </form>
        </div>

    <?php endif; ?>

    <!-- VIEW JOB DESCRIPTION -->
    <?php if ($pageMode === 'view' && $selectedJob): ?>

        <div class="page-card p-5 md:p-7 mb-7">

            <div class="flex flex-col sm:flex-row sm:items-start
                        sm:justify-between gap-4 border-b border-gray-200
                        pb-5 mb-6">

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider
                              text-blue-700">
                        <?= h($selectedJob['department']) ?>
                    </p>

                    <h2 class="text-2xl font-bold text-gray-800 mt-2">
                        <?= h($selectedJob['position_title']) ?>
                    </h2>

                    <p class="text-sm text-gray-500 mt-2">
                        Job Role: <?= h($selectedJob['job_role']) ?>
                    </p>
                </div>

                <a
                    href="jobs_description.php?edit=<?= (int)$selectedJob['job_id'] ?>"
                    class="btn btn-edit"
                >
                    Edit Position
                </a>

            </div>

            <div class="space-y-6">

                <section>
                    <h3 class="font-bold text-gray-800 mb-2">
                        Job Requirements
                    </h3>

                    <div class="description-text text-sm text-gray-600">
                        <?= h($selectedJob['requirements']) ?>
                    </div>
                </section>

                <section>
                    <h3 class="font-bold text-gray-800 mb-2">
                        Skills Required
                    </h3>

                    <div class="description-text text-sm text-gray-600">
                        <?= h($selectedJob['skills_required']) ?>
                    </div>
                </section>

                <section>
                    <h3 class="font-bold text-gray-800 mb-2">
                        Responsibilities
                    </h3>

                    <div class="description-text text-sm text-gray-600">
                        <?= h($selectedJob['responsibilities']) ?>
                    </div>
                </section>

                <section class="border-t border-gray-200 pt-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4
                                text-xs text-gray-500">
                        <p>
                            Created:
                            <?= h($selectedJob['created_at']) ?>
                        </p>

                        <p>
                            Last Updated:
                            <?= h($selectedJob['updated_at']) ?>
                        </p>
                    </div>
                </section>

            </div>

            <div class="mt-7">
                <a href="jobs_description.php" class="btn btn-secondary">
                    Back to Positions
                </a>
            </div>

        </div>

    <?php endif; ?>

    <!-- JOB POSITIONS TABLE -->
    <?php if ($pageMode === 'list' || $pageMode === 'add' || $pageMode === 'edit'): ?>

        <div class="page-card overflow-hidden">

            <div class="p-5 border-b border-gray-200
                        flex flex-col md:flex-row md:items-center
                        md:justify-between gap-4">

                <div>
                    <h2 class="text-lg font-bold text-gray-800">
                        Added Positions
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        View and manage the job descriptions in your system.
                    </p>
                </div>

                <form method="GET" action="jobs_description.php"
                      class="flex flex-col sm:flex-row gap-2">

                    <input
                        type="text"
                        name="q"
                        class="field-input"
                        placeholder="Search department or position..."
                        value="<?= h($search) ?>"
                    >

                    <button type="submit" class="btn btn-view">
                        Search
                    </button>

                    <?php if ($search !== ''): ?>
                        <a href="jobs_description.php"
                           class="btn btn-secondary">
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
                            <th class="table-heading">Department</th>
                            <th class="table-heading">Job Role</th>
                            <th class="table-heading">Position Title</th>
                            <th class="table-heading">Date Added</th>
                            <th class="table-heading">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white">

                        <?php if ($totalJobs > 0): ?>

                            <?php $counter = 1; ?>

                            <?php while ($job = $jobs->fetch_assoc()): ?>

                                <tr class="hover:bg-gray-50">

                                    <td class="table-cell text-gray-500">
                                        <?= $counter++ ?>
                                    </td>

                                    <td class="table-cell">
                                        <span class="inline-block rounded-full
                                                     bg-blue-50 text-blue-700
                                                     px-3 py-1 text-xs font-semibold">
                                            <?= h($job['department']) ?>
                                        </span>
                                    </td>

                                    <td class="table-cell font-medium text-gray-700">
                                        <?= h($job['job_role']) ?>
                                    </td>

                                    <td class="table-cell font-semibold text-gray-800">
                                        <?= h($job['position_title']) ?>
                                    </td>

                                    <td class="table-cell text-gray-500 whitespace-nowrap">
                                        <?= h(date(
                                            'd M Y',
                                            strtotime($job['created_at'])
                                        )) ?>
                                    </td>

                                    <td class="table-cell">
                                        <div class="flex flex-wrap gap-2">

                                            <a
                                                href="jobs_description.php?view=<?= (int)$job['job_id'] ?>"
                                                class="btn btn-view"
                                            >
                                                View
                                            </a>

                                            <a
                                                href="jobs_description.php?edit=<?= (int)$job['job_id'] ?>"
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
                                <td colspan="6" class="px-6 py-12 text-center">

                                    <div class="text-gray-400 text-4xl mb-3">
                                        &#128196;
                                    </div>

                                    <p class="font-semibold text-gray-700">
                                        No job descriptions found
                                    </p>

                                    <p class="text-sm text-gray-500 mt-2">
                                        <?= $search !== ''
                                            ? 'Try a different search term.'
                                            : 'Click Add Job Description to create your first position.' ?>
                                    </p>

                                    <?php if ($search === ''): ?>
                                        <a
                                            href="jobs_description.php?add=1"
                                            class="btn btn-primary mt-4"
                                        >
                                            + Add First Position
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
                Showing <?= number_format($totalJobs) ?>
                <?= $totalJobs === 1 ? 'position' : 'positions' ?>.
            </div>

        </div>

    <?php endif; ?>

    <div class="text-center text-xs text-gray-400 mt-8">
        HR Management System &mdash; Job Descriptions
    </div>

</div>

</body>
</html>

<?php
$stmt->close();
?>
