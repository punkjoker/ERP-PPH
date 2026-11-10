<?php
session_start();
require_once __DIR__ . '/db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function deptH($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Resolve supervisor's employee_id through the existing users link.
$stmt = $conn->prepare("
    SELECT employee_id, full_name
    FROM users
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$supervisor = $stmt->get_result()->fetch_assoc();
$stmt->close();

$supervisorEmployeeId = (int)($supervisor['employee_id'] ?? 0);
$supervisorName = $supervisor['full_name'] ?? 'Supervisor';

if ($supervisorEmployeeId <= 0) {
    http_response_code(400);
    die('Your staff account is not linked to an employee record. Please contact HR.');
}

if (empty($_SESSION['department_leave_csrf'])) {
    $_SESSION['department_leave_csrf'] = bin2hex(random_bytes(32));
}

// Load only direct reports assigned to this supervisor.
// The same assignment is independently checked again in update_leave.php.
$stmt = $conn->prepare("
    SELECT
        l.leave_id,
        l.user_id,
        l.leave_type,
        l.description,
        l.start_date,
        l.end_date,
        l.status,
        l.supervisor_status,
        l.supervisor_approval_date,
        l.supervisor_remarks,
        u.full_name,
        u.national_id,
        e.department,
        e.position
    FROM leaves l
    INNER JOIN users u ON u.user_id = l.user_id
    INNER JOIN employees e ON e.employee_id = u.employee_id
    INNER JOIN organization_hierarchy h ON h.employee_id = e.employee_id
    INNER JOIN groups g ON g.group_id = u.group_id
    WHERE h.reports_to_employee_id = ?
      AND LOWER(TRIM(g.group_name)) = 'staff'
    ORDER BY
        CASE WHEN COALESCE(l.supervisor_status, 'Pending') = 'Pending' THEN 0 ELSE 1 END,
        l.start_date DESC,
        l.leave_id DESC
");
$stmt->bind_param('i', $supervisorEmployeeId);
$stmt->execute();
$leaveRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pendingCount = 0;
foreach ($leaveRows as $row) {
    if (($row['supervisor_status'] ?? 'Pending') === 'Pending') $pendingCount++;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Department Leave Approvals | Lynntech Manager</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
body{background:#f3f4f6}.table-wrap{overflow-x:auto}table{min-width:1200px}
.modal-overlay{z-index:99999}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="ml-64 p-6 max-w-full mx-auto">
    <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-blue-800">Department Leave Approvals</h1>
            <p class="text-gray-600 mt-1">
                Requests submitted by employees directly assigned to <?= deptH($supervisorName) ?>.
            </p>
        </div>
        <div class="bg-white shadow rounded-lg px-5 py-3">
            <div class="text-sm text-gray-500">Awaiting your decision</div>
            <div class="text-2xl font-bold text-orange-600"><?= $pendingCount ?></div>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'supervisor_updated'): ?>
        <div class="bg-green-100 border border-green-300 text-green-800 rounded p-3 mb-5">
            Department decision saved successfully.
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow p-5 table-wrap">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Assigned Employees' Leave Requests</h2>
        <table class="w-full border border-gray-300 text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border px-3 py-3">#</th>
                    <th class="border px-3 py-3">Employee</th>
                    <th class="border px-3 py-3">Department</th>
                    <th class="border px-3 py-3">Position</th>
                    <th class="border px-3 py-3">Leave Type</th>
                    <th class="border px-3 py-3">Description</th>
                    <th class="border px-3 py-3">Start</th>
                    <th class="border px-3 py-3">End</th>
                    <th class="border px-3 py-3">Days</th>
                    <th class="border px-3 py-3">Department Status</th>
                    <th class="border px-3 py-3">Remarks</th>
                    <th class="border px-3 py-3">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$leaveRows): ?>
                <tr><td colspan="12" class="text-center text-gray-500 py-8">
                    No leave requests were found for employees assigned directly to you.
                </td></tr>
            <?php else: ?>
                <?php foreach ($leaveRows as $i => $leave):
                    $days = max(0, (int)((strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400) + 1);
                    $supervisorStatus = $leave['supervisor_status'] ?: 'Pending';
                    $canDecide = $supervisorStatus === 'Pending';
                ?>
                <tr class="<?= $i % 2 ? 'bg-gray-50' : 'bg-white' ?>">
                    <td class="border px-3 py-3 text-center"><?= $i + 1 ?></td>
                    <td class="border px-3 py-3 font-semibold"><?= deptH($leave['full_name']) ?></td>
                    <td class="border px-3 py-3"><?= deptH($leave['department']) ?></td>
                    <td class="border px-3 py-3"><?= deptH($leave['position']) ?></td>
                    <td class="border px-3 py-3"><?= deptH($leave['leave_type']) ?></td>
                    <td class="border px-3 py-3"><?= deptH($leave['description']) ?></td>
                    <td class="border px-3 py-3"><?= deptH($leave['start_date']) ?></td>
                    <td class="border px-3 py-3"><?= deptH($leave['end_date']) ?></td>
                    <td class="border px-3 py-3 text-center font-bold"><?= $days ?></td>
                    <td class="border px-3 py-3 font-semibold <?= $supervisorStatus === 'Approved' ? 'text-green-700' : ($supervisorStatus === 'Denied' ? 'text-red-700' : 'text-orange-700') ?>">
                        <?= deptH($supervisorStatus) ?>
                    </td>
                    <td class="border px-3 py-3 whitespace-pre-wrap"><?= deptH($leave['supervisor_remarks'] ?: '—') ?></td>
                    <td class="border px-3 py-3">
                        <?php if ($canDecide): ?>
                            <button type="button"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded"
                                data-id="<?= (int)$leave['leave_id'] ?>"
                                data-name="<?= deptH($leave['full_name']) ?>"
                                data-type="<?= deptH($leave['leave_type']) ?>"
                                onclick="openDecision(this)">Review</button>
                        <?php else: ?>
                            <span class="text-gray-500 text-xs">Decision recorded</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="decisionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center p-4 modal-overlay">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-lg">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-blue-800">Department Leave Decision</h2>
            <button type="button" onclick="closeDecision()" class="text-2xl text-gray-500">&times;</button>
        </div>
        <p class="text-sm text-gray-600 mb-4">Employee: <strong id="employeeName"></strong> — <span id="leaveType"></span></p>
        <form method="POST" action="update_leave.php">
            <input type="hidden" name="action" value="supervisor_decision">
            <input type="hidden" name="leave_id" id="decisionLeaveId">
            <input type="hidden" name="csrf_token" value="<?= deptH($_SESSION['department_leave_csrf']) ?>">
            <div class="mb-4">
                <label class="block font-semibold mb-1">Decision</label>
                <select name="decision" required class="border p-2 rounded w-full">
                    <option value="">Select decision</option>
                    <option value="Approved">Approve and forward to TD</option>
                    <option value="Denied">Reject request</option>
                </select>
            </div>
            <div class="mb-5">
                <label class="block font-semibold mb-1">Remarks</label>
                <textarea name="remarks" rows="4" maxlength="2000" class="border p-2 rounded w-full"
                    placeholder="Add comments or reason for rejection"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeDecision()" class="bg-gray-300 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save Decision</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDecision(button) {
    document.getElementById('decisionLeaveId').value = button.dataset.id;
    document.getElementById('employeeName').textContent = button.dataset.name || '';
    document.getElementById('leaveType').textContent = button.dataset.type || '';
    const modal = document.getElementById('decisionModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}
function closeDecision() {
    const modal = document.getElementById('decisionModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDecision(); });
</script>
</body>
</html>
