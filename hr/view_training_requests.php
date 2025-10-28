<?php
session_start();
include 'db_con.php';

// Ensure admin or logged-in user
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ✅ Handle Update Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['training_id'])) {
    $training_id = intval($_POST['training_id']);
    $start_date  = $_POST['start_date'];
    $end_date    = $_POST['end_date'];
    $status      = $_POST['status'];

    if ($training_id > 0) {
        $stmt = $conn->prepare("UPDATE trainings_request 
                                SET start_date = ?, end_date = ?, status = ? 
                                WHERE training_id = ?");
        $stmt->bind_param("sssi", $start_date, $end_date, $status, $training_id);
        $stmt->execute();
        $stmt->close();
        $success = "Training request updated successfully!";
    } else {
        $error = "Invalid training request ID!";
    }
}

// ✅ Fetch all training requests + user details
$sql = "SELECT tr.*, u.full_name, u.email 
        FROM trainings_request tr
        INNER JOIN users u ON tr.user_id = u.user_id
        ORDER BY tr.start_date DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Training Requests</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
.modal {
    display: none;
    position: fixed;
    z-index: 50;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
}
.modal-content {
    background: white;
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
}
</style>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">
    <h1 class="text-3xl font-bold text-blue-700 mb-6">All Training Requests</h1>

    <!-- ✅ Success / Error Message -->
    <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php elseif (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow rounded-lg p-6">
        <table class="min-w-full border border-gray-300 text-sm">
            <thead class="bg-gray-200">
                <tr>
                    <th>#</th>
                    <th>Requester</th>
                    <th>Training Title</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): $i=1; ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="<?= $i%2==0 ? 'bg-gray-50':'bg-white' ?> hover:bg-blue-50">
                        <td class="border px-3 py-2"><?= $i++ ?></td>
                        <td class="border px-3 py-2">
                            <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
                            <small class="text-gray-500"><?= htmlspecialchars($row['email']) ?></small>
                        </td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($row['training_title']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($row['start_date']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($row['end_date']) ?></td>
                        <td class="border px-3 py-2 text-center font-semibold 
                            <?= $row['status']=='Approved'?'text-green-600':
                               ($row['status']=='Denied'?'text-red-600':'text-yellow-600') ?>">
                            <?= htmlspecialchars($row['status']) ?>
                        </td>
                        <td class="border px-3 py-2 text-center">
                            <button 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs"
                                onclick='openViewModal(<?= json_encode($row) ?>)'>
                                View
                            </button>
                            <button 
                                class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs"
                                onclick='openEditModal(<?= json_encode($row) ?>)'>
                                Edit
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center text-gray-500 py-3">No training requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Modal -->
<div id="viewModal" class="modal">
  <div class="modal-content">
    <h2 class="text-xl font-bold mb-4 text-blue-700">Training Details</h2>
    <p><strong>Requester:</strong> <span id="viewRequester"></span></p>
    <p><strong>Email:</strong> <span id="viewEmail"></span></p>
    <p><strong>Training Title:</strong> <span id="viewTitle"></span></p>
    <p><strong>Description:</strong> <span id="viewDescription"></span></p>
    <p><strong>Start Date:</strong> <span id="viewStart"></span></p>
    <p><strong>End Date:</strong> <span id="viewEnd"></span></p>
    <p><strong>Status:</strong> <span id="viewStatus"></span></p>

    <div class="mt-4 text-right">
      <button onclick="closeModal('viewModal')" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Close</button>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <h2 class="text-xl font-bold mb-4 text-green-700">Edit Training Request</h2>
    <form id="editForm" method="POST">
        <input type="hidden" name="training_id" id="editId">
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-semibold">Start Date:</label>
                <input type="date" name="start_date" id="editStart" class="border p-2 rounded w-full">
            </div>
            <div>
                <label class="block text-sm font-semibold">End Date:</label>
                <input type="date" name="end_date" id="editEnd" class="border p-2 rounded w-full">
            </div>
            <div>
                <label class="block text-sm font-semibold">Status:</label>
                <select name="status" id="editStatus" class="border p-2 rounded w-full">
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Denied">Denied</option>
                </select>
            </div>
        </div>
        <div class="mt-4 text-right">
            <button type="button" onclick="closeModal('editModal')" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">Cancel</button>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
        </div>
    </form>
  </div>
</div>

<script>
function openViewModal(data) {
    document.getElementById('viewRequester').textContent = data.full_name;
    document.getElementById('viewEmail').textContent = data.email;
    document.getElementById('viewTitle').textContent = data.training_title;
    document.getElementById('viewDescription').textContent = data.description;
    document.getElementById('viewStart').textContent = data.start_date;
    document.getElementById('viewEnd').textContent = data.end_date;
    document.getElementById('viewStatus').textContent = data.status;
    document.getElementById('viewModal').style.display = 'flex';
}

function openEditModal(data) {
    document.getElementById('editId').value = data.training_id; // ✅ Correct field
    document.getElementById('editStart').value = data.start_date;
    document.getElementById('editEnd').value = data.end_date;
    document.getElementById('editStatus').value = data.status;
    document.getElementById('editModal').style.display = 'flex';
}


function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

window.onclick = function(e) {
    document.querySelectorAll('.modal').forEach(m => {
        if (e.target === m) m.style.display = 'none';
    });
}
</script>
</body>
</html>
