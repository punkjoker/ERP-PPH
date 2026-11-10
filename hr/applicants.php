
<?php
session_start();
require_once 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

// CSRF protection
if (empty($_SESSION['applicants_csrf'])) {
    $_SESSION['applicants_csrf'] = bin2hex(random_bytes(32));
}

/* ---------------------------------------------
   Helper functions
--------------------------------------------- */

function app_escape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function app_redirect($url): void
{
    header("Location: " . $url);
    exit;
}

/**
 * Upload or replace an applicant document.
 *
 * Supported formats: PDF, DOC, DOCX
 * Maximum size: 5MB
 * Existing document is retained when no new file is selected.
 */
function app_upload_document(
    mysqli $conn,
    int $applicantId,
    string $fieldName,
    string $documentType
): void {

    if (
        !isset($_FILES[$fieldName]) ||
        $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            "Upload failed for " . $documentType . "."
        );
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException(
            $documentType . " must not exceed 5 MB."
        );
    }

    if (
        !is_uploaded_file($file['tmp_name']) ||
        !is_file($file['tmp_name'])
    ) {
        throw new RuntimeException(
            "Invalid uploaded file for " . $documentType . "."
        );
    }

    $extension = strtolower(
        pathinfo($file['name'], PATHINFO_EXTENSION)
    );

    $allowedExtensions = ['pdf', 'doc', 'docx'];

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException(
            $documentType . " must be a PDF, DOC or DOCX file."
        );
    }

    if (!class_exists('finfo')) {
        throw new RuntimeException(
            "PHP fileinfo extension is required for document uploads."
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedMimes = [
        'pdf' => [
            'application/pdf'
        ],
        'doc' => [
            'application/msword',
            'application/x-ole-storage',
            'application/octet-stream'
        ],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream'
        ]
    ];

    if (!in_array($mime, $allowedMimes[$extension], true)) {
        throw new RuntimeException(
            "The selected " . $documentType . " file is not valid."
        );
    }

    $uploadDirectory = __DIR__ . '/uploads/applicant_documents/';

    if (
        !is_dir($uploadDirectory) &&
        !mkdir($uploadDirectory, 0755, true)
    ) {
        throw new RuntimeException(
            "Could not create applicant document folder."
        );
    }

    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException(
            "Applicant document folder is not writable."
        );
    }

    $storedName = bin2hex(random_bytes(20)) . '.' . $extension;
    $absolutePath = $uploadDirectory . $storedName;
    $relativePath = 'uploads/applicant_documents/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException(
            "Could not save the " . $documentType . "."
        );
    }

    try {

        // Get the previous file so it can be deleted after replacement.
        $oldStmt = $conn->prepare("
            SELECT stored_name
            FROM applicant_documents
            WHERE applicant_id = ?
              AND document_type = ?
            LIMIT 1
        ");

        $oldStmt->bind_param(
            "is",
            $applicantId,
            $documentType
        );

        $oldStmt->execute();

        $oldDocument = $oldStmt->get_result()->fetch_assoc();
        $oldStmt->close();

        $originalName = basename($file['name']);
        $fileSize = (int)$file['size'];

        // Insert a new document or replace the existing one.
        $stmt = $conn->prepare("
            INSERT INTO applicant_documents (
                applicant_id,
                document_type,
                original_name,
                stored_name,
                file_path,
                mime_type,
                file_size
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                original_name = VALUES(original_name),
                stored_name = VALUES(stored_name),
                file_path = VALUES(file_path),
                mime_type = VALUES(mime_type),
                file_size = VALUES(file_size),
                uploaded_at = CURRENT_TIMESTAMP
        ");

        $stmt->bind_param(
            "isssssi",
            $applicantId,
            $documentType,
            $originalName,
            $storedName,
            $relativePath,
            $mime,
            $fileSize
        );

        $stmt->execute();
        $stmt->close();

        // Delete the old physical file after successful replacement.
        if (!empty($oldDocument['stored_name'])) {

            $oldStoredName = basename($oldDocument['stored_name']);
            $oldFilePath = $uploadDirectory . $oldStoredName;

            if (
                $oldStoredName !== $storedName &&
                is_file($oldFilePath)
            ) {
                unlink($oldFilePath);
            }
        }

    } catch (Throwable $e) {

        // Remove the newly uploaded file if database saving fails.
        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }

        throw $e;
    }
}


/* ---------------------------------------------
   Initial page variables
--------------------------------------------- */

$errors = [];
$success = '';

$form = [
    'full_name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'position_applied' => '',
    'qualifications' => ''
];

$editing = false;
$editId = 0;
$viewRecord = null;
$viewDocuments = [];


/* ---------------------------------------------
   Handle POST requests
--------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['applicants_csrf'], $token)
    ) {

        $errors[] = "Invalid session token. Refresh the page and try again.";

    } else {

        $action = $_POST['action'] ?? '';

        /* -------------------------------------
           Add or update applicant
        ------------------------------------- */

        if ($action === 'save') {

            foreach ($form as $key => $value) {
                $form[$key] = trim((string)($_POST[$key] ?? ''));
            }

            $editId = filter_var(
                $_POST['applicant_id'] ?? '0',
                FILTER_VALIDATE_INT
            ) ?: 0;

            $editing = $editId > 0;

            // Normal applicant validation.
            if ($form['full_name'] === '') {
                $errors[] = "Applicant full name is required.";
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

            // Validate upload errors, sizes and extensions.
            $documentFields = [
                'application_letter' => 'Application Letter',
                'cv' => 'CV'
            ];

            foreach ($documentFields as $field => $label) {

                if (
                    !isset($_FILES[$field]) ||
                    $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
                ) {
                    continue;
                }

                if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = $label . " upload failed.";
                    continue;
                }

                if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
                    $errors[] = $label . " must not exceed 5 MB.";
                }

                $extension = strtolower(
                    pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION)
                );

                if (!in_array($extension, ['pdf', 'doc', 'docx'], true)) {
                    $errors[] = $label . " must be PDF, DOC or DOCX.";
                }
            }

            if (empty($errors)) {

                $email = $form['email'] !== ''
                    ? $form['email'] : null;

                $address = $form['address'] !== ''
                    ? $form['address'] : null;

                $qualifications = $form['qualifications'] !== ''
                    ? $form['qualifications'] : null;

                try {

                    if ($editing) {

                        /* -----------------------------
                           UPDATE EXISTING APPLICANT
                        ----------------------------- */

                        $checkStmt = $conn->prepare("
                            SELECT applicant_id
                            FROM applicants
                            WHERE applicant_id = ?
                            LIMIT 1
                        ");

                        $checkStmt->bind_param("i", $editId);
                        $checkStmt->execute();

                        $existingApplicant = $checkStmt
                            ->get_result()
                            ->fetch_assoc();

                        $checkStmt->close();

                        if (!$existingApplicant) {
                            throw new RuntimeException(
                                "Applicant record not found."
                            );
                        }

                        $stmt = $conn->prepare("
                            UPDATE applicants
                            SET
                                full_name = ?,
                                phone = ?,
                                email = ?,
                                address = ?,
                                position_applied = ?,
                                qualifications = ?
                            WHERE applicant_id = ?
                        ");

                        $stmt->bind_param(
                            "ssssssi",
                            $form['full_name'],
                            $form['phone'],
                            $email,
                            $address,
                            $form['position_applied'],
                            $qualifications,
                            $editId
                        );

                        $stmt->execute();
                        $stmt->close();

                        // Upload or replace application letter.
                        app_upload_document(
                            $conn,
                            $editId,
                            'application_letter',
                            'Application Letter'
                        );

                        // Upload or replace CV.
                        app_upload_document(
                            $conn,
                            $editId,
                            'cv',
                            'CV'
                        );

                        app_redirect("applicants.php?updated=1");

                    } else {

                        /* -----------------------------
                           INSERT NEW APPLICANT
                        ----------------------------- */

                        $stmt = $conn->prepare("
                            INSERT INTO applicants (
                                full_name,
                                phone,
                                email,
                                address,
                                position_applied,
                                qualifications
                            ) VALUES (?, ?, ?, ?, ?, ?)
                        ");

                        $stmt->bind_param(
                            "ssssss",
                            $form['full_name'],
                            $form['phone'],
                            $email,
                            $address,
                            $form['position_applied'],
                            $qualifications
                        );

                        $stmt->execute();

                        $newApplicantId = (int)$conn->insert_id;

                        $stmt->close();

                        // Keep the new applicant ID in case an upload fails.
                        $editId = $newApplicantId;
                        $editing = true;

                        // Upload or replace application letter.
                        app_upload_document(
                            $conn,
                            $newApplicantId,
                            'application_letter',
                            'Application Letter'
                        );

                        // Upload or replace CV.
                        app_upload_document(
                            $conn,
                            $newApplicantId,
                            'cv',
                            'CV'
                        );

                        app_redirect("applicants.php?added=1");
                    }

                } catch (Throwable $e) {

                    $errors[] = $e->getMessage();
                }
            }
        }

        /* -------------------------------------
           Shortlist applicant
        ------------------------------------- */

        elseif ($action === 'shortlist') {

            $applicantId = filter_var(
                $_POST['applicant_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$applicantId || $applicantId < 1) {

                $errors[] = "Invalid applicant selected.";

            } else {

                try {

                    $conn->begin_transaction();

                    // Lock and fetch applicant.
                    $stmt = $conn->prepare("
                        SELECT *
                        FROM applicants
                        WHERE applicant_id = ?
                        FOR UPDATE
                    ");

                    $stmt->bind_param("i", $applicantId);
                    $stmt->execute();

                    $applicant = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$applicant) {
                        throw new Exception("Applicant not found.");
                    }

                    // Check whether already shortlisted.
                    $check = $conn->prepare("
                        SELECT applicant_id
                        FROM shortlisted_applicants
                        WHERE source_applicant_id = ?
                        LIMIT 1
                    ");

                    $check->bind_param("i", $applicantId);
                    $check->execute();

                    $alreadyShortlisted = $check
                        ->get_result()
                        ->fetch_assoc();

                    $check->close();

                    if ($alreadyShortlisted) {
                        throw new Exception(
                            "This applicant has already been shortlisted."
                        );
                    }

                    // Create shortlisted record.
                    $status = 'Pending';

                    $stmt = $conn->prepare("
                        INSERT INTO shortlisted_applicants (
                            source_applicant_id,
                            full_name,
                            phone,
                            email,
                            address,
                            position_applied,
                            interview_status,
                            qualifications
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->bind_param(
                        "isssssss",
                        $applicantId,
                        $applicant['full_name'],
                        $applicant['phone'],
                        $applicant['email'],
                        $applicant['address'],
                        $applicant['position_applied'],
                        $status,
                        $applicant['qualifications']
                    );

                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();

                    app_redirect("applicants.php?shortlisted=1");

                } catch (Throwable $e) {

                    if ($conn->errno === 0 || $conn->errno !== 0) {
                        // Roll back only if a transaction is still active.
                        try {
                            $conn->rollback();
                        } catch (Throwable $rollbackError) {
                            // Ignore rollback errors.
                        }
                    }

                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}


/* ---------------------------------------------
   Load applicant for editing
--------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['edit']) &&
    ctype_digit((string)$_GET['edit'])
) {

    $editId = (int)$_GET['edit'];

    $stmt = $conn->prepare("
        SELECT *
        FROM applicants
        WHERE applicant_id = ?
    ");

    $stmt->bind_param("i", $editId);
    $stmt->execute();

    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($record) {

        $editing = true;

        foreach ($form as $key => $value) {
            $form[$key] = $record[$key] ?? '';
        }

    } else {

        $errors[] = "Applicant record not found.";
    }
}


/* ---------------------------------------------
   View applicant and their documents
--------------------------------------------- */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' &&
    isset($_GET['view']) &&
    ctype_digit((string)$_GET['view'])
) {

    $viewId = (int)$_GET['view'];

    $stmt = $conn->prepare("
        SELECT a.*,
               s.applicant_id AS shortlisted_id
        FROM applicants a
        LEFT JOIN shortlisted_applicants s
            ON s.source_applicant_id = a.applicant_id
        WHERE a.applicant_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $viewId);
    $stmt->execute();

    $viewRecord = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($viewRecord) {

        $docStmt = $conn->prepare("
            SELECT
                document_id,
                document_type,
                original_name,
                uploaded_at
            FROM applicant_documents
            WHERE applicant_id = ?
            ORDER BY document_type ASC
        ");

        $docStmt->bind_param("i", $viewId);
        $docStmt->execute();

        $viewDocuments = $docStmt
            ->get_result()
            ->fetch_all(MYSQLI_ASSOC);

        $docStmt->close();

    } else {

        $errors[] = "Applicant record not found.";
    }
}


/* ---------------------------------------------
   Search and filtering
--------------------------------------------- */

$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$sort = $_GET['sort'] ?? 'latest';

$sql = "
    SELECT a.*,
           s.applicant_id AS shortlisted_id
    FROM applicants a
    LEFT JOIN shortlisted_applicants s
        ON s.source_applicant_id = a.applicant_id
    WHERE 1 = 1
";

$params = [];
$types = '';

if ($search !== '') {

    $sql .= "
        AND (
            a.full_name LIKE ?
            OR a.phone LIKE ?
            OR a.email LIKE ?
            OR a.position_applied LIKE ?
        )
    ";

    $term = '%' . $search . '%';

    $params = [$term, $term, $term, $term];
    $types = 'ssss';
}

if ($roleFilter !== '') {

    $sql .= " AND a.position_applied = ?";

    $params[] = $roleFilter;
    $types .= 's';
}

if ($sort === 'oldest') {
    $sql .= " ORDER BY a.created_at ASC";
} else {
    $sql .= " ORDER BY a.created_at DESC";
}

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$applicants = $stmt->get_result();


/* ---------------------------------------------
   Available roles for filter
--------------------------------------------- */

$rolesResult = $conn->query("
    SELECT DISTINCT position_applied
    FROM applicants
    WHERE position_applied IS NOT NULL
      AND position_applied <> ''
    ORDER BY position_applied ASC
");


/* ---------------------------------------------
   Statistics
--------------------------------------------- */

$stats = $conn->query("
    SELECT
        COUNT(*) AS total_applicants,
        COUNT(DISTINCT position_applied) AS total_roles
    FROM applicants
")->fetch_assoc();

$shortlistedCount = $conn->query("
    SELECT COUNT(*) AS total
    FROM shortlisted_applicants
")->fetch_assoc()['total'];


/* ---------------------------------------------
   Success messages
--------------------------------------------- */

if (isset($_GET['added'])) {
    $success = "Applicant registered successfully.";
} elseif (isset($_GET['updated'])) {
    $success = "Applicant details and documents updated successfully.";
} elseif (isset($_GET['shortlisted'])) {
    $success = "Applicant successfully added to the shortlist.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Applicants Register | Lynntech Manager</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <!-- Header -->
    <div class="flex flex-col md:flex-row
                justify-between md:items-center gap-4 mb-8">

        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                Applicants Register
            </h1>

            <p class="text-gray-500 mt-2">
                Register and manage all job applicants.
            </p>
        </div>

        <a href="applicants.php#applicant-form"
           class="bg-blue-600 hover:bg-blue-700 text-white
                  px-5 py-3 rounded-lg font-semibold">
            + Add Applicant
        </a>

    </div>


    <!-- Messages -->
    <?php if ($success): ?>

        <div class="bg-green-100 border border-green-300
                    text-green-800 p-4 rounded-lg mb-5">
            <?= app_escape($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($errors): ?>

        <div class="bg-red-100 border border-red-300
                    text-red-800 p-4 rounded-lg mb-5">

            <?php foreach ($errors as $error): ?>
                <p><?= app_escape($error) ?></p>
            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!-- Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">

        <div class="bg-white rounded-xl shadow-sm p-5 border">
            <p class="text-sm text-gray-500">Total Applicants</p>

            <p class="text-3xl font-bold text-blue-700 mt-2">
                <?= (int)$stats['total_applicants'] ?>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5 border">
            <p class="text-sm text-gray-500">Available Roles</p>

            <p class="text-3xl font-bold text-indigo-700 mt-2">
                <?= (int)$stats['total_roles'] ?>
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5 border">
            <p class="text-sm text-gray-500">Shortlisted Candidates</p>

            <p class="text-3xl font-bold text-green-700 mt-2">
                <?= (int)$shortlistedCount ?>
            </p>
        </div>

    </div>


    <!-- Add/Edit Form -->
    <div id="applicant-form"
         class="bg-white rounded-xl shadow-sm border p-6 mb-8">

        <h2 class="text-xl font-bold text-gray-800 mb-5">
            <?= $editing ? 'Edit Applicant' : 'Register New Applicant' ?>
        </h2>

        <form method="POST"
              action="applicants.php#applicant-form"
              enctype="multipart/form-data"
              class="space-y-5">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= app_escape($_SESSION['applicants_csrf']) ?>">

            <input type="hidden" name="action" value="save">

            <input type="hidden"
                   name="applicant_id"
                   value="<?= (int)$editId ?>">

            <div class="grid grid-cols-1 md:grid-cols-2
                        xl:grid-cols-3 gap-5">

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Applicant Full Name *
                    </label>

                    <input type="text"
                           name="full_name"
                           required
                           value="<?= app_escape($form['full_name']) ?>"
                           class="w-full border rounded-lg p-3">
                </div>


                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Phone Number *
                    </label>

                    <input type="tel"
                           name="phone"
                           required
                           value="<?= app_escape($form['phone']) ?>"
                           class="w-full border rounded-lg p-3">
                </div>


                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Email Address
                    </label>

                    <input type="email"
                           name="email"
                           value="<?= app_escape($form['email']) ?>"
                           class="w-full border rounded-lg p-3">
                </div>


                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Position / Role Applied For *
                    </label>

                    <input type="text"
                           name="position_applied"
                           required
                           list="available-roles"
                           value="<?= app_escape($form['position_applied']) ?>"
                           placeholder="e.g. Accountant"
                           class="w-full border rounded-lg p-3">

                    <datalist id="available-roles">

                        <?php
                        $rolesResult->data_seek(0);

                        while ($role = $rolesResult->fetch_assoc()):
                        ?>

                            <option value="<?= app_escape($role['position_applied']) ?>">

                        <?php endwhile; ?>

                    </datalist>
                </div>


                <div class="md:col-span-2">

                    <label class="block text-sm font-semibold mb-2">
                        Address / Location
                    </label>

                    <input type="text"
                           name="address"
                           value="<?= app_escape($form['address']) ?>"
                           class="w-full border rounded-lg p-3">

                </div>


                <div class="md:col-span-2 xl:col-span-3">

                    <label class="block text-sm font-semibold mb-2">
                        Qualifications / Experience
                    </label>

                    <textarea name="qualifications"
                              rows="4"
                              placeholder="Education, qualifications, certifications and work experience..."
                              class="w-full border rounded-lg p-3"><?= app_escape($form['qualifications']) ?></textarea>

                </div>


                <!-- Application Documents -->
                <div class="md:col-span-2 xl:col-span-3">

                    <div class="border-t pt-5">

                        <h3 class="text-lg font-bold text-gray-800 mb-1">
                            Application Documents
                        </h3>

                        <p class="text-sm text-gray-500 mb-4">
                            Upload the applicant's application letter and CV.
                            Accepted formats: PDF, DOC and DOCX.
                            Maximum size: 5 MB each.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                            <div>

                                <label class="block text-sm font-semibold mb-2">
                                    <i class="fas fa-file-alt mr-1"></i>
                                    Application Letter
                                </label>

                                <input type="file"
                                       name="application_letter"
                                       accept=".pdf,.doc,.docx"
                                       class="w-full border rounded-lg p-3 bg-white">

                                <p class="text-xs text-gray-500 mt-1">
                                    Leave blank to keep the existing document.
                                </p>

                            </div>


                            <div>

                                <label class="block text-sm font-semibold mb-2">
                                    <i class="fas fa-file-pdf mr-1"></i>
                                    Curriculum Vitae (CV)
                                </label>

                                <input type="file"
                                       name="cv"
                                       accept=".pdf,.doc,.docx"
                                       class="w-full border rounded-lg p-3 bg-white">

                                <p class="text-xs text-gray-500 mt-1">
                                    Leave blank to keep the existing document.
                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Form Buttons -->
            <div class="flex flex-wrap gap-3">

                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700
                               text-white px-6 py-3 rounded-lg font-semibold">

                    <?= $editing ? 'Update Applicant' : 'Save Applicant' ?>

                </button>


                <?php if ($editing): ?>

                    <a href="applicants.php"
                       class="bg-gray-200 hover:bg-gray-300
                              text-gray-800 px-6 py-3 rounded-lg font-semibold">

                        Cancel

                    </a>

                <?php else: ?>

                    <button type="reset"
                            class="bg-gray-200 hover:bg-gray-300
                                   text-gray-800 px-6 py-3 rounded-lg font-semibold">

                        Clear

                    </button>

                <?php endif; ?>

            </div>

        </form>

    </div>


    <!-- Applicant Details -->
    <?php if ($viewRecord): ?>

        <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">

            <div class="flex justify-between items-center mb-5">

                <h2 class="text-xl font-bold">
                    Applicant Details
                </h2>

                <a href="applicants.php"
                   class="text-gray-600 hover:text-gray-900">
                    Close
                </a>

            </div>


            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <?php

                $details = [
                    'Full Name' => $viewRecord['full_name'],
                    'Phone' => $viewRecord['phone'],
                    'Email' => $viewRecord['email'],
                    'Address' => $viewRecord['address'],
                    'Position Applied For' => $viewRecord['position_applied'],
                    'Qualifications / Experience' => $viewRecord['qualifications'],
                    'Date Registered' => $viewRecord['created_at']
                ];

                ?>

                <?php foreach ($details as $label => $value): ?>

                    <div class="bg-gray-50 p-4 rounded-lg">

                        <p class="text-sm text-gray-500 mb-1">
                            <?= app_escape($label) ?>
                        </p>

                        <p class="font-semibold text-gray-800 whitespace-pre-wrap break-words">
                            <?= app_escape($value ?: 'Not provided') ?>
                        </p>

                    </div>

                <?php endforeach; ?>

            </div>


            <!-- Applicant Documents -->
            <div class="mt-6 border-t pt-5">

                <h3 class="text-lg font-bold text-gray-800 mb-3">
                    Application Documents
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <?php

                    $documentTypes = [
                        'Application Letter',
                        'CV'
                    ];

                    foreach ($documentTypes as $documentType):

                        $document = null;

                        foreach ($viewDocuments as $doc) {

                            if ($doc['document_type'] === $documentType) {
                                $document = $doc;
                                break;
                            }
                        }

                    ?>

                        <div class="border rounded-lg p-4 bg-gray-50">

                            <p class="font-semibold text-gray-800 mb-2">
                                <?= app_escape($documentType) ?>
                            </p>

                            <?php if ($document): ?>

                                <p class="text-sm text-gray-600 mb-3 break-all">
                                    <?= app_escape($document['original_name']) ?>
                                </p>

                                <a href="download_applicant_document.php?id=<?= (int)$document['document_id'] ?>"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-block bg-blue-600 hover:bg-blue-700
                                          text-white px-4 py-2 rounded-lg text-sm">

                                    <i class="fas fa-download mr-1"></i>
                                    View / Download

                                </a>

                                <p class="text-xs text-gray-500 mt-2">
                                    Uploaded:
                                    <?= app_escape($document['uploaded_at']) ?>
                                </p>

                            <?php else: ?>

                                <p class="text-sm text-orange-600">
                                    Not uploaded yet.
                                </p>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>


            <!-- Applicant Actions -->
            <div class="flex flex-wrap gap-3 mt-6">

                <a href="applicants.php?edit=<?= (int)$viewRecord['applicant_id'] ?>#applicant-form"
                   class="bg-yellow-500 hover:bg-yellow-600
                          text-white px-5 py-2 rounded-lg">

                    Edit Applicant

                </a>


                <?php if (empty($viewRecord['shortlisted_id'])): ?>

                    <form method="POST" action="applicants.php">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= app_escape($_SESSION['applicants_csrf']) ?>">

                        <input type="hidden" name="action" value="shortlist">

                        <input type="hidden"
                               name="applicant_id"
                               value="<?= (int)$viewRecord['applicant_id'] ?>">

                        <button type="submit"
                                onclick="return confirm('Add this applicant to the shortlist?')"
                                class="bg-green-600 hover:bg-green-700
                                       text-white px-5 py-2 rounded-lg">

                            Shortlist Candidate

                        </button>

                    </form>

                <?php else: ?>

                    <span class="bg-green-100 text-green-800
                                 px-5 py-2 rounded-lg font-semibold">

                        Already Shortlisted

                    </span>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>


    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border p-5 mb-6">

        <h2 class="text-lg font-bold text-gray-800 mb-4">
            Search and Filter Applicants
        </h2>

        <form method="GET"
              action="applicants.php"
              class="grid grid-cols-1 md:grid-cols-4 gap-4">

            <div class="md:col-span-2">

                <label class="block text-sm font-semibold mb-2">
                    Search Applicants
                </label>

                <input type="text"
                       name="search"
                       value="<?= app_escape($search) ?>"
                       placeholder="Name, phone, email..."
                       class="w-full border rounded-lg p-3">

            </div>


            <div>

                <label class="block text-sm font-semibold mb-2">
                    Filter by Role
                </label>

                <select name="role"
                        class="w-full border rounded-lg p-3 bg-white">

                    <option value="">All Roles</option>

                    <?php

                    $rolesResult->data_seek(0);

                    while ($role = $rolesResult->fetch_assoc()):

                    ?>

                        <option value="<?= app_escape($role['position_applied']) ?>"
                            <?= $roleFilter === $role['position_applied']
                                ? 'selected' : '' ?>>

                            <?= app_escape($role['position_applied']) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <div>

                <label class="block text-sm font-semibold mb-2">
                    Sort Applicants
                </label>

                <select name="sort"
                        class="w-full border rounded-lg p-3 bg-white">

                    <option value="latest"
                        <?= $sort === 'latest' ? 'selected' : '' ?>>

                        Latest First

                    </option>

                    <option value="oldest"
                        <?= $sort === 'oldest' ? 'selected' : '' ?>>

                        Oldest First

                    </option>

                </select>

            </div>


            <div class="md:col-span-4 flex flex-wrap gap-3">

                <button type="submit"
                        class="bg-gray-800 hover:bg-gray-900
                               text-white px-5 py-3 rounded-lg">

                    Apply Filters

                </button>

                <a href="applicants.php"
                   class="bg-gray-200 hover:bg-gray-300
                          text-gray-800 px-5 py-3 rounded-lg">

                    Reset Filters

                </a>

            </div>

        </form>

    </div>


    <!-- Applicants Register -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">

        <div class="p-5 border-b">

            <h2 class="text-xl font-bold text-gray-800">
                All Applicants
            </h2>

            <p class="text-sm text-gray-500 mt-1">
                Newest applicants appear first.
            </p>

        </div>


        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-gray-200">

                <thead class="bg-gray-800 text-white">

                    <tr>

                        <th class="px-5 py-4 text-left text-xs uppercase">
                            Applicant
                        </th>

                        <th class="px-5 py-4 text-left text-xs uppercase">
                            Contact
                        </th>

                        <th class="px-5 py-4 text-left text-xs uppercase">
                            Position Applied
                        </th>

                        <th class="px-5 py-4 text-left text-xs uppercase">
                            Date Registered
                        </th>

                        <th class="px-5 py-4 text-left text-xs uppercase">
                            Shortlist Status
                        </th>

                        <th class="px-5 py-4 text-left text-xs uppercase">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody class="divide-y divide-gray-100">

                    <?php if ($applicants->num_rows > 0): ?>

                        <?php while ($applicant = $applicants->fetch_assoc()): ?>

                            <tr class="hover:bg-gray-50">

                                <td class="px-5 py-4">

                                    <p class="font-semibold text-gray-800">
                                        <?= app_escape($applicant['full_name']) ?>
                                    </p>

                                    <p class="text-sm text-gray-500">
                                        <?= app_escape(
                                            $applicant['address'] ?: 'No location provided'
                                        ) ?>
                                    </p>

                                </td>


                                <td class="px-5 py-4 text-sm">

                                    <p>
                                        <?= app_escape($applicant['phone']) ?>
                                    </p>

                                    <p class="text-gray-500">
                                        <?= app_escape(
                                            $applicant['email'] ?: 'No email'
                                        ) ?>
                                    </p>

                                </td>


                                <td class="px-5 py-4 text-sm font-medium">
                                    <?= app_escape($applicant['position_applied']) ?>
                                </td>


                                <td class="px-5 py-4 text-sm whitespace-nowrap">

                                    <?= app_escape(
                                        date(
                                            'd M Y',
                                            strtotime($applicant['created_at'])
                                        )
                                    ) ?>

                                </td>


                                <td class="px-5 py-4">

                                    <?php if ($applicant['shortlisted_id']): ?>

                                        <span class="bg-green-100 text-green-800
                                                     px-3 py-1 rounded-full
                                                     text-xs font-semibold">

                                            Shortlisted

                                        </span>

                                    <?php else: ?>

                                        <span class="bg-gray-100 text-gray-700
                                                     px-3 py-1 rounded-full
                                                     text-xs font-semibold">

                                            Not Shortlisted

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td class="px-5 py-4">

                                    <div class="flex flex-col gap-3 items-start">

                                        <a href="applicants.php?view=<?= (int)$applicant['applicant_id'] ?>"
                                           class="text-blue-600 hover:text-blue-800
                                                  font-semibold text-sm">

                                            View

                                        </a>


                                        <a href="applicants.php?edit=<?= (int)$applicant['applicant_id'] ?>#applicant-form"
                                           class="text-yellow-700 hover:text-yellow-900
                                                  font-semibold text-sm">

                                            Edit

                                        </a>


                                        <?php if (empty($applicant['shortlisted_id'])): ?>

                                            <form method="POST"
                                                  action="applicants.php"
                                                  onsubmit="return confirm('Shortlist this candidate?');">

                                                <input type="hidden"
                                                       name="csrf_token"
                                                       value="<?= app_escape($_SESSION['applicants_csrf']) ?>">

                                                <input type="hidden"
                                                       name="action"
                                                       value="shortlist">

                                                <input type="hidden"
                                                       name="applicant_id"
                                                       value="<?= (int)$applicant['applicant_id'] ?>">

                                                <button type="submit"
                                                        class="text-green-700 hover:text-green-900
                                                               font-semibold text-sm">

                                                    + Shortlist

                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <span class="text-gray-400 text-sm">
                                                Already added
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="6"
                                class="px-5 py-12 text-center text-gray-500">

                                No applicants found. Register a new applicant
                                or change your filters.

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
