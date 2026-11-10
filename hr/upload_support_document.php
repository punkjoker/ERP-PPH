
<?php
session_start();
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function uploadRedirect(string $message): void {
    header("Location: leaves_request.php?success=" . urlencode($message));
    exit;
}

// Require an authenticated session.
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Please log in first.');
}

// Validate CSRF token.
$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['leave_upload_csrf'] ?? '';

if (
    !$sessionToken ||
    !$submittedToken ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit('Invalid request token. Refresh the page and try again.');
}

$leaveId = filter_input(INPUT_POST, 'leave_id', FILTER_VALIDATE_INT);

if (!$leaveId || $leaveId < 1) {
    uploadRedirect('document_error');
}

if (
    !isset($_FILES['supporting_document']) ||
    $_FILES['supporting_document']['error'] !== UPLOAD_ERR_OK
) {
    uploadRedirect('document_error');
}

$file = $_FILES['supporting_document'];
$maxSize = 5 * 1024 * 1024;

if ($file['size'] <= 0 || $file['size'] > $maxSize) {
    uploadRedirect('document_error');
}

$allowedTypes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png'
];

$originalName = $file['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!isset($allowedTypes[$extension])) {
    uploadRedirect('document_error');
}

// Verify actual file MIME type.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if ($mimeType !== $allowedTypes[$extension]) {
    uploadRedirect('document_error');
}

// Confirm the leave request exists.
$stmt = $conn->prepare("
    SELECT supporting_document
    FROM leaves
    WHERE leave_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $leaveId);
$stmt->execute();
$leave = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leave) {
    uploadRedirect('document_error');
}

// Create a protected document directory.
$uploadDirectory = __DIR__ . '/uploads/leave_documents';

if (!is_dir($uploadDirectory)) {
    if (!mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        uploadRedirect('document_error');
    }
}

// Prevent direct browsing and execution of uploaded files.
$htaccess = $uploadDirectory . '/.htaccess';

if (!file_exists($htaccess)) {
    file_put_contents(
        $htaccess,
        "Require all denied\nOptions -Indexes\n"
    );
}

// Generate a unique, unpredictable filename.
$newFilename = bin2hex(random_bytes(24)) . '.' . $extension;
$destination = $uploadDirectory . '/' . $newFilename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    uploadRedirect('document_error');
}

// Store a relative path in the database.
$relativePath = 'uploads/leave_documents/' . $newFilename;

try {
    $update = $conn->prepare("
        UPDATE leaves
        SET supporting_document = ?
        WHERE leave_id = ?
    ");
    $update->bind_param("si", $relativePath, $leaveId);
    $update->execute();
    $update->close();

    // Remove the previous file only after the new file is saved.
    $oldPath = $leave['supporting_document'] ?? '';

    if (
        $oldPath !== '' &&
        strpos($oldPath, 'uploads/leave_documents/') === 0
    ) {
        $oldFullPath = __DIR__ . '/' . $oldPath;

        if (is_file($oldFullPath)) {
            unlink($oldFullPath);
        }
    }

} catch (Throwable $e) {
    if (is_file($destination)) {
        unlink($destination);
    }

    error_log('Leave document upload error: ' . $e->getMessage());
    uploadRedirect('document_error');
}

$conn->close();
uploadRedirect('document_uploaded');
