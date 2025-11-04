<?php
session_start();
include 'db_con.php';

// --- Fetch filter values ---
$filter_user = $_GET['user_id'] ?? '';
$filter_type = $_GET['leave_type'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$where = [];
$params = [];
$types = "";

// --- Base Query ---
$sql = "SELECT l.*, u.full_name, u.national_id
        FROM leaves l
        JOIN users u ON l.user_id = u.user_id
        JOIN groups g ON u.group_id = g.group_id
        WHERE g.group_name = 'staff'";


// --- Apply filters ---
if ($filter_user) {
    $where[] = "l.user_id = ?";
    $params[] = $filter_user;
    $types .= "i";
}
if ($filter_type) {
    $where[] = "l.leave_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}
if ($from_date && $to_date) {
    $where[] = "l.start_date BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
    $types .= "ss";
}

if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}

$sql .= " ORDER BY l.start_date DESC";

// --- Prepare + Execute Query ---
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$leaves = [];
while ($row = $res->fetch_assoc()) {
    $leaves[] = $row;
}
$stmt->close();

// --- Fetch all users for filter dropdown ---
$users = $conn->query("
    SELECT u.user_id, u.full_name, u.national_id
    FROM users u
    JOIN groups g ON u.group_id = g.group_id
    WHERE g.group_name = 'Staff'
    ORDER BY u.full_name ASC
")->fetch_all(MYSQLI_ASSOC);


// --- Fetch distinct leave types ---
$types_res = $conn->query("SELECT DISTINCT leave_type FROM leaves ORDER BY leave_type ASC")->fetch_all(MYSQLI_ASSOC);

// --- If a user + type are selected, calculate total + remaining ---
$summary = [];
if ($filter_user && $filter_type) {
    $year = date('Y');
    $entitlements = [
        'Annual' => 21,
        'Sick' => 30,
        'Paternity' => 14,
        'Maternity' => 90
    ];
    $entitled = $entitlements[$filter_type] ?? 0;

    $stmt = $conn->prepare("
        SELECT SUM(DATEDIFF(end_date, start_date) + 1) AS taken
        FROM leaves
        WHERE user_id = ? AND leave_type = ? AND YEAR(start_date) = ? AND status='Approved'
    ");
    $stmt->bind_param("isi", $filter_user, $filter_type, $year);
    $stmt->execute();
    $resSum = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $taken = $resSum['taken'] ?? 0;
    $remaining = max($entitled - $taken, 0);

    $summary = [
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
    <title>Leave Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">All Leave Requests</h1>
<div class="flex justify-end mb-4">
  <a href="download_leaves_requests.php?<?= http_build_query($_GET) ?>"
     class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
     ⬇️ Download Leave Requests
  </a>
</div>

    <!-- 🔹 FILTER SECTION -->
    <form method="GET" class="bg-white shadow rounded-lg p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div>
            <label class="block font-semibold mb-1">From</label>
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border p-2 rounded w-full">
        </div>
        <div>
            <label class="block font-semibold mb-1">To</label>
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border p-2 rounded w-full">
        </div>
        <div>
            <label class="block font-semibold mb-1">User</label>
            <select name="user_id" class="border p-2 rounded w-full">
                <option value="">All</option>
                <?php foreach($users as $u): ?>
                    <option value="<?= $u['user_id'] ?>" <?= ($filter_user==$u['user_id'])?'selected':'' ?>>
                        <?= htmlspecialchars($u['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block font-semibold mb-1">Leave Type</label>
            <select name="leave_type" class="border p-2 rounded w-full">
                <option value="">All</option>
                <?php foreach($types_res as $t): ?>
                    <option value="<?= htmlspecialchars($t['leave_type']) ?>" <?= ($filter_type==$t['leave_type'])?'selected':'' ?>>
                        <?= htmlspecialchars($t['leave_type']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex space-x-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">Filter</button>
            <a href="leaves_request.php" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400 w-full text-center">Reset</a>
        </div>
    </form>

    <!-- 🔹 Summary Section -->
    <?php if (!empty($summary)): ?>
        <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded mb-6">
            <p class="text-blue-800 font-semibold">
                <?= htmlspecialchars($users[array_search($filter_user, array_column($users, 'user_id'))]['full_name'] ?? '') ?>'s <?= htmlspecialchars($filter_type) ?> Leave (<?= date('Y') ?>)
            </p>
            <p>Entitled: <strong><?= $summary['entitled'] ?></strong> days | Taken: <strong><?= $summary['taken'] ?></strong> days | Remaining: <strong><?= $summary['remaining'] ?></strong> days</p>
        </div>
    <?php endif; ?>

    <!-- Leave Table -->
    <div class="bg-white shadow rounded-lg p-6">
        <table class="w-full border border-gray-300 rounded text-xs">
    <thead class="bg-gray-200 text-gray-700 uppercase">
        <tr class="text-center">
            <th class="px-2 py-1">#</th>
            <th class="px-2 py-1">Name</th>
            <th class="px-2 py-1">National ID</th>
            <th class="px-2 py-1">Type</th>
            <th class="px-2 py-1">Description</th>
            <th class="px-2 py-1">Start</th>
            <th class="px-2 py-1">End</th>
            <th class="px-2 py-1">Days</th>
            <th class="px-2 py-1">Status</th>
            <th class="px-2 py-1">Action</th>
        </tr>
    </thead>
    <tbody class="text-gray-700">

            <?php if(!empty($leaves)): $count=1; ?>
                <?php foreach($leaves as $leave): ?>
                    <tr class="<?= ($count%2==0)?'bg-gray-50':'bg-white' ?> hover:bg-blue-50">
                        <td class="border px-2 py-1"><?= $count++ ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($leave['full_name']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($leave['national_id']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($leave['leave_type']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($leave['description']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($leave['start_date']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($leave['end_date']) ?></td>
                        <td class="border px-2 py-1 text-center font-semibold text-blue-700"><?= $leave['total_days'] ?></td>
                        <td class="border px-2 py-1 text-center font-semibold <?= $leave['status']=='Approved'?'text-green-600':($leave['status']=='Denied'?'text-red-600':'text-yellow-600') ?>">
                            <?= $leave['status'] ?>
                        </td>
                        <td class="border px-2 py-1 text-center">
                            <button class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600"
                                    onclick="openModal(<?= $leave['leave_id'] ?>, '<?= $leave['start_date'] ?>', '<?= $leave['end_date'] ?>', '<?= $leave['status'] ?>')">
                                Update
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9" class="text-center text-gray-500 py-3">No leave requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Existing modal scripts remain same -->
<script>
function openModal(leave_id, start_date, end_date, status) {
    document.getElementById('modal_leave_id').value = leave_id;
    document.getElementById('modal_start_date').value = start_date;
    document.getElementById('modal_end_date').value = end_date;
    document.getElementById('modal_status').value = status;
    document.getElementById('updateModal').classList.remove('hidden');
    document.getElementById('updateModal').classList.add('flex');
}
function closeModal() {
    document.getElementById('updateModal').classList.remove('flex');
    document.getElementById('updateModal').classList.add('hidden');
}
</script>

</body>
</html>
