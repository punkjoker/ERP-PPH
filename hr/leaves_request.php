<?php
session_start();
require 'db_con.php';
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
if (empty($_SESSION['leave_upload_csrf'])) {
    $_SESSION['leave_upload_csrf'] = bin2hex(random_bytes(32));
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$currentYear = (int)date('Y');
$entitlements = ['Annual' => 21, 'Sick' => 30, 'Paternity' => 14, 'Maternity' => 90];
$leaveTypes = ['Annual', 'Sick', 'Paternity', 'Maternity'];

function leaveH($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function leaveDateDays($start, $end): int {
    if (!$start || !$end) return 0;
    $startTime = strtotime($start);
    $endTime = strtotime($end);
    if ($startTime === false || $endTime === false || $endTime < $startTime) return 0;
    return (int)(($endTime - $startTime) / 86400) + 1;
}

$filter_user = $_GET['user_id'] ?? '';
$filter_type = $_GET['leave_type'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$where = [];
$params = [];
$bindTypes = '';
$sql = "
    SELECT
        l.*,
        u.full_name,
        u.national_id
    FROM leaves l
    JOIN users u ON l.user_id = u.user_id
    WHERE u.group_id = 9
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
    if ($validFrom && $validTo &&
        $validFrom->format('Y-m-d') === $from_date &&
        $validTo->format('Y-m-d') === $to_date && $from_date <= $to_date) {
        $where[] = "l.start_date BETWEEN ? AND ?";
        $params[] = $from_date; $params[] = $to_date; $bindTypes .= 'ss';
    }
}
if ($where) $sql .= ' AND ' . implode(' AND ', $where);
$sql .= " ORDER BY l.start_date DESC, l.leave_id DESC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($bindTypes, ...$params);
$stmt->execute();
$leaves = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$usersResult = $conn->query("
    SELECT user_id, full_name, national_id
    FROM users
    WHERE group_id = 9
    ORDER BY full_name ASC
");

$users = $usersResult->fetch_all(MYSQLI_ASSOC);


$approvedTotals = [];
$balanceStmt = $conn->prepare("
    SELECT user_id, leave_type, YEAR(start_date) AS leave_year,
           SUM(DATEDIFF(end_date, start_date) + 1) AS days_taken
    FROM leaves WHERE status = 'Approved'
    GROUP BY user_id, leave_type, YEAR(start_date)
");
$balanceStmt->execute();
$balanceResult = $balanceStmt->get_result();
while ($row = $balanceResult->fetch_assoc()) {
    $key = $row['user_id'].'|'.$row['leave_type'].'|'.$row['leave_year'];
    $approvedTotals[$key] = (int)$row['days_taken'];
}
$balanceStmt->close();

$historicalTotals = [];
$historyStmt = $conn->prepare("
    SELECT user_id, leave_type, YEAR(start_date) AS leave_year, SUM(total_days) AS days_taken
    FROM historical_leaves
    GROUP BY user_id, leave_type, YEAR(start_date)
");
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
while ($row = $historyResult->fetch_assoc()) {
    $key = $row['user_id'].'|'.$row['leave_type'].'|'.$row['leave_year'];
    $historicalTotals[$key] = (int)$row['days_taken'];
}
$historyStmt->close();

$summary = [];
$selectedUserName = '';
if ($filter_user !== '') {
    foreach ($users as $u) {
        if ((string)$u['user_id'] === (string)$filter_user) {
            $selectedUserName = $u['full_name']; break;
        }
    }
}
if ($filter_user !== '' && ctype_digit((string)$filter_user) && in_array($filter_type, $leaveTypes, true)) {
    $userId = (int)$filter_user;
    $entitled = $entitlements[$filter_type] ?? null;
    $key = $userId.'|'.$filter_type.'|'.$currentYear;
    $approved = $approvedTotals[$key] ?? 0;
    $historical = $historicalTotals[$key] ?? 0;
    $taken = $approved + $historical;
    if ($entitled !== null) {
        $summary = [
            'entitled' => $entitled, 'approved' => $approved, 'historical' => $historical,
            'taken' => $taken, 'remaining' => $entitled - $taken
        ];
    }
}

$historySql = "
    SELECT h.*, u.full_name, recorder.full_name AS recorder_name
    FROM historical_leaves h
    JOIN users u ON h.user_id = u.user_id
    LEFT JOIN users recorder ON h.recorded_by = recorder.user_id
    WHERE YEAR(h.start_date) = ?
";
$historyParams = [$currentYear];
$historyTypes = 'i';
if ($filter_user !== '' && ctype_digit((string)$filter_user)) {
    $historySql .= " AND h.user_id = ?";
    $historyParams[] = (int)$filter_user; $historyTypes .= 'i';
}
if (in_array($filter_type, $leaveTypes, true)) {
    $historySql .= " AND h.leave_type = ?";
    $historyParams[] = $filter_type; $historyTypes .= 's';
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
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
body {
    background: #f3f4f6;
}

.table-wrap {
    overflow-x: auto;
}

table {
    min-width: 1350px;
    border-collapse: collapse;
}

th {
    white-space: nowrap;
    padding: 8px 6px !important;
    font-size: 11px;
}

td {
    padding: 6px !important;
    font-size: 11px;
    vertical-align: middle;
}

.balance-negative {
    color: #b91c1c;
    font-weight: 700;
}

.balance-positive {
    color: #047857;
    font-weight: 700;
}

.modal-overlay {
    z-index: 99999;
}

/* Row actions dropdown */
.actions-dropdown {
    position: relative;
    display: inline-block;
}

.actions-dropdown summary {
    list-style: none;
    cursor: pointer;
    user-select: none;
}

.actions-dropdown summary::-webkit-details-marker {
    display: none;
}

.actions-menu {
    position: absolute;
    right: 0;
    top: calc(100% + 5px);
    min-width: 190px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(0,0,0,.15);
    padding: 5px;
    z-index: 1000;
}

.actions-menu button,
.actions-menu a {
    display: block;
    width: 100%;
    text-align: left;
    padding: 9px 10px;
    border-radius: 5px;
    font-size: 12px;
    color: #374151;
    background: white;
    border: none;
    cursor: pointer;
    text-decoration: none;
}

.actions-menu button:hover,
.actions-menu a:hover {
    background: #f3f4f6;
}

.actions-menu button:disabled {
    opacity: .4;
    cursor: not-allowed;
}
</style>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>
<div class="ml-64 p-6 max-w-full mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-blue-700">All Leave Requests</h1>
            <p class="text-gray-600 mt-1">Monitor department decisions, final TD approvals, leave balances and historical leave.</p>
        </div>
        <a href="download_leaves_requests.php?<?= leaveH(http_build_query($_GET)) ?>"
           class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Download Leave Requests</a>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 border border-green-300 text-green-800 rounded p-3 mb-5">
        <?php
        $messages = [
            'updated' => 'Leave dates updated. Department and TD approval stages have been reset.',
            'td_updated' => 'TD final decision saved successfully.',
            'historical_added' => 'Historical leave record added successfully.',
            'supervisor_updated' => 'Department decision saved successfully.'
        ];
        echo leaveH($messages[$_GET['success']] ?? 'Operation completed successfully.');
        ?>
    </div>
    <?php endif; ?>

    <form method="GET" class="bg-white shadow rounded-lg p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div><label class="block font-semibold mb-1">From Date</label>
            <input type="date" name="from_date" value="<?= leaveH($from_date) ?>" class="border p-2 rounded w-full"></div>
        <div><label class="block font-semibold mb-1">To Date</label>
            <input type="date" name="to_date" value="<?= leaveH($to_date) ?>" class="border p-2 rounded w-full"></div>
        <div><label class="block font-semibold mb-1">Staff Member</label>
            <select name="user_id" class="border p-2 rounded w-full"><option value="">All Staff</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['user_id'] ?>" <?= ((string)$filter_user === (string)$u['user_id']) ? 'selected' : '' ?>><?= leaveH($u['full_name']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><label class="block font-semibold mb-1">Leave Type</label>
            <select name="leave_type" class="border p-2 rounded w-full"><option value="">All Types</option>
                <?php foreach ($leaveTypes as $type): ?>
                <option value="<?= leaveH($type) ?>" <?= $filter_type === $type ? 'selected' : '' ?>><?= leaveH($type) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">Filter</button>
            <a href="leaves_request.php" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400 w-full text-center">Reset</a>
        </div>
    </form>

    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <div><h2 class="text-xl font-bold text-purple-700">Record Historical Leave</h2>
                <p class="text-sm text-gray-600">Record leave taken before the system was introduced.</p></div>
            <span class="text-sm bg-purple-100 text-purple-800 px-3 py-1 rounded">Historical records</span>
        </div>
        <form method="POST" action="update_leave.php" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input type="hidden" name="action" value="record_historical">
            <div><label class="block font-semibold mb-1">Staff Member</label>
                <select name="user_id" required class="border p-2 rounded w-full"><option value="">Select Staff</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['user_id'] ?>"><?= leaveH($u['full_name']) ?><?= !empty($u['national_id']) ? ' - '.leaveH($u['national_id']) : '' ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="block font-semibold mb-1">Leave Type</label>
                <select name="leave_type" required class="border p-2 rounded w-full"><option value="">Select Leave Type</option>
                    <?php foreach ($leaveTypes as $type): ?><option value="<?= leaveH($type) ?>"><?= leaveH($type) ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block font-semibold mb-1">Start Date</label><input type="date" name="start_date" required class="border p-2 rounded w-full"></div>
            <div><label class="block font-semibold mb-1">End Date</label><input type="date" name="end_date" required class="border p-2 rounded w-full"></div>
            <div><label class="block font-semibold mb-1">Description / Reference</label>
                <input type="text" name="description" maxlength="255" placeholder="e.g. Leave taken before system adoption" class="border p-2 rounded w-full"></div>
            <div class="flex items-end"><button type="submit" class="bg-purple-600 text-white px-5 py-2 rounded hover:bg-purple-700 w-full">Add Historical Leave</button></div>
        </form>
    </div>

    <?php if (!empty($summary)): ?>
    <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded mb-6">
        <h2 class="text-lg font-bold text-blue-800"><?= leaveH($selectedUserName) ?> — <?= leaveH($filter_type) ?> Leave (<?= $currentYear ?>)</h2>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-3">
            <div class="bg-white rounded p-3 shadow-sm"><p class="text-sm text-gray-600">Annual Entitlement</p><p class="text-xl font-bold"><?= $summary['entitled'] ?> days</p></div>
            <div class="bg-white rounded p-3 shadow-sm"><p class="text-sm text-gray-600">Historical Leave</p><p class="text-xl font-bold text-purple-700"><?= $summary['historical'] ?> days</p></div>
            <div class="bg-white rounded p-3 shadow-sm"><p class="text-sm text-gray-600">Approved System Leave</p><p class="text-xl font-bold text-orange-600"><?= $summary['approved'] ?> days</p></div>
            <div class="bg-white rounded p-3 shadow-sm"><p class="text-sm text-gray-600">Remaining Balance</p><p class="text-xl font-bold <?= $summary['remaining'] < 0 ? 'text-red-700' : 'text-green-700' ?>"><?= $summary['remaining'] ?> days</p></div>
        </div>
    </div>
    <?php elseif ($filter_user !== '' && $filter_type !== ''): ?>
    <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 rounded p-4 mb-6">No entitlement has been configured for the selected leave type.</div>
    <?php endif; ?>

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
    <th class="border px-3 py-3">Support Document</th>
    <th class="border px-3 py-3">Start Date</th>
    <th class="border px-3 py-3">End Date</th>
    <th class="border px-3 py-3">Requested Days</th>
    <th class="border px-3 py-3">Entitlement</th>
    <th class="border px-3 py-3">Used Before Request</th>
    <th class="border px-3 py-3">Balance Before</th>
    <th class="border px-3 py-3">Balance If Approved</th>
    <th class="border px-3 py-3">Final Status</th>
    <th class="border px-3 py-3">Department Decision</th>
    <th class="border px-3 py-3">TD Decision</th>
    <th class="border px-3 py-3">Actions</th>
</tr>
            </thead>
            <tbody class="text-gray-700">
            <?php if ($leaves): $count = 1; foreach ($leaves as $leave): ?>
                <?php
                $leaveType = $leave['leave_type'];
                $leaveYear = (int)date('Y', strtotime($leave['start_date']));
                $key = $leave['user_id'].'|'.$leaveType.'|'.$leaveYear;
                $entitled = $entitlements[$leaveType] ?? null;
                $requestedDays = leaveDateDays($leave['start_date'], $leave['end_date']);
                $approvedIncluding = $approvedTotals[$key] ?? 0;
                $historical = $historicalTotals[$key] ?? 0;
                $approvedBefore = $leave['status'] === 'Approved' ? max(0, $approvedIncluding - $requestedDays) : $approvedIncluding;
                $usedBefore = $historical + $approvedBefore;
                $balanceBefore = $entitled !== null ? $entitled - $usedBefore : null;
                $balanceAfter = $balanceBefore !== null ? $balanceBefore - $requestedDays : null;
                $status = $leave['status'] ?? 'Pending';
                $statusClass = $status === 'Approved' ? 'text-green-700' : ($status === 'Denied' ? 'text-red-700' : 'text-yellow-700');
                $supervisorStatus = $leave['supervisor_status'] ?? 'Pending';
         
                $tdStatus = $leave['td_status'] ?? 'Waiting';
                
             // TD can decide regardless of department status.
// Once TD has approved or rejected, the decision button is disabled.
$tdCanDecide = !in_array($tdStatus, ['Approved', 'Denied'], true);
?>

                <tr class="<?= ($count % 2 === 0) ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">
                    <td class="border px-3 py-3 text-center"><?= $count++ ?></td>
                    <td class="border px-3 py-3"><?= leaveH($leave['full_name']) ?></td>
                    <td class="border px-3 py-3"><?= leaveH($leave['national_id']) ?></td>
                    <td class="border px-3 py-3"><?= leaveH($leaveType) ?></td>
                    <td class="border px-3 py-3"><?= leaveH($leave['description']) ?></td>
                    <td class="border px-3 py-3 text-center">
    <?php if (!empty($leave['supporting_document'])): ?>
        <a href="view_support_document.php?leave_id=<?= (int)$leave['leave_id'] ?>"
           target="_blank"
           rel="noopener noreferrer"
           class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded font-semibold hover:bg-green-200">
            Attached – View
        </a>
    <?php else: ?>
        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded font-semibold">
            Not Attached
        </span>
    <?php endif; ?>
</td>
                    <td class="border px-3 py-3 text-center"><?= leaveH($leave['start_date']) ?></td>
                    <td class="border px-3 py-3 text-center"><?= leaveH($leave['end_date']) ?></td>
                    <td class="border px-3 py-3 text-center font-bold text-blue-700"><?= $requestedDays ?> days</td>
                    <td class="border px-3 py-3 text-center"><?= $entitled !== null ? $entitled.' days' : 'Not configured' ?></td>
                    <td class="border px-3 py-3 text-center"><?= $entitled !== null ? $usedBefore.' days' : '—' ?></td>
                    <td class="border px-3 py-3 text-center font-semibold"><?= $balanceBefore !== null ? $balanceBefore.' days' : '—' ?></td>
                    <td class="border px-3 py-3 text-center <?= ($balanceAfter !== null && $balanceAfter < 0) ? 'balance-negative' : 'balance-positive' ?>">
                        <?php if ($balanceAfter !== null): ?><?= $balanceAfter ?> days<?php if ($balanceAfter < 0): ?><div class="text-red-600">Exceeds balance</div><?php endif; ?><?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="border px-3 py-3 text-center font-bold <?= $statusClass ?>"><?= leaveH($status) ?></td>
                    <td class="border px-3 py-3 text-center font-semibold <?= $supervisorStatus === 'Approved' ? 'text-green-700' : ($supervisorStatus === 'Denied' ? 'text-red-700' : 'text-orange-700') ?>"><?= leaveH($supervisorStatus) ?></td>
                    
                    
                    <td class="border px-3 py-3 text-center font-semibold"><?= leaveH($tdStatus) ?></td>
                    
                    
        
                    <td class="border px-2 py-1 text-center">

    <details class="actions-dropdown">

        <summary class="bg-blue-600 text-white px-3 py-2 rounded-md hover:bg-blue-700 font-semibold">
            Actions <span class="ml-1">▼</span>
        </summary>

        <div class="actions-menu">

            <!-- Edit Leave Dates -->
            <button type="button"
                data-id="<?= (int)$leave['leave_id'] ?>"
                data-start="<?= leaveH($leave['start_date']) ?>"
                data-end="<?= leaveH($leave['end_date']) ?>"
                onclick="openDateModal(this)">
                <span class="text-gray-600">✏</span> Edit Leave Dates
            </button>

            <!-- Attach or Replace Document -->
            <button type="button"
                data-id="<?= (int)$leave['leave_id'] ?>"
                data-name="<?= leaveH($leave['full_name']) ?>"
                onclick="openDocumentModal(this)">
                <span class="text-blue-600">📎</span>
                <?= !empty($leave['supporting_document'])
                    ? 'Replace Document'
                    : 'Attach Document' ?>
            </button>

            <!-- View Supporting Document -->
            <?php if (!empty($leave['supporting_document'])): ?>
                <a href="view_support_document.php?leave_id=<?= (int)$leave['leave_id'] ?>"
                   target="_blank"
                   rel="noopener noreferrer">
                    <span class="text-green-600">📄</span>
                    View Supporting Document
                </a>
            <?php endif; ?>

            <div class="border-t border-gray-200 my-1"></div>

            <!-- TD Final Decision -->
            <!-- TD Final Decision -->
<button type="button"
    class="text-purple-700"
    <?= !$tdCanDecide ? 'disabled title="TD decision already completed"' : '' ?>
    data-id="<?= (int)$leave['leave_id'] ?>"
    onclick="openTdModal(this)">

    <span>✓</span> TD Final Decision

    <?php if (!$tdCanDecide): ?>
        <span class="text-xs text-gray-400">(Completed)</span>
    <?php endif; ?>

</button>

        </div>

    </details>

</td>

                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="17" class="text-center text-gray-500 py-6">No leave requests found for the selected filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bg-white shadow rounded-lg p-6 table-wrap">
        <h2 class="text-xl font-bold text-purple-700 mb-1">Historical Leave Register — <?= $currentYear ?></h2>
        <p class="text-sm text-gray-600 mb-4">These records are deducted from the relevant staff member's leave entitlement for the year in which the leave was taken.</p>
        <table class="w-full border border-gray-300 text-sm" style="min-width:900px;">
            <thead class="bg-purple-100 text-gray-700"><tr>
                <th class="border px-3 py-3">#</th><th class="border px-3 py-3">Staff Member</th>
                <th class="border px-3 py-3">Leave Type</th><th class="border px-3 py-3">Start Date</th>
                <th class="border px-3 py-3">End Date</th><th class="border px-3 py-3">Days Taken</th>
                <th class="border px-3 py-3">Description</th><th class="border px-3 py-3">Recorded By</th>
                <th class="border px-3 py-3">Recorded On</th>
            </tr></thead>
            <tbody>
            <?php if ($historicalRecords): $historyCount = 1; foreach ($historicalRecords as $record): ?>
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
            <?php endforeach; else: ?>
                <tr><td colspan="9" class="text-center text-gray-500 py-5">No historical leave records found for <?= $currentYear ?>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="dateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center p-4 modal-overlay" onclick="if(event.target===this)closeDateModal();">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-5"><h2 class="text-xl font-bold text-blue-700">Edit Leave Dates</h2>
            <button type="button" onclick="closeDateModal()" class="text-gray-500 text-2xl">&times;</button></div>
        <form method="POST" action="update_leave.php">
            <input type="hidden" name="action" value="update_dates"><input type="hidden" name="leave_id" id="date_leave_id">
            <div class="mb-4"><label class="block font-semibold mb-1">Start Date</label><input type="date" name="start_date" id="date_start" required class="border p-2 rounded w-full"></div>
            <div class="mb-5"><label class="block font-semibold mb-1">End Date</label><input type="date" name="end_date" id="date_end" required class="border p-2 rounded w-full"></div>
            <p class="text-sm text-orange-700 bg-orange-50 p-3 rounded mb-4">Changing dates resets the department and TD decisions. The request must go through approval again.</p>
            <div class="flex justify-end gap-2"><button type="button" onclick="closeDateModal()" class="bg-gray-300 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save Dates</button></div>
        </form>
    </div>
</div>

<div id="approvalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center p-4 modal-overlay" onclick="if(event.target===this)closeApprovalModal();">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-lg max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-5"><h2 class="text-xl font-bold text-purple-700">TD Final Leave Decision</h2>
            <button type="button" onclick="closeApprovalModal()" class="text-gray-500 text-2xl">&times;</button></div>
        <div class="bg-blue-50 border border-blue-200 rounded p-3 mb-4 text-sm">
            <h3 class="font-bold text-blue-800 mb-2">Department Supervisor Review</h3>
            <p><strong>Supervisor:</strong> <span id="displaySupervisorName"></span></p>
            <p><strong>Decision date:</strong> <span id="displaySupervisorDate"></span></p>
            <p class="mt-2"><strong>Remarks:</strong></p><p id="displaySupervisorRemarks" class="whitespace-pre-wrap"></p>
        </div>
        <form method="POST" action="update_leave.php">
            <input type="hidden" name="action" value="td_decision"><input type="hidden" name="leave_id" id="approval_leave_id">
            <div class="mb-4"><label class="block font-semibold mb-1">Decision</label>
                <select name="decision" required class="border p-2 rounded w-full"><option value="">Select Decision</option>
                    <option value="Approved">Approve</option><option value="Denied">Reject</option></select></div>
           <div class="mb-4">
    <label class="block font-semibold mb-1">TD Approver Full Name</label>
    <input
        type="text"
        name="approver_name"
        required
        maxlength="150"
        value="Julius Maingi"
        placeholder="Enter TD approver full name"
        class="border p-2 rounded w-full">
</div>
            <div class="mb-5"><label class="block font-semibold mb-1">Remarks</label>
                <textarea name="remarks" rows="4" maxlength="2000" placeholder="Enter comments or reasons for your decision" class="border p-2 rounded w-full"></textarea></div>
            <div class="flex justify-end gap-2"><button type="button" onclick="closeApprovalModal()" class="bg-gray-300 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Save TD Decision</button></div>
        </form>
    </div>
</div>
<div id="approvalModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center p-4 modal-overlay"
     onclick="if(event.target===this)closeApprovalModal();">

    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-lg">

        <div class="flex justify-between items-center mb-5">
            <h2 class="text-xl font-bold text-purple-700">
                TD Final Leave Decision
            </h2>

            <button type="button"
                    onclick="closeApprovalModal()"
                    class="text-gray-500 text-2xl">&times;</button>
        </div>

        <form method="POST" action="update_leave.php">

            <input type="hidden" name="action" value="td_decision">

            <input type="hidden"
                   name="leave_id"
                   id="approval_leave_id">

            <!-- Fixed TD approver name -->
            <input type="hidden"
                   name="approver_name"
                   value="Julius Maingi">

            <div class="mb-4">
                <label class="block font-semibold mb-1">
                    TD Approver
                </label>

                <input type="text"
                       value="Julius Maingi"
                       readonly
                       class="border p-2 rounded w-full bg-gray-100 font-semibold text-gray-700">
            </div>

            <div class="mb-4">
                <label class="block font-semibold mb-1">
                    Decision
                </label>

                <select name="decision" required
                        class="border p-2 rounded w-full">
                    <option value="">Select Decision</option>
                    <option value="Approved">Approve</option>
                    <option value="Denied">Reject</option>
                </select>
            </div>

            <div class="mb-5">
                <label class="block font-semibold mb-1">
                    Remarks
                </label>

                <textarea name="remarks"
                          rows="4"
                          maxlength="2000"
                          placeholder="Enter comments or reasons for your decision"
                          class="border p-2 rounded w-full"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button"
                        onclick="closeApprovalModal()"
                        class="bg-gray-300 px-4 py-2 rounded">
                    Cancel
                </button>

                <button type="submit"
                        class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                    Save TD Decision
                </button>
            </div>

        </form>
    </div>
</div>
<div id="documentModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center p-4 modal-overlay"
     onclick="if(event.target===this)closeDocumentModal();">

    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">

        <div class="flex justify-between items-center mb-5">
            <h2 class="text-xl font-bold text-blue-700">Attach Supporting Document</h2>
            <button type="button"
                    onclick="closeDocumentModal()"
                    class="text-gray-500 text-2xl">&times;</button>
        </div>

        <p class="text-sm text-gray-600 mb-4">
            Staff member: <strong id="documentStaffName"></strong>
        </p>

        <form method="POST"
              action="upload_support_document.php"
              enctype="multipart/form-data">

            <input type="hidden" name="leave_id" id="document_leave_id">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= leaveH($_SESSION['leave_upload_csrf']) ?>">

            <div class="mb-4">
                <label class="block font-semibold mb-2">
                    Select Supporting Document
                </label>

                <input type="file"
                       name="supporting_document"
                       accept=".pdf,.jpg,.jpeg,.png"
                       required
                       class="border p-2 rounded w-full">

                <p class="text-xs text-gray-500 mt-2">
                    Allowed formats: PDF, JPG, JPEG and PNG. Maximum size: 5 MB.
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button"
                        onclick="closeDocumentModal()"
                        class="bg-gray-300 px-4 py-2 rounded">
                    Cancel
                </button>

                <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Upload Document
                </button>
            </div>

        </form>
    </div>
</div>
<script>
function openDateModal(button){
    document.getElementById('date_leave_id').value=button.dataset.id;
    document.getElementById('date_start').value=button.dataset.start;
    document.getElementById('date_end').value=button.dataset.end;
    const modal=document.getElementById('dateModal');modal.classList.remove('hidden');modal.classList.add('flex');document.body.style.overflow='hidden';
}
function closeDateModal(){const modal=document.getElementById('dateModal');modal.classList.add('hidden');modal.classList.remove('flex');document.body.style.overflow='';}
function openTdModal(button){
    document.getElementById('approval_leave_id').value = button.dataset.id;

    const modal = document.getElementById('approvalModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    document.body.style.overflow = 'hidden';
}
function closeApprovalModal(){const modal=document.getElementById('approvalModal');modal.classList.add('hidden');modal.classList.remove('flex');document.body.style.overflow='';}
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDateModal();
        closeApprovalModal();
        closeDocumentModal();
    }
});

function openDocumentModal(button) {
    document.getElementById('document_leave_id').value = button.dataset.id;
    document.getElementById('documentStaffName').textContent = button.dataset.name;

    const modal = document.getElementById('documentModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    document.body.style.overflow = 'hidden';
}

function closeDocumentModal() {
    const modal = document.getElementById('documentModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    document.body.style.overflow = '';
}
// Close other action dropdowns when a new one is opened
document.querySelectorAll('.actions-dropdown').forEach(dropdown => {
    dropdown.addEventListener('toggle', function () {
        if (this.open) {
            document.querySelectorAll('.actions-dropdown').forEach(other => {
                if (other !== this) {
                    other.removeAttribute('open');
                }
            });
        }
    });
});

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.actions-dropdown')) {
        document.querySelectorAll('.actions-dropdown').forEach(dropdown => {
            dropdown.removeAttribute('open');
        });
    }
});
</script>
</body>
</html>
