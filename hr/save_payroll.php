
<?php
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

$user_id = filter_var(
    $_POST['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

$full_name = trim($_POST['full_name'] ?? '');
$payroll_no = trim($_POST['payroll_no'] ?? '');
$base_salary = (float) ($_POST['base_salary'] ?? 0);
$total_allowances = (float) ($_POST['total_allowances'] ?? 0);
$total_deductions = (float) ($_POST['total_deductions'] ?? 0);
$net_pay = (float) ($_POST['net_pay'] ?? 0);
$month = trim($_POST['month'] ?? '');
$year = filter_var($_POST['year'] ?? null, FILTER_VALIDATE_INT);
$details = $_POST['details'] ?? '{}';

$validMonths = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

if (!$user_id || !$year || $year < 2000 || $year > 2100 ||
    !in_array($month, $validMonths, true)) {
    http_response_code(400);
    exit('Invalid employee, month, or year.');
}

if ($full_name === '' || $base_salary < 0 ||
    $total_allowances < 0 || $total_deductions < 0 ||
    $net_pay < 0) {
    http_response_code(400);
    exit('Please check the payroll details and amounts.');
}

if (!is_string($details) || json_decode($details) === null) {
    http_response_code(400);
    exit('Invalid payroll details.');
}
if (strlen($payroll_no) > 100) {
    http_response_code(400);
    exit('Payroll number cannot exceed 100 characters.');
}
// Read workflow fields.
$prepared_by = trim($_POST['prepared_by'] ?? '');
$date_prepared = trim($_POST['date_prepared'] ?? '');
$preparation_status = $_POST['preparation_status'] ?? 'Pending';

$approved_by = trim($_POST['approved_by'] ?? '');
$approval_date = trim($_POST['approval_date'] ?? '');
$approval_status = $_POST['approval_status'] ?? 'Pending';

$accountant_name = trim($_POST['accountant_name'] ?? '');
$payment_date = trim($_POST['payment_date'] ?? '');
$payment_status = $_POST['payment_status'] ?? 'Pending Pay';

// Convert blank dates to NULL and reject invalid dates.
function validatePayrollDate($value, $fieldName)
{
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();

    if (
        !$date ||
        ($errors !== false &&
            ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
        $date->format('Y-m-d') !== $value
    ) {
        throw new InvalidArgumentException("Invalid $fieldName.");
    }

    return $value;
}

try {
    $date_prepared = validatePayrollDate(
        $date_prepared,
        'preparation date'
    );

    $approval_date = validatePayrollDate(
        $approval_date,
        'approval date'
    );

    $payment_date = validatePayrollDate(
        $payment_date,
        'payment date'
    );

    if (!in_array($preparation_status, ['Pending', 'Prepared'], true)) {
        throw new InvalidArgumentException('Invalid preparation status.');
    }

    if (!in_array($approval_status, ['Pending', 'Approved'], true)) {
        throw new InvalidArgumentException('Invalid approval status.');
    }

    if (!in_array($payment_status, ['Pending Pay', 'Paid'], true)) {
        throw new InvalidArgumentException('Invalid payment status.');
    }

    // Require a name and date when a stage is marked complete.
    if ($preparation_status === 'Prepared' &&
        ($prepared_by === '' || $date_prepared === null)) {
        throw new InvalidArgumentException(
            'Enter the preparer name and preparation date.'
        );
    }

    if ($approval_status === 'Approved' &&
        ($approved_by === '' || $approval_date === null)) {
        throw new InvalidArgumentException(
            'Enter the approver name and approval date.'
        );
    }

    if ($payment_status === 'Paid' &&
        ($accountant_name === '' || $payment_date === null)) {
        throw new InvalidArgumentException(
            'Enter the accountant name and payment date.'
        );
    }

    // Find an existing payroll record.
    $check = $conn->prepare("
        SELECT payroll_id
        FROM payroll_records
        WHERE user_id = ? AND month = ? AND year = ?
        LIMIT 1
    ");

    $check->bind_param('isi', $user_id, $month, $year);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

 
if ($existing) {

    // Update existing payroll record
    $stmt = $conn->prepare("
        UPDATE payroll_records
        SET payroll_no = ?,
            full_name = ?,
            base_salary = ?,
            total_allowances = ?,
            total_deductions = ?,
            net_pay = ?,
            details = ?,
            prepared_by = ?,
            date_prepared = ?,
            preparation_status = ?,
            approved_by = ?,
            approval_date = ?,
            approval_status = ?,
            accountant_name = ?,
            payment_date = ?,
            payment_status = ?
        WHERE payroll_id = ?
    ");

    $payroll_id = (int) $existing['payroll_id'];

    $stmt->bind_param(
        'ssddddssssssssssi',
        $payroll_no,
        $full_name,
        $base_salary,
        $total_allowances,
        $total_deductions,
        $net_pay,
        $details,
        $prepared_by,
        $date_prepared,
        $preparation_status,
        $approved_by,
        $approval_date,
        $approval_status,
        $accountant_name,
        $payment_date,
        $payment_status,
        $payroll_id
    );

    $stmt->execute();
    $stmt->close();

    echo 'Payroll updated successfully for '
        . htmlspecialchars($month . ' ' . $year, ENT_QUOTES, 'UTF-8')
        . '.';

}


 
else {

    // Insert a new payroll record
    $stmt = $conn->prepare("
        INSERT INTO payroll_records (
            user_id,
            payroll_no,
            full_name,
            base_salary,
            total_allowances,
            total_deductions,
            net_pay,
            month,
            year,
            details,
            prepared_by,
            date_prepared,
            preparation_status,
            approved_by,
            approval_date,
            approval_status,
            accountant_name,
            payment_date,
            payment_status
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->bind_param(
        'issddddsissssssssss',
        $user_id,
        $payroll_no,
        $full_name,
        $base_salary,
        $total_allowances,
        $total_deductions,
        $net_pay,
        $month,
        $year,
        $details,
        $prepared_by,
        $date_prepared,
        $preparation_status,
        $approved_by,
        $approval_date,
        $approval_status,
        $accountant_name,
        $payment_date,
        $payment_status
    );

   
    $stmt->execute();
    $stmt->close();

    echo 'Payroll saved successfully for '
        . htmlspecialchars($month . ' ' . $year, ENT_QUOTES, 'UTF-8')
        . '.';
}

} catch (InvalidArgumentException $e) {
    http_response_code(400);

    echo htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    );

} catch (Throwable $e) {
    error_log('Payroll save error: ' . $e->getMessage());

    http_response_code(500);

    echo 'Unable to save payroll. Please check the server error log.';
}
?>
