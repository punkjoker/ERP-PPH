
<?php
session_start();
include 'db_con.php';

date_default_timezone_set('Africa/Nairobi');

$today = date('Y-m-d');
$selected_date = $_GET['attendance_date'] ?? $today;
$message = '';
$message_type = 'success';

// Validate date
$date_obj = DateTime::createFromFormat('!Y-m-d', $selected_date);
if (!$date_obj || $date_obj->format('Y-m-d') !== $selected_date) {
    $selected_date = $today;
    $date_obj = new DateTime($today);
}

$day_number = (int)$date_obj->format('N');
$is_working_day = $day_number >= 1 && $day_number <= 6;

if (!$is_working_day) {
    $message = 'Attendance can only be recorded from Monday to Saturday.';
    $message_type = 'error';
}

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $posted_date = $_POST['attendance_date'] ?? '';

    $posted_obj = DateTime::createFromFormat('!Y-m-d', $posted_date);

    if (!$posted_obj || $posted_obj->format('Y-m-d') !== $posted_date) {
        $message = 'Invalid attendance date.';
        $message_type = 'error';
    } elseif ((int)$posted_obj->format('N') === 7) {
        $message = 'Attendance cannot be recorded on Sunday.';
        $message_type = 'error';
    } elseif (empty($_POST['attendance']) || !is_array($_POST['attendance'])) {
        $message = 'No attendance records were submitted.';
        $message_type = 'error';
    } else {
        $selected_date = $posted_date;
        $success = true;

        try {
            $conn->begin_transaction();

            // Check approved leave for each staff member.
            $leave_stmt = $conn->prepare("
                SELECT COUNT(*) AS leave_count
                FROM leaves
                WHERE user_id = ?
                  AND status = 'Approved'
                  AND start_date <= ?
                  AND end_date >= ?
            ");

            $save_stmt = $conn->prepare("
                INSERT INTO attendance
                    (user_id, attendance_date, status, remarks)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    remarks = VALUES(remarks),
                    updated_at = CURRENT_TIMESTAMP
            ");

            foreach ($_POST['attendance'] as $staff_id => $record) {
                $staff_id = filter_var($staff_id, FILTER_VALIDATE_INT);

                if (!$staff_id || !is_array($record)) {
                    throw new Exception('Invalid staff attendance data.');
                }

                // Verify the staff member belongs to the Staff group.
                $check_staff = $conn->prepare("
                    SELECT u.user_id
                    FROM users u
                    JOIN groups g ON u.group_id = g.group_id
                    WHERE u.user_id = ?
                      AND LOWER(g.group_name) = 'staff'
                    LIMIT 1
                ");

                $check_staff->bind_param("i", $staff_id);
                $check_staff->execute();
                $staff_exists = $check_staff->get_result()->num_rows > 0;
                $check_staff->close();

                if (!$staff_exists) {
                    throw new Exception('A submitted user is not a valid staff member.');
                }

                $status = $record['status'] ?? '';
                $remarks = trim($record['remarks'] ?? '');
                $remarks = substr($remarks, 0, 255);

                if (!in_array($status, ['Present', 'Absent', 'Leave'], true)) {
                    throw new Exception('Invalid attendance status submitted.');
                }

                // Approved leave takes priority over manual status.
                $leave_stmt->bind_param(
                    "iss",
                    $staff_id,
                    $selected_date,
                    $selected_date
                );
                $leave_stmt->execute();
                $leave_result = $leave_stmt->get_result()->fetch_assoc();

                if ((int)$leave_result['leave_count'] > 0) {
                    $status = 'Leave';
                    $remarks = $remarks ?: 'Approved leave';
                }

                $save_stmt->bind_param(
                    "isss",
                    $staff_id,
                    $selected_date,
                    $status,
                    $remarks
                );
                $save_stmt->execute();
            }

            $conn->commit();
            $message = 'Attendance saved successfully.';
            $message_type = 'success';

            $save_stmt->close();
            $leave_stmt->close();

        } catch (Throwable $e) {
            $conn->rollback();
            $message = 'Unable to save attendance. Please check your database tables and try again.';
            $message_type = 'error';
            error_log('Attendance save error: ' . $e->getMessage());
        }
    }
}

// Load staff and existing attendance for the selected date.
$staff = [];

$stmt = $conn->prepare("
    SELECT
        u.user_id,
        u.full_name,
        u.national_id,
        a.status AS attendance_status,
        a.remarks,
        CASE WHEN EXISTS (
            SELECT 1
            FROM leaves l
            WHERE l.user_id = u.user_id
              AND l.status = 'Approved'
              AND l.start_date <= ?
              AND l.end_date >= ?
        ) THEN 1 ELSE 0 END AS on_approved_leave
    FROM users u
    JOIN groups g ON u.group_id = g.group_id
    LEFT JOIN attendance a
        ON a.user_id = u.user_id
       AND a.attendance_date = ?
    WHERE LOWER(g.group_name) = 'staff'
    ORDER BY u.full_name ASC
");

$stmt->bind_param("sss", $selected_date, $selected_date, $selected_date);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Display approved leave consistently even if no record exists yet.
    if ((int)$row['on_approved_leave'] === 1) {
        $row['attendance_status'] = 'Leave';
        $row['remarks'] = $row['remarks'] ?: 'Approved leave';
    }

    $staff[] = $row;
}

$stmt->close();

// Today's attendance summary — always uses the current date.
$today_present = 0;
$today_absent = 0;
$today_leave = 0;

$summary_stmt = $conn->prepare("
    SELECT
        SUM(
            CASE
                WHEN l.user_id IS NOT NULL THEN 0
                WHEN a.status = 'Present' THEN 1
                ELSE 0
            END
        ) AS present_count,

        SUM(
            CASE
                WHEN l.user_id IS NOT NULL THEN 0
                WHEN a.status = 'Absent' THEN 1
                ELSE 0
            END
        ) AS absent_count,

        SUM(
            CASE
                WHEN l.user_id IS NOT NULL THEN 1
                WHEN a.status = 'Leave' THEN 1
                ELSE 0
            END
        ) AS leave_count

    FROM users u

    INNER JOIN groups g
        ON g.group_id = u.group_id

    LEFT JOIN attendance a
        ON a.user_id = u.user_id
       AND a.attendance_date = ?

    LEFT JOIN (
        SELECT DISTINCT user_id
        FROM leaves
        WHERE status = 'Approved'
          AND start_date <= ?
          AND end_date >= ?
    ) l
        ON l.user_id = u.user_id

    WHERE LOWER(TRIM(g.group_name)) = 'staff'
");

$summary_stmt->bind_param("sss", $today, $today, $today);
$summary_stmt->execute();

$summary = $summary_stmt->get_result()->fetch_assoc();

$today_present = (int)($summary['present_count'] ?? 0);
$today_absent = (int)($summary['absent_count'] ?? 0);
$today_leave = (int)($summary['leave_count'] ?? 0);

$summary_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Staff Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
        <!-- Today's Attendance Summary -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-6">

    <!-- Present -->
    <div class="bg-white rounded-xl shadow p-5 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">
                    Present Today
                </p>

                <h2 class="text-3xl font-bold text-green-600 mt-2">
                    <?= $today_present ?>
                </h2>
            </div>

            <div class="bg-green-100 text-green-700 rounded-full p-4 text-xl">
                ✓
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-3">
            <?= htmlspecialchars(date('d M Y')) ?>
        </p>
    </div>

    <!-- Absent -->
    <div class="bg-white rounded-xl shadow p-5 border-l-4 border-red-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">
                    Absent Today
                </p>

                <h2 class="text-3xl font-bold text-red-600 mt-2">
                    <?= $today_absent ?>
                </h2>
            </div>

            <div class="bg-red-100 text-red-700 rounded-full p-4 text-xl">
                !
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-3">
            <?= htmlspecialchars(date('d M Y')) ?>
        </p>
    </div>

    <!-- On Leave -->
    <div class="bg-white rounded-xl shadow p-5 border-l-4 border-orange-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">
                    On Leave Today
                </p>

                <h2 class="text-3xl font-bold text-orange-600 mt-2">
                    <?= $today_leave ?>
                </h2>
            </div>

            <div class="bg-orange-100 text-orange-700 rounded-full p-4 text-xl">
                L
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-3">
            Approved leave for today
        </p>
    </div>

</div>
        <div>
            <h1 class="text-3xl font-bold text-blue-700">
                Staff Attendance
            </h1>
            <p class="text-gray-600 mt-1">
                Record and update daily staff attendance.
            </p>
        </div>

        <a href="attendance_list.php"
           class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
            View Attendance Reports
        </a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="mb-5 p-4 rounded-lg border
            <?= $message_type === 'error'
                ? 'bg-red-50 text-red-700 border-red-200'
                : 'bg-green-50 text-green-700 border-green-200' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="GET"
          class="bg-white shadow rounded-xl p-5 mb-6 flex flex-wrap items-end gap-4">

        <div class="flex-1 min-w-56">
            <label for="attendance_date"
                   class="block font-semibold text-gray-700 mb-2">
                Attendance Date
            </label>

            <input
                type="date"
                id="attendance_date"
                name="attendance_date"
                value="<?= htmlspecialchars($selected_date) ?>"
                max="<?= htmlspecialchars($today) ?>"
                required
                class="border border-gray-300 p-3 rounded-lg w-full focus:ring-2 focus:ring-blue-400"
            >
        </div>

        <button type="submit"
                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
            Load Staff
        </button>

        <a href="attendance.php"
           class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300">
            Today
        </a>
    </form>

    <div class="bg-white rounded-xl shadow p-5">

        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800">
                    Attendance Register
                </h2>
                <p class="text-gray-500 text-sm">
                    <?= htmlspecialchars($date_obj->format('l, d F Y')) ?>
                </p>
            </div>

            <span class="bg-blue-100 text-blue-800 px-3 py-2 rounded-lg font-semibold">
                Staff: <?= count($staff) ?>
            </span>
        </div>

        <?php if (!$is_working_day): ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-lg mb-4">
                This date is Sunday. Select a Monday-to-Saturday date to record attendance.
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="attendance_date"
                   value="<?= htmlspecialchars($selected_date) ?>">

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-gray-200 text-gray-700">
                        <tr>
                            <th class="border p-3 text-left">#</th>
                            <th class="border p-3 text-left">Staff Name</th>
                            <th class="border p-3 text-left">National ID</th>
                            <th class="border p-3 text-left">Attendance Status</th>
                            <th class="border p-3 text-left">Remarks</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($staff)): ?>
                        <tr>
                            <td colspan="5" class="p-6 text-center text-gray-500">
                                No staff members found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staff as $index => $person): ?>
                            <?php
                                $status = $person['attendance_status'] ?? 'Present';
                                $on_leave = (int)$person['on_approved_leave'] === 1;
                            ?>
                            <tr class="<?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?> hover:bg-blue-50">
                                <td class="border p-3">
                                    <?= $index + 1 ?>
                                </td>

                                <td class="border p-3 font-semibold">
                                    <?= htmlspecialchars($person['full_name']) ?>
                                    <?php if ($on_leave): ?>
                                        <span class="block text-xs text-orange-600 font-normal">
                                            Approved leave
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="border p-3">
                                    <?= htmlspecialchars($person['national_id'] ?? '') ?>
                                </td>

                                <td class="border p-3">
                                    <select
                                        name="attendance[<?= (int)$person['user_id'] ?>][status]"
                                        class="border rounded-lg p-2 w-full min-w-32
                                        <?= $status === 'Present' ? 'text-green-700' : '' ?>
                                        <?= $status === 'Absent' ? 'text-red-700' : '' ?>
                                        <?= $status === 'Leave' ? 'text-orange-700' : '' ?>"
                                        <?= (!$is_working_day || $on_leave) ? 'disabled' : '' ?>
                                        required
                                    >
                                        <option value="Present" <?= $status === 'Present' ? 'selected' : '' ?>>
                                            Present
                                        </option>
                                        <option value="Absent" <?= $status === 'Absent' ? 'selected' : '' ?>>
                                            Absent
                                        </option>
                                        <option value="Leave" <?= $status === 'Leave' ? 'selected' : '' ?>>
                                            Leave
                                        </option>
                                    </select>

                                    <?php if (!$is_working_day || $on_leave): ?>
                                        <input type="hidden"
                                            name="attendance[<?= (int)$person['user_id'] ?>][status]"
                                            value="<?= htmlspecialchars($status) ?>">
                                    <?php endif; ?>
                                </td>

                                <td class="border p-3">
                                    <input
                                        type="text"
                                        name="attendance[<?= (int)$person['user_id'] ?>][remarks]"
                                        maxlength="255"
                                        value="<?= htmlspecialchars($person['remarks'] ?? '') ?>"
                                        placeholder="Optional remarks"
                                        class="border rounded-lg p-2 w-full min-w-40"
                                        <?= !$is_working_day ? 'disabled' : '' ?>
                                    >
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap justify-end gap-3 mt-5">
                <a href="attendance_list.php"
                   class="bg-gray-200 text-gray-800 px-5 py-3 rounded-lg hover:bg-gray-300">
                    Cancel
                </a>

                <button
                    type="submit"
                    name="save_attendance"
                    class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 disabled:opacity-50"
                    <?= (!$is_working_day || empty($staff)) ? 'disabled' : '' ?>
                >
                    Save Attendance
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
