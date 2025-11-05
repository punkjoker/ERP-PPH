<?php
include 'db_con.php';

// Handle training submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'])) {
    $employee_id   = $_POST['employee_id']; // don't cast yet
    $training_name = trim($_POST['training_name']);
    $training_date = $_POST['training_date'];
    $status        = $_POST['status'];
    $done_by       = trim($_POST['done_by']);
    $approved_by   = trim($_POST['approved_by']);

    if ($employee_id === "all") {
        // Insert training for all active employees
        $result = $conn->query("SELECT employee_id FROM employees WHERE status='Active'");
        $stmt = $conn->prepare("INSERT INTO trainings (employee_id, training_name, training_date, status, done_by, approved_by) VALUES (?,?,?,?,?,?)");

        while ($row = $result->fetch_assoc()) {
            $emp_id = $row['employee_id'];
            $stmt->bind_param("isssss", $emp_id, $training_name, $training_date, $status, $done_by, $approved_by);
            $stmt->execute();
        }

        $stmt->close();
        $success = "Training recorded for all employees successfully!";
    } else {
        // Insert for one employee
        $emp_id = intval($employee_id);
        $stmt = $conn->prepare("INSERT INTO trainings (employee_id, training_name, training_date, status, done_by, approved_by) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("isssss", $emp_id, $training_name, $training_date, $status, $done_by, $approved_by);
        $stmt->execute();
        $stmt->close();
        $success = "Training recorded successfully!";
    }
}

// Fetch employees
$employees_res = $conn->query("SELECT employee_id, first_name, last_name FROM employees WHERE status='Active' ORDER BY first_name");
$employees = $employees_res->fetch_all(MYSQLI_ASSOC);

// Fetch trainings
$result = $conn->query("SELECT t.*, e.first_name, e.last_name FROM trainings t JOIN employees e ON t.employee_id=e.employee_id ORDER BY t.training_date DESC");
$trainings = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Training</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">Record Training</h1>

    <?php if(isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Add Training Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <select name="employee_id" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
    <option value="">Select Employee</option>
    <option value="all">All Employees</option> <!-- ✅ Added -->
    <?php foreach($employees as $emp): ?>
        <option value="<?= $emp['employee_id'] ?>">
            <?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?>
        </option>
    <?php endforeach; ?>
</select>

                <input type="text" name="training_name" placeholder="Training Name" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300" required>
                <input type="date" name="training_date" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300" required>
                <select name="status" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                    <option value="Pending">Pending</option>
                    <option value="Done">Done</option>
                </select>
                <input type="text" name="done_by" placeholder="Training Done By" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="approved_by" placeholder="Approved By" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Record Training</button>
        </form>
    </div>

    <!-- Trainings List -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Training Records</h2>
        <div class="flex justify-end mb-4">
  <a href="download_training_records.php"
     class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
     ⬇️ Download Training Records
  </a>
</div>

        <table class="w-full border border-gray-300 rounded">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border px-3 py-2">#</th>
                    <th class="border px-3 py-2">Employee</th>
                    <th class="border px-3 py-2">Training Name</th>
                    <th class="border px-3 py-2">Date</th>
                    <th class="border px-3 py-2">Status</th>
                    <th class="border px-3 py-2">Done By</th>
                    <th class="border px-3 py-2">Approved By</th>
                    <th class="border px-3 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($trainings)): $count=1; ?>
                    <?php foreach($trainings as $tr): ?>
                        <tr class="<?= ($count%2==0)?'bg-gray-50':'bg-white' ?> hover:bg-blue-50">
                            <td class="border px-3 py-2"><?= $count++ ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($tr['first_name'].' '.$tr['last_name']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($tr['training_name']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($tr['training_date']) ?></td>
                            <td class="border px-3 py-2 text-center">
                                <?php if($tr['status']=='Done'): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded">Done</span>
                                <?php else: ?>
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($tr['done_by']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($tr['approved_by']) ?></td>
                            <td class="border px-3 py-2 space-x-2">
                                <button onclick="openModal('modal-<?= $tr['training_id'] ?>')" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition">Edit</button>
                                <a href="view_training.php?id=<?= $tr['training_id'] ?>" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">View</a>
                            </td>
                        </tr>

                       <!-- Edit Modal -->
<div id="modal-<?= $tr['training_id'] ?>" 
     class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden overflow-auto p-4">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-lg">
        <h2 class="text-xl font-bold mb-4">Edit Training</h2>
        <form method="POST" action="edit_training.php">
            <input type="hidden" name="training_id" value="<?= $tr['training_id'] ?>">

            

            <!-- Training Name -->
            <label class="block mb-2 font-semibold">Training Name</label>
            <input type="text" name="training_name" value="<?= htmlspecialchars($tr['training_name']) ?>" 
                   class="border p-2 rounded w-full mb-4" required>

            <!-- Training Date -->
            <label class="block mb-2 font-semibold">Training Date</label>
            <input type="date" name="training_date" value="<?= htmlspecialchars($tr['training_date']) ?>" 
                   class="border p-2 rounded w-full mb-4" required>

            <!-- Status -->
            <label class="block mb-2 font-semibold">Status</label>
            <select name="status" class="border p-2 rounded w-full mb-4">
                <option value="Pending" <?= $tr['status']=='Pending'?'selected':'' ?>>Pending</option>
                <option value="Done" <?= $tr['status']=='Done'?'selected':'' ?>>Done</option>
            </select>

            <!-- Training Done By -->
            <label class="block mb-2 font-semibold">Training Done By</label>
            <input type="text" name="done_by" value="<?= htmlspecialchars($tr['done_by']) ?>" 
                   class="border p-2 rounded w-full mb-4">

            <!-- Approved By -->
            <label class="block mb-2 font-semibold">Approved By</label>
            <input type="text" name="approved_by" value="<?= htmlspecialchars($tr['approved_by']) ?>" 
                   class="border p-2 rounded w-full mb-4">

            <!-- Actions -->
            <div class="flex justify-end gap-2">
                <button type="button" 
                        onclick="closeModal('modal-<?= $tr['training_id'] ?>')" 
                        class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    Cancel
                </button>
                <button type="submit" 
                        class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="border px-3 py-2 text-center text-gray-500">No trainings found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.remove('hidden'); }
function closeModal(id){ document.getElementById(id).classList.add('hidden'); }
</script>

</body>
</html>
