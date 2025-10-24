<?php
session_start();
include 'db_con.php';

// --- Ensure user is logged in ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle training submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $training_title = trim($_POST['training_title']);
    $description    = trim($_POST['description']);
    $start_date     = $_POST['start_date'];
    $end_date       = $_POST['end_date'];

    $stmt = $conn->prepare("INSERT INTO trainings_request (user_id, training_title, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("issss", $user_id, $training_title, $description, $start_date, $end_date);
    $stmt->execute();
    $stmt->close();

    $success = "Training request submitted successfully!";
}

// Fetch logged-in user info
$user = $conn->query("SELECT * FROM users WHERE user_id = $user_id")->fetch_assoc();

// Fetch user training records
$trainings_request = [];
$stmt = $conn->prepare("SELECT * FROM trainings_request WHERE user_id = ? ORDER BY start_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $trainings_request[] = $row;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Training Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">My Training Requests</h1>

    <?php if(isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Training Request Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <form method="POST" id="trainingForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="text" name="training_title" placeholder="Training Title" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="description" placeholder="Training Description" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="date" name="start_date" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="date" name="end_date" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
            </div>

            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Submit Training Request</button>
        </form>
    </div>

    <!-- Training Records Table -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Training Records</h2>
        <table class="w-full border border-gray-300 rounded text-sm">
            <thead class="bg-gray-200">
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if(!empty($trainings_request)): $count=1; ?>
                <?php foreach($trainings_request as $training): ?>
                    <tr class="<?= ($count%2==0)?'bg-gray-50':'bg-white' ?> hover:bg-blue-50">
                        <td class="border px-3 py-2"><?= $count++ ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($training['training_title']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($training['description']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($training['start_date']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($training['end_date']) ?></td>
                        <td class="border px-3 py-2 text-center font-semibold <?= $training['status']=='Approved'?'text-green-600':($training['status']=='Denied'?'text-red-600':'text-yellow-600') ?>">
                            <?= $training['status'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-gray-500 py-3">No training requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
