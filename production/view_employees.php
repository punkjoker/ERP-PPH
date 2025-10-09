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
    <div class="bg-white shadow rounded-lg p-6">
        <table class="w-full border border-gray-300 rounded">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border px-3 py-2">#</th>
                    <th class="border px-3 py-2">Name</th>
                    <th class="border px-3 py-2">National ID</th>
                    <th class="border px-3 py-2">KRA PIN</th>
                    <th class="border px-3 py-2">NSSF</th>
                    <th class="border px-3 py-2">NHIF</th>
                    <th class="border px-3 py-2">Phone</th>
                    <th class="border px-3 py-2">Department</th>
                    <th class="border px-3 py-2">Position</th>
                    <th class="border px-3 py-2">Date Hired</th>
                    <th class="border px-3 py-2">Status</th>
                    <th class="border px-3 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($employees)): $count = 1; ?>
                    <?php foreach ($employees as $row): 
                        $status = $row['status'] ?? 'Active';
                        $statusColor = ($status === 'Active') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border px-3 py-2"><?= $count++ ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($row['national_id']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($row['kra_pin']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($row['nssf_number']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($row['nhif_number']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($row['phone']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($row['department']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($row['position']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($row['date_of_hire']) ?></td>
                            <td class="border px-3 py-2 text-center rounded <?= $statusColor ?>"><?= htmlspecialchars($status) ?></td>
                            <td class="border px-3 py-2 space-x-2">
                                <!-- Edit Button triggers modal -->
                                <button onclick="openModal('modal-<?= $row['employee_id'] ?>')" 
                                    class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition">Edit</button>
                                <a href="view_employee.php?id=<?= $row['employee_id'] ?>" 
                                    class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">View</a>
                            </td>
                        </tr>

                        <!-- Full Edit Modal -->
<div id="modal-<?= $row['employee_id'] ?>" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden overflow-auto p-4">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md max-h-full overflow-auto">
        <h2 class="text-xl font-bold mb-4">Edit Employee - <?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></h2>
        <form method="POST" action="edit_employee.php">
            <input type="hidden" name="employee_id" value="<?= $row['employee_id'] ?>">

            <input type="text" name="first_name" value="<?= htmlspecialchars($row['first_name']) ?>" placeholder="First Name" class="border p-2 rounded w-full mb-2" required>
            <input type="text" name="last_name" value="<?= htmlspecialchars($row['last_name']) ?>" placeholder="Last Name" class="border p-2 rounded w-full mb-2" required>
            <input type="text" name="national_id" value="<?= htmlspecialchars($row['national_id']) ?>" placeholder="National ID" class="border p-2 rounded w-full mb-2">
            <input type="text" name="kra_pin" value="<?= htmlspecialchars($row['kra_pin']) ?>" placeholder="KRA PIN" class="border p-2 rounded w-full mb-2">
            <input type="text" name="nssf_number" value="<?= htmlspecialchars($row['nssf_number']) ?>" placeholder="NSSF Number" class="border p-2 rounded w-full mb-2">
            <input type="text" name="nhif_number" value="<?= htmlspecialchars($row['nhif_number']) ?>" placeholder="NHIF Number" class="border p-2 rounded w-full mb-2">
            <input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" placeholder="Email" class="border p-2 rounded w-full mb-2">
            <input type="text" name="phone" value="<?= htmlspecialchars($row['phone']) ?>" placeholder="Phone" class="border p-2 rounded w-full mb-2">
            <input type="text" name="department" value="<?= htmlspecialchars($row['department']) ?>" placeholder="Department" class="border p-2 rounded w-full mb-2">
            <input type="text" name="position" value="<?= htmlspecialchars($row['position']) ?>" placeholder="Position" class="border p-2 rounded w-full mb-2">
            <input type="date" name="date_of_hire" value="<?= htmlspecialchars($row['date_of_hire']) ?>" class="border p-2 rounded w-full mb-2">

            <!-- Employment Type -->
            <label class="block mb-1">Employment Type:</label>
            <select name="employment_type" onchange="toggleContractFields('contract-fields-<?= $row['employee_id'] ?>', this)" class="border p-2 rounded w-full mb-2">
                <option value="Permanent" <?= ($row['employment_type']==='Permanent') ? 'selected' : '' ?>>Permanent</option>
                <option value="Contract" <?= ($row['employment_type']==='Contract') ? 'selected' : '' ?>>Contract</option>
            </select>

            <!-- Contract Dates -->
            <div id="contract-fields-<?= $row['employee_id'] ?>" class="space-y-2 mb-2" style="display: <?= ($row['employment_type']==='Contract') ? 'block' : 'none' ?>;">
                <input type="date" name="contract_start" value="<?= htmlspecialchars($row['contract_start']) ?>" placeholder="Contract Start Date" class="border p-2 rounded w-full">
                <input type="date" name="contract_end" value="<?= htmlspecialchars($row['contract_end']) ?>" placeholder="Contract End Date" class="border p-2 rounded w-full">
            </div>

            <!-- Status -->
            <label class="block mb-1">Status:</label>
            <select name="status" class="border p-2 rounded w-full mb-4">
                <option value="Active" <?= ($status === 'Active') ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= ($status === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
            </select>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal('modal-<?= $row['employee_id'] ?>')" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Cancel</button>
                <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition">Save</button>
            </div>
        </form>
    </div>
</div>

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
