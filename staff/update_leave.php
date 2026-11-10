<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function redirectLeave(string $message = 'updated', string $page = 'leaves_request.php'): void {
    header('Location: ' . $page . '?success=' . urlencode($message));
    exit;
}

function validDate($date): bool {
    if (!is_string($date) || $date === '') return false;
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function cleanText($value, int $max = 255): string {
    $value = trim((string)$value);
    if (mb_strlen($value, 'UTF-8') > $max) {
        throw new InvalidArgumentException("Text must not exceed {$max} characters.");
    }
    return $value;
}

$conn = null;
$transactionStarted = false;

try {
    require_once __DIR__ . '/db_con.php';
    $conn->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirectLeave();
    }

    if (empty($_SESSION['user_id'])) {
        throw new InvalidArgumentException('Please log in before processing this request.');
    }

    $loggedInUserId = (int)$_SESSION['user_id'];
    $action = $_POST['action'] ?? '';

    // -------------------------------------------------
    // RECORD HISTORICAL LEAVE (existing HR function)
    // -------------------------------------------------
    if ($action === 'record_historical') {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $type = $_POST['leave_type'] ?? '';
        $start = trim($_POST['start_date'] ?? '');
        $end = trim($_POST['end_date'] ?? '');
        $description = cleanText($_POST['description'] ?? '', 255);
        $allowedTypes = ['Annual', 'Sick', 'Paternity', 'Maternity'];

        if (!$userId || $userId <= 0) {
            throw new InvalidArgumentException('Please select a valid staff member.');
        }
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException('Invalid leave type.');
        }
        if (!validDate($start) || !validDate($end) || $end < $start) {
            throw new InvalidArgumentException('Please select valid leave dates.');
        }

        $check = $conn->prepare("
            SELECT u.user_id
            FROM users u
            JOIN groups g ON u.group_id = g.group_id
            WHERE u.user_id = ? AND LOWER(g.group_name) = 'staff'
            LIMIT 1
        ");
        $check->bind_param('i', $userId);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$exists) {
            throw new InvalidArgumentException('The selected staff member was not found.');
        }

        $days = (int)(new DateTime($start))->diff(new DateTime($end))->days + 1;

        $stmt = $conn->prepare("
            INSERT INTO historical_leaves
                (user_id, leave_type, start_date, end_date, total_days, description, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isssisi', $userId, $type, $start, $end, $days, $description, $loggedInUserId);
        $stmt->execute();
        $stmt->close();

        redirectLeave('historical_added');
    }

    $leaveId = filter_input(INPUT_POST, 'leave_id', FILTER_VALIDATE_INT);
    if (!$leaveId || $leaveId <= 0) {
        throw new InvalidArgumentException('Invalid leave request.');
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $stmt = $conn->prepare("
        SELECT leave_id, user_id, supervisor_status, td_status, status
        FROM leaves
        WHERE leave_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param('i', $leaveId);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$leave) {
        throw new InvalidArgumentException('Leave request not found.');
    }

    // -------------------------------------------------
    // UPDATE DATES: reset department and TD decisions
    // -------------------------------------------------
    if ($action === 'update_dates') {
        $start = trim($_POST['start_date'] ?? '');
        $end = trim($_POST['end_date'] ?? '');

        if (!validDate($start) || !validDate($end) || $end < $start) {
            throw new InvalidArgumentException('Please select valid leave dates.');
        }

        $stmt = $conn->prepare("
            UPDATE leaves
            SET start_date = ?,
                end_date = ?,
                status = 'Pending',
                supervisor_status = 'Pending',
                supervisor_approved_by = NULL,
                supervisor_approved_by_name = NULL,
                supervisor_approval_date = NULL,
                supervisor_remarks = NULL,
                td_status = 'Waiting',
                td_approved_by = NULL,
                td_approved_by_name = NULL,
                td_approval_date = NULL,
                td_remarks = NULL,
                approved_by = NULL,
                approved_by_name = NULL,
                approval_date = NULL,
                hr_status = 'Pending',
                hr_approved_by = NULL,
                hr_approved_by_name = NULL,
                hr_approval_date = NULL,
                hr_remarks = NULL
            WHERE leave_id = ?
        ");
        $stmt->bind_param('ssi', $start, $end, $leaveId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $transactionStarted = false;
        redirectLeave('updated');
    }

    // -------------------------------------------------
    // DEPARTMENT SUPERVISOR DECISION
    // Only direct reports in organization_hierarchy.
    // -------------------------------------------------
    if ($action === 'supervisor_decision') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!is_string($submittedToken) || empty($_SESSION['department_leave_csrf']) ||
            !hash_equals($_SESSION['department_leave_csrf'], $submittedToken)) {
            throw new InvalidArgumentException('Invalid request token. Refresh the page and try again.');
        }

        $decision = $_POST['decision'] ?? '';
        $remarks = cleanText($_POST['remarks'] ?? '', 2000);

        if (!in_array($decision, ['Approved', 'Denied'], true)) {
            throw new InvalidArgumentException('Select a valid department decision.');
        }

        // Resolve the logged-in supervisor's employee record.
        $stmt = $conn->prepare("
            SELECT employee_id, full_name
            FROM users
            WHERE user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $loggedInUserId);
        $stmt->execute();
        $supervisor = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $supervisorEmployeeId = (int)($supervisor['employee_id'] ?? 0);
        $supervisorName = trim((string)($supervisor['full_name'] ?? ''));

        if ($supervisorEmployeeId <= 0 || $supervisorName === '') {
            throw new InvalidArgumentException('Your staff account is not linked to an employee record.');
        }

        // Verify this employee reports directly to the logged-in supervisor.
        $stmt = $conn->prepare("
            SELECT l.leave_id
            FROM leaves l
            INNER JOIN users staff_user ON staff_user.user_id = l.user_id
            INNER JOIN organization_hierarchy h
                ON h.employee_id = staff_user.employee_id
            WHERE l.leave_id = ?
              AND h.reports_to_employee_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ii', $leaveId, $supervisorEmployeeId);
        $stmt->execute();
        $authorized = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$authorized) {
            throw new InvalidArgumentException('You are not the assigned supervisor for this leave request.');
        }

        if (($leave['supervisor_status'] ?? 'Pending') !== 'Pending') {
            throw new InvalidArgumentException('This department decision has already been recorded. Edit the leave dates to restart approval.');
        }

        $tdStatus = $decision === 'Approved' ? 'Waiting' : 'Waiting';
        $finalStatus = $decision === 'Approved' ? 'Pending' : 'Denied';

        $stmt = $conn->prepare("
            UPDATE leaves
            SET supervisor_status = ?,
                supervisor_approved_by = ?,
                supervisor_approved_by_name = ?,
                supervisor_approval_date = NOW(),
                supervisor_remarks = ?,
                td_status = ?,
                td_approved_by = NULL,
                td_approved_by_name = NULL,
                td_approval_date = NULL,
                td_remarks = NULL,
                status = ?,
                approved_by = NULL,
                approved_by_name = NULL,
                approval_date = NULL
            WHERE leave_id = ?
        ");
        $stmt->bind_param(
            'sissssi',
            $decision,
            $loggedInUserId,
            $supervisorName,
            $remarks,
            $tdStatus,
            $finalStatus,
            $leaveId
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $transactionStarted = false;
        redirectLeave('supervisor_updated', 'department_leaves.php');
    }

    // -------------------------------------------------
    // TD FINAL DECISION: only after supervisor approval
    // -------------------------------------------------
    if ($action === 'td_decision') {
        if (($leave['supervisor_status'] ?? 'Pending') !== 'Approved') {
            throw new InvalidArgumentException(
                'TD can only decide after the department supervisor has approved the request.'
            );
        }
        if (($leave['td_status'] ?? 'Waiting') !== 'Waiting') {
            throw new InvalidArgumentException('A TD decision has already been recorded. Edit the leave dates to restart approval.');
        }

        $decision = $_POST['decision'] ?? '';
        $name = cleanText($_POST['approver_name'] ?? '', 150);
        $remarks = cleanText($_POST['remarks'] ?? '', 2000);

        if (!in_array($decision, ['Approved', 'Denied'], true)) {
            throw new InvalidArgumentException('Select a valid TD decision.');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Enter the TD approver name.');
        }

        $finalStatus = $decision;
        $finalName = $decision === 'Approved' ? $name : null;

        $stmt = $conn->prepare("
            UPDATE leaves
            SET td_status = ?,
                td_approved_by = ?,
                td_approved_by_name = ?,
                td_approval_date = NOW(),
                td_remarks = ?,
                status = ?,
                approved_by = NULL,
                approved_by_name = ?,
                approval_date = CASE WHEN ? = 'Approved' THEN NOW() ELSE NULL END
            WHERE leave_id = ?
        ");
        $stmt->bind_param(
            'sisssssi',
            $decision,
            $loggedInUserId,
            $name,
            $remarks,
            $finalStatus,
            $finalName,
            $finalStatus,
            $leaveId
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $transactionStarted = false;
        redirectLeave('td_updated');
    }

    throw new InvalidArgumentException('Unknown leave action.');

} catch (InvalidArgumentException $e) {
    if ($transactionStarted && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    http_response_code(400);
    echo '<h3>Unable to process leave request</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="leaves_request.php">Return to Leave Requests</a>';
    exit;

} catch (Throwable $e) {
    if ($transactionStarted && $conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    error_log('update_leave.php: ' . $e->getMessage());
    http_response_code(500);
    echo '<h3>Something went wrong while processing the leave request.</h3>';
    echo '<a href="leaves_request.php">Return to Leave Requests</a>';
    exit;
}
?>
 