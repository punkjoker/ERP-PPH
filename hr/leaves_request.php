<?php
session_start();
include 'db_con.php';

// Fetch all leave requests with user info
$leaves = [];
$sql = "SELECT l.*, u.full_name 
        FROM leaves l
        JOIN users u ON l.user_id = u.user_id
        ORDER BY l.start_date DESC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $leaves[] = $row;
}

// Handle leave update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_leave'])) {
    $leave_id = intval($_POST['leave_id']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE leaves SET start_date=?, end_date=?, status=? WHERE leave_id=?");
    $stmt->bind_param("sssi", $start_date, $end_date, $status, $leave_id);
    $stmt->execute();
    $stmt->close();

    header("Location: leaves_request.php");
    exit();
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

<div class="ml-64 p-6 max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">All Leave Requests</h1>

    <div class="bg-white shadow rounded-lg p-6">
        <table class="w-full border border-gray-300 rounded text-sm">
            <thead class="bg-gray-200">
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if(!empty($leaves)): $count=1; ?>
                <?php foreach($leaves as $leave): ?>
                    <tr class="<?= ($count%2==0)?'bg-gray-50':'bg-white' ?> hover:bg-blue-50">
                        <td class="border px-3 py-2"><?= $count++ ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['full_name']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['leave_type']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['description']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['start_date']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($leave['end_date']) ?></td>
                        <td class="border px-3 py-2 text-center font-semibold text-blue-700"><?= $leave['total_days'] ?></td>
                        <td class="border px-3 py-2 text-center font-semibold <?= $leave['status']=='Approved'?'text-green-600':($leave['status']=='Denied'?'text-red-600':'text-yellow-600') ?>">
                            <?= $leave['status'] ?>
                        </td>
                        <td class="border px-3 py-2 text-center">
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

<!-- Update Modal -->
<div id="updateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Update Leave</h2>
        <form method="POST">
            <input type="hidden" name="leave_id" id="modal_leave_id">
            <div class="mb-4">
                <label class="block mb-1 font-semibold">Start Date</label>
                <input type="date" name="start_date" id="modal_start_date" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
            </div>
            <div class="mb-4">
                <label class="block mb-1 font-semibold">End Date</label>
                <input type="date" name="end_date" id="modal_end_date" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
            </div>
            <div class="mb-4">
                <label class="block mb-1 font-semibold">Status</label>
                <select name="status" id="modal_status" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Denied">Denied</option>
                </select>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                <button type="submit" name="update_leave" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
            </div>
        </form>
    </div>
</div>

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
