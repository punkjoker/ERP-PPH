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
        <a href="download_employee_list.php" 
   class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
   ⬇️ Download Active Employees
</a>

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

                    // ✅ Handle contract calculations
                    $remainingWeeksText = '';
                    $remainingWeeksColor = '';
                    if ($row['employment_type'] === 'Contract' && !empty($row['contract_end'])) {
                        $today = new DateTime();
                        $endDate = new DateTime($row['contract_end']);
                        $diff = $today->diff($endDate);
                        $remainingWeeks = floor($diff->days / 7);

                        if ($endDate < $today) {
                            // Contract ended → set inactive
                            if ($row['status'] === 'Active') {
                                $conn->query("UPDATE employees SET status = 'Inactive' WHERE employee_id = {$row['employee_id']}");
                                $row['status'] = 'Inactive';
                                $statusColor = 'bg-red-100 text-red-800';
                            }
                            $remainingWeeksText = "Ended";
                            $remainingWeeksColor = 'text-red-600 font-semibold';
                        } else {
                            $remainingWeeksText = "{$remainingWeeks} week" . ($remainingWeeks !== 1 ? 's' : '') . " left";
                            $remainingWeeksColor = ($remainingWeeks <= 2) ? 'text-red-600 font-semibold' : 'text-gray-700';
                        }
                    }
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
                        <td class="border px-2 py-1 text-center rounded <?= $statusColor ?>">
                            <?= htmlspecialchars($row['status']) ?>
                            <?php if ($row['employment_type'] === 'Contract'): ?>
                                <div class="<?= $remainingWeeksColor ?> text-xs"><?= $remainingWeeksText ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="border px-2 py-1 space-x-1">
                            <button onclick="openModal('modal-<?= $row['employee_id'] ?>')" 
                                class="bg-yellow-500 text-white px-2 py-0.5 text-xs rounded hover:bg-yellow-600 transition">Edit</button>
                            <a href="view_employee.php?id=<?= $row['employee_id'] ?>" 
                                class="bg-blue-600 text-white px-2 py-0.5 text-xs rounded hover:bg-blue-700 transition">View</a>
                        </td>
                    </tr>

                    <!-- ✅ Edit Modal -->
<div id="modal-<?= $row['employee_id'] ?>" class="hidden fixed inset-0 z-50 bg-black bg-opacity-50 overflow-y-auto">
  <div class="flex justify-center items-start min-h-screen py-10">
    <div class="bg-white rounded-lg w-full max-w-md mx-4 shadow-lg relative">
      <div class="p-6 overflow-y-auto max-h-[80vh]">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Edit Employee</h2>

        <form class="updateForm" data-id="<?= $row['employee_id'] ?>">
          <input type="hidden" name="employee_id" value="<?= $row['employee_id'] ?>">

          <label class="block text-sm mb-1">First Name</label>
          <input type="text" name="first_name" value="<?= htmlspecialchars($row['first_name']) ?>" class="border p-2 w-full mb-2 rounded">

          <label class="block text-sm mb-1">Last Name</label>
          <input type="text" name="last_name" value="<?= htmlspecialchars($row['last_name']) ?>" class="border p-2 w-full mb-2 rounded">

          <label class="block text-sm mb-1">National ID</label>
          <input type="text" name="national_id" value="<?= htmlspecialchars($row['national_id']) ?>" class="border p-2 w-full mb-2 rounded">

          <label class="block text-sm mb-1">KRA PIN</label>
          <input type="text" name="kra_pin" value="<?= htmlspecialchars($row['kra_pin']) ?>" class="border p-2 w-full mb-2 rounded">

          <label class="block text-sm mb-1">NSSF Number</label>
          <input type="text" name="nssf_number" value="<?= htmlspecialchars($row['nssf_number']) ?>" class="border p-2 w-full mb-2 rounded">

          <label class="block text-sm mb-1">NHIF Number</label>
          <input type="text" name="nhif_number" value="<?= htmlspecialchars($row['nhif_number']) ?>" class="border p-2 w-full mb-2 rounded">

          <label class="block text-sm mb-1">Phone</label>
          <input type="text" name="phone" value="<?= htmlspecialchars($row['phone']) ?>" class="border p-2 w-full mb-2 rounded">

          <label class="block text-sm mb-1">Department</label>
          <input type="text" name="department" value="<?= htmlspecialchars($row['department']) ?>" class="border p-2 w-full mb-2 rounded">

          <label class="block text-sm mb-1">Position</label>
          <input type="text" name="position" value="<?= htmlspecialchars($row['position']) ?>" class="border p-2 w-full mb-2 rounded">

          <label class="block text-sm mb-1">Date Hired</label>
          <input type="date" name="date_of_hire" value="<?= htmlspecialchars($row['date_of_hire']) ?>" class="border p-2 w-full mb-2 rounded">

          <label class="block text-sm mb-1">Status</label>
          <select name="status" class="border p-2 w-full mb-2 rounded">
            <option value="Active" <?= ($row['status'] === 'Active') ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= ($row['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
          </select>

          <label class="block text-sm mb-1">Employment Type</label>
          <select name="employment_type" class="border p-2 w-full mb-2 rounded" onchange="toggleContractFields(this, 'contractFields-<?= $row['employee_id'] ?>')">
            <option value="Permanent" <?= ($row['employment_type'] === 'Permanent') ? 'selected' : '' ?>>Permanent</option>
            <option value="Contract" <?= ($row['employment_type'] === 'Contract') ? 'selected' : '' ?>>Contract</option>
          </select>

          <div id="contractFields-<?= $row['employee_id'] ?>" class="<?= ($row['employment_type'] === 'Contract') ? '' : 'hidden' ?>">
            <label class="block text-sm mb-1">Contract Start</label>
            <input type="date" name="contract_start" value="<?= htmlspecialchars($row['contract_start']) ?>" class="border p-2 w-full mb-2 rounded">

            <label class="block text-sm mb-1">Contract End</label>
            <input type="date" name="contract_end" value="<?= htmlspecialchars($row['contract_end']) ?>" class="border p-2 w-full mb-2 rounded">
          </div>

          <div class="flex justify-end space-x-2 mt-4 sticky bottom-0 bg-white py-2">
            <button type="button" onclick="closeModal('modal-<?= $row['employee_id'] ?>')" 
                    class="bg-gray-400 text-white px-4 py-1 rounded hover:bg-gray-500 transition">Cancel</button>
            <button type="submit" class="bg-blue-600 text-white px-4 py-1 rounded hover:bg-blue-700 transition">Save</button>
          </div>
        </form>
      </div>
    </div>
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

function toggleContractFields(select, id) {
    const div = document.getElementById(id);
    if (select.value === 'Contract') div.classList.remove('hidden');
    else div.classList.add('hidden');
}

// ✅ AJAX Update
document.querySelectorAll(".updateForm").forEach(form => {
    form.addEventListener("submit", async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const employeeId = this.dataset.id;

        const response = await fetch("update_employe.php", {
            method: "POST",
            body: formData
        });

        const result = await response.text();
        if (result.includes("success")) {
            alert("Employee updated successfully!");
            closeModal(`modal-${employeeId}`);
            location.reload();
        } else {
            alert("Update failed. Please try again.");
        }
    });
});
</script>

</body>
</html>
