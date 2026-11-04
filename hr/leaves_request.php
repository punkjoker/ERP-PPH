
<?php
session_start();
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// --------------------------------------------------
// CONFIGURATION
// --------------------------------------------------
$currentYear = (int)date('Y');

$entitlements = [
    'Annual'    => 21,
    'Sick'      => 30,
    'Paternity' => 14,
    'Maternity' => 90
];

$leaveTypes = ['Annual', 'Sick', 'Paternity', 'Maternity'];

function leaveH($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function leaveDateDays($start, $end): int {
    if (!$start || !$end) {
        return 0;
    }

    $startTime = strtotime($start);
    $endTime = strtotime($end);

    if ($startTime === false || $endTime === false || $endTime < $startTime) {
        return 0;
    }

    return (int)(($endTime - $startTime) / 86400) + 1;
}

// --------------------------------------------------
// FILTER VALUES
// --------------------------------------------------
$filter_user = $_GET['user_id'] ?? '';
$filter_type = $_GET['leave_type'] ?? '';
$from_date   = $_GET['from_date'] ?? '';
$to_date     = $_GET['to_date'] ?? '';

$where = [];
$params = [];
$bindTypes = '';

$sql = "
    SELECT
        l.*,
        u.full_name,
        u.national_id,
        COALESCE(
            NULLIF(TRIM(l.approved_by_name), ''),
            approver.full_name,
            ''
        ) AS approver_name
    FROM leaves l
    JOIN users u ON l.user_id = u.user_id
    JOIN groups g ON u.group_id = g.group_id
    LEFT JOIN users approver ON l.approved_by = approver.user_id
    WHERE LOWER(g.group_name) = 'staff'
";

if ($filter_user !== '' && ctype_digit((string)$filter_user)) {
    $where[] = "l.user_id = ?";
    $params[] = (int)$filter_user;
    $bindTypes .= 'i';
}

if ($filter_type !== '' && in_array($filter_type, $leaveTypes, true)) {
    $where[] = "l.leave_type = ?";
    $params[] = $filter_type;
    $bindTypes .= 's';
}

if ($from_date !== '' && $to_date !== '') {
    $validFrom = DateTime::createFromFormat('!Y-m-d', $from_date);
    $validTo = DateTime::createFromFormat('!Y-m-d', $to_date);

    if (
        $validFrom &&
        $validTo &&
        $validFrom->format('Y-m-d') === $from_date &&
        $validTo->format('Y-m-d') === $to_date &&
        $from_date <= $to_date
    ) {
        $where[] = "l.start_date BETWEEN ? AND ?";
        $params[] = $from_date;
        $params[] = $to_date;
        $bindTypes .= 'ss';
    }
}

if ($where) {
    $sql .= ' AND ' . implode(' AND ', $where);
}

$sql .= " ORDER BY l.start_date DESC, l.leave_id DESC";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($bindTypes, ...$params);
}

