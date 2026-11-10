
<?php
include 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function uploadTrainingCertificate($file): ?string
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Certificate upload failed.");
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("Certificate must not exceed 5MB.");
    }

    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset($allowed[$mime])) {
        throw new Exception("Only PDF, JPG, JPEG and PNG certificates are allowed.");
    }

    $uploadDir = __DIR__ . '/uploads/training_certificates/';

    if (!is_dir($uploadDir) &&
        !mkdir($uploadDir, 0755, true) &&
        !is_dir($uploadDir)) {
        throw new Exception("Could not create certificate upload folder.");
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        throw new Exception("Could not save the certificate.");
    }

    return 'uploads/training_certificates/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newCertificatePath = null;

    try {
        $training_id   = filter_var($_POST['training_id'] ?? null, FILTER_VALIDATE_INT);
        $training_name = trim($_POST['training_name'] ?? '');
        $training_date = $_POST['training_date'] ?? '';
        $status        = $_POST['status'] ?? '';
        $done_by       = trim($_POST['done_by'] ?? '');
        $approved_by   = trim($_POST['approved_by'] ?? '');

        if (!$training_id || $training_name === '' || $training_date === '') {
            throw new Exception("Please provide all required training details.");
        }

        if (!in_array($status, ['Pending', 'Done'], true)) {
            throw new Exception("Invalid training status.");
        }

        // Get the existing record and certificate
        $checkStmt = $conn->prepare("
            SELECT certificate_path
            FROM trainings
            WHERE training_id = ?
        ");

        $checkStmt->bind_param("i", $training_id);
        $checkStmt->execute();

        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$existing) {
            throw new Exception("Training record not found.");
        }

        // Keep the existing certificate unless a new one is uploaded
        $certificatePath = $existing['certificate_path'];

        $newCertificatePath = uploadTrainingCertificate(
            $_FILES['certificate'] ?? null
        );

        if ($newCertificatePath !== null) {
            $certificatePath = $newCertificatePath;
        }

        // Update training record
        $stmt = $conn->prepare("
            UPDATE trainings
            SET training_name = ?,
                training_date = ?,
                status = ?,
                done_by = ?,
                approved_by = ?,
                certificate_path = ?
            WHERE training_id = ?
        ");

        $stmt->bind_param(
            "ssssssi",
            $training_name,
            $training_date,
            $status,
            $done_by,
            $approved_by,
            $certificatePath,
            $training_id
        );

        $stmt->execute();
        $stmt->close();

        header("Location: record_training.php?updated=1");
        exit;

    } catch (Throwable $e) {

        // Remove newly uploaded file if the database update failed
        if ($newCertificatePath !== null) {
            $newFile = __DIR__ . '/' . $newCertificatePath;

            if (is_file($newFile)) {
                unlink($newFile);
            }
        }

        echo "
            <div style='margin:40px auto;max-width:600px;
                        padding:20px;background:#fee2e2;
                        color:#991b1b;border-radius:8px;'>
                <strong>Unable to update training:</strong>
                " . htmlspecialchars($e->getMessage()) . "
                <br><br>
                <a href='trainings.php'>Return to Training Records</a>
            </div>
        ";
        exit;
    }
}

header("Location: record_training.php");
exit;
