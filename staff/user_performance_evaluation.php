<?php
session_start();
require 'db_con.php';

if (!isset($_SESSION['user_id'])) {
    die("Access denied. Please log in.");
}

$user_id = $_SESSION['user_id'];
$year = date('Y');

// ✅ Check if user already submitted this year's evaluation
$check = $conn->prepare("SELECT id FROM user_performance_evaluation WHERE user_id = ? AND YEAR(eval_date) = ?");
$check->bind_param("ii", $user_id, $year);
$check->execute();
$check_result = $check->get_result();
$already_submitted = $check_result->num_rows > 0;
$check->close();

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

    
    $eval_date = date('Y-m-d');

    // ✅ Insert into main table
    $stmt = $conn->prepare("INSERT INTO user_performance_evaluation (
        user_id, evaluator_name, position_department, academic_qualification, academic_grade,
        professional_qualification, professional_grade, strengths, key_activities, accomplishments,
        challenges, improvement_plan, previous_goals, future_goals, manager_support, employee_concerns,
        overall_comments_appraisee, overall_comments_appraiser, approved_by, approved_signature,
        approved_date, authorized_by, authorized_signature, authorized_date, eval_date
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt->bind_param("issssssssssssssssssssssss",
        $user_id, $evaluator_name, $position_department, $academic_qualification, $academic_grade,
        $professional_qualification, $professional_grade, $strengths, $key_activities, $accomplishments,
        $challenges, $improvement_plan, $previous_goals, $future_goals, $manager_support, $employee_concerns,
        $overall_comments_appraisee, $overall_comments_appraiser, $approved_by, $approved_signature,
        $approved_date, $authorized_by, $authorized_signature, $authorized_date, $eval_date
    );
    $stmt->execute();
    $eval_id = $stmt->insert_id;
    $stmt->close();

    // ✅ Insert behaviours
    foreach ($_POST['behaviour'] as $category => $rating) {
        if (!empty($rating)) {
            $b = $conn->prepare("INSERT INTO user_performance_behaviours (evaluation_id, category, rating) VALUES (?, ?, ?)");
            $b->bind_param("isi", $eval_id, $category, $rating);
            $b->execute();
            $b->close();
        }
    }

    $success = "✅ Your performance evaluation for $year has been submitted successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Performance Evaluation</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-5xl mx-auto bg-white shadow rounded-lg">
    <h1 class="text-2xl font-bold text-blue-700 mb-4">User Performance Evaluation (<?= date('Y') ?>)</h1>

    <?php if(isset($success)): ?>
        <div class="bg-green-100 text-green-800 border border-green-400 px-4 py-2 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!$already_submitted): ?>
    <form method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="evaluator_name" placeholder="Evaluator Name" class="border p-2 rounded" required>
            <input type="text" name="position_department" placeholder="Evaluator Position & Department" class="border p-2 rounded" required>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="academic_qualification" placeholder="Academic Qualification" class="border p-2 rounded">
            <input type="text" name="academic_grade" placeholder="Academic Grade" class="border p-2 rounded">
            <input type="text" name="professional_qualification" placeholder="Professional Qualification" class="border p-2 rounded">
            <input type="text" name="professional_grade" placeholder="Professional Grade" class="border p-2 rounded">
        </div>

        <?php
        $areas = [
            "strengths" => "Your Strengths",
            "key_activities" => "Key Activities Undertaken",
            "accomplishments" => "Accomplishments",
            "challenges" => "Challenges and Solutions",
            "improvement_plan" => "Improvement Plan",
            "previous_goals" => "Previous Goals",
            "future_goals" => "Future Goals",
            "manager_support" => "Support Needed from Management",
            "employee_concerns" => "Concerns or Suggestions"
        ];
        foreach ($areas as $n => $l): ?>
        <div>
            <label class="font-semibold"><?= $l ?></label>
            <textarea name="<?= $n ?>" rows="3" class="w-full border p-2 rounded"></textarea>
        </div>
        <?php endforeach; ?>

        <div>
            <h2 class="text-lg font-semibold text-blue-700 mb-2">Behavioural Ratings</h2>
            <table class="w-full border text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border p-2 text-left">Category</th>
                        <th class="border p-2 text-center">Rating (1-5)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cats = [
                        "Integrity",
                        "Coworker Relations",
                        "Client Relations",
                        "Technical Skills",
                        "Dependability",
                        "Punctuality",
                        "Attendance"
                    ];
                    foreach ($cats as $c): ?>
                    <tr>
                        <td class="border p-2"><?= $c ?></td>
                        <td class="border p-2 text-center">
                            <input type="number" name="behaviour[<?= $c ?>]" min="1" max="5" class="border p-1 rounded text-center w-16">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">Submit Evaluation</button>
    </form>
    <?php endif; ?>

    <!-- ✅ Previous Evaluations Table -->
    <div class="mt-10">
        <h2 class="text-xl font-semibold text-blue-700 mb-4">Previous Evaluations</h2>
        <?php
        $evals = $conn->prepare("SELECT id, eval_date FROM user_performance_evaluation WHERE user_id = ? ORDER BY eval_date DESC");
        $evals->bind_param("i", $user_id);
        $evals->execute();
        $res = $evals->get_result();
        if ($res->num_rows > 0): ?>
            <table class="w-full border text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border p-2 text-left">Year</th>
                        <th class="border p-2 text-left">Evaluation Date</th>
                        <th class="border p-2 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($r = $res->fetch_assoc()): ?>
                    <tr>
                        <td class="border p-2"><?= date('Y', strtotime($r['eval_date'])) ?></td>
                        <td class="border p-2"><?= htmlspecialchars($r['eval_date']) ?></td>
                        <td class="border p-2 text-center">
                            <a href="view_user_evaluation.php?id=<?= $r['id'] ?>" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                View Evaluation
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-gray-600">No previous evaluations found.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
