<?php 
include 'db_con.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Performance Evaluation List</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">Performance Evaluation</h1>

    <!-- Search Form -->
    <form method="GET" class="mb-4 flex items-center gap-2">
        <input type="text" name="search" placeholder="Search by name" 
            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
            class="border p-2 rounded focus:ring-2 focus:ring-blue-300 w-64">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Search</button>
        <a href="performance_evaluation_list.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Reset</a>
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

    // Fetch evaluation status
    $evals = [];
    $res = $conn->query("SELECT employee_id, eval_date FROM performance_evaluations ORDER BY eval_date DESC");
    while ($row = $res->fetch_assoc()) {
        $evals[$row['employee_id']] = $row['eval_date'];
    }
    ?>

    <!-- Employee Table -->
    <div class="bg-white shadow rounded-lg p-4">
        <table class="w-full border border-gray-300 rounded text-sm">
            <thead class="bg-gray-200 text-gray-700">
                <tr>
                    <th class="border px-2 py-1">#</th>
                    <th class="border px-2 py-1">Name</th>
                    <th class="border px-2 py-1">Department</th>
                    <th class="border px-2 py-1">Position</th>
                    <th class="border px-2 py-1">Date Hired</th>
                    <th class="border px-2 py-1">Last Evaluation</th>
                    <th class="border px-2 py-1">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($employees)): $count = 1; ?>
                    <?php foreach ($employees as $row): 
                        $lastEval = $evals[$row['employee_id']] ?? null;
                    ?>
                    <tr class="hover:bg-gray-50 text-sm">
                        <td class="border px-2 py-1"><?= $count++ ?></td>
                        <td class="border px-2 py-1 font-semibold text-gray-700"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['department']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['position']) ?></td>
                        <td class="border px-2 py-1"><?= htmlspecialchars($row['date_of_hire']) ?></td>
                        <td class="border px-2 py-1 text-center">
                            <?= $lastEval ? date('d M Y', strtotime($lastEval)) : '<span class="text-gray-400 italic">No record</span>' ?>
                        </td>
                        <td class="border px-2 py-1 space-x-1 text-center">
                            <a href="perfomance_evaluation.php?id=<?= $row['employee_id'] ?>" 
                               class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition text-xs">Update Evaluation</a>
                            <a href="view_evaluation.php?id=<?= $row['employee_id'] ?>" 
                               class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition text-xs">View Evaluation</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="border px-3 py-2 text-center text-gray-500">No employees found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
