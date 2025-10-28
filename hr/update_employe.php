<?php
include 'db_con.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $national_id = $_POST['national_id'];
    $kra_pin = $_POST['kra_pin'];
    $nssf_number = $_POST['nssf_number'];
    $nhif_number = $_POST['nhif_number'];
    $phone = $_POST['phone'];
    $department = $_POST['department'];
    $position = $_POST['position'];
    $date_of_hire = $_POST['date_of_hire'];
    $status = $_POST['status'] ?? 'Active';
    $employment_type = $_POST['employment_type'] ?? 'Permanent';
    $contract_start = $_POST['contract_start'] ?? null;
    $contract_end = $_POST['contract_end'] ?? null;

    // ✅ Automatically deactivate if contract has ended
    if ($employment_type === 'Contract' && !empty($contract_end)) {
        $today = date('Y-m-d');
        if ($today > $contract_end) {
            $status = 'Inactive';
        }
    }

    $stmt = $conn->prepare("
        UPDATE employees 
        SET first_name=?, 
            last_name=?, 
            national_id=?, 
            kra_pin=?, 
            nssf_number=?, 
            nhif_number=?, 
            phone=?, 
            department=?, 
            position=?, 
            date_of_hire=?, 
            status=?, 
            employment_type=?, 
            contract_start=?, 
            contract_end=? 
        WHERE employee_id=?
    ");

    $stmt->bind_param(
        "ssssssssssssssi",
        $first_name,
        $last_name,
        $national_id,
        $kra_pin,
        $nssf_number,
        $nhif_number,
        $phone,
        $department,
        $position,
        $date_of_hire,
        $status,
        $employment_type,
        $contract_start,
        $contract_end,
        $employee_id
    );

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
