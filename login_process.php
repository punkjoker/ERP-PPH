<?php  
require 'db_con.php'; // this sets $conn (MySQLi)

$email = trim($_POST['email']);
$password = $_POST['password'];

// Fetch user by email
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "<script>alert('Invalid email or password'); window.history.back();</script>";
    exit;
}

// Check if inactive
if ($user['status'] === 'inactive') {
    echo "<script>alert('Your account is inactive. Please contact the administrator.'); window.history.back();</script>";
    exit;
}

// Verify password
if (password_verify($password, $user['password'])) {
    session_start();
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['group_id'] = $user['group_id'];

    // Redirect based on group_id
    switch ($user['group_id']) {
        case 1: header("Location: superadmin/dashboard.php"); exit;
        case 2: header("Location: hr/dashboard.php"); exit;
        case 3: header("Location: stores/dashboard.php"); exit;
        case 4: header("Location: production/dashboard.php"); exit;
        case 5: header("Location: qc/dashboard.php"); exit;
        case 6: header("Location: procurement/dashboard.php"); exit;
        case 7: header("Location: drivers/dashboard.php"); exit;
        case 8: header("Location: reports/dashboard.php"); exit;
        case 9: header("Location: staff/dashboard.php"); exit;
        case 10: header("Location: accounts/dashboard.php"); exit;
        case 11: header("Location: sales/dashboard.php"); exit;
        default:
            echo "<script>alert('No dashboard found for this user group.'); window.history.back();</script>";
            exit;
    }
} else {
    echo "<script>alert('Invalid email or password'); window.history.back();</script>";
}
?>
