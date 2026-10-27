
<?php
session_start();
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// -------------------- CONFIGURATION --------------------
$currentYear = (int)date('Y');

$entitlements = [
    'Annual'    => 21,
    'Sick'      => 30,
    'Paternity' => 14,
    'Maternity' => 90
];

// -------------------- HELPER --------------------
function leaveH($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// -------------------- FILTER VALUES --------------------
$filter_user = $_GET['user_id'] ?? '';
$filter_type = $_GET['leave_type'] ?? '';
$from_date   = $_GET['from_date'] ?? '';
$to_date     = $_GET['to_date'] ?? '';

$where = [];
$params = [];
$bindTypes = '';

// -------------------- FETCH LEAVE REQUESTS --------------------
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

if ($filter_type !== '') {
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
$leavesResult = $stmt->get_result();
$leaves = $leavesResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -------------------- FETCH STAFF FOR FILTER --------------------
$usersResult = $conn->query("
    SELECT u.user_id, u.full_name, u.national_id
    FROM users u
    JOIN groups g ON u.group_id = g.group_id
    WHERE LOWER(g.group_name) = 'staff'
    ORDER BY u.full_name ASC
");

$users = $usersResult->fetch_all(MYSQLI_ASSOC);

// -------------------- FETCH LEAVE TYPES --------------------
$typesResult = $conn->query("
    SELECT DISTINCT leave_type
    FROM leaves
    WHERE leave_type IS NOT NULL
      AND leave_type <> ''
    ORDER BY leave_type ASC
");

$types_res = $typesResult->fetch_all(MYSQLI_ASSOC);

// -------------------- FETCH APPROVED DAYS --------------------
// Calculate approved leave days by staff member and leave type.
// The calculation uses the start year, consistent with the existing system.
$approvedTotals = [];

$balanceStmt = $conn->prepare("
    SELECT
        user_id,
        leave_type,
        COALESCE(
            SUM(DATEDIFF(end_date, start_date) + 1), 0
        ) AS days_taken
    FROM leaves
    WHERE status = 'Approved'
      AND YEAR(start_date) = ?
    GROUP BY user_id, leave_type
");

$balanceStmt->bind_param('i', $currentYear);
$balanceStmt->execute();
$balanceResult = $balanceStmt->get_result();

while ($row = $balanceResult->fetch_assoc()) {
    $key = $row['user_id'] . '|' . $row['leave_type'];
    $approvedTotals[$key] = (int)$row['days_taken'];
}

$balanceStmt->close();

// -------------------- LEAVE SUMMARY --------------------
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
    $filter_type !== ''
) {
    $userId = (int)$filter_user;
    $entitled = $entitlements[$filter_type] ?? null;
    $key = $userId . '|' . $filter_type;
    $taken = $approvedTotals[$key] ?? 0;

    if ($entitled !== null) {
        $summary = [
            'entitled'  => $entitled,
            'taken'     => $taken,
            'remaining' => max(0, $entitled - $taken)
        ];
    }
}
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
        body {
            background: #f3f4f6;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            min-width: 1500px;
        }

        th {
            white-space: nowrap;
        }

        .balance-negative {
            color: #b91c1c;
            font-weight: 700;
        }

        .balance-positive {
            color: #047857;
            font-weight: 700;
        }
    </style>
</head>

<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-full mx-auto">

    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-blue-700">
                All Leave Requests
            </h1>
            <p class="text-gray-600 mt-1">
                Review leave balances, approval status and approving administrator.
            </p>
        </div>

        <a
            href="download_leaves_requests.php?<?= leaveH(http_build_query($_GET)) ?>"
            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
        >
            Download Leave Requests
        </a>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
        <div class="bg-green-100 border border-green-300 text-green-800 rounded p-3 mb-5">
            Leave request updated successfully.
        </div>
    <?php endif; ?>

    <!-- FILTERS -->
    <form
        method="GET"
        class="bg-white shadow rounded-lg p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-4 items-end"
    >
        <div>
            <label class="block font-semibold mb-1">From Date</label>
            <input
                type="date"
                name="from_date"
                value="<?= leaveH($from_date) ?>"
                class="border p-2 rounded w-full"
            >
        </div>

        <div>
            <label class="block font-semibold mb-1">To Date</label>
            <input
                type="date"
                name="to_date"
                value="<?= leaveH($to_date) ?>"
                class="border p-2 rounded w-full"
            >
        </div>

        <div>
            <label class="block font-semibold mb-1">Staff Member</label>
            <select name="user_id" class="border p-2 rounded w-full">
                <option value="">All Staff</option>

                <?php foreach ($users as $u): ?>
                    <option
                        value="<?= (int)$u['user_id'] ?>"
                        <?= ((string)$filter_user === (string)$u['user_id']) ? 'selected' : '' ?>
                    >
                        <?= leaveH($u['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block font-semibold mb-1">Leave Type</label>
            <select name="leave_type" class="border p-2 rounded w-full">
                <option value="">All Types</option>

                <?php foreach ($types_res as $t): ?>
                    <option
                        value="<?= leaveH($t['leave_type']) ?>"
                        <?= ($filter_type === $t['leave_type']) ? 'selected' : '' ?>
                    >
                        <?= leaveH($t['leave_type']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex gap-2">
            <button
                type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full"
            >
                Filter
            </button>

            <a
                href="leaves_request.php"
                class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400 w-full text-center"
            >
                Reset
            </a>
        </div>
    </form>

    <!-- INDIVIDUAL LEAVE SUMMARY -->
    <?php if (!empty($summary)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded mb-6">
            <h2 class="text-lg font-bold text-blue-800">
                <?= leaveH($selectedUserName) ?> —
                <?= leaveH($filter_type) ?> Leave
                (<?= $currentYear ?>)
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-3">
                <div class="bg-white rounded p-3 shadow-sm">
                    <p class="text-sm text-gray-600">Annual Entitlement</p>
                    <p class="text-xl font-bold">
                        <?= $summary['entitled'] ?> days
                    </p>
                </div>

                <div class="bg-white rounded p-3 shadow-sm">
                    <p class="text-sm text-gray-600">Approved Days Used</p>
                    <p class="text-xl font-bold text-orange-600">
                        <?= $summary['taken'] ?> days
                    </p>
                </div>

                <div class="bg-white rounded p-3 shadow-sm">
                    <p class="text-sm text-gray-600">Remaining Balance</p>
                    <p class="text-xl font-bold text-green-700">
                        <?= $summary['remaining'] ?> days
                    </p>
                </div>
            </div>
        </div>
    <?php elseif ($filter_user !== '' && $filter_type !== ''): ?>
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 rounded p-4 mb-6">
            No entitlement has been configured for the selected leave type.
            Please update the entitlement settings in this file.
        </div>
    <?php endif; ?>

    <!-- LEAVE REQUESTS TABLE -->
    <div class="bg-white shadow rounded-lg p-6 table-wrap">

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
                    <th class="border px-3 py-3">Balance Before Request</th>
                    <th class="border px-3 py-3">Balance If Approved</th>
                    <th class="border px-3 py-3">Status</th>
                    <th class="border px-3 py-3">Approved By</th>
                    <th class="border px-3 py-3">Approval Date</th>
                    <th class="border px-3 py-3">Action</th>
                </tr>
            </thead>

            <tbody class="text-gray-700">

            <?php if (!empty($leaves)): ?>
                <?php $count = 1; ?>

                <?php foreach ($leaves as $leave): ?>
                    <?php
                        $leaveType = $leave['leave_type'] ?? '';
                        $leaveKey = $leave['user_id'] . '|' . $leaveType;

                        $entitled = $entitlements[$leaveType] ?? null;

                        $startTimestamp = strtotime($leave['start_date'] ?? '');
                        $endTimestamp = strtotime($leave['end_date'] ?? '');

                        $requestedDays = (
                            $startTimestamp !== false &&
                            $endTimestamp !== false &&
                            $endTimestamp >= $startTimestamp
                        )
                            ? (int)(($endTimestamp - $startTimestamp) / 86400) + 1
                            : (int)($leave['total_days'] ?? 0);

                        $approvedDaysIncludingRequest = $approvedTotals[$leaveKey] ?? 0;

                        // For an already-approved request, exclude its own
                        // days so the displayed "before request" balance
                        // reflects the position before that request was approved.
                        if (($leave['status'] ?? '') === 'Approved') {
                            $approvedDaysBefore = max(
                                0,
                                $approvedDaysIncludingRequest - $requestedDays
                            );
                        } else {
                            $approvedDaysBefore = $approvedDaysIncludingRequest;
                        }

                        $balanceBefore = $entitled !== null
                            ? max(0, $entitled - $approvedDaysBefore)
                            : null;

                        $balanceAfter = $balanceBefore !== null
                            ? $balanceBefore - $requestedDays
                            : null;

                        $statusClass = 'text-yellow-600';

                        if (($leave['status'] ?? '') === 'Approved') {
                            $statusClass = 'text-green-600';
                        } elseif (($leave['status'] ?? '') === 'Denied') {
                            $statusClass = 'text-red-600';
                        }
                    ?>

                    <tr class="<?= ($count % 2 === 0) ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">

                        <td class="border px-3 py-3 text-center">
                            <?= $count++ ?>
                        </td>

                        <td class="border px-3 py-3">
                            <?= leaveH($leave['full_name']) ?>
                        </td>

                        <td class="border px-3 py-3">
                            <?= leaveH($leave['national_id']) ?>
                        </td>

                        <td class="border px-3 py-3">
                            <?= leaveH($leaveType) ?>
                        </td>

                        <td class="border px-3 py-3">
                            <?= leaveH($leave['description'] ?? '') ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= leaveH($leave['start_date']) ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= leaveH($leave['end_date']) ?>
                        </td>

                        <td class="border px-3 py-3 text-center font-bold text-blue-700">
                            <?= $requestedDays ?> days
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= $entitled !== null
                                ? $entitled . ' days'
                                : 'Not configured' ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= $entitled !== null
                                ? $approvedDaysBefore . ' days'
                                : '—' ?>
                        </td>

                        <td class="border px-3 py-3 text-center font-semibold">
                            <?= $balanceBefore !== null
                                ? $balanceBefore . ' days'
                                : '—' ?>
                        </td>

                        <td class="border px-3 py-3 text-center <?= (
                            $balanceAfter !== null && $balanceAfter < 0
                        ) ? 'balance-negative' : 'balance-positive' ?>">
                            <?php if ($balanceAfter !== null): ?>
                                <?= $balanceAfter ?> days

                                <?php if ($balanceAfter < 0): ?>
                                    <div class="text-red-600 mt-1">
                                        Exceeds balance
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td class="border px-3 py-3 text-center font-bold <?= $statusClass ?>">
                            <?= leaveH($leave['status']) ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= !empty($leave['approver_name'])
                                ? leaveH($leave['approver_name'])
                                : '—' ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <?= !empty($leave['approval_date'])
                                ? leaveH($leave['approval_date'])
                                : '—' ?>
                        </td>

                        <td class="border px-3 py-3 text-center">
                            <button
    type="button"
    class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700"
    onclick='openModal(
        <?= (int)$leave["leave_id"] ?>,
        <?= json_encode($leave["start_date"]) ?>,
        <?= json_encode($leave["end_date"]) ?>,
        <?= json_encode($leave["status"]) ?>,
        <?= json_encode($leave["approver_name"] ?? "") ?>
    )'
>
    Update
</button>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>
                <tr>
                    <td colspan="16" class="text-center text-gray-500 py-6">
                        No leave requests found for the selected filters.
                    </td>
                </tr>
            <?php endif; ?>

            </tbody>
        </table>
    </div>
</div>

<!-- UPDATE LEAVE REQUEST MODAL -->
<div
    id="updateModal"
    class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center p-4"
    style="z-index: 99999;"
    role="dialog"
    aria-modal="true"
    aria-labelledby="updateModalTitle"
    onclick="if (event.target === this) closeModal();"
>
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">

        <div class="flex justify-between items-center mb-5">
            <h2 id="updateModalTitle" class="text-xl font-bold text-blue-700">
                Update Leave Request
            </h2>

            <button
                type="button"
                onclick="closeModal()"
                class="text-gray-500 text-2xl leading-none"
                aria-label="Close"
            >
                &times;
            </button>
        </div>

        <form method="POST" action="update_leave.php">

            <input type="hidden" name="leave_id" id="modal_leave_id">

            <div class="mb-4">
                <label for="modal_start_date" class="block font-semibold mb-1">
                    Start Date
                </label>
                <input
                    type="date"
                    name="start_date"
                    id="modal_start_date"
                    required
                    class="border p-2 rounded w-full"
                >
            </div>

            <div class="mb-4">
                <label for="modal_end_date" class="block font-semibold mb-1">
                    End Date
                </label>
                <input
                    type="date"
                    name="end_date"
                    id="modal_end_date"
                    required
                    class="border p-2 rounded w-full"
                >
            </div>

            <div class="mb-6">
                <label for="modal_status" class="block font-semibold mb-1">
                    Status
                </label>
                <select
                    name="status"
                    id="modal_status"
                    required
                    class="border p-2 rounded w-full"
                >
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Denied">Denied</option>
                </select>
            </div>
<div class="mb-4">
    <label for="modal_approved_by" class="block font-semibold mb-1">
        Approved By
    </label>

    <input
        type="text"
        name="approved_by"
        id="modal_approved_by"
        placeholder="Enter the name of the approving person"
        maxlength="150"
        class="border p-2 rounded w-full"
    >

    <p class="text-xs text-gray-500 mt-1">
        Enter the full name of the person who approved the leave.
    </p>
</div>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    onclick="closeModal()"
                    class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                >
                    Save Changes
                </button>
            </div>

        </form>
    </div>
</div>

<script>
function openModal(leaveId, startDate, endDate, status, approvedBy) {
    const modal = document.getElementById('updateModal');

    document.getElementById('modal_leave_id').value = leaveId;
    document.getElementById('modal_start_date').value = startDate || '';
    document.getElementById('modal_end_date').value = endDate || '';
    document.getElementById('modal_status').value = status || 'Pending';
    document.getElementById('modal_approved_by').value = approvedBy || '';

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    document.body.style.overflow = 'hidden';
}function openModal(leaveId, startDate, endDate, status, approvedBy) {
    const modal = document.getElementById('updateModal');

    document.getElementById('modal_leave_id').value = leaveId;
    document.getElementById('modal_start_date').value = startDate || '';
    document.getElementById('modal_end_date').value = endDate || '';
    document.getElementById('modal_status').value = status || 'Pending';
    document.getElementById('modal_approved_by').value = approvedBy || '';

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('updateModal');

    modal.classList.add('hidden');
    modal.classList.remove('flex');

    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>

</body>
</html>
