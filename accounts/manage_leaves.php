<?php
include 'db_con.php';

// --- Initialize filters to prevent warnings ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// --- Leave entitlements ---
$leaveEntitlements = [
    'Annual' => 21,
    'Sick' => 30,
    'Paternity' => 14,
    'Maternity' => 90
];

// --- Handle leave submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'])) {
    $employee_id = intval($_POST['employee_id']);
    $leave_type = $_POST['leave_type'];
    $description = trim($_POST['description']);
    $start_date  = $_POST['start_date'];
    $end_date    = $_POST['end_date'];

    $stmt = $conn->prepare("INSERT INTO leaves (employee_id, leave_type, description, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $employee_id, $leave_type, $description, $start_date, $end_date);
    $stmt->execute();
    $stmt->close();
    $success = "Leave added successfully!";
}

// Fetch employees
$employees = $conn->query("SELECT employee_id, first_name, last_name, date_of_hire FROM employees WHERE status = 'Active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// --- Function to calculate remaining leave ---
function getRemainingLeave($conn, $employee_id, $leave_type, $entitlement, $year) {
    $stmt = $conn->prepare("SELECT SUM(DATEDIFF(end_date, start_date) + 1) AS days_taken 
                            FROM leaves 
                            WHERE employee_id = ? AND leave_type = ? AND YEAR(start_date) = ?");
    $stmt->bind_param("isi", $employee_id, $leave_type, $year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $taken = $result['days_taken'] ?? 0;
    $remaining = max($entitlement - $taken, 0);
    return ['taken' => $taken, 'remaining' => $remaining];
}
// --- Function to calculate leave stats (entitled, taken, remaining) ---
function calculateLeaveStats($conn, $employee_id, $date_of_hire, $year) {
    // Default entitlement (Annual = 21 days)
    $entitled = 21; 

    // Calculate total leave days taken in the selected year
    $stmt = $conn->prepare("SELECT SUM(DATEDIFF(end_date, start_date) + 1) AS days_taken
                            FROM leaves 
                            WHERE employee_id = ? AND YEAR(start_date) = ?");
    $stmt->bind_param("ii", $employee_id, $year);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $taken = $res['days_taken'] ?? 0;
    $remaining = max($entitled - $taken, 0);

    return [
        'entitled' => $entitled,
        'taken' => $taken,
        'remaining' => $remaining
    ];
}

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

<div class="ml-64 p-6 max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">Manage Employee Leaves</h1>

    <?php if(isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow rounded-lg p-6 mb-8">
    <form method="POST" id="leaveForm" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Employee -->
            <select id="employeeSelect" name="employee_id" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <option value="">Select Employee</option>
                <?php foreach($employees as $emp): ?>
                    <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></option>
                <?php endforeach; ?>
            </select>

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
        <div id="leaveBalance" class="text-blue-700 font-semibold"></div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Add Leave</button>
    </form>
</div>


    <!-- Filter by Year -->
    <div class="bg-white shadow rounded-lg p-4 mb-8 flex flex-col md:flex-row items-center justify-between gap-4">
        <form method="GET" class="flex items-center gap-4">
            <input type="text" name="search" placeholder="Search by name" value="<?= htmlspecialchars($search) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            <select name="year" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
                <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
        </form>
        <a href="manage_leaves.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Reset</a>
    </div>

    <!-- Leave Balances -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-blue-700 mb-4">Leave Balances (<?= htmlspecialchars($selected_year) ?>)</h2>
        <table class="w-full border border-gray-300 rounded text-sm">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border px-3 py-2">Employee</th>
                    <th class="border px-3 py-2">Hire Date</th>
                    <th class="border px-3 py-2 text-center">Entitled</th>
                    <th class="border px-3 py-2 text-center">Taken</th>
                    <th class="border px-3 py-2 text-center">Remaining</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($employees as $emp): 
                    $stats = calculateLeaveStats($conn, $emp['employee_id'], $emp['date_of_hire'], $selected_year); ?>
                    <tr class="hover:bg-blue-50">
                        <td class="border px-3 py-2"><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($emp['date_of_hire']) ?></td>
                        <td class="border px-3 py-2 text-center"><?= number_format($stats['entitled'], 2) ?></td>
                        <td class="border px-3 py-2 text-center"><?= number_format($stats['taken'], 2) ?></td>
                        <td class="border px-3 py-2 text-center font-semibold <?= $stats['remaining'] < 5 ? 'text-red-600' : 'text-green-600' ?>">
                            <?= number_format($stats['remaining'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Leave Records -->
<div class="bg-white shadow rounded-lg p-6">
    <h2 class="text-xl font-semibold mb-4 text-blue-700">Leave Records</h2>
    <table class="w-full border border-gray-300 rounded text-sm">
        <thead class="bg-gray-200">
            <tr>
                <th class="border px-3 py-2">#</th>
                <th class="border px-3 py-2">Employee</th>
                <th class="border px-3 py-2">Description</th>
                <th class="border px-3 py-2">Start Date</th>
                <th class="border px-3 py-2">End Date</th>
                <th class="border px-3 py-2 text-center">Days Taken</th>
            </tr>
        </thead>
        <tbody>
            <?php if(!empty($leaves)): $count=1; ?>
                <?php foreach($leaves as $leave): 
                    // Calculate days taken (inclusive of start and end date)
                    $daysTaken = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / (60 * 60 * 24) + 1;
                ?>
                    <tr class="<?= ($count % 2 == 0) ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">
                        <td class="border px-3 py-2"><?= $count++ ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['first_name'].' '.$leave['last_name']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['description']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['start_date']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['end_date']) ?></td>
                        <td class="border px-3 py-2 text-center font-semibold text-blue-700"><?= $daysTaken ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-gray-500 py-3">No leaves found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</div>
<script>
document.getElementById('leaveTypeSelect').addEventListener('change', updateLeaveBalance);
document.getElementById('employeeSelect').addEventListener('change', updateLeaveBalance);

function updateLeaveBalance() {
    const emp = document.getElementById('employeeSelect').value;
    const type = document.getElementById('leaveTypeSelect').value;

    if (emp && type) {
        fetch(`get_leave_balance.php?employee_id=${emp}&leave_type=${type}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('leaveBalance').textContent = 
                `Remaining ${type} Leave: ${data.remaining} days (Taken: ${data.taken})`;
        });
    }
}
</script>

</body>
</html>
