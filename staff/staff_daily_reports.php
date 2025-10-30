<?php  
session_start();
include 'db_con.php';

// --- Ensure user is logged in ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle new report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report'])) {
    $report_date = $_POST['report_date'];
    $day_name    = $_POST['day_name'];

    // Check if report already exists
    $stmt = $conn->prepare("SELECT report_id FROM daily_reports WHERE user_id=? AND report_date=?");
    $stmt->bind_param("is", $user_id, $report_date);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $report = $res->fetch_assoc();
        $report_id = $report['report_id'];
    } else {
        // Insert new report
        $stmt2 = $conn->prepare("INSERT INTO daily_reports (user_id, report_date, day_name, created_at) VALUES (?, ?, ?, NOW())");
        $stmt2->bind_param("iss", $user_id, $report_date, $day_name);
        $stmt2->execute();
        $report_id = $stmt2->insert_id;
        $stmt2->close();
    }
    $stmt->close();

    // Insert multiple tasks
    if (!empty($_POST['task_name'])) {
        foreach ($_POST['task_name'] as $i => $task_name) {
            if (trim($task_name) === '') continue;
            $time_taken  = $_POST['time_taken'][$i];
            $overseen_by = $_POST['overseen_by'][$i];
            $stmt3 = $conn->prepare("INSERT INTO daily_tasks (report_id, task_name, time_taken, overseen_by) VALUES (?, ?, ?, ?)");
            $stmt3->bind_param("isss", $report_id, $task_name, $time_taken, $overseen_by);
            $stmt3->execute();
            $stmt3->close();
        }
    }

    $success = "Daily report saved successfully!";
}

// Fetch user's daily reports with optional date filter
$reports = [];
$query = "SELECT * FROM daily_reports WHERE user_id=?";

