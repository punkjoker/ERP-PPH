<?php
include 'db_con.php';

// Handle adding expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id']) && isset($_POST['expense_name'])) {
    $employee_id = intval($_POST['employee_id']);
    $expense_name = trim($_POST['expense_name']);
    $expense_date = $_POST['expense_date'];
    $description  = trim($_POST['description']);
    $amount       = floatval($_POST['amount']);
    $status       = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO expenses (employee_id, expense_name, expense_date, description, amount, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssds", $employee_id, $expense_name, $expense_date, $description, $amount, $status);
    $stmt->execute();
    $stmt->close();
    $success = "Expense added successfully!";
}

// Fetch active employees for dropdown
$employees_res = $conn->query("SELECT employee_id, first_name, last_name FROM employees WHERE status='Active' ORDER BY first_name");
$employees = $employees_res->fetch_all(MYSQLI_ASSOC);

// Filters
$search = trim($_GET['search'] ?? '');
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Build query dynamically
$query = "SELECT e.*, emp.first_name, emp.last_name FROM expenses e JOIN employees emp ON e.employee_id = emp.employee_id WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $query .= " AND CONCAT(emp.first_name,' ',emp.last_name) LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}
if ($from_date !== '') {
    $query .= " AND e.expense_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}
if ($to_date !== '') {
    $query .= " AND e.expense_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}

$query .= " ORDER BY e.expense_date DESC";
$stmt = $conn->prepare($query);
if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$expenses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate total
$total_amount = 0;
foreach($expenses as $exp) $total_amount += $exp['amount'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Expenses</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">Manage Employee Expenses</h1>

    <?php if(isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Add Expense Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <select name="employee_id" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                    <option value="">Select Employee</option>
                    <?php foreach($employees as $emp): ?>
                        <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="expense_name" placeholder="Expense Name e.g Lunch, Transport" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300" required>
                <input type="date" name="expense_date" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300" required>
                <input type="text" name="description" placeholder="Expense Description" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="number" step="0.01" name="amount" placeholder="Amount Requested" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300" required>
                <select name="status" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                    <option value="Paid">Paid</option>
                    <option value="Not Paid">Not Paid</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">Add Expense</button>
        </form>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-6 mb-4">
        <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
            <input type="text" name="search" placeholder="Search by employee name" value="<?= htmlspecialchars($search) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300 w-full md:w-64">
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Filter</button>
            <a href="manage_expenses.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Reset</a>
            <button type="button" onclick="printContent('expenses-table')" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">Print</button>
            <a href="download_expenses.php?search=<?= urlencode($search) ?>&from_date=<?= $from_date ?>&to_date=<?= $to_date ?>" 
   class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
   Download Report
</a>

        </form>
    </div>

    <!-- Expenses List -->
    <div class="bg-white shadow rounded-lg p-6" id="expenses-table">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Expenses List</h2>
        <table class="w-full border border-gray-300 rounded">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border px-3 py-2">#</th>
                    <th class="border px-3 py-2">Employee</th>
                    <th class="border px-3 py-2">Expense Name</th>
                    <th class="border px-3 py-2">Date</th>
                    <th class="border px-3 py-2">Description</th>
                    <th class="border px-3 py-2">Amount</th>
                    <th class="border px-3 py-2">Status</th>
                    <th class="border px-3 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($expenses)): $count=1; ?>
                    <?php foreach($expenses as $exp): ?>
                        <tr class="<?= ($count % 2 == 0) ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">
                            <td class="border px-3 py-2"><?= $count++ ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($exp['first_name'].' '.$exp['last_name']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($exp['expense_name']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($exp['expense_date']) ?></td>
                            <td class="border px-3 py-2"><?= htmlspecialchars($exp['description']) ?></td>
                            <td class="border px-3 py-2"><?= number_format($exp['amount'],2) ?></td>
                            <td class="border px-3 py-2 text-center">
                                <?php if($exp['status']=='Paid'): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded"><?= $exp['status'] ?></span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded"><?= $exp['status'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="border px-3 py-2 space-x-2">
                                <!-- Edit -->
                                <button onclick="openModal('modal-<?= $exp['expense_id'] ?>')" class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition">Edit</button>
                                <!-- Delete -->
                                <a href="delete_expense.php?id=<?= $exp['expense_id'] ?>" onclick="return confirm('Delete this expense?')" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition">Delete</a>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div id="modal-<?= $exp['expense_id'] ?>" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden overflow-auto p-4">
                            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md max-h-full overflow-auto">
                                <h2 class="text-xl font-bold mb-4">Edit Expense Status</h2>
                                <form method="POST" action="edit_expense.php">
                                    <input type="hidden" name="expense_id" value="<?= $exp['expense_id'] ?>">
                                    <label class="block mb-2 font-semibold">Status:</label>
                                    <select name="status" class="border p-2 rounded w-full mb-4">
                                        <option value="Paid" <?= ($exp['status']=='Paid') ? 'selected' : '' ?>>Paid</option>
                                        <option value="Not Paid" <?= ($exp['status']=='Not Paid') ? 'selected' : '' ?>>Not Paid</option>
                                    </select>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" onclick="closeModal('modal-<?= $exp['expense_id'] ?>')" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Cancel</button>
                                        <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 transition">Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="border px-3 py-2 text-center text-gray-500">No expenses found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="bg-gray-200 font-semibold">
                    <td colspan="5" class="text-right px-3 py-2">Total:</td>
                    <td class="px-3 py-2"><?= number_format($total_amount,2) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.remove('hidden'); }
function closeModal(id){ document.getElementById(id).classList.add('hidden'); }

// Print function
function printContent(divId){
    var content = document.getElementById(divId).innerHTML;
    var myWindow = window.open('', '', 'width=900,height=700');
    myWindow.document.write('<html><head><title>Print Expenses</title>');
    myWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">');
    myWindow.document.write('</head><body class="p-4">'+content+'</body></html>');
    myWindow.document.close();
    myWindow.focus();
    myWindow.print();
    myWindow.close();
}
</script>

</body>
</html>
