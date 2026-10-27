
<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| Error handling
|--------------------------------------------------------------------------
| Detailed errors are logged on the server instead of being exposed
| to users in production.
*/
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require_once __DIR__ . '/db_con.php';

    // Check database connection
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException(
            'Database connection was not initialized correctly.'
        );
    }

    $conn->set_charset('utf8mb4');

    /*
    |--------------------------------------------------------------------------
    | Accept POST requests only
    |--------------------------------------------------------------------------
    */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: leaves_request.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Retrieve submitted values
    |--------------------------------------------------------------------------
    */
    $leave_id = filter_input(
        INPUT_POST,
        'leave_id',
        FILTER_VALIDATE_INT
    );

    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $approved_by_name = trim($_POST['approved_by'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validate leave ID
    |--------------------------------------------------------------------------
    */
    if ($leave_id === false || $leave_id === null || $leave_id <= 0) {
        throw new InvalidArgumentException(
            'Invalid leave request ID.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate approver name length without mbstring
    |--------------------------------------------------------------------------
    */
    $approverLength = preg_match_all(
        '/./us',
        $approved_by_name
    );

    if ($approverLength === false) {
        throw new InvalidArgumentException(
            'The approver name contains invalid text encoding.'
        );
    }

    if ($approverLength > 150) {
        throw new InvalidArgumentException(
            'The Approved By name must not exceed 150 characters.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate dates
    |--------------------------------------------------------------------------
    */
    $start = DateTime::createFromFormat(
        '!Y-m-d',
        $start_date
    );

    $end = DateTime::createFromFormat(
        '!Y-m-d',
        $end_date
    );

    if (
        !$start ||
        !$end ||
        $start->format('Y-m-d') !== $start_date ||
        $end->format('Y-m-d') !== $end_date
    ) {
        throw new InvalidArgumentException(
            'Invalid date format. Please select valid dates.'
        );
    }

    if ($end < $start) {
        throw new InvalidArgumentException(
            'End date cannot be earlier than the start date.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate leave status
    |--------------------------------------------------------------------------
    */
    $allowed_statuses = [
        'Pending',
        'Approved',
        'Denied'
    ];

    if (!in_array($status, $allowed_statuses, true)) {
        throw new InvalidArgumentException(
            'Invalid leave status.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate approval information
    |--------------------------------------------------------------------------
    */
    if ($status === 'Approved' && $approved_by_name === '') {
        throw new InvalidArgumentException(
            'Please enter the name of the person who approved the leave.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate leave days, including both start and end dates
    |--------------------------------------------------------------------------
    */
    $total_days = (int) $start->diff($end)->days + 1;

    /*
    |--------------------------------------------------------------------------
    | Confirm that the leave request exists
    |--------------------------------------------------------------------------
    */
    $check = $conn->prepare(
        'SELECT leave_id FROM leaves WHERE leave_id = ? LIMIT 1'
    );

    $check->bind_param('i', $leave_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $check->close();

        throw new InvalidArgumentException(
            'The requested leave record was not found.'
        );
    }

    $check->close();

    /*
    |--------------------------------------------------------------------------
    | Update leave request
    |--------------------------------------------------------------------------
    | - Keeps the original approval date when an approved request is edited.
    | - Sets the approval date when a request is approved for the first time.
    | - Clears the approval date when status becomes Pending or Denied.
    |--------------------------------------------------------------------------
    */
    $stmt = $conn->prepare("
        UPDATE leaves
        SET
            start_date = ?,
            end_date = ?,
            total_days = ?,
            status = ?,
            approved_by_name = ?,
            approval_date = CASE
                WHEN ? = 'Approved' THEN
                    COALESCE(approval_date, CURRENT_TIMESTAMP)
                ELSE NULL
            END
        WHERE leave_id = ?
    ");

    $stmt->bind_param(
        'ssisssi',
        $start_date,
        $end_date,
        $total_days,
        $status,
        $approved_by_name,
        $status,
        $leave_id
    );

    $stmt->execute();
    $stmt->close();

    $conn->close();

    /*
    |--------------------------------------------------------------------------
    | Redirect after successful update
    |--------------------------------------------------------------------------
    */
    header('Location: leaves_request.php?success=updated');
    exit;

} catch (InvalidArgumentException $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }

    http_response_code(400);

    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Leave Update</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f4f6f9;
                padding: 40px 20px;
            }
            .message {
                max-width: 600px;
                margin: 40px auto;
                background: #fff;
                padding: 25px;
                border-radius: 8px;
                border-left: 5px solid #dc2626;
                box-shadow: 0 3px 12px rgba(0,0,0,.08);
            }
            a {
                color: #2563eb;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="message">
            <h2>Unable to Update Leave</h2>
            <p>' . htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            ) . '</p>
            <a href="leaves_request.php">Return to Leave Requests</a>
        </div>
    </body>
    </html>';

    exit;

} catch (Throwable $e) {
    // Record the detailed error in the server's PHP error log.
    error_log(
        'update_leave.php error: ' .
        $e->getMessage() .
        ' in ' . $e->getFile() .
        ' on line ' . $e->getLine()
    );

    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->close();
        } catch (Throwable $closeError) {
            error_log(
                'Database close error: ' . $closeError->getMessage()
            );
        }
    }

    http_response_code(500);

    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Leave Update Error</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f4f6f9;
                padding: 40px 20px;
            }
            .message {
                max-width: 600px;
                margin: 40px auto;
                background: #fff;
                padding: 25px;
                border-radius: 8px;
                border-left: 5px solid #dc2626;
                box-shadow: 0 3px 12px rgba(0,0,0,.08);
            }
            a {
                color: #2563eb;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="message">
            <h2>Something Went Wrong</h2>
            <p>The leave request could not be updated. Please try again.</p>
            <p>If the problem continues, contact the system administrator.</p>
            <a href="leaves_request.php">Return to Leave Requests</a>
        </div>
    </body>
    </html>';

    exit;
}
?>
