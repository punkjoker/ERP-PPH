<?php
include 'db_con.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'];
    $first_name  = $_POST['first_name'];
    $last_name   = $_POST['last_name'];
    $national_id = $_POST['national_id'];
    $kra_pin     = $_POST['kra_pin'];
    $nssf       = $_POST['nssf_number'];
    $nhif       = $_POST['nhif_number'];
    $email      = $_POST['email'];
    $phone      = $_POST['phone'];
    $department = $_POST['department'];
    $position   = $_POST['position'];
    $date_of_hire = $_POST['date_of_hire'];
    $status     = $_POST['status'];

    $stmt = $conn->prepare("UPDATE employees SET first_name=?, last_name=?, national_id=?, kra_pin=?, nssf_number=?, nhif_number=?, email=?, phone=?, department=?, position=?, date_of_hire=?, status=? WHERE employee_id=?");
    $stmt->bind_param("ssssssssssssi", $first_name, $last_name, $national_id, $kra_pin, $nssf, $nhif, $email, $phone, $department, $position, $date_of_hire, $status, $employee_id);

    if ($stmt->execute()) {
        header("Location: view_employees.php?success=Employee+updated");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
