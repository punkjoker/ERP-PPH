
<?php
include 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "error: Invalid request method.";
    exit;
}

try {
    // Employee details
    $employee_id = filter_var(
        $_POST['employee_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $first_name      = trim($_POST['first_name'] ?? '');
    $last_name       = trim($_POST['last_name'] ?? '');
    $national_id     = trim($_POST['national_id'] ?? '');
    $kra_pin         = trim($_POST['kra_pin'] ?? '');
    $nssf_number     = trim($_POST['nssf_number'] ?? '');
    $nhif_number     = trim($_POST['nhif_number'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $department      = trim($_POST['department'] ?? '');
    $position        = trim($_POST['position'] ?? '');
    $date_of_hire    = trim($_POST['date_of_hire'] ?? '');
    $status          = trim($_POST['status'] ?? 'Active');
    $employment_type = trim($_POST['employment_type'] ?? 'Permanent');

    $contract_start = trim($_POST['contract_start'] ?? '');
    $contract_end   = trim($_POST['contract_end'] ?? '');

    // Convert empty contract dates to NULL
    $contract_start = ($contract_start !== '')
        ? $contract_start
        : null;

    $contract_end = ($contract_end !== '')
        ? $contract_end
        : null;

    // Validate employee ID
    if (!$employee_id || $employee_id <= 0) {
        throw new Exception("Invalid employee ID.");
    }

    // Validate required fields
    if (
        $first_name === '' ||
        $last_name === '' ||
        $date_of_hire === ''
    ) {
        throw new Exception(
            "First name, last name and date of hire are required."
        );
    }

    // Validate email when provided
    if (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        throw new Exception("Please enter a valid email address.");
    }

    // Check that the employee exists
    $checkEmployee = $conn->prepare(
        "SELECT employee_id
         FROM employees
         WHERE employee_id = ?
         LIMIT 1"
    );

    $checkEmployee->bind_param("i", $employee_id);
    $checkEmployee->execute();

    $employeeResult = $checkEmployee->get_result();

    if ($employeeResult->num_rows === 0) {
        $checkEmployee->close();
        throw new Exception("Employee not found.");
    }

    $checkEmployee->close();

    // Prevent duplicate email addresses
    if ($email !== '') {
        $checkEmail = $conn->prepare(
            "SELECT employee_id
             FROM employees
             WHERE email = ?
               AND employee_id <> ?
             LIMIT 1"
        );

        $checkEmail->bind_param("si", $email, $employee_id);
        $checkEmail->execute();

        $emailResult = $checkEmail->get_result();

        if ($emailResult->num_rows > 0) {
            $checkEmail->close();

            throw new Exception(
                "This email address is already assigned to another employee."
            );
        }

        $checkEmail->close();
    }

    // Automatically deactivate employees whose contracts have ended
    if ($employment_type === 'Contract' && !empty($contract_end)) {
        $today = date('Y-m-d');

        if ($today > $contract_end) {
            $status = 'Inactive';
        }
    }

    // Update employee, including email and contract information
    $stmt = $conn->prepare("
        UPDATE employees
        SET first_name = ?,
            last_name = ?,
            national_id = ?,
            kra_pin = ?,
            nssf_number = ?,
            nhif_number = ?,
            email = ?,
            phone = ?,
            department = ?,
            position = ?,
            date_of_hire = ?,
            status = ?,
            employment_type = ?,
            contract_start = ?,
            contract_end = ?
        WHERE employee_id = ?
    ");

    $stmt->bind_param(
        "sssssssssssssssi",
        $first_name,
        $last_name,
        $national_id,
        $kra_pin,
        $nssf_number,
        $nhif_number,
        $email,
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

    $stmt->execute();
    $stmt->close();

    echo "success";

} catch (Exception $e) {
    error_log("Employee update failed: " . $e->getMessage());

    echo "error: " . $e->getMessage();

} finally {
    $conn->close();
}
?>
