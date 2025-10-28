<?php
session_start();
include 'db_con.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$success = $error = "";

// Fetch current user details
$stmt = $conn->prepare("SELECT full_name, email, national_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        // Verify old password
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($current_password, $hashed_password)) {
            $error = "Current password is incorrect.";
        } else {
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_hashed, $user_id);
            if ($stmt->execute()) {
                $success = "Password updated successfully!";
            } else {
                $error = "Failed to update password.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Update Profile</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-8">
  <h1 class="text-3xl font-bold text-blue-700 mb-6">Update My Profile</h1>

  <div class="bg-white rounded-lg shadow p-6 max-w-lg">
    <?php if ($success): ?>
      <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?php echo htmlspecialchars($success); ?></div>
    <?php elseif ($error): ?>
      <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="mb-4">
      <p><strong>Name:</strong> <?= htmlspecialchars($user['full_name']) ?></p>
      <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
      <p><strong>National ID:</strong> <?= htmlspecialchars($user['national_id'] ?? '-') ?></p>
    </div>

    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Current Password</label>
        <input type="password" name="current_password" class="w-full mt-1 p-2 border border-gray-300 rounded-lg" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">New Password</label>
        <input type="password" name="new_password" class="w-full mt-1 p-2 border border-gray-300 rounded-lg" required>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Confirm New Password</label>
        <input type="password" name="confirm_password" class="w-full mt-1 p-2 border border-gray-300 rounded-lg" required>
      </div>

      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        Update Password
      </button>
    </form>
  </div>
</div>

</body>
</html>
