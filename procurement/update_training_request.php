<?php
session_start();
include 'db_con.php';

// ✅ Ensure only logged-in admin/staff can access
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ✅ Check that form data was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $training_id = intval($_POST['training_id'] ?? 0);
    $start_date  = $_POST['start_date'] ?? '';
    $end_date    = $_POST['end_date'] ?? '';
    $status      = $_POST['status'] ?? 'Pending';

    // ✅ Validate data
    if ($training_id <= 0) {
        die("<div style='margin:100px;text-align:center;color:red;font-weight:bold;'>Invalid training ID.</div>");
    }

    // ✅ Update training request
    $stmt = $conn->prepare("
        UPDATE trainings_request 
        SET start_date = ?, end_date = ?, status = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("sssi", $start_date, $end_date, $status, $training_id);
    $success = $stmt->execute();
    $stmt->close();

    // ✅ Feedback message
    if ($success) {
        echo "
        <div style='margin:100px auto;text-align:center;font-family:Arial;'>
            <div style='background:#d1fae5;border:1px solid #10b981;padding:20px;border-radius:8px;display:inline-block;'>
                <h2 style='color:#065f46;'>✅ Training request updated successfully!</h2>
            </div>
            <script>
                setTimeout(() => { window.location.href = 'view_training_requests.php'; }, 1500);
            </script>
        </div>";
    } else {
        echo "
        <div style='margin:100px auto;text-align:center;font-family:Arial;'>
            <div style='background:#fee2e2;border:1px solid #ef4444;padding:20px;border-radius:8px;display:inline-block;'>
                <h2 style='color:#991b1b;'>❌ Failed to update training request. Please try again.</h2>
            </div>
            <script>
                setTimeout(() => { window.location.href = 'view_training_requests.php'; }, 2000);
            </script>
        </div>";
    }
} else {
    // If accessed directly without POST
    header("Location: view_training_requests.php");
    exit();
}
?>
