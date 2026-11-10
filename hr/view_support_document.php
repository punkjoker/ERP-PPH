
<?php
session_start();
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Please log in first.');
}

$leaveId = filter_input(INPUT_GET, 'leave_id', FILTER_VALIDATE_INT);

if (!$leaveId || $leaveId < 1) {
    http_response_code(400);
    exit('Invalid leave request.');
}

$stmt = $conn->prepare("
    SELECT supporting_document
    FROM leaves
    WHERE leave_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $leaveId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row || empty($row['supporting_document'])) {
    http_response_code(404);
    exit('No supporting document was found.');
}

$relativePath = $row['supporting_document'];
$expectedPrefix = 'uploads/leave_documents/';

if (strpos($relativePath, $expectedPrefix) !== 0) {
    http_response_code(403);
    exit('Invalid document location.');
}

$baseDirectory = realpath(__DIR__ . '/uploads/leave_documents');
$filePath = realpath(__DIR__ . '/' . $relativePath);

if (
    !$baseDirectory ||
    !$filePath ||
    strpos($filePath, $baseDirectory . DIRECTORY_SEPARATOR) !== 0 ||
    !is_file($filePath)
) {
    http_response_code(404);
    exit('Document not found.');
}

$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

$mimeTypes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png'
];

if (!isset($mimeTypes[$extension])) {
    http_response_code(415);
    exit('Unsupported document format.');
}

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="leave-support-document.' . $extension . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');

readfile($filePath);
exit;