$stmt->execute();
$leaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --------------------------------------------------
// FETCH STAFF
// --------------------------------------------------
$usersResult = $conn->query("
    SELECT u.user_id, u.full_name, u.national_id
    FROM users u
    JOIN groups g ON u.group_id = g.group_id
    WHERE LOWER(g.group_name) = 'staff'
    ORDER BY u.full_name ASC
");

$users = $usersResult->fetch_all(MYSQLI_ASSOC);

// --------------------------------------------------
// APPROVED LEAVE TOTALS BY YEAR
// Only fully approved requests count.
// --------------------------------------------------
$approvedTotals = [];

$balanceStmt = $conn->prepare("
    SELECT
        user_id,
        leave_type,
        YEAR(start_date) AS leave_year,
        SUM(DATEDIFF(end_date, start_date) + 1) AS days_taken
    FROM leaves
    WHERE status = 'Approved'
    GROUP BY user_id, leave_type, YEAR(start_date)
");

$balanceStmt->execute();
$balanceResult = $balanceStmt->get_result();

while ($row = $balanceResult->fetch_assoc()) {
    $key = $row['user_id'] . '|' . $row['leave_type'] . '|' . $row['leave_year'];
    $approvedTotals[$key] = (int)$row['days_taken'];
}
$balanceStmt->close();

// --------------------------------------------------
// HISTORICAL LEAVE TOTALS BY YEAR
// --------------------------------------------------
$historicalTotals = [];

$historyStmt = $conn->prepare("
    SELECT
        user_id,
        leave_type,
        YEAR(start_date) AS leave_year,
        SUM(total_days) AS days_taken
    FROM historical_leaves
    GROUP BY user_id, leave_type, YEAR(start_date)
");

$historyStmt->execute();
$historyResult = $historyStmt->get_result();

while ($row = $historyResult->fetch_assoc()) {
    $key = $row['user_id'] . '|' . $row['leave_type'] . '|' . $row['leave_year'];
    $historicalTotals[$key] = (int)$row['days_taken'];
}
$historyStmt->close();

// --------------------------------------------------
// INDIVIDUAL SUMMARY FOR CURRENT YEAR
// --------------------------------------------------
$summary = [];
$selectedUserName = '';

if ($filter_user !== '') {
    foreach ($users as $u) {
        if ((string)$u['user_id'] === (string)$filter_user) {
            $selectedUserName = $u['full_name'];
            break;
        }
    }
}

if (
    $filter_user !== '' &&
    ctype_digit((string)$filter_user) &&
    in_array($filter_type, $leaveTypes, true)
) {
    $userId = (int)$filter_user;
    $entitled = $entitlements[$filter_type] ?? null;
    $key = $userId . '|' . $filter_type . '|' . $currentYear;

    $approved = $approvedTotals[$key] ?? 0;
    $historical = $historicalTotals[$key] ?? 0;
    $taken = $approved + $historical;

    if ($entitled !== null) {
        $summary = [
            'entitled'   => $entitled,
            'approved'   => $approved,
            'historical' => $historical,
            'taken'      => $taken,
            'remaining'  => $entitled - $taken
        ];
    }
}

// --------------------------------------------------
// HISTORICAL RECORDS FOR DISPLAY
// --------------------------------------------------
$historySql = "
    SELECT
        h.*,
        u.full_name,
        recorder.full_name AS recorder_name
    FROM historical_leaves h
    JOIN users u ON h.user_id = u.user_id
    LEFT JOIN users recorder ON h.recorded_by = recorder.user_id
    WHERE YEAR(h.start_date) = ?
";

$historyParams = [$currentYear];
$historyTypes = 'i';

if ($filter_user !== '' && ctype_digit((string)$filter_user)) {
    $historySql .= " AND h.user_id = ?";
    $historyParams[] = (int)$filter_user;
    $historyTypes .= 'i';
}

if (in_array($filter_type, $leaveTypes, true)) {
    $historySql .= " AND h.leave_type = ?";
    $historyParams[] = $filter_type;
    $historyTypes .= 's';
}

$historySql .= " ORDER BY h.start_date DESC, h.historical_leave_id DESC";

$historyListStmt = $conn->prepare($historySql);
$historyListStmt->bind_param($historyTypes, ...$historyParams);
$historyListStmt->execute();
$historicalRecords = $historyListStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$historyListStmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests | Lynntech Manager</title>

    <link
        href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"
        rel="stylesheet"
    >

    <style>
        body { background: #f3f4f6; }
        .table-wrap { overflow-x: auto; }
        table { min-width: 1900px; }
        th { white-space: nowrap; }
        .balance-negative { color: #b91c1c; font-weight: 700; }
        .balance-positive { color: #047857; font-weight: 700; }
        .modal-overlay { z-index: 99999; }
    </style>
</head>

<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-full mx-auto">

    <!-- PAGE HEADER -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-blue-700">All Leave Requests</h1>
            <p class="text-gray-600 mt-1">
                Review leave balances, historical leave and HR/TD approvals.
            </p>
        </div>

        <a
            href="download_leaves_requests.php?<?= leaveH(http_build_query($_GET)) ?>"
            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
        >
            Download Leave Requests
        </a>
    </div>

    <!-- SUCCESS MESSAGES -->
    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border border-green-300 text-green-800 rounded p-3 mb-5">
            <?php
            $messages = [
                'updated' => 'Leave request dates updated. Both approval stages have been reset.',
                'hr_updated' => 'HR decision saved successfully.',
                'td_updated' => 'TD decision saved successfully.',
                'historical_added' => 'Historical leave record added successfully.'
            ];
            echo leaveH($messages[$_GET['success']] ?? 'Operation completed successfully.');
            ?>
        </div>
    <?php endif; ?>

    <!-- FILTERS -->
    <form method="GET"
          class="bg-white shadow rounded-lg p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">

        <div>
            <label class="block font-semibold mb-1">From Date</label>
            <input type="date" name="from_date"
                   value="<?= leaveH($from_date) ?>"
                   class="border p-2 rounded w-full">
        </div>

        <div>
            <label class="block font-semibold mb-1">To Date</label>
            <input type="date" name="to_date"
                   value="<?= leaveH($to_date) ?>"
                   class="border p-2 rounded w-full">
        </div>

        <div>
            <label class="block font-semibold mb-1">Staff Member</label>
            <select name="user_id" class="border p-2 rounded w-full">
                <option value="">All Staff</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['user_id'] ?>"
                        <?= ((string)$filter_user === (string)$u['user_id']) ? 'selected' : '' ?>>
                        <?= leaveH($u['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block font-semibold mb-1">Leave Type</label>
            <select name="leave_type" class="border p-2 rounded w-full">
                <option value="">All Types</option>
                <?php foreach ($leaveTypes as $type): ?>
                    <option value="<?= leaveH($type) ?>"
                        <?= $filter_type === $type ? 'selected' : '' ?>>
                        <?= leaveH($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">
                Filter
            </button>
            <a href="leaves_request.php"
               class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400 w-full text-center">
                Reset
            </a>
        </div>
    </form>

    <!-- HISTORICAL LEAVE ENTRY -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <div>
                <h2 class="text-xl font-bold text-purple-700">Record Historical Leave</h2>
                <p class="text-sm text-gray-600">
                    Record leave taken before the system was introduced.
                </p>
            </div>
            <span class="text-sm bg-purple-100 text-purple-800 px-3 py-1 rounded">
                Historical records
            </span>
        </div>

        <form method="POST" action="update_leave.php"
              class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <input type="hidden" name="action" value="record_historical">

            <div>
                <label class="block font-semibold mb-1">Staff Member</label>
                <select name="user_id" required class="border p-2 rounded w-full">
                    <option value="">Select Staff</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['user_id'] ?>">
                            <?= leaveH($u['full_name']) ?>
                            <?= !empty($u['national_id']) ? ' - ' . leaveH($u['national_id']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block font-semibold mb-1">Leave Type</label>
                <select name="leave_type" required class="border p-2 rounded w-full">
                    <option value="">Select Leave Type</option>
                    <?php foreach ($leaveTypes as $type): ?>
                        <option value="<?= leaveH($type) ?>"><?= leaveH($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block font-semibold mb-1">Start Date</label>
                <input type="date" name="start_date" required
                       class="border p-2 rounded w-full">
            </div>

            <div>
                <label class="block font-semibold mb-1">End Date</label>
                <input type="date" name="end_date" required
                       class="border p-2 rounded w-full">
            </div>

            <div>
                <label class="block font-semibold mb-1">Description / Reference</label>
                <input type="text" name="description" maxlength="255"
                       placeholder="e.g. Leave taken before system adoption"
                       class="border p-2 rounded w-full">
            </div>

            <div class="flex items-end">
                <button type="submit"
                        class="bg-purple-600 text-white px-5 py-2 rounded hover:bg-purple-700 w-full">
                    Add Historical Leave
                </button>
            </div>
        </form>
    </div>

    <!-- INDIVIDUAL SUMMARY -->
    <?php if (!empty($summary)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded mb-6">
            <h2 class="text-lg font-bold text-blue-800">
                <?= leaveH($selectedUserName) ?> —
                <?= leaveH($filter_type) ?> Leave (<?= $currentYear ?>)
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-3">

                <div class="bg-white rounded p-3 shadow-sm">
                    <p class="text-sm text-gray-600">Annual Entitlement</p>
                    <p class="text-xl font-bold"><?= $summary['entitled'] ?> days</p>
                </div>

                <div class="bg-white rounded p-3 shadow-sm">
                    <p class="text-sm text-gray-600">Historical Leave</p>
                    <p class="text-xl font-bold text-purple-700">
                        <?= $summary['historical'] ?> days
                    </p>
                </div>

                <div class="bg-white rounded p-3 shadow-sm">
                    <p class="text-sm text-gray-600">Approved System Leave</p>
                    <p class="text-xl font-bold text-orange-600">
                        <?= $summary['approved'] ?> days
                    </p>
                </div>

                <div class="bg-white rounded p-3 shadow-sm">
                    <p class="text-sm text-gray-600">Remaining Balance</p>
                    <p class="text-xl font-bold <?= $summary['remaining'] < 0 ? 'text-red-700' : 'text-green-700' ?>">
                        <?= $summary['remaining'] ?> days
                    </p>
                </div>
            </div>
        </div>
    <?php elseif ($filter_user !== '' && $filter_type !== ''): ?>
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 rounded p-4 mb-6">
            No entitlement has been configured for the selected leave type.
        </div>
    <?php endif; ?>

    <!-- LEAVE REQUESTS TABLE -->
    <div class="bg-white shadow rounded-lg p-6 table-wrap mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Leave Requests</h2>

        <table class="w-full border border-gray-300 text-xs">
            <thead class="bg-gray-200 text-gray-700 uppercase">
                <tr class="text-center">
                    <th class="border px-3 py-3">#</th>
                    <th class="border px-3 py-3">Staff Name</th>
                    <th class="border px-3 py-3">National ID</th>
                    <th class="border px-3 py-3">Leave Type</th>
                    <th class="border px-3 py-3">Description</th>
                    <th class="border px-3 py-3">Start Date</th>
                    <th class="border px-3 py-3">End Date</th>
                    <th class="border px-3 py-3">Requested Days</th>
                    <th class="border px-3 py-3">Entitlement</th>
                    <th class="border px-3 py-3">Used Before Request</th>
                    <th class="border px-3 py-3">Balance Before</th>
                    <th class="border px-3 py-3">Balance If Approved</th>
                    <th class="border px-3 py-3">Final Status</th>
                    <th class="border px-3 py-3">HR Decision</th>
                    <th class="border px-3 py-3">HR Approver</th>
                    <th class="border px-3 py-3">HR Date</th>
                    <th class="border px-3 py-3">HR Remarks</th>
                    <th class="border px-3 py-3">TD Decision</th>
                    <th class="border px-3 py-3">TD Approver</th>
                    <th class="border px-3 py-3">TD Date</th>
                    <th class="border px-3 py-3">TD Remarks</th>
                    <th class="border px-3 py-3">Actions</th>
                </tr>
            </thead>

            <tbody class="text-gray-700">

            <?php if ($leaves): ?>
                <?php $count = 1; ?>

                <?php foreach ($leaves as $leave): ?>
                    <?php
                    $leaveType = $leave['leave_type'];
                    $leaveYear = (int)date('Y', strtotime($leave['start_date']));
                    $key = $leave['user_id'] . '|' . $leaveType . '|' . $leaveYear;

                    $entitled = $entitlements[$leaveType] ?? null;
                    $requestedDays = leaveDateDays($leave['start_date'], $leave['end_date']);

                    $approvedIncluding = $approvedTotals[$key] ?? 0;
                    $historical = $historicalTotals[$key] ?? 0;

                    $approvedBefore = $approvedIncluding;

                    if ($leave['status'] === 'Approved') {
                        $approvedBefore = max(0, $approvedIncluding - $requestedDays);
                    }

                    $usedBefore = $historical + $approvedBefore;

                    $balanceBefore = $entitled !== null
                        ? $entitled - $usedBefore
                        : null;

                    $balanceAfter = $balanceBefore !== null
                        ? $balanceBefore - $requestedDays
                        : null;

                    $status = $leave['status'] ?? 'Pending';

                    $statusClass = 'text-yellow-700';
                    if ($status === 'Approved') $statusClass = 'text-green-700';
                    if ($status === 'Denied') $statusClass = 'text-red-700';

                    $hrStatus = $leave['hr_status'] ?? 'Pending';
                    $tdStatus = $leave['td_status'] ?? 'Waiting';

                    $hrName = $leave['hr_approved_by_name'] ?? '';
                    $tdName = $leave['td_approved_by_name'] ?? '';

                    $tdCanDecide = ($hrStatus === 'Approved');
                    ?>

                    <tr class="<?= ($count % 2 === 0) ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">

                        <td class="border px-3 py-3 text-center"><?= $count++ ?></td>
                        <td class="border px-3 py-3"><?= leaveH($leave['full_name']) ?></td>
                        <td class="border px-3 py-3"><?= leaveH($leave['national_id']) ?></td>
                        <td class="border px-3 py-3"><?= leaveH($leaveType) ?></td>
                        <td class="border px-3 py-3"><?= leaveH($leave['description']) ?></td>
                        <td class="border px-3 py-3 text-center"><?= leaveH($leave['start_date']) ?></td>
                        <td class="border px-3 py-3 text-center"><?= leaveH($leave['end_date']) ?></td>

                        <td class="border px-3 py-3 text-center font-bold text-blue-700">
                            <?= $requestedDays ?> days
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= $entitled !== null ? $entitled . ' days' : 'Not configured' ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= $entitled !== null ? $usedBefore . ' days' : '—' ?>
                        </td>

                        <td class="border px-3 py-3 text-center font-semibold">
                            <?= $balanceBefore !== null ? $balanceBefore . ' days' : '—' ?>
                        </td>

                        <td class="border px-3 py-3 text-center <?= ($balanceAfter !== null && $balanceAfter < 0) ? 'balance-negative' : 'balance-positive' ?>">
                            <?php if ($balanceAfter !== null): ?>
                                <?= $balanceAfter ?> days
                                <?php if ($balanceAfter < 0): ?>
                                    <div class="text-red-600">Exceeds balance</div>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td class="border px-3 py-3 text-center font-bold <?= $statusClass ?>">
                            <?= leaveH($status) ?>
                        </td>

                        <td class="border px-3 py-3 text-center font-semibold">
                            <?= leaveH($hrStatus) ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= $hrName !== '' ? leaveH($hrName) : '—' ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= !empty($leave['hr_approval_date']) ? leaveH($leave['hr_approval_date']) : '—' ?>
                        </td>

                        <td class="border px-3 py-3">
                            <?= !empty($leave['hr_remarks']) ? leaveH($leave['hr_remarks']) : '—' ?>
                        </td>

                        <td class="border px-3 py-3 text-center font-semibold">
                            <?= leaveH($tdStatus) ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= $tdName !== '' ? leaveH($tdName) : '—' ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= !empty($leave['td_approval_date']) ? leaveH($leave['td_approval_date']) : '—' ?>
                        </td>

                        <td class="border px-3 py-3">
                            <?= !empty($leave['td_remarks']) ? leaveH($leave['td_remarks']) : '—' ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <div class="flex flex-col gap-2 min-w-28">

                                <button type="button"
                                    class="bg-gray-600 text-white px-2 py-2 rounded hover:bg-gray-700"
                                    data-id="<?= (int)$leave['leave_id'] ?>"
                                    data-start="<?= leaveH($leave['start_date']) ?>"
                                    data-end="<?= leaveH($leave['end_date']) ?>"
                                    onclick="openDateModal(this)">
                                    Edit Dates
                                </button>

                                <button type="button"
                                    class="bg-blue-600 text-white px-2 py-2 rounded hover:bg-blue-700"
                                    data-id="<?= (int)$leave['leave_id'] ?>"
                                    onclick="openApprovalModal(this, 'hr_decision')">
                                    HR Decision
                                </button>

                                <button type="button"
                                    class="bg-purple-600 text-white px-2 py-2 rounded hover:bg-purple-700"
                                    <?= !$tdCanDecide ? 'disabled title="Waiting for HR approval"' : '' ?>
                                    data-id="<?= (int)$leave['leave_id'] ?>"
                                    data-hr-name="<?= leaveH($hrName) ?>"
                                    data-hr-date="<?= leaveH($leave['hr_approval_date'] ?? '') ?>"
                                    data-hr-remarks="<?= leaveH($leave['hr_remarks'] ?? '') ?>"
                                    onclick="openApprovalModal(this, 'td_decision')"
                                    style="<?= !$tdCanDecide ? 'opacity:.45;cursor:not-allowed;' : '' ?>">
                                    TD Decision
                                </button>

                            </div>
                        </td>
                    </tr>

                <?php endforeach; ?>

            <?php else: ?>
                <tr>
                    <td colspan="22" class="text-center text-gray-500 py-6">
                        No leave requests found for the selected filters.
                    </td>
                </tr>
            <?php endif; ?>

            </tbody>
        </table>
    </div>

    <!-- HISTORICAL LEAVE REGISTER -->
    <div class="bg-white shadow rounded-lg p-6 table-wrap">
        <h2 class="text-xl font-bold text-purple-700 mb-1">
            Historical Leave Register — <?= $currentYear ?>
        </h2>
        <p class="text-sm text-gray-600 mb-4">
            These records are deducted from the relevant staff member's leave entitlement for the year in which the leave was taken.
        </p>

        <table class="w-full border border-gray-300 text-sm" style="min-width:900px;">
            <thead class="bg-purple-100 text-gray-700">
                <tr>
                    <th class="border px-3 py-3">#</th>
                    <th class="border px-3 py-3">Staff Member</th>
                    <th class="border px-3 py-3">Leave Type</th>
                    <th class="border px-3 py-3">Start Date</th>
                    <th class="border px-3 py-3">End Date</th>
                    <th class="border px-3 py-3">Days Taken</th>
                    <th class="border px-3 py-3">Description</th>
                    <th class="border px-3 py-3">Recorded By</th>
                    <th class="border px-3 py-3">Recorded On</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($historicalRecords): ?>
                    <?php $historyCount = 1; ?>
                    <?php foreach ($historicalRecords as $record): ?>
                        <tr class="<?= $historyCount % 2 === 0 ? 'bg-gray-50' : 'bg-white' ?>">
                            <td class="border px-3 py-2 text-center"><?= $historyCount++ ?></td>
                            <td class="border px-3 py-2"><?= leaveH($record['full_name']) ?></td>
                            <td class="border px-3 py-2"><?= leaveH($record['leave_type']) ?></td>
                            <td class="border px-3 py-2 text-center"><?= leaveH($record['start_date']) ?></td>
                            <td class="border px-3 py-2 text-center"><?= leaveH($record['end_date']) ?></td>
                            <td class="border px-3 py-2 text-center font-bold"><?= (int)$record['total_days'] ?></td>
                            <td class="border px-3 py-2"><?= leaveH($record['description']) ?></td>
                            <td class="border px-3 py-2"><?= leaveH($record['recorder_name'] ?? '—') ?></td>
                            <td class="border px-3 py-2"><?= leaveH($record['recorded_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-gray-500 py-5">
                            No historical leave records found for <?= $currentYear ?>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- EDIT DATES MODAL -->
<div id="dateModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center p-4 modal-overlay"
     onclick="if(event.target === this) closeDateModal();">

    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-5">
            <h2 class="text-xl font-bold text-blue-700">Edit Leave Dates</h2>
            <button type="button" onclick="closeDateModal()" class="text-gray-500 text-2xl">&times;</button>
        </div>

        <form method="POST" action="update_leave.php">
            <input type="hidden" name="action" value="update_dates">
            <input type="hidden" name="leave_id" id="date_leave_id">

            <div class="mb-4">
                <label class="block font-semibold mb-1">Start Date</label>
                <input type="date" name="start_date" id="date_start"
                       required class="border p-2 rounded w-full">
            </div>

            <div class="mb-5">
                <label class="block font-semibold mb-1">End Date</label>
                <input type="date" name="end_date" id="date_end"
                       required class="border p-2 rounded w-full">
            </div>

            <p class="text-sm text-orange-700 bg-orange-50 p-3 rounded mb-4">
                Changing the dates resets HR and TD approvals. The request will need to go through both stages again.
            </p>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeDateModal()"
                        class="bg-gray-300 px-4 py-2 rounded">Cancel</button>
                <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded">Save Dates</button>
            </div>
        </form>
    </div>
</div>

<!-- HR / TD APPROVAL MODAL -->
<div id="approvalModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center p-4 modal-overlay"
     onclick="if(event.target === this) closeApprovalModal();">

    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-lg max-h-screen overflow-y-auto">

        <div class="flex justify-between items-center mb-5">
            <h2 id="approvalTitle" class="text-xl font-bold text-blue-700">
                Leave Approval
            </h2>
            <button type="button" onclick="closeApprovalModal()"
                    class="text-gray-500 text-2xl">&times;</button>
        </div>

        <div id="hrInformation"
             class="hidden bg-blue-50 border border-blue-200 rounded p-3 mb-4 text-sm">
            <h3 class="font-bold text-blue-800 mb-2">HR Approval Information</h3>
            <p><strong>HR Approver:</strong> <span id="displayHrName"></span></p>
            <p><strong>HR Approval Date:</strong> <span id="displayHrDate"></span></p>
            <p class="mt-2"><strong>HR Remarks:</strong></p>
            <p id="displayHrRemarks" class="whitespace-pre-wrap"></p>
        </div>

        <form method="POST" action="update_leave.php">
            <input type="hidden" name="action" id="approval_action">
            <input type="hidden" name="leave_id" id="approval_leave_id">

            <div class="mb-4">
                <label class="block font-semibold mb-1">Decision</label>
                <select name="decision" required class="border p-2 rounded w-full">
                    <option value="">Select Decision</option>
                    <option value="Approved">Approve</option>
                    <option value="Denied">Reject</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block font-semibold mb-1">Approver Full Name</label>
                <input type="text" name="approver_name" required maxlength="150"
                       placeholder="Enter your full name"
                       class="border p-2 rounded w-full">
            </div>

            <div class="mb-5">
                <label class="block font-semibold mb-1">Remarks</label>
                <textarea name="remarks" rows="4" maxlength="2000"
                          placeholder="Enter comments or reasons for your decision"
                          class="border p-2 rounded w-full"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeApprovalModal()"
                        class="bg-gray-300 px-4 py-2 rounded">Cancel</button>
                <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Save Decision
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDateModal(button) {
    document.getElementById('date_leave_id').value = button.dataset.id;
    document.getElementById('date_start').value = button.dataset.start;
    document.getElementById('date_end').value = button.dataset.end;

    const modal = document.getElementById('dateModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeDateModal() {
    const modal = document.getElementById('dateModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

function openApprovalModal(button, action) {
    document.getElementById('approval_action').value = action;
    document.getElementById('approval_leave_id').value = button.dataset.id;

    const title = document.getElementById('approvalTitle');
    const hrInfo = document.getElementById('hrInformation');

    if (action === 'hr_decision') {
        title.textContent = 'HR Leave Decision';
        hrInfo.classList.add('hidden');
    } else {
        title.textContent = 'TD Leave Decision';
        hrInfo.classList.remove('hidden');

        document.getElementById('displayHrName').textContent =
            button.dataset.hrName || 'Not provided';

        document.getElementById('displayHrDate').textContent =
            button.dataset.hrDate || 'Not provided';

        document.getElementById('displayHrRemarks').textContent =
            button.dataset.hrRemarks || 'No remarks provided';
    }

    const modal = document.getElementById('approvalModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeApprovalModal() {
    const modal = document.getElementById('approvalModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDateModal();
        closeApprovalModal();
    }
});
</script>

</body>
</html>
