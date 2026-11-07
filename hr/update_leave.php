
<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function redirectLeave($message = 'updated') {
    header('Location: leaves_request.php?success=' . urlencode($message));
    exit;
}

function validDate($date): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function cleanText($value, $max = 255): string {
    $value = trim((string)$value);
    if (mb_strlen($value, 'UTF-8') > $max) {
        throw new InvalidArgumentException("Text must not exceed {$max} characters.");
    }
    return $value;
}

try {
    require_once __DIR__ . '/db_con.php';
    $conn->set_charset('utf8mb4');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirectLeave();
    }

    $action = $_POST['action'] ?? '';

    // -------------------------------------------------
    // RECORD HISTORICAL LEAVE
    // -------------------------------------------------
    if ($action === 'record_historical') {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $type = $_POST['leave_type'] ?? '';
        $start = trim($_POST['start_date'] ?? '');
        $end = trim($_POST['end_date'] ?? '');
        $description = cleanText($_POST['description'] ?? '', 255);
        $recordedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

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

        // Confirm selected user is a staff member.
        $check = $conn->prepare("
            SELECT u.user_id
            FROM users u
            JOIN groups g ON u.group_id = g.group_id
            WHERE u.user_id = ?
              AND LOWER(g.group_name) = 'staff'
            LIMIT 1
        ");
        $check->bind_param('i', $userId);
        $check->execute();

        if (!$check->get_result()->fetch_assoc()) {
            throw new InvalidArgumentException('The selected staff member was not found.');
        }
        $check->close();

        $startObj = new DateTime($start);
        $endObj = new DateTime($end);
        $days = (int)$startObj->diff($endObj)->days + 1;

        $stmt = $conn->prepare("
            INSERT INTO historical_leaves
                (user_id, leave_type, start_date, end_date,
                 total_days, description, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'isssisi',
            $userId,
            $type,
            $start,
            $end,
            $days,
            $description,
            $recordedBy
        );

        $stmt->execute();
        $stmt->close();

        redirectLeave('historical_added');
    }

    // All other actions require a valid leave request ID.
    $leaveId = filter_input(INPUT_POST, 'leave_id', FILTER_VALIDATE_INT);

    if (!$leaveId || $leaveId <= 0) {
        throw new InvalidArgumentException('Invalid leave request.');
    }

    // Lock the record while processing a decision.
    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT leave_id, hr_status, td_status, status
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
    // UPDATE DATES
    // Changing dates resets both approval stages.
    // -------------------------------------------------
    if ($action === 'update_dates') {
        $start = trim($_POST['start_date'] ?? '');
        $end = trim($_POST['end_date'] ?? '');

        if (!validDate($start) || !validDate($end) || $end < $start) {
            throw new InvalidArgumentException('Please select valid leave dates.');
        }

        $stmt = $conn->prepare("
            UPDATE leaves
            SET
                start_date = ?,
                end_date = ?,
                status = 'Pending',
                hr_status = 'Pending',
                hr_approved_by = NULL,
                hr_approved_by_name = NULL,
                hr_approval_date = NULL,
                hr_remarks = NULL,
                td_status = 'Waiting',
                td_approved_by = NULL,
                td_approved_by_name = NULL,
                td_approval_date = NULL,
                td_remarks = NULL,
                approved_by = NULL,
                approved_by_name = NULL,
                approval_date = NULL
            WHERE leave_id = ?
        ");

        $stmt->bind_param('ssi', $start, $end, $leaveId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        redirectLeave('updated');
    }

    // -------------------------------------------------
    // HR DECISION
    // -------------------------------------------------
    if ($action === 'hr_decision') {
        $decision = $_POST['decision'] ?? '';
        $name = cleanText($_POST['approver_name'] ?? '', 150);
        $remarks = cleanText($_POST['remarks'] ?? '', 2000);

        if (!in_array($decision, ['Approved', 'Denied'], true)) {
            throw new InvalidArgumentException('Select a valid HR decision.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Enter the HR approver name.');
        }

        $hrUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        if ($decision === 'Approved') {
            $tdStatus = 'Waiting';
            $finalStatus = 'Pending';
        } else {
            $tdStatus = 'Waiting';
            $finalStatus = 'Denied';
        }

        $stmt = $conn->prepare("
            UPDATE leaves
            SET
                hr_status = ?,
                hr_approved_by = ?,
                hr_approved_by_name = ?,
                hr_approval_date = NOW(),
                hr_remarks = ?,
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
            $hrUserId,
            $name,
            $remarks,
            $tdStatus,
            $finalStatus,
            $leaveId
        );

        $stmt->execute();
        $stmt->close();

        $conn->commit();
        redirectLeave('hr_updated');
    }

    // -------------------------------------------------
    // TD DECISION
    // TD can only act after HR approval.
    // -------------------------------------------------
    if ($action === 'td_decision') {
        if ($leave['hr_status'] !== 'Approved') {
            throw new InvalidArgumentException(
                'TD can only decide after HR has approved the request.'
            );
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

        $tdUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        if ($decision === 'Approved') {
            $finalStatus = 'Approved';
            $finalName = $name;
        } else {
            $finalStatus = 'Denied';
            $finalName = null;
        }

        $stmt = $conn->prepare("
            UPDATE leaves
            SET
                td_status = ?,
                td_approved_by = ?,
                td_approved_by_name = ?,
                td_approval_date = NOW(),
                td_remarks = ?,
                status = ?,
                approved_by = NULL,
                approved_by_name = ?,
                approval_date = CASE
                    WHEN ? = 'Approved' THEN NOW()
                    ELSE NULL
                END
            WHERE leave_id = ?
        ");

        $stmt->bind_param(
            'sisssssi',
            $decision,
            $tdUserId,
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
        redirectLeave('td_updated');
    }

    throw new InvalidArgumentException('Unknown leave action.');

} catch (InvalidArgumentException $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            if ($conn->errno === 0) {
                $conn->rollback();
            }
        } catch (Throwable $ignored) {}
        $conn->close();
    }

    http_response_code(400);
    echo '<h3>Unable to process leave request</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<a href="leaves_request.php">Return to Leave Requests</a>';
    exit;

} catch (Throwable $e) {
    error_log('update_leave.php: ' . $e->getMessage());

    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
            $conn->close();
        } catch (Throwable $ignored) {}
    }

    http_response_code(500);
    echo '<h3>Something went wrong while processing the leave request.</h3>';
    echo '<a href="leaves_request.php">Return to Leave Requests</a>';
    exit;
}
?>
