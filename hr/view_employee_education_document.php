
<?php
session_start();
require_once 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Access denied. Please log in.");
}

// Document ID
$document_id = (int)($_GET['id'] ?? 0);

if ($document_id <= 0) {
    http_response_code(400);
    exit("Invalid document ID.");
}

// Fetch document and associated employee
$stmt = $conn->prepare("
    SELECT
        d.document_id,
        d.employee_id,
        d.document_name,
        d.file_path,
        d.uploaded_at,
        e.first_name,
        e.last_name
    FROM employee_education_documents d
    INNER JOIN employees e
        ON e.employee_id = d.employee_id
    WHERE d.document_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $document_id);
$stmt->execute();

$document = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$document) {
    http_response_code(404);
    exit("Education document not found.");
}

// Resolve the stored path relative to this HR directory.
// Adjust this if your database stores absolute paths or paths with a different prefix.
$storedPath = str_replace('\\', '/', $document['file_path']);

$baseDirectory = realpath(__DIR__ . '/uploads/education_documents');

if ($baseDirectory === false) {
    http_response_code(500);
    exit("Education documents directory was not found.");
}

// Support paths stored either as:
// uploads/education_documents/filename.pdf
// education_documents/filename.pdf
// or just filename.pdf
$filename = basename($storedPath);
$filePath = realpath($baseDirectory . DIRECTORY_SEPARATOR . $filename);

// Prevent directory traversal and access outside the upload folder
if (
    $filePath === false ||
    !is_file($filePath) ||
    strpos($filePath, $baseDirectory . DIRECTORY_SEPARATOR) !== 0
) {
    http_response_code(404);
    exit("The requested document file could not be found.");
}

// Validate allowed extensions
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

$allowedTypes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

if (!isset($allowedTypes[$extension])) {
    http_response_code(403);
    exit("This file type is not allowed.");
}

// Detect actual MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);

$validMimeTypes = [
    'pdf' => ['application/pdf'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'doc' => ['application/msword', 'application/x-ole-storage'],
    'docx' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip'
    ]
];

if (!in_array($mimeType, $validMimeTypes[$extension], true)) {
    http_response_code(403);
    exit("The document file type could not be verified.");
}

// Prepare safe download/display filename
$downloadName = preg_replace(
    '/[^A-Za-z0-9._ -]/',
    '_',
    $document['document_name']
);

if (empty($downloadName)) {
    $downloadName = 'education_document.' . $extension;
}

$disposition = in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'])
    ? 'inline'
    : 'attachment';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: ' . $allowedTypes[$extension]);
header(
    'Content-Disposition: ' . $disposition .
    '; filename="' . addcslashes($downloadName, '"\\') . '"'
);
header('Content-Length: ' . filesize($filePath));

// Output document
readfile($filePath);
exit;
