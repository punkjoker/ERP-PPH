<?php
include 'db_con.php';

// Handle adding leave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'])) {
    $employee_id = intval($_POST['employee_id']);
    $description = trim($_POST['description']);
    $start_date  = $_POST['start_date'];
    $end_date    = $_POST['end_date'];

    $stmt = $conn->prepare("INSERT INTO leaves (employee_id, description, start_date, end_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $employee_id, $description, $start_date, $end_date);
    $stmt->execute();
    $stmt->close();
    $success = "Leave added successfully!";
}

// Fetch employees for dropdown (only active)
$employees_res = $conn->query("SELECT employee_id, first_name, last_name, date_of_hire FROM employees WHERE status = 'Active' ORDER BY first_name");
$employees = $employees_res->fetch_all(MYSQLI_ASSOC);

// Selected year filter
$selected_year = $_GET['year'] ?? date('Y');

// --- Function to calculate leave stats ---
function calculateLeaveStats($conn, $employee_id, $date_of_hire, $year) {
    $yearStart = new DateTime("$year-01-01");
    $yearEnd   = new DateTime("$year-12-31");
    $hireDate  = new DateTime($date_of_hire);

    // Determine when the employee starts counting leave
    $startCount = ($hireDate > $yearStart) ? $hireDate : $yearStart;
    $now = ($year == date('Y')) ? new DateTime() : $yearEnd;

    // Count months worked this year
    $monthsWorked = 0;
    $temp = clone $startCount;
    while ($temp <= $now && $temp <= $yearEnd) {
        $monthsWorked++;
        $temp->modify('+1 month');
    }

    // Leave entitlement (max 21 days)
    $entitledDays = min($monthsWorked * 1.75, 21);

    // Leave taken in that year
    $stmt = $conn->prepare("SELECT SUM(DATEDIFF(end_date, start_date) + 1) AS days_taken 
                            FROM leaves 
                            WHERE employee_id = ? AND YEAR(start_date) = ?");
    $stmt->bind_param("ii", $employee_id, $year);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $daysTaken = $result['days_taken'] ?? 0;
    $remaining = max(21 - $daysTaken, 0);

    return [
        'entitled' => $entitledDays,
        'taken' => $daysTaken,
        'remaining' => $remaining
    ];
}

// Filters
$search = trim($_GET['search'] ?? '');
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

$query = "SELECT l.*, e.first_name, e.last_name 
          FROM leaves l 
          JOIN employees e ON l.employee_id = e.employee_id 
          WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $query .= " AND CONCAT(e.first_name, ' ', e.last_name) LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}
if ($from_date !== '') {
    $query .= " AND l.start_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}
if ($to_date !== '') {
    $query .= " AND l.end_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}
if (!empty($selected_year)) {
    $query .= " AND YEAR(l.start_date) = ?";
    $params[] = $selected_year;
    $types .= 'i';
}

$query .= " ORDER BY l.start_date DESC";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$leaves = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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

    <!-- Add Leave Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <select name="employee_id" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                    <option value="">Select Employee</option>
                    <?php foreach($employees as $emp): ?>
                        <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="description" placeholder="Leave Description" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300" required>
                <input type="date" name="start_date" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300" required>
                <input type="date" name="end_date" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300" required>
            </div>
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
</body>
</html>
