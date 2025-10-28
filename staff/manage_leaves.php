<?php
session_start();
include 'db_con.php';

// --- Ensure user is logged in ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Leave entitlements
$leaveEntitlements = [
    'Annual' => 21,
    'Sick' => 30,
    'Paternity' => 14,
    'Maternity' => 90
];

// Handle leave submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type  = $_POST['leave_type'];
    $description = trim($_POST['description']);
    $start_date  = $_POST['start_date'];
    $end_date    = $_POST['end_date'];

    $stmt = $conn->prepare("INSERT INTO leaves (user_id, leave_type, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("issss", $user_id, $leave_type, $description, $start_date, $end_date);
    $stmt->execute();
    $stmt->close();
    $success = "Leave request submitted successfully!";
}

// Fetch logged-in user info
$user = $conn->query("SELECT * FROM users WHERE user_id = $user_id")->fetch_assoc();

// Fetch user leave records
$leaves = [];
$stmt = $conn->prepare("SELECT * FROM leaves WHERE user_id = ? ORDER BY start_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $leaves[] = $row;
$stmt->close();

// Function to get remaining leave
function getRemainingLeave($conn, $user_id, $leave_type, $entitlement, $year) {
    $stmt = $conn->prepare("
        SELECT SUM(DATEDIFF(end_date, start_date)+1) AS days_taken
        FROM leaves
        WHERE user_id=? AND leave_type=? AND YEAR(start_date)=? AND status='Approved'
    ");
    $stmt->bind_param("isi", $user_id, $leave_type, $year);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $taken = $res['days_taken'] ?? 0;
    return ['taken' => $taken, 'remaining' => max($entitlement - $taken, 0)];
}

// Selected year for display
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Leaves</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">My Leave Requests</h1>

    <?php if(isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Leave Request Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <form method="POST" id="leaveForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Leave Type -->
                <select id="leaveTypeSelect" name="leave_type" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                    <option value="">Select Leave Type</option>
                    <?php foreach($leaveEntitlements as $type => $days): ?>
                        <option value="<?= $type ?>"><?= $type ?> (<?= $days ?> days)</option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="description" placeholder="Leave Description" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="date" name="start_date" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="date" name="end_date" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
            </div>

            <!-- Dynamic Remaining Days Display -->
            <div id="leaveBalance" class="text-blue-700 font-semibold mt-2"></div>

            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Submit Leave</button>
        </form>
    </div>

    <!-- Leave Records Table -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Leave Records (<?= $selected_year ?>)</h2>
        <table class="w-full border border-gray-300 rounded text-sm">
            <thead class="bg-gray-200">
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Days</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if(!empty($leaves)): $count=1; ?>
                <?php foreach($leaves as $leave):
                    $days = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / (60*60*24) + 1;

                ?>
                    <tr class="<?= ($count%2==0)?'bg-gray-50':'bg-white' ?> hover:bg-blue-50">
                        <td class="border px-3 py-2"><?= $count++ ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['leave_type']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['description']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['start_date']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['end_date']) ?></td>
                        <td class="border px-3 py-2 text-center font-semibold text-blue-700"><?= $days ?></td>
                        <td class="border px-3 py-2 text-center font-semibold <?= $leave['status']=='Approved'?'text-green-600':($leave['status']=='Denied'?'text-red-600':'text-yellow-600') ?>">
                            <?= $leave['status'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center text-gray-500 py-3">No leave requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const leaveSelect = document.getElementById('leaveTypeSelect');
const leaveBalanceDiv = document.getElementById('leaveBalance');

leaveSelect.addEventListener('change', updateLeaveBalance);

function updateLeaveBalance() {
    const leaveType = leaveSelect.value;

    // Clear balance if no leave type selected
    if (!leaveType) {
        leaveBalanceDiv.textContent = '';
        return;
    }

    // Fetch remaining leave from server
    fetch(`get_leave_balance.php?user_id=<?= $user_id ?>&leave_type=${encodeURIComponent(leaveType)}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not OK');
            return response.json();
        })
        .then(data => {
            // Check if data contains expected fields
            if (data && typeof data.remaining !== 'undefined' && typeof data.taken !== 'undefined') {
                leaveBalanceDiv.textContent = 
                    `Remaining ${leaveType} Leave: ${data.remaining} day(s) (Taken: ${data.taken} day(s))`;
            } else {
                leaveBalanceDiv.textContent = 'Unable to fetch leave balance.';
            }
        })
        .catch(error => {
            console.error('Error fetching leave balance:', error);
            leaveBalanceDiv.textContent = 'Error fetching leave balance.';
        });
}
</script>

</body>
</html>