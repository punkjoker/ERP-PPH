
<?php
include 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("error: Invalid request method.");
}

try {
    $employee_id = filter_var(
        $_POST['employee_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    if (!$employee_id || $employee_id <= 0) {
        throw new Exception("Invalid employee ID.");
    }

    // Fetch existing employee information
    $stmt = $conn->prepare("
        SELECT *
        FROM employees
        WHERE employee_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $employee_id);
    $stmt->execute();

    $employee = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$employee) {
        throw new Exception("Employee not found.");
    }

    // Submitted fields
    $fields = [
        'first_name',
        'last_name',
        'national_id',
        'kra_pin',
        'nssf_number',
        'nhif_number',
        'email',
        'phone',
        'department',
        'position',
        'date_of_hire',
        'status',
        'employment_type',
        'contract_start',
        'contract_end'
    ];

    $updates = [];

    foreach ($fields as $field) {
        $newValue = trim($_POST[$field] ?? '');

        // Convert empty contract dates to NULL
        if (in_array($field, ['contract_start', 'contract_end'], true)) {
            $newValue = ($newValue === '') ? null : $newValue;
        }

        $oldValue = $employee[$field] ?? null;

        if ($newValue !== $oldValue) {
            $updates[$field] = $newValue;
        }
    }

    // Validate required fields
    $firstName = $updates['first_name'] ?? $employee['first_name'];
    $lastName = $updates['last_name'] ?? $employee['last_name'];
    $hireDate = $updates['date_of_hire'] ?? $employee['date_of_hire'];

    if ($firstName === '' || $lastName === '' || $hireDate === '') {
        throw new Exception("First name, last name and date of hire are required.");
    }

    // Validate email if changed
    $emailChanged = array_key_exists('email', $updates);

    if ($emailChanged && $updates['email'] !== null && $updates['email'] !== '') {
        if (!filter_var($updates['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }
    }

    // Automatically deactivate expired contracts
    $employmentType = $updates['employment_type'] ?? $employee['employment_type'];
    $contractEnd = $updates['contract_end'] ?? $employee['contract_end'];

    if ($employmentType === 'Contract' && !empty($contractEnd) && date('Y-m-d') > $contractEnd) {
        $updates['status'] = 'Inactive';
    }

    // Nothing changed
    if (empty($updates)) {
        echo "success";
        exit;
    }

    $conn->begin_transaction();

    // Update only changed employee columns
    if (!empty($updates)) {
        $setParts = [];
        $values = [];
        $types = '';

        foreach ($updates as $column => $value) {
            $setParts[] = "`$column` = ?";
            $values[] = $value;
            $types .= 's';
        }

        $sql = "
            UPDATE employees
            SET " . implode(', ', $setParts) . "
            WHERE employee_id = ?
        ";

        $types .= 'i';
        $values[] = $employee_id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }

    // Update users only when email has actually changed
    if ($emailChanged) {
        $newEmail = $updates['email'];

        // Check for duplicate email in another user account
        if ($newEmail !== null && $newEmail !== '') {
            $check = $conn->prepare("
                SELECT user_id
                FROM users
                WHERE email = ?
                  AND employee_id <> ?
                LIMIT 1
            ");

            $check->bind_param("si", $newEmail, $employee_id);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $check->close();
                throw new Exception("This email is already assigned to another user account.");
            }

            $check->close();
        }

        $userStmt = $conn->prepare("
            UPDATE users
            SET email = ?
            WHERE employee_id = ?
        ");

        $userStmt->bind_param("si", $newEmail, $employee_id);
        $userStmt->execute();
        $userStmt->close();
    }

    $conn->commit();

    echo "success";

} catch (Throwable $e) {

    try {
        $conn->rollback();
    } catch (Throwable $ignored) {}

    error_log("Employee update failed: " . $e->getMessage());

    http_response_code(400);
    echo "error: " . $e->getMessage();

} finally {
    $conn->close();
}
?>
