<?php
include 'db_con.php';

// ✅ Get employee ID from URL
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ✅ Fetch employee details
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("<div style='margin:50px;color:red;font-weight:bold;'>Employee not found.</div>");
}

// ✅ Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evaluator_name = $_POST['evaluator_name'];
    $position_department = $_POST['position_department'];
    $academic_qualification = $_POST['academic_qualification'];
    $academic_grade = $_POST['academic_grade'];
    $professional_qualification = $_POST['professional_qualification'];
    $professional_grade = $_POST['professional_grade'];
    $strengths = $_POST['strengths'];
    $key_activities = $_POST['key_activities'];
    $accomplishments = $_POST['accomplishments'];
    $challenges = $_POST['challenges'];
    $improvement_plan = $_POST['improvement_plan'];
    $previous_goals = $_POST['previous_goals'];
    $future_goals = $_POST['future_goals'];
    $manager_support = $_POST['manager_support'];
    $employee_concerns = $_POST['employee_concerns'];
    $overall_comments_appraisee = $_POST['overall_comments_appraisee'];
    $overall_comments_appraiser = $_POST['overall_comments_appraiser'];
    $employee_signature_date = $_POST['employee_signature_date'] ?? null;
    $evaluator_signature_date = $_POST['evaluator_signature_date'] ?? null;
    $approved_by = $_POST['approved_by'] ?? null;
    $approved_signature = $_POST['approved_signature'] ?? null;
    $approved_date = $_POST['approved_date'] ?? null;
    $authorized_by = $_POST['authorized_by'] ?? null;
    $authorized_signature = $_POST['authorized_signature'] ?? null;
    $authorized_date = $_POST['authorized_date'] ?? null;
    $eval_date = $_POST['eval_date'];

    // ✅ Insert main evaluation
    $stmt = $conn->prepare("INSERT INTO performance_evaluations (
        employee_id, evaluator_name, position_department, academic_qualification, academic_grade, 
        professional_qualification, professional_grade, strengths, key_activities, accomplishments, challenges,
        improvement_plan, previous_goals, future_goals, manager_support, employee_concerns,
        overall_comments_appraisee, overall_comments_appraiser, employee_signature_date, evaluator_signature_date,
        approved_by, approved_signature, approved_date, authorized_by, authorized_signature, authorized_date, eval_date
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt->bind_param("issssssssssssssssssssssssss",
        $employee_id, $evaluator_name, $position_department, $academic_qualification, $academic_grade,
        $professional_qualification, $professional_grade, $strengths, $key_activities, $accomplishments, $challenges,
        $improvement_plan, $previous_goals, $future_goals, $manager_support, $employee_concerns,
        $overall_comments_appraisee, $overall_comments_appraiser, $employee_signature_date, $evaluator_signature_date,
        $approved_by, $approved_signature, $approved_date, $authorized_by, $authorized_signature, $authorized_date, $eval_date
    );
    $stmt->execute();
    $eval_id = $stmt->insert_id;
    $stmt->close();

    // ✅ Insert behaviours
    foreach ($_POST['behaviour'] as $category => $rating) {
        if (!empty($rating)) {
            $stmt = $conn->prepare("INSERT INTO performance_behaviours (eval_id, category, rating) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $eval_id, $category, $rating);
            $stmt->execute();
            $stmt->close();
        }
    }

    // ✅ Insert objectives
    foreach ($_POST['objectives'] as $row) {
        if (!empty($row['objective'])) {
            $stmt = $conn->prepare("INSERT INTO performance_objectives (eval_id, department_objective, target, actual_performance, rating_appraisee, rating_appraiser, rating_consensus) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("isssiii", $eval_id, $row['objective'], $row['target'], $row['actual'], $row['rating_appraisee'], $row['rating_appraiser'], $row['rating_consensus']);
            $stmt->execute();
            $stmt->close();
        }
    }

    $success = "✅ Performance Evaluation for {$employee['first_name']} {$employee['last_name']} saved successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Performance Evaluation - <?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto bg-white shadow rounded-lg">
    <!-- Header Section -->
    <div class="flex items-center justify-between border-b pb-4 mb-6">
        <div class="flex items-center">
            <img src="images/lynn_logo.png" class="w-20 h-20 mr-4" alt="Logo">
            <div>
                <h1 class="text-2xl font-bold text-blue-700">LYNNTECH MANAGEMENT</h1>
                <p class="text-gray-600">EMPLOYEE PERFORMANCE APPRAISAL FORM (QF 45 - 2025)</p>
            </div>
        </div>
        <div class="text-right text-sm text-gray-700">
            <p><strong>Date:</strong> <?= date('Y-m-d') ?></p>
        </div>
    </div>

    <!-- Employee Details -->
    <div class="bg-blue-50 p-4 rounded mb-6 border">
        <h2 class="text-lg font-semibold text-blue-800 mb-2">Employee Information</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-gray-700">
            <p><strong>Name:</strong> <?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?></p>
            <p><strong>Department:</strong> <?= htmlspecialchars($employee['department']) ?></p>
            <p><strong>Position:</strong> <?= htmlspecialchars($employee['position']) ?></p>
            <p><strong>Employee ID:</strong> <?= htmlspecialchars($employee['employee_id']) ?></p>
            <p><strong>Date of Hire:</strong> <?= htmlspecialchars($employee['date_of_hire']) ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($employee['status']) ?></p>
        </div>
    </div>

    <?php if(isset($success)): ?>
        <div class="bg-green-100 text-green-800 border border-green-400 px-4 py-2 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">

        <!-- Evaluator Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="evaluator_name" placeholder="Evaluator Name" class="border p-2 rounded" required>
            <input type="text" name="position_department" placeholder="Evaluator Position & Department" class="border p-2 rounded" required>
            <input type="date" name="eval_date" class="border p-2 rounded" required>
        </div>

        <!-- Qualifications -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="academic_qualification" placeholder="Academic Qualification" class="border p-2 rounded">
            <input type="text" name="academic_grade" placeholder="Academic Grade" class="border p-2 rounded">
            <input type="text" name="professional_qualification" placeholder="Professional Qualification" class="border p-2 rounded">
            <input type="text" name="professional_grade" placeholder="Professional Grade" class="border p-2 rounded">
        </div>

        <!-- Narrative Fields -->
        <?php
        $textAreas = [
            "strengths" => "Employee Strengths",
            "key_activities" => "Key Activities Undertaken",
            "accomplishments" => "Key Accomplishments",
            "challenges" => "Challenges and Solutions",
            "improvement_plan" => "Improvement Plan",
            "previous_goals" => "Previous Goals Status",
            "future_goals" => "Future Goals",
            "manager_support" => "Manager/Organization Support",
            "employee_concerns" => "Employee Concerns"
        ];
        foreach ($textAreas as $name => $label): ?>
            <div>
                <label class="font-semibold"><?= $label ?></label>
                <textarea name="<?= $name ?>" rows="3" class="w-full border p-2 rounded"></textarea>
            </div>
        <?php endforeach; ?>

        <!-- Behaviours -->
        <div>
            <h2 class="text-lg font-semibold text-blue-700 mb-2">Work Behaviours (25%)</h2>
            <table class="w-full border text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border p-2 text-left">Category</th>
                        <th class="border p-2 text-center">Rating (1-5)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $categories = ["Works to full potential","Quality of work","Consistency","Communication","Initiative","Teamwork","Productivity","Creativity","Integrity","Attendance","Dependability"];
                    foreach ($categories as $cat): ?>
                        <tr>
                            <td class="border p-2"><?= htmlspecialchars($cat) ?></td>
                            <td class="border p-2 text-center"><input type="number" name="behaviour[<?= htmlspecialchars($cat) ?>]" min="1" max="5" class="w-16 border p-1 rounded text-center"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Objectives -->
        <div>
            <h2 class="text-lg font-semibold text-blue-700 mb-2">Job Objectives (75%)</h2>
            <table id="objectivesTable" class="w-full border text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border p-2">Objective</th>
                        <th class="border p-2">Target</th>
                        <th class="border p-2">Actual</th>
                        <th class="border p-2">Appraisee</th>
                        <th class="border p-2">Appraiser</th>
                        <th class="border p-2">Consensus</th>
                        <th class="border p-2">Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <button type="button" onclick="addObjectiveRow()" class="mt-2 bg-blue-600 text-white px-4 py-1 rounded hover:bg-blue-700">+ Add Objective</button>
        </div>

        <!-- Comments -->
        <div>
            <label class="font-semibold">Comments (Appraisee)</label>
            <textarea name="overall_comments_appraisee" class="w-full border p-2 rounded" rows="2"></textarea>
            <label class="font-semibold">Comments (Appraiser)</label>
            <textarea name="overall_comments_appraiser" class="w-full border p-2 rounded" rows="2"></textarea>
        </div>

        <!-- Approval Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="approved_by" placeholder="Approved By" class="border p-2 rounded">
            <input type="text" name="approved_signature" placeholder="Approved Signature" class="border p-2 rounded">
            <input type="date" name="approved_date" class="border p-2 rounded">
            <input type="text" name="authorized_by" placeholder="Authorized By" class="border p-2 rounded">
            <input type="text" name="authorized_signature" placeholder="Authorized Signature" class="border p-2 rounded">
            <input type="date" name="authorized_date" class="border p-2 rounded">
        </div>

        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">Submit Evaluation</button>
        <a href="performance_evaluation_list.php" class="ml-2 bg-gray-600 text-white px-6 py-2 rounded hover:bg-gray-700">Back</a>
    </form>
</div>

<script>
function addObjectiveRow() {
    const tbody = document.querySelector('#objectivesTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td class="border p-2"><input name="objectives[][objective]" class="border p-1 rounded w-full"></td>
        <td class="border p-2"><input name="objectives[][target]" class="border p-1 rounded w-full"></td>
        <td class="border p-2"><input name="objectives[][actual]" class="border p-1 rounded w-full"></td>
        <td class="border p-2"><input name="objectives[][rating_appraisee]" type="number" min="1" max="5" class="border p-1 rounded w-16 text-center"></td>
        <td class="border p-2"><input name="objectives[][rating_appraiser]" type="number" min="1" max="5" class="border p-1 rounded w-16 text-center"></td>
        <td class="border p-2"><input name="objectives[][rating_consensus]" type="number" min="1" max="5" class="border p-1 rounded w-16 text-center"></td>
        <td class="border p-2 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-500 font-bold">X</button></td>
    `;
    tbody.appendChild(row);
}
</script>
</body>
</html>
