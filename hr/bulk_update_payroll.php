
<?php
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function redirectWithMessage($month, $type, $message)
{
    $url = 'payroll_list.php?month=' . rawurlencode($month)
         . '&bulk_type=' . rawurlencode($type)
         . '&bulk_message=' . rawurlencode($message);

    header('Location: ' . $url);
    exit;
}

function validDate($date)
{
    if (!is_string($date) || $date === '') {
        return false;
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed && $parsed->format('Y-m-d') === $date;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: payroll_list.php');
    exit;
}

$monthInput = trim($_POST['month'] ?? '');

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthInput)) {
    redirectWithMessage(
        date('Y-m'),
        'error',
        'Invalid payroll month.'
    );
}

$monthDate = DateTime::createFromFormat('!Y-m', $monthInput);
$payrollMonth = $monthDate->format('F');
$payrollYear = (int) $monthDate->format('Y');

$operation = $_POST['operation'] ?? '';

if (!in_array($operation, ['approve', 'pay'], true)) {
    redirectWithMessage(
        $monthInput,
        'error',
        'Invalid bulk operation.'
    );
}

// Collect selected employee user IDs
$employeeIds = $_POST['employee_ids'] ?? [];

if (!is_array($employeeIds) || empty($employeeIds)) {
    redirectWithMessage(
        $monthInput,
        'error',
        'Please select at least one employee.'
    );
}

$employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

if (in_array(0, $employeeIds, true)) {
    redirectWithMessage(
        $monthInput,
        'error',
        'One or more selected employee IDs are invalid.'
    );
}

// Validate operation details
if ($operation === 'approve') {

    $approvedBy = trim($_POST['approved_by'] ?? '');
    $approvalDate = trim($_POST['approval_date'] ?? '');

    if ($approvedBy === '' || strlen($approvedBy) > 150 || !validDate($approvalDate)) {
        redirectWithMessage(
            $monthInput,
            'error',
            'Enter a valid approver name and approval date.'
        );
    }

} else {

    $accountantName = trim($_POST['accountant_name'] ?? '');
    $paymentDate = trim($_POST['payment_date'] ?? '');

    if ($accountantName === '' || strlen($accountantName) > 150 || !validDate($paymentDate)) {
        redirectWithMessage(
            $monthInput,
            'error',
            'Enter a valid accountant name and payment date.'
        );
    }
}

$placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
$idTypes = str_repeat('i', count($employeeIds));

try {

    $conn->begin_transaction();

    /*
     * Lock and verify all selected payroll records.
     * Only active staff with an existing payroll record
     * for the selected month are accepted.
     */
    $sql = "
        SELECT
            pp.user_id,
            pp.preparation_status,
            pp.approval_status,
            pp.payment_status

        FROM payroll_records pp

        INNER JOIN users u
            ON u.user_id = pp.user_id

        INNER JOIN groups g
            ON g.group_id = u.group_id

        INNER JOIN employees e
            ON e.employee_id = u.employee_id

        WHERE pp.month = ?
          AND pp.year = ?
          AND pp.user_id IN ($placeholders)
          AND LOWER(TRIM(g.group_name)) = 'staff'
          AND LOWER(TRIM(e.status)) = 'active'

        FOR UPDATE
    ";

    $stmt = $conn->prepare($sql);

    $types = 'si' . $idTypes;
    $params = array_merge(
        [$payrollMonth, $payrollYear],
        $employeeIds
    );

    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Every selected employee must have a matching payroll record.
    if (count($records) !== count($employeeIds)) {
        throw new Exception(
            'Some selected employees do not have an existing payroll record for '
            . $monthDate->format('F Y')
            . ', or are no longer active staff. No records were changed.'
        );
    }

    // Validate all records before updating any of them.
    foreach ($records as $record) {

        $preparationStatus = trim($record['preparation_status'] ?? 'Pending');
        $approvalStatus = trim($record['approval_status'] ?? 'Pending');
        $paymentStatus = trim($record['payment_status'] ?? 'Pending Pay');

        if ($operation === 'approve') {

            if ($preparationStatus !== 'Prepared') {
                throw new Exception(
                    'Approval failed. Every selected employee must have Prepared status. '
                    . 'No records were changed.'
                );
            }

            if ($approvalStatus === 'Approved') {
                throw new Exception(
                    'One or more selected payroll records are already approved. '
                    . 'No records were changed.'
                );
            }

            if ($paymentStatus === 'Paid') {
                throw new Exception(
                    'One or more selected payroll records are already paid. '
                    . 'No records were changed.'
                );
            }

        } elseif ($operation === 'pay') {

            if ($approvalStatus !== 'Approved') {
                throw new Exception(
                    'Payment failed. Every selected employee must already be Approved. '
                    . 'No records were changed.'
                );
            }

            if ($paymentStatus === 'Paid') {
                throw new Exception(
                    'One or more selected payroll records are already marked as Paid. '
                    . 'No records were changed.'
                );
            }
        }
    }

    // Update only the relevant workflow fields.
    if ($operation === 'approve') {

        $updateSql = "
            UPDATE payroll_records
            SET
                approved_by = ?,
                approval_date = ?,
                approval_status = 'Approved'

            WHERE month = ?
              AND year = ?
              AND user_id IN ($placeholders)
              AND preparation_status = 'Prepared'
              AND COALESCE(approval_status, 'Pending') <> 'Approved'
              AND COALESCE(payment_status, 'Pending Pay') <> 'Paid'
        ";

        $stmt = $conn->prepare($updateSql);

        $types = 'sssi' . $idTypes;
        $params = array_merge(
            [$approvedBy, $approvalDate, $payrollMonth, $payrollYear],
            $employeeIds
        );

        $stmt->bind_param($types, ...$params);

    } else {

        $updateSql = "
            UPDATE payroll_records
            SET
                accountant_name = ?,
                payment_date = ?,
                payment_status = 'Paid'

            WHERE month = ?
              AND year = ?
              AND user_id IN ($placeholders)
              AND approval_status = 'Approved'
              AND COALESCE(payment_status, 'Pending Pay') <> 'Paid'
        ";

        $stmt = $conn->prepare($updateSql);

        $types = 'sssi' . $idTypes;
        $params = array_merge(
            [$accountantName, $paymentDate, $payrollMonth, $payrollYear],
            $employeeIds
        );

        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    if ($stmt->affected_rows !== count($employeeIds)) {
        $stmt->close();

        throw new Exception(
            'The number of updated records did not match the selected employees. '
            . 'The operation was cancelled.'
        );
    }

    $stmt->close();

    $conn->commit();

    if ($operation === 'approve') {
        redirectWithMessage(
            $monthInput,
            'success',
            count($employeeIds) . ' payroll record(s) successfully approved for '
            . $monthDate->format('F Y') . '.'
        );
    }

    redirectWithMessage(
        $monthInput,
        'success',
        count($employeeIds) . ' payroll record(s) successfully marked as paid for '
        . $monthDate->format('F Y') . '.'
    );

} catch (Throwable $e) {

    if ($conn->errno === 0 || $conn->connect_errno === 0) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Preserve the original error.
        }
    }

    redirectWithMessage(
        $monthInput,
        'error',
        $e->getMessage()
    );
}
