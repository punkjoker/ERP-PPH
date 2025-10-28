<?php
session_start();
require '../db_con.php'; // adjust path if needed

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch user info
$user_id = $_SESSION['user_id'];
$full_name = "Staff"; // fallback
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) $full_name = $user['full_name'];
    } elseif (isset($conn) && $conn instanceof mysqli) {
        $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        if ($user) $full_name = $user['full_name'];
    }
} catch (Exception $e) {
    // fallback to generic name
}

// Set correct timezone (adjust as needed)
date_default_timezone_set('Africa/Nairobi');

// Get current hour in 24-hour format (int)
$hour = (int) date('G'); // 'G' gives 0–23 (always 24-hour)
$hour_12 = (int) date('g'); // 12-hour format (1–12)
$ampm = strtolower(date('a')); // 'am' or 'pm'

// Decide greeting and emoji
if (($ampm === 'am' && $hour_12 < 12) || ($hour < 12)) {
    $greeting = "Good morning";
    $emoji = "☀️";
} elseif (($ampm === 'pm' && $hour_12 < 6) || ($hour < 18)) {
    $greeting = "Good afternoon";
    $emoji = "🌤️";
} else {
    $greeting = "Good evening";
    $emoji = "🌙";
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">

  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-10">
    <!-- Greeting -->
<h1 class="text-3xl font-bold text-blue-700 mb-2 flex items-center gap-2">
  <span><?= $emoji ?></span>
  <?= "$greeting, $full_name!"; ?>
</h1>

    <p class="text-gray-600 mb-6">Welcome to your dashboard</p>

    <!-- Dashboard Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Tasks</h2>
        <p class="text-sm text-gray-600">View and manage your tasks</p>
      </div>
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Attendance</h2>
        <p class="text-sm text-gray-600">Check in/out and attendance records</p>
      </div>
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Requests</h2>
        <p class="text-sm text-gray-600">Submit leave or material requests</p>
      </div>
      <div class="bg-blue-50 p-6 rounded-lg shadow hover:shadow-lg transition">
        <h2 class="text-xl font-bold text-blue-800">Reports</h2>
        <p class="text-sm text-gray-600">View reports and updates</p>
      </div>
    </div>
  </div>

</body>
</html>
