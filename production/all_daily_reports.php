<?php  
session_start();
include 'db_con.php';

// ✅ Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ✅ Fetch all reports (with optional date filter)
$reports = [];

$query = "
    SELECT dr.*, u.full_name 
    FROM daily_reports dr 
    JOIN users u ON dr.user_id = u.user_id
";

$params = [];
$types = "";

// ✅ Optional date filter
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $query .= " WHERE dr.report_date BETWEEN ? AND ? ORDER BY dr.report_date DESC";
    $params = [$_GET['from_date'], $_GET['to_date']];
    $types = "ss";
} else {
    $query .= " ORDER BY dr.report_date DESC";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $reports[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Staff Daily Reports</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
    /* ✅ Thinner rows and subtle hover shadow */
    table tr {
        transition: all 0.2s ease-in-out;
    }
    table tr:hover {
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        transform: scale(1.01);
        background-color: #f8fafc;
    }
</style>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">All Staff Daily Reports</h1>

    <!-- ✅ Filter Reports by Date and Search -->
    <div class="bg-white shadow rounded-lg p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">From Date</label>
                <input type="date" name="from_date" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>" class="border p-2 rounded w-full">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">To Date</label>
                <input type="date" name="to_date" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>" class="border p-2 rounded w-full">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Search by Name</label>
                <input type="text" id="searchName" placeholder="Type staff name..." class="border p-2 rounded w-full">
            </div>
            <div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Filter</button>
            </div>
            <?php if(isset($_GET['from_date']) || isset($_GET['to_date'])): ?>
            <div>
                <a href="all_daily_reports.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ✅ All Reports Table -->
    <div class="bg-white shadow-lg rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Reports Overview</h2>
        <div class="overflow-x-auto">
        <table class="w-full border border-gray-300 rounded text-sm" id="reportsTable">
            <thead class="bg-gray-200 text-gray-700">
                <tr class="text-left">
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">Staff Name</th>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Day</th>
                    <th class="px-3 py-2">Tasks</th>
                    <th class="px-3 py-2 text-center">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if(!empty($reports)): $count=1; ?>
                <?php foreach($reports as $report): 
                    $stmt = $conn->prepare("SELECT COUNT(*) AS task_count FROM daily_tasks WHERE report_id=?");
                    $stmt->bind_param("i", $report['report_id']);
                    $stmt->execute();
                    $task_count = $stmt->get_result()->fetch_assoc()['task_count'];
                    $stmt->close();
                ?>
                    <tr class="hover:bg-blue-50 border-b border-gray-200">
                        <td class="px-3 py-1"><?= $count++ ?></td>
                        <td class="px-3 py-1 font-medium text-gray-800"><?= htmlspecialchars($report['full_name']) ?></td>
                        <td class="px-3 py-1"><?= htmlspecialchars($report['report_date']) ?></td>
                        <td class="px-3 py-1"><?= htmlspecialchars($report['day_name']) ?></td>
                        <td class="px-3 py-1 text-center"><?= $task_count ?></td>
                        <td class="px-3 py-1 text-center">
                            <button onclick="viewTasks(<?= $report['report_id'] ?>)" 
                                class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition">
                                View
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-gray-500 py-3">No reports found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ✅ Popup Modal -->
<div id="taskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div id="modalBox" class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-2xl transform scale-95 opacity-0 transition-all duration-300">
    <div class="flex justify-between items-center mb-4 border-b pb-2">
      <h3 class="text-2xl font-bold text-blue-700">Daily Report Details</h3>
      <button onclick="closeModal()" class="text-gray-500 hover:text-red-600 text-2xl font-bold">&times;</button>
    </div>
    <p id="reportInfo" class="text-gray-600 text-sm mb-4"></p>
    <div class="overflow-x-auto max-h-96">
      <table class="w-full border border-gray-300 rounded text-sm" id="tasksTable">
        <thead class="bg-gray-200 sticky top-0">
          <tr>
            <th class="border px-2 py-1">#</th>
            <th class="border px-2 py-1">Task Name</th>
            <th class="border px-2 py-1">Time Taken</th>
            <th class="border px-2 py-1">Overseen By</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="text-right mt-4">
      <button onclick="closeModal()" class="bg-red-500 text-white px-5 py-2 rounded-lg hover:bg-red-600 transition">Close</button>
    </div>
  </div>
</div>

<script>
// ✅ Search filter by staff name
$('#searchName').on('keyup', function() {
    const value = $(this).val().toLowerCase();
    $('#reportsTable tbody tr').filter(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
});

// ✅ Fetch and show tasks popup
function viewTasks(report_id){
    $.get('fetch_tasks.php', {report_id: report_id}, function(data){
        const tbody = $('#tasksTable tbody');
        tbody.empty();
        if (data.tasks.length === 0) {
            tbody.append(`<tr><td colspan="4" class="text-center text-gray-500 py-2">No tasks found</td></tr>`);
        } else {
            data.tasks.forEach((task, i) => {
                tbody.append(`<tr class="hover:bg-gray-50">
                    <td class="border px-2 py-1">${i+1}</td>
                    <td class="border px-2 py-1">${task.task_name}</td>
                    <td class="border px-2 py-1">${task.time_taken}</td>
                    <td class="border px-2 py-1">${task.overseen_by}</td>
                </tr>`);
            });
        }
        $('#reportInfo').text(`Date: ${data.report_date} (${data.day_name})`);
        openModal();
    }, 'json');
}

// ✅ Smooth modal animation
function openModal() {
  const modal = $('#taskModal');
  const box = $('#modalBox');
  modal.removeClass('hidden').addClass('flex');
  setTimeout(() => box.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100'), 10);
}

function closeModal() {
  const modal = $('#taskModal');
  const box = $('#modalBox');
  box.removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
  setTimeout(() => modal.addClass('hidden'), 300);
}
</script>

</body>
</html>
