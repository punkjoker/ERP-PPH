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
$employees_res = $conn->query("SELECT employee_id, first_name, last_name FROM employees WHERE status = 'Active' ORDER BY first_name");
$employees = $employees_res->fetch_all(MYSQLI_ASSOC);


// Filters
$search = trim($_GET['search'] ?? '');
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

// Build query dynamically
$query = "SELECT l.*, e.first_name, e.last_name FROM leaves l JOIN employees e ON l.employee_id = e.employee_id WHERE 1=1";
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

$query .= " ORDER BY l.start_date DESC";
$stmt = $conn->prepare($query);
if(!empty($params)){
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

<div class="ml-64 p-6 max-w-5xl mx-auto">
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

    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-6 mb-4">
        <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
            <input type="text" name="search" placeholder="Search by name" value="<?= htmlspecialchars($search) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300 w-full md:w-64">
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Filter</button>
            <a href="manage_leaves.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Reset</a>
        </form>
    </div>

    <!-- Leaves List -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Leaves List</h2>
        <table class="w-full border border-gray-300 rounded">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border px-3 py-2">#</th>
                    <th class="border px-3 py-2">Employee</th>
                    <th class="border px-3 py-2">Description</th>
                    <th class="border px-3 py-2">Start Date</th>
                    <th class="border px-3 py-2">End Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($leaves)): $count=1; ?>
                    <?php foreach($leaves as $leave): ?>
                        <tr class="<?= ($count % 2 == 0) ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">
                            <td class="border px-3 py-2"><?= $count++ ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($leave['first_name'].' '.$leave['last_name']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($leave['description']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($leave['start_date']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($leave['end_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="border px-3 py-2 text-center text-gray-500">No leaves found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