if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $query .= " AND report_date BETWEEN ? AND ? ORDER BY report_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $_GET['from_date'], $_GET['to_date']);
} else {
    $query .= " ORDER BY report_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $reports[] = $row;
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Daily Reports</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-5xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">Daily Reports</h1>

    <?php if(isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Daily Report Task Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <form method="POST" id="reportForm">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <input type="date" name="report_date" id="report_date" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="day_name" id="day_name" placeholder="Day (e.g. Monday)" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
            </div>

            <table class="w-full border border-gray-300 text-sm mb-4" id="taskTable">
                <thead class="bg-gray-200">
                    <tr>
                        <th>Task Name</th>
                        <th>Time Taken</th>
                        <th>Overseen By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="text" name="task_name[]" placeholder="Task Name" class="border p-1 rounded w-full"></td>
                        <td><input type="text" name="time_taken[]" placeholder="e.g. 2 hrs" class="border p-1 rounded w-full"></td>
                        <td><input type="text" name="overseen_by[]" placeholder="Supervisor" class="border p-1 rounded w-full"></td>
                        <td class="text-center">
                            <button type="button" class="bg-green-500 text-white px-2 py-1 rounded addRow">+</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <button type="submit" name="save_report" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Save Report</button>
        </form>
    </div>
<!-- Filter Reports by Date -->
<div class="bg-white shadow rounded-lg p-4 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">From Date</label>
            <input type="date" name="from_date" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>" class="border p-2 rounded w-full">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">To Date</label>
            <input type="date" name="to_date" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>" class="border p-2 rounded w-full">
        </div>
        <div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Filter</button>
        </div>
        <?php if(isset($_GET['from_date']) || isset($_GET['to_date'])): ?>
        <div>
            <a href="daily_reports.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Clear</a>
        </div>
        <?php endif; ?>
    </form>
</div>

    <!-- Daily Reports Table -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">My Daily Reports</h2>
        <table class="w-full border border-gray-300 rounded text-sm">
            <thead class="bg-gray-200">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Tasks</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if(!empty($reports)): $count=1; ?>
                <?php foreach($reports as $report):
                    $stmt = $conn->prepare("SELECT COUNT(*) as task_count FROM daily_tasks WHERE report_id=?");
                    $stmt->bind_param("i", $report['report_id']);
                    $stmt->execute();
                    $task_count = $stmt->get_result()->fetch_assoc()['task_count'];
                    $stmt->close();
                ?>
                    <tr class="<?= ($count%2==0)?'bg-gray-50':'bg-white' ?> hover:bg-blue-50">
                        <td class="border px-3 py-2"><?= $count++ ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($report['report_date']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($report['day_name']) ?></td>
                        <td class="border px-3 py-2 text-center"><?= $task_count ?></td>
                        <td class="border px-3 py-2 text-center">
                            <button onclick="viewTasks(<?= $report['report_id'] ?>)" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">View</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" class="text-center text-gray-500 py-3">No reports found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<!-- Popup Modal -->
<div id="taskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 opacity-0 transition-opacity duration-300">
  <div id="modalBox" class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-xl transform scale-95 opacity-0 transition-all duration-300">
    <div class="flex justify-between items-center mb-4 border-b pb-2">
      <h3 class="text-2xl font-bold text-blue-700">Daily Report Details</h3>
      <button onclick="closeModal()" class="text-gray-500 hover:text-red-600 text-2xl font-bold">&times;</button>
    </div>

    <p id="reportInfo" class="text-gray-600 text-sm mb-4"></p>

    <div class="overflow-x-auto max-h-80">
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

</div>

<script>
// --- Auto-fill day when date is picked ---
$('#report_date').on('change', function() {
    const date = new Date(this.value);
    const days = ["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"];
    $('#day_name').val(days[date.getUTCDay()]);
});

// --- Add new row ---
$(document).on('click', '.addRow', function(){
    const newRow = `<tr>
        <td><input type="text" name="task_name[]" placeholder="Task Name" class="border p-1 rounded w-full"></td>
        <td><input type="text" name="time_taken[]" placeholder="e.g. 2 hrs" class="border p-1 rounded w-full"></td>
        <td><input type="text" name="overseen_by[]" placeholder="Supervisor" class="border p-1 rounded w-full"></td>
        <td class="text-center">
            <button type="button" class="bg-red-500 text-white px-2 py-1 rounded removeRow">-</button>
        </td>
    </tr>`;
    $('#taskTable tbody').append(newRow);
});

// --- Remove row ---
$(document).on('click', '.removeRow', function(){
    $(this).closest('tr').remove();
});
function viewTasks(report_id) {
    console.log("Fetching tasks for report_id:", report_id);
    $.get('fetch_tasks.php', {report_id: report_id}, function (data) {
        console.log("Response:", data); // 👈 add this line

        const tbody = $('#tasksTable tbody');
        tbody.empty();
        if (data.tasks.length === 0) {
            tbody.append(`<tr><td colspan="4" class="text-center text-gray-500 py-2">No tasks found</td></tr>`);
        } else {
            data.tasks.forEach((task, i) => {
                tbody.append(`<tr>
                    <td class="border px-2 py-1">${i + 1}</td>
                    <td class="border px-2 py-1">${task.task_name}</td>
                    <td class="border px-2 py-1">${task.time_taken}</td>
                    <td class="border px-2 py-1">${task.overseen_by}</td>
                </tr>`);
            });
        }
        $('#reportInfo').text(`Date: ${data.report_date} (${data.day_name})`);
        openModal();
    }, 'json')
    .fail(function(xhr, status, error) {
        console.error("AJAX Error:", status, error, xhr.responseText);
    });
}

// --- View tasks modal ---
function viewTasks(report_id){
    $.get('fetch_tasks.php', {report_id: report_id}, function(data){
        const tbody = $('#tasksTable tbody');
        tbody.empty();
        if (data.tasks.length === 0) {
            tbody.append(`<tr><td colspan="4" class="text-center text-gray-500 py-2">No tasks found</td></tr>`);
        } else {
            data.tasks.forEach((task, i) => {
                tbody.append(`<tr>
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
// --- Modal Animation Functions ---
function openModal() {
  const modal = $('#taskModal');
  const box = $('#modalBox');
  modal.removeClass('hidden opacity-0').addClass('flex opacity-100');
  setTimeout(() => {
    box.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
  }, 10);
}

function closeModal() {
  const modal = $('#taskModal');
  const box = $('#modalBox');
  box.removeClass('scale-100 opacity-100').addClass('scale-95 opacity-0');
  modal.removeClass('opacity-100').addClass('opacity-0');
  setTimeout(() => {
    modal.addClass('hidden');
  }, 300);
}

</script>

</body>
</html>
