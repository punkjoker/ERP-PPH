<?php
include 'db_con.php';

$employee_id = intval($_GET['employee_id']);
$leave_type = $_GET['leave_type'];
$year = date('Y');

$entitlements = [
    'Annual' => 21,
    'Sick' => 30,
    'Paternity' => 14,
    'Maternity' => 90
];
$entitled = $entitlements[$leave_type] ?? 0;

$stmt = $conn->prepare("SELECT SUM(DATEDIFF(end_date, start_date) + 1) AS taken 
                        FROM leaves 
                        WHERE employee_id = ? AND leave_type = ? AND YEAR(start_date) = ?");
$stmt->bind_param("isi", $employee_id, $leave_type, $year);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

$taken = $res['taken'] ?? 0;
$remaining = max($entitled - $taken, 0);

echo json_encode(['taken' => $taken, 'remaining' => $remaining]);
?>
