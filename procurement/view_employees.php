<?php 
include 'db_con.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Employees</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">Employees</h1>

    <!-- Search Form -->
    <form method="GET" class="mb-4 flex items-center gap-2">
        <input type="text" name="search" placeholder="Search by name" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
            class="border p-2 rounded focus:ring-2 focus:ring-blue-300 w-64">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Search</button>
        <a href="view_employees.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Reset</a>
    </form>

    <?php
    // Fetch employees
    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE CONCAT(first_name, ' ', last_name) LIKE ? ORDER BY created_at DESC");
        $likeSearch = "%$search%";
        $stmt->bind_param("s", $likeSearch);
        $stmt->execute();
        $result = $stmt->get_result();
        $employees = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query("SELECT * FROM employees ORDER BY created_at DESC");
        $employees = $result->fetch_all(MYSQLI_ASSOC);
    }
    ?>

    <!-- Employee Table -->
<div class="bg-white shadow rounded-lg p-4">
    <table class="w-full border border-gray-300 rounded text-sm">
        <thead class="bg-gray-200 text-gray-700">
            <tr>
                <th class="border px-2 py-1">#</th>
                <th class="border px-2 py-1">Name</th>
                <th class="border px-2 py-1">National ID</th>
                <th class="border px-2 py-1">KRA PIN</th>
                <th class="border px-2 py-1">NSSF</th>
                <th class="border px-2 py-1">NHIF</th>
                <th class="border px-2 py-1">Phone</th>
                <th class="border px-2 py-1">Department</th>
                <th class="border px-2 py-1">Position</th>
                <th class="border px-2 py-1">Date Hired</th>
                <th class="border px-2 py-1">Status</th>
                <th class="border px-2 py-1">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($employees)): $count = 1; ?>
                <?php foreach ($employees as $row): 
                    $status = $row['status'] ?? 'Active';
                    $statusColor = ($status === 'Active') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                ?>
                    <tr class="hover:bg-gray-50 text-sm">
                        <td class="border px-2 py-1"><?= $count++ ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['national_id']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['kra_pin']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['nssf_number']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['nhif_number']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['phone']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['department']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['position']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['date_of_hire']) ?></td>
                        <td class="border px-2 py-1 text-center rounded <?= $statusColor ?>"><?= htmlspecialchars($status) ?></td>
                        <td class="border px-2 py-1 space-x-1">
                            <button onclick="openModal('modal-<?= $row['employee_id'] ?>')" 
                                class="bg-yellow-500 text-white px-2 py-0.5 text-xs rounded hover:bg-yellow-600 transition">Edit</button>
                            <a href="view_employee.php?id=<?= $row['employee_id'] ?>" 
                                class="bg-blue-600 text-white px-2 py-0.5 text-xs rounded hover:bg-blue-700 transition">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12" class="border px-3 py-2 text-center text-gray-500">No employees found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}
function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
</script>

</body>
</html>
