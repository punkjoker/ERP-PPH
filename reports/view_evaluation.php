<?php
include 'db_con.php';

$employee_id = intval($_GET['id'] ?? 0);
$year = intval($_GET['year'] ?? date('Y'));


// ✅ Fetch employee
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("<div style='margin:50px;color:red;font-weight:bold;'>Employee not found.</div>");
}

// ✅ Fetch evaluation for selected year
$eval_stmt = $conn->prepare("SELECT * FROM performance_evaluations WHERE employee_id = ? AND YEAR(eval_date) = ? ORDER BY eval_date DESC LIMIT 1");
$eval_stmt->bind_param("ii", $employee_id, $year);
$eval_stmt->execute();
$evaluation = $eval_stmt->get_result()->fetch_assoc();
$eval_stmt->close();

if (!$evaluation) {
    $noEval = true;
} else {
    $eval_id = $evaluation['eval_id'];

    // ✅ Fetch behaviours
    $behaviours = $conn->query("SELECT category, rating FROM performance_behaviours WHERE eval_id = $eval_id")->fetch_all(MYSQLI_ASSOC);

    // ✅ Fetch objectives
    $objectives = $conn->query("SELECT * FROM performance_objectives WHERE eval_id = $eval_id")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Performance Evaluation - <?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto bg-white shadow rounded-lg">

    <!-- Header -->
    <div class="flex justify-between items-center border-b pb-4 mb-6">
        <div class="flex items-center">
            <img src="images/lynn_logo.png" alt="Logo" class="w-20 h-20 mr-4">
            <div>
                <h1 class="text-2xl font-bold text-blue-700">LYNNTECH MANAGEMENT</h1>
                <p class="text-gray-600">Employee Performance Evaluation Report (QF 45 - <?= htmlspecialchars($year) ?>)</p>
            </div>
        </div>
        <form method="GET" class="flex gap-2 items-center">
            <input type="hidden" name="id" value="<?= $employee_id ?>">
            <select name="year" class="border p-2 rounded">
                <?php
                $years = $conn->query("SELECT DISTINCT YEAR(eval_date) AS yr FROM performance_evaluations WHERE employee_id = $employee_id ORDER BY yr DESC");
                while ($y = $years->fetch_assoc()) {
                    $selected = ($y['yr'] == $year) ? 'selected' : '';
                    echo "<option value='{$y['yr']}' $selected>{$y['yr']}</option>";
                }
                ?>
            </select>
            <button class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">View</button>
        </form>
    </div>

    <!-- Employee Info -->
    <div class="bg-blue-50 p-4 rounded border mb-6">
        <h2 class="text-lg font-semibold text-blue-800 mb-2">Employee Details</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-gray-700">
            <p><strong>Name:</strong> <?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?></p>
            <p><strong>Department:</strong> <?= htmlspecialchars($employee['department']) ?></p>
            <p><strong>Position:</strong> <?= htmlspecialchars($employee['position']) ?></p>
            <p><strong>Date of Hire:</strong> <?= htmlspecialchars($employee['date_of_hire']) ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($employee['status']) ?></p>
        </div>
    </div>

    <?php if(isset($noEval)): ?>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded">
            No performance evaluation found for <?= $year ?>.
        </div>
    <?php else: ?>
        <!-- Summary Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <p><strong>Evaluator:</strong> <?= htmlspecialchars($evaluation['evaluator_name']) ?></p>
            <p><strong>Evaluation Date:</strong> <?= htmlspecialchars($evaluation['eval_date']) ?></p>
            <p><strong>Academic Qualification:</strong> <?= htmlspecialchars($evaluation['academic_qualification']) ?></p>
            <p><strong>Professional Qualification:</strong> <?= htmlspecialchars($evaluation['professional_qualification']) ?></p>
        </div>

        <!-- Work Behaviour Chart -->
        <div class="bg-gray-50 border p-4 rounded mb-8">
            <h3 class="text-lg font-semibold text-blue-700 mb-2">Work Behaviour Ratings Overview</h3>
            <canvas id="behaviourChart" height="120"></canvas>
        </div>

        <!-- Narrative Sections -->
        <?php
        $sections = [
            "strengths" => "Employee Strengths",
            "key_activities" => "Key Activities Undertaken",
            "accomplishments" => "Accomplishments",
            "challenges" => "Challenges and Solutions",
            "improvement_plan" => "Improvement Plan",
            "previous_goals" => "Status of Previous Goals",
            "future_goals" => "Future Goals",
            "manager_support" => "Manager/Organization Support",
            "employee_concerns" => "Employee Concerns",
            "overall_comments_appraisee" => "Comments by Appraisee",
            "overall_comments_appraiser" => "Comments by Appraiser"
        ];
        foreach ($sections as $field => $label):
            if (!empty($evaluation[$field])):
        ?>
        <div class="mb-6">
            <h4 class="text-lg font-semibold text-blue-700 mb-1"><?= $label ?></h4>
            <p class="border p-3 rounded bg-gray-50 text-gray-700"><?= nl2br(htmlspecialchars($evaluation[$field])) ?></p>
        </div>
        <?php endif; endforeach; ?>

        <!-- Objectives Table -->
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-blue-700 mb-2">Performance Objectives</h3>
            <table class="w-full border text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border p-2">Objective</th>
                        <th class="border p-2">Target</th>
                        <th class="border p-2">Actual</th>
                        <th class="border p-2">Appraisee</th>
                        <th class="border p-2">Appraiser</th>
                        <th class="border p-2">Consensus</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($objectives as $obj): ?>
                    <tr>
                        <td class="border p-2"><?= htmlspecialchars($obj['department_objective']) ?></td>
                        <td class="border p-2"><?= htmlspecialchars($obj['target']) ?></td>
                        <td class="border p-2"><?= htmlspecialchars($obj['actual_performance']) ?></td>
                        <td class="border p-2 text-center"><?= htmlspecialchars($obj['rating_appraisee']) ?></td>
                        <td class="border p-2 text-center"><?= htmlspecialchars($obj['rating_appraiser']) ?></td>
                        <td class="border p-2 text-center"><?= htmlspecialchars($obj['rating_consensus']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Approval Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8 text-gray-700">
            <div><strong>Approved By:</strong><br><?= htmlspecialchars($evaluation['approved_by']) ?></div>
            <div><strong>Authorized By:</strong><br><?= htmlspecialchars($evaluation['authorized_by']) ?></div>
            <div><strong>Evaluator:</strong><br><?= htmlspecialchars($evaluation['evaluator_name']) ?></div>
        </div>

        <div class="mt-10 flex gap-3">
            <a href="perfomance_evaluation_list.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">Back</a>
            <a href="perfomance_evaluation.php?id=<?= $employee_id ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update Evaluation</a>
        </div>
    <?php endif; ?>
</div>

<?php if(!$noEval): ?>
<script>
// Chart data
const labels = <?= json_encode(array_column($behaviours, 'category')) ?>;
const data = <?= json_encode(array_column($behaviours, 'rating')) ?>;

const ctx = document.getElementById('behaviourChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Rating (1-5)',
            data: data,
            borderWidth: 1,
            backgroundColor: 'rgba(37, 99, 235, 0.6)',
            borderColor: 'rgba(37, 99, 235, 1)',
        }]
    },
    options: {
        scales: {
            y: { beginAtZero: true, max: 5 }
        },
        plugins: {
            legend: { display: false }
        }
    }
});
</script>
<?php endif; ?>

</body>
</html>
