<?php
include 'db_con.php';

// Use the same parameter name as in your fetch URL
$user_id = intval($_GET['user_id']);
$leave_type = $_GET['leave_type'];
$year = date('Y');

// Leave entitlements
$entitlements = [
    'Annual' => 21,
    'Sick' => 30,
    'Paternity' => 14,
    'Maternity' => 90
];
$entitled = $entitlements[$leave_type] ?? 0;

// Fetch total approved leave taken for this user, type, and year
$stmt = $conn->prepare("
    SELECT SUM(DATEDIFF(end_date, start_date) + 1) AS taken
    FROM leaves
    WHERE user_id = ? AND leave_type = ? AND YEAR(start_date) = ? AND status='Approved'
");
$stmt->bind_param("isi", $user_id, $leave_type, $year);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

$taken = $res['taken'] ?? 0;
$remaining = max($entitled - $taken, 0);

// Return as JSON
header('Content-Type: application/json');
echo json_encode(['taken' => (int)$taken, 'remaining' => (int)$remaining]);
?>
