<?php
require 'db_con.php';

$user_id = intval($_POST['user_id'] ?? 0);
$full_name = $_POST['full_name'] ?? '';
$base_salary = floatval($_POST['base_salary'] ?? 0);
$total_allowances = floatval($_POST['total_allowances'] ?? 0);
$total_deductions = floatval($_POST['total_deductions'] ?? 0);
$net_pay = floatval($_POST['net_pay'] ?? 0);
$month = $_POST['month'] ?? '';
$year = intval($_POST['year'] ?? date('Y'));
$details = $_POST['details'] ?? '{}';

if (!$user_id || !$month) {
    die("Missing required fields.");
}

// Check if already exists
$stmt = $conn->prepare("SELECT payroll_id FROM payroll_records WHERE user_id=? AND month=? AND year=?");
$stmt->bind_param("isi", $user_id, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing record
    $update = $conn->prepare("
        UPDATE payroll_records 
        SET base_salary=?, total_allowances=?, total_deductions=?, net_pay=?, details=?, created_at=NOW()
        WHERE user_id=? AND month=? AND year=?
    ");
    $update->bind_param("ddddsisi", $base_salary, $total_allowances, $total_deductions, $net_pay, $details, $user_id, $month, $year);
    $update->execute();
    echo "Payroll updated successfully for $month $year.";
} else {
    // Insert new record
    $insert = $conn->prepare("
        INSERT INTO payroll_records 
        (user_id, full_name, base_salary, total_allowances, total_deductions, net_pay, month, year, details)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->bind_param("isddddsis", $user_id, $full_name, $base_salary, $total_allowances, $total_deductions, $net_pay, $month, $year, $details);
    $insert->execute();
    echo "Payroll saved successfully for $month $year.";
}
?>
