<?php
session_start();
require_once 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// Validate document ID
$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$documentId || $documentId < 1) {
    http_response_code(400);
    exit('Invalid document request.');
}

$stmt = $conn->prepare("
    SELECT
        document_type,
        original_name,
        file_path,
        mime_type
    FROM applicant_documents
    WHERE document_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $documentId);
$stmt->execute();

$document = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$uploadDirectory = realpath(__DIR__ . '/uploads/applicant_documents');
$filePath = realpath(__DIR__ . '/' . $document['file_path']);

if (
    !$uploadDirectory ||
    !$filePath ||
    !is_file($filePath) ||
    strpos($filePath, $uploadDirectory . DIRECTORY_SEPARATOR) !== 0
) {
    http_response_code(404);
    exit('The document file could not be found.');
}

$originalName = basename($document['original_name']);
$mimeType = $document['mime_type'];

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

$allowedMimes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

if (
    !isset($allowedMimes[$extension]) ||
    !in_array($mimeType, [
        $allowedMimes[$extension],
        'application/x-ole-storage',
        'application/zip'
    ], true)
) {
    http_response_code(403);
    exit('Unsupported document type.');
}

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mimeType);

$disposition = ($extension === 'pdf') ? 'inline' : 'attachment';

header(
    'Content-Disposition: ' . $disposition .
    '; filename="' . str_replace('"', '', $originalName) . '"'
);

header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit;