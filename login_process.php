<?php  
require 'db_con.php'; // this sets $conn (MySQLi)

$email = $_POST['email'];
$password = $_POST['password'];

// Fetch user with MySQLi
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status='active'");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    session_start();
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['group_id'] = $user['group_id'];

    // Redirect based on group_id
    switch ($user['group_id']) {
        case 1: // SuperAdmin
            header("Location: superadmin/dashboard.php");
            exit;
        case 2: // HR
            header("Location: hr/dashboard.php");
            exit;
        case 3: // Stores
            header("Location: stores/dashboard.php");
            exit;
        case 4: // Production
            header("Location: production/dashboard.php");
            exit;
        case 5: // Quality Control
            header("Location: qc/dashboard.php");
            exit;
        default:
            echo "No dashboard found for this user group.";
    }
} else {
    echo "Invalid email or password";
}
?>
