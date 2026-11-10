
<?php
include 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$employee_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if ($employee_id <= 0) {
    die("Invalid employee ID.");
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// --------------------------------------------------
// FETCH EMPLOYEE
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT *
    FROM employees
    WHERE employee_id = ?
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();

$emp = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$emp) {
    die("Employee not found.");
}

// --------------------------------------------------
// FETCH MAIN EMPLOYEE INFORMATION
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT *
    FROM employee_information
    WHERE employee_id = ?
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();

$info = $stmt->get_result()->fetch_assoc() ?? [];

$stmt->close();

// --------------------------------------------------
// INITIALISE FORM DATA
// --------------------------------------------------

$educationRecords = [];
$experienceRecords = [];
$documents = [];

$error = '';
$success = '';

// --------------------------------------------------
// HANDLE FORM SUBMISSION
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Detect a request that exceeded PHP post_max_size.
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxSize = trim(ini_get('post_max_size'));

    $unit = strtolower(substr($postMaxSize, -1));
    $postLimit = (float)$postMaxSize;

    switch ($unit) {
        case 'g':
            $postLimit *= 1024;
            // fall through
        case 'm':
            $postLimit *= 1024;
            // fall through
        case 'k':
            $postLimit *= 1024;
    }

    if (
        $contentLength > 0 &&
        $postLimit > 0 &&
        $contentLength > $postLimit
    ) {
        die(
            "The total upload is larger than PHP's post_max_size limit. " .
            "Increase post_max_size in php.ini and restart Apache."
        );
    }

    $skills = trim($_POST['skills'] ?? '');
    $certifications = trim($_POST['certifications'] ?? '');

    $expected_salary = (float)($_POST['expected_salary'] ?? 0);
    $approved_salary = (float)($_POST['approved_salary'] ?? 0);

    $uploadDirectory = __DIR__ . '/uploads/education_documents/';

    $uploadedFiles = [];

    try {

        // ------------------------------------------
        // CREATE UPLOAD DIRECTORY
        // ------------------------------------------

        if (!is_dir($uploadDirectory)) {
            if (!mkdir($uploadDirectory, 0755, true)) {
                throw new Exception(
                    "Could not create the education documents directory."
                );
            }
        }

        // Block direct web access to uploaded files.
        $htaccess = $uploadDirectory . '.htaccess';

        if (!file_exists($htaccess)) {
            if (file_put_contents(
                $htaccess,
                "Require all denied\nOptions -Indexes\n"
            ) === false) {
                throw new Exception(
                    "Could not protect the education documents directory."
                );
            }
        }

        // ------------------------------------------
        // BEGIN DATABASE TRANSACTION
        // ------------------------------------------

        $conn->begin_transaction();

        // ------------------------------------------
        // SAVE MAIN EMPLOYEE INFORMATION
        // ------------------------------------------

        $stmt = $conn->prepare("
            INSERT INTO employee_information
                (
                    employee_id,
                    skills,
                    certifications,
                    expected_salary,
                    approved_salary
                )
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                skills = VALUES(skills),
                certifications = VALUES(certifications),
                expected_salary = VALUES(expected_salary),
                approved_salary = VALUES(approved_salary)
        ");

        $stmt->bind_param(
            "issdd",
            $employee_id,
            $skills,
            $certifications,
            $expected_salary,
            $approved_salary
        );

        $stmt->execute();
        $stmt->close();

        // ------------------------------------------
        // UPDATE EDUCATION AND EXPERIENCE RECORDS
        // ------------------------------------------

        // Existing entries are loaded into the form.
        // On saving, replace them with the submitted list.

        $stmt = $conn->prepare("
            DELETE FROM employee_information_items
            WHERE employee_id = ?
        ");

        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $stmt->close();

        // ------------------------------------------
        // SAVE EDUCATION RECORDS
        // ------------------------------------------

        if (
            isset($_POST['school_name']) &&
            is_array($_POST['school_name'])
        ) {

            foreach ($_POST['school_name'] as $i => $school) {

                $school = trim($school);

                if ($school === '') {
                    continue;
                }

                $level = trim($_POST['level'][$i] ?? '');
                $startYear = trim($_POST['start_year'][$i] ?? '');
                $endYear = trim($_POST['end_year'][$i] ?? '');
                $result = trim($_POST['result'][$i] ?? '');

                $stmt = $conn->prepare("
                    INSERT INTO employee_information_items
                        (
                            employee_id,
                            category,
                            field1,
                            field2,
                            field3,
                            field4,
                            field5
                        )
                    VALUES (?, 'education', ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "isssss",
                    $employee_id,
                    $school,
                    $level,
                    $startYear,
                    $endYear,
                    $result
                );

                $stmt->execute();
                $stmt->close();
            }
        }
// ------------------------------------------
// SAVE WORK EXPERIENCE RECORDS
// ------------------------------------------

if (
    isset($_POST['organization_name']) &&
    is_array($_POST['organization_name'])
) {

    foreach ($_POST['organization_name'] as $i => $org) {

        $org = trim($org);
        $position = trim($_POST['position'][$i] ?? '');

        if ($org === '' && $position === '') {
            continue;
        }

        $startDate = trim($_POST['exp_start_date'][$i] ?? '');
        $endDate = trim($_POST['exp_end_date'][$i] ?? '');

        $stmt = $conn->prepare("
            INSERT INTO employee_information_items
                (
                    employee_id,
                    category,
                    field1,
                    field2,
                    field3,
                    field4
                )
            VALUES (?, 'experience', ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "issss",
            $employee_id,
            $org,
            $position,
            $startDate,
            $endDate
        );

        $stmt->execute();
        $stmt->close();
    }
}
        // ------------------------------------------
        // UPLOAD MULTIPLE EDUCATION DOCUMENTS
        // ------------------------------------------

        $uploadedCount = 0;

        if (
            isset($_FILES['education_documents']) &&
            is_array($_FILES['education_documents']['name'])
        ) {

            $allowedExtensions = [
                'pdf',
                'jpg',
                'jpeg',
                'png',
                'doc',
                'docx'
            ];

            $allowedMimeTypes = [
                'pdf' => ['application/pdf'],
                'jpg' => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png' => ['image/png'],
                'doc' => ['application/msword'],
                'docx' => [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip'
                ]
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $fileCount = count($_FILES['education_documents']['name']);

            for ($i = 0; $i < $fileCount; $i++) {

                $originalName = $_FILES['education_documents']['name'][$i];

                $uploadError = $_FILES['education_documents']['error'][$i];

                // No file selected in this position.
                if ($uploadError === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                // Handle PHP upload errors.
                if ($uploadError !== UPLOAD_ERR_OK) {

                    $uploadErrors = [
                        UPLOAD_ERR_INI_SIZE =>
                            'A document exceeds PHP upload_max_filesize.',

                        UPLOAD_ERR_FORM_SIZE =>
                            'A document exceeds the form upload limit.',

                        UPLOAD_ERR_PARTIAL =>
                            'A document was only partially uploaded.',

                        UPLOAD_ERR_NO_TMP_DIR =>
                            'PHP temporary upload directory is missing.',

                        UPLOAD_ERR_CANT_WRITE =>
                            'PHP could not write the uploaded document.',

                        UPLOAD_ERR_EXTENSION =>
                            'A PHP extension stopped the upload.'
                    ];

                    throw new Exception(
                        $uploadErrors[$uploadError] ??
                        "Upload failed for: " . $originalName
                    );
                }

                $tmpName = $_FILES['education_documents']['tmp_name'][$i];

                $fileSize = (int)$_FILES['education_documents']['size'][$i];

                if (!is_uploaded_file($tmpName)) {
                    throw new Exception(
                        "Invalid uploaded document: " . $originalName
                    );
                }

                // Maximum 10 MB per document.
                if ($fileSize > 10 * 1024 * 1024) {
                    throw new Exception(
                        "The document " . $originalName .
                        " exceeds the 10 MB limit."
                    );
                }

                $extension = strtolower(
                    pathinfo($originalName, PATHINFO_EXTENSION)
                );

                if (!in_array($extension, $allowedExtensions, true)) {
                    throw new Exception(
                        "Unsupported document format: " . $originalName
                    );
                }

                $mimeType = $finfo->file($tmpName);

                if (
                    !in_array(
                        $mimeType,
                        $allowedMimeTypes[$extension],
                        true
                    )
                ) {
                    throw new Exception(
                        "The file content does not match its extension: " .
                        $originalName
                    );
                }

                // Generate a unique filename.
                $newFileName = bin2hex(random_bytes(20)) . '.' . $extension;

                $destination = $uploadDirectory . $newFileName;

                if (!move_uploaded_file($tmpName, $destination)) {
                    throw new Exception(
                        "Could not save document: " . $originalName
                    );
                }

                // Track uploaded file in case the database transaction fails.
                $uploadedFiles[] = $destination;

                $relativePath =
                    'uploads/education_documents/' . $newFileName;

                $documentName = basename($originalName);

                // Save document information.
                $stmt = $conn->prepare("
                    INSERT INTO employee_education_documents
                        (
                            employee_id,
                            document_name,
                            file_path
                        )
                    VALUES (?, ?, ?)
                ");

                $stmt->bind_param(
                    "iss",
                    $employee_id,
                    $documentName,
                    $relativePath
                );

                $stmt->execute();
                $stmt->close();

                $uploadedCount++;
            }
        }

        // ------------------------------------------
        // COMMIT DATABASE TRANSACTION
        // ------------------------------------------

       $conn->commit();

$message = "Employee information updated successfully.";

if ($uploadedCount > 0) {
    $message .= " " . $uploadedCount .
        " education document(s) uploaded.";
}

echo "<script>
    alert(" . json_encode($message) . ");
    window.location.href = 'view_employee.php?id=" .
        (int)$employee_id . "';
</script>";

exit;

    } catch (Throwable $e) {

        // Roll back database changes.
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Preserve original error.
        }

        // Remove newly uploaded files if the database save failed.
        foreach ($uploadedFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $error = $e->getMessage();
    }
}

// --------------------------------------------------
// FETCH EDUCATION AND EXPERIENCE RECORDS
// --------------------------------------------------

$educationRecords = [];
$experienceRecords = [];

$stmt = $conn->prepare("
    SELECT *
    FROM employee_information_items
    WHERE employee_id = ?
    ORDER BY category ASC
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    if (($row['category'] ?? '') === 'education') {
        $educationRecords[] = $row;
    }

    elseif (($row['category'] ?? '') === 'experience') {
        $experienceRecords[] = $row;
    }
}

$stmt->close();

// --------------------------------------------------
// FETCH EDUCATION DOCUMENTS
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT *
    FROM employee_education_documents
    WHERE employee_id = ?
    ORDER BY uploaded_at DESC
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();

// --------------------------------------------------
// REFRESH MAIN INFORMATION AFTER SUBMISSION ERROR
// --------------------------------------------------

if ($error !== '') {

    $stmt = $conn->prepare("
        SELECT *
        FROM employee_information
        WHERE employee_id = ?
    ");

    $stmt->bind_param("i", $employee_id);
    $stmt->execute();

    $info = $stmt->get_result()->fetch_assoc() ?? [];

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Update Employee Information</title>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

</head>

<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">

<div class="max-w-6xl mx-auto bg-white shadow-lg rounded-lg p-6">

    <!-- PAGE HEADER -->

    <div class="flex justify-between items-center mb-6">

        <div>

            <h1 class="text-2xl font-bold text-blue-700">
                Employee Information
            </h1>

            <p class="text-gray-600 mt-1">
                <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?>
            </p>

        </div>

        <a href="employee_information.php"
           class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
            Back
        </a>

    </div>

    <!-- ERROR MESSAGE -->

    <?php if ($error !== ''): ?>

        <div class="bg-red-100 border border-red-300 text-red-800 p-4 rounded mb-6">

            <strong>Unable to save information:</strong>

            <p><?= e($error) ?></p>

        </div>

    <?php endif; ?>

    <form method="POST"
          enctype="multipart/form-data"
          class="space-y-8"
          x-data="{
              eduCount: <?= count($educationRecords) === 0 ? 1 : 0 ?>,
              expCount: <?= count($experienceRecords) === 0 ? 1 : 0 ?>,
              selectedFiles: []
          }">

        <!-- ====================================== -->
        <!-- EDUCATION BACKGROUND -->
        <!-- ====================================== -->

        <div class="border rounded-lg p-5">

            <div class="flex justify-between items-center mb-4">

                <h2 class="text-xl font-semibold text-gray-700">
                    Education Background
                </h2>

                <span class="text-sm text-gray-500">
                    <?= count($educationRecords) ?> existing record(s)
                </span>

            </div>

            <!-- EXISTING EDUCATION RECORDS -->

            <?php if (!empty($educationRecords)): ?>

                <div class="space-y-3">

                    <?php foreach ($educationRecords as $education): ?>

                        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 bg-gray-50 p-3 rounded">

                            <input
                                type="text"
                                name="school_name[]"
                                placeholder="School Name"
                                value="<?= e($education['field1']) ?>"
                                class="border p-2 rounded w-full"
                            >

                            <input
                                type="text"
                                name="level[]"
                                placeholder="Level"
                                value="<?= e($education['field2']) ?>"
                                class="border p-2 rounded w-full"
                            >

                            <input
                                type="text"
                                name="start_year[]"
                                placeholder="Start Year"
                                value="<?= e($education['field3']) ?>"
                                class="border p-2 rounded w-full"
                            >

                            <input
                                type="text"
                                name="end_year[]"
                                placeholder="End Year"
                                value="<?= e($education['field4']) ?>"
                                class="border p-2 rounded w-full"
                            >

                            <input
                                type="text"
                                name="result[]"
                                placeholder="Result/Award"
                                value="<?= e($education['field5']) ?>"
                                class="border p-2 rounded w-full"
                            >

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p class="text-gray-500 text-sm mb-3">
                    No education records have been entered yet.
                </p>

            <?php endif; ?>

            <!-- ADD NEW EDUCATION RECORDS -->

            <template x-for="i in eduCount" :key="i">

                <div class="grid grid-cols-1 md:grid-cols-5 gap-3 bg-blue-50 p-3 rounded mb-3">

                    <input
                        type="text"
                        name="school_name[]"
                        placeholder="School Name"
                        class="border p-2 rounded w-full"
                    >

                    <input
                        type="text"
                        name="level[]"
                        placeholder="Level"
                        class="border p-2 rounded w-full"
                    >

                    <input
                        type="text"
                        name="start_year[]"
                        placeholder="Start Year"
                        class="border p-2 rounded w-full"
                    >

                    <input
                        type="text"
                        name="end_year[]"
                        placeholder="End Year"
                        class="border p-2 rounded w-full"
                    >

                    <input
                        type="text"
                        name="result[]"
                        placeholder="Result/Award"
                        class="border p-2 rounded w-full"
                    >

                </div>

            </template>

            <button
                type="button"
                @click="eduCount++"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 mt-3">

                + Add Education

            </button>

        </div>

        <!-- ====================================== -->
        <!-- EDUCATION DOCUMENTS -->
        <!-- ====================================== -->

        <div class="border rounded-lg p-5 bg-blue-50">

            <h2 class="text-xl font-semibold text-blue-800 mb-2">
                Education Supporting Documents
            </h2>

            <p class="text-sm text-gray-600 mb-4">

                Upload academic certificates, transcripts, diplomas,
                degrees and other supporting documents.

            </p>

            <input
                type="file"
                name="education_documents[]"
                multiple
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                @change="selectedFiles = Array.from($event.target.files)"
                class="border bg-white p-3 rounded w-full"
            >

            <!-- SELECTED FILES -->

            <div x-show="selectedFiles.length > 0"
                 class="mt-4 bg-white border rounded-lg p-4">

                <p class="font-semibold text-blue-800 mb-2">

                    <span x-text="selectedFiles.length"></span>
                    document(s) selected

                </p>

                <template x-for="(file, index) in selectedFiles" :key="index">

                    <div class="flex justify-between text-sm py-1 border-b">

                        <span class="break-all" x-text="file.name"></span>

                        <span class="text-gray-500 ml-3 whitespace-nowrap"
                              x-text="(file.size / 1024 / 1024).toFixed(2) + ' MB'">
                        </span>

                    </div>

                </template>

            </div>

            <p class="text-xs text-gray-500 mt-3">

                Accepted formats: PDF, JPG, JPEG, PNG, DOC and DOCX.
                Maximum 10 MB per document.

            </p>

            <!-- PREVIOUS DOCUMENTS -->

            <div class="mt-6">

                <h3 class="font-semibold text-gray-700 mb-3">

                    Previously Uploaded Documents
                    (<?= count($documents) ?>)

                </h3>

                <?php if (!empty($documents)): ?>

                    <div class="space-y-3">

                        <?php foreach ($documents as $document): ?>

                            <div class="flex flex-col md:flex-row md:items-center justify-between bg-white border rounded-lg p-3 gap-3">

                                <div class="flex items-center gap-3 min-w-0">

                                    <div class="bg-red-100 text-red-700 p-3 rounded">

                                        <svg xmlns="http://www.w3.org/2000/svg"
                                             class="h-6 w-6"
                                             fill="none"
                                             viewBox="0 0 24 24"
                                             stroke="currentColor">

                                            <path stroke-linecap="round"
                                                  stroke-linejoin="round"
                                                  stroke-width="2"
                                                  d="M7 21h10a2 2 0 002-2V9l-7-7H7a2 2 0 00-2 2v15a2 2 0 002 2z"/>

                                        </svg>

                                    </div>

                                    <div class="min-w-0">

                                        <p class="font-medium text-gray-800 break-all">

                                            <?= e($document['document_name']) ?>

                                        </p>

                                        <p class="text-xs text-gray-500">

                                            Uploaded:
                                            <?= e($document['uploaded_at']) ?>

                                        </p>

                                    </div>

                                </div>

                                <a
                                    href="view_employee_education_document.php?id=<?= (int)$document['document_id'] ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="bg-green-600 text-white px-4 py-2 rounded text-sm text-center hover:bg-green-700">

                                    View Document

                                </a>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p class="text-sm text-gray-500 bg-white p-4 rounded border">

                        No education documents have been uploaded yet.

                    </p>

                <?php endif; ?>

            </div>

        </div>
<!-- ====================================== -->
<!-- WORK EXPERIENCE -->
<!-- ====================================== -->

<div class="border rounded-lg p-5">

    <div class="flex justify-between items-center mb-4">

        <h2 class="text-xl font-semibold text-gray-700">
            Work Experience
        </h2>

        <span class="text-sm text-gray-500">
            <?= count($experienceRecords) ?> existing record(s)
        </span>

    </div>

    <!-- EXISTING EXPERIENCE -->

    <?php if (!empty($experienceRecords)): ?>

        <div class="space-y-3">

            <?php foreach ($experienceRecords as $experience): ?>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-gray-50 p-3 rounded">

                    <input
                        type="text"
                        name="organization_name[]"
                        placeholder="Organization"
                        value="<?= e($experience['field1']) ?>"
                        class="border p-2 rounded w-full"
                    >

                    <input
                        type="text"
                        name="position[]"
                        placeholder="Position Held"
                        value="<?= e($experience['field2']) ?>"
                        class="border p-2 rounded w-full"
                    >

                    <input
                        type="date"
                        name="exp_start_date[]"
                        value="<?= e($experience['field3']) ?>"
                        class="border p-2 rounded w-full"
                    >

                    <input
                        type="date"
                        name="exp_end_date[]"
                        value="<?= e($experience['field4']) ?>"
                        class="border p-2 rounded w-full"
                    >

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <p class="text-gray-500 text-sm mb-3">
            No work experience records have been entered yet.
        </p>

    <?php endif; ?>

    <!-- ADD NEW EXPERIENCE -->

    <template x-for="i in expCount" :key="i">

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-blue-50 p-3 rounded mb-3">

            <input
                type="text"
                name="organization_name[]"
                placeholder="Organization"
                class="border p-2 rounded w-full"
            >

            <input
                type="text"
                name="position[]"
                placeholder="Position Held"
                class="border p-2 rounded w-full"
            >

            <input
                type="date"
                name="exp_start_date[]"
                class="border p-2 rounded w-full"
            >

            <input
                type="date"
                name="exp_end_date[]"
                class="border p-2 rounded w-full"
            >

        </div>

    </template>

    <button
        type="button"
        @click="expCount++"
        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 mt-3">

        + Add Work Experience

    </button>

</div>

        <!-- ====================================== -->
        <!-- SKILLS -->
        <!-- ====================================== -->

        <div class="border rounded-lg p-5">

            <h2 class="text-xl font-semibold text-gray-700 mb-3">
                Skills
            </h2>

            <textarea
                name="skills"
                rows="4"
                placeholder="Enter employee skills separated by commas"
                class="w-full border p-3 rounded"><?= e($info['skills'] ?? '') ?></textarea>

        </div>

        <!-- ====================================== -->
        <!-- CERTIFICATIONS -->
        <!-- ====================================== -->

        <div class="border rounded-lg p-5">

            <h2 class="text-xl font-semibold text-gray-700 mb-3">
                Certifications
            </h2>

            <textarea
                name="certifications"
                rows="4"
                placeholder="Enter certifications, separated by commas"
                class="w-full border p-3 rounded"><?= e($info['certifications'] ?? '') ?></textarea>

        </div>

        <!-- ====================================== -->
        <!-- SALARY INFORMATION -->
        <!-- ====================================== -->

        <div class="border rounded-lg p-5">

            <h2 class="text-xl font-semibold text-gray-700 mb-4">
                Salary Information
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>

                    <label class="block text-gray-600 mb-2">
                        Expected Salary (Ksh)
                    </label>

                    <input
                        type="number"
                        name="expected_salary"
                        step="0.01"
                        min="0"
                        class="border p-3 w-full rounded"
                        value="<?= e($info['expected_salary'] ?? '') ?>"
                    >

                </div>

                <div>

                    <label class="block text-gray-600 mb-2">
                        Approved Salary (Ksh)
                    </label>

                    <input
                        type="number"
                        name="approved_salary"
                        step="0.01"
                        min="0"
                        class="border p-3 w-full rounded"
                        value="<?= e($info['approved_salary'] ?? '') ?>"
                    >

                </div>

            </div>

        </div>

        <!-- ====================================== -->
        <!-- SAVE BUTTONS -->
        <!-- ====================================== -->

        <div class="pt-5 border-t flex flex-wrap gap-3">

            <button
                type="submit"
                class="bg-green-600 text-white px-6 py-3 rounded font-semibold hover:bg-green-700">

                Save Employee Information

            </button>

            <a
                href="employee_information.php"
                class="bg-gray-500 text-white px-6 py-3 rounded hover:bg-gray-600">

                Cancel

            </a>

        </div>

    </form>

</div>

</div>

</body>
</html>
