<?php
include 'db_con.php';
$report_id = intval($_GET['report_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM daily_reports WHERE report_id=?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM daily_tasks WHERE report_id=?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

header('Content-Type: application/json');
echo json_encode([
    'report_date' => $report['report_date'] ?? '',
    'day_name' => $report['day_name'] ?? '',
    'tasks' => $tasks
]);
?>
