<?php 
include 'db_con.php';

// Fetch all employees from users table where group is 'staff' and active
$query = "
    SELECT u.user_id, u.full_name, u.national_id
    FROM users u
    INNER JOIN groups g ON u.group_id = g.group_id
    WHERE g.group_name = 'staff' 
      AND u.status = 'active'
";
$result = $conn->query($query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['employee_id']);
    $evaluator_name = $_POST['evaluator_name'];
    $evaluator_department = $_POST['evaluator_department'];
    $eval_date = $_POST['eval_date'];

    // Behavior categories as per your table
    $fields = [
        'quality_of_work',
        'work_consistency',
        'communication',
        'independent_work',
        'takes_initiative',
        'exercises_teamwork',
        'productivity',
        'creativity',
        'honesty'
    ];

    $values = [];
    $sum = 0;
    $count = 0;

    foreach ($fields as $field) {
        $values[$field] = intval($_POST[$field]);
        $sum += $values[$field];
        $count++;
    }

    // Calculate total score (average out of 5)
    $total_score = round(($sum / ($count * 5)) * 100, 2); // e.g., percent score

    // Insert record
    $sql = "INSERT INTO employee_appraisal 
        (user_id, evaluator_name, evaluator_department, eval_date, 
         quality_of_work, work_consistency, communication, independent_work, 
         takes_initiative, exercises_teamwork, productivity, creativity, honesty, total_score)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "isssiiiiiiiiid",
        $user_id,
        $evaluator_name,
        $evaluator_department,
        $eval_date,
        $values['quality_of_work'],
        $values['work_consistency'],
        $values['communication'],
        $values['independent_work'],
        $values['takes_initiative'],
        $values['exercises_teamwork'],
        $values['productivity'],
        $values['creativity'],
        $values['honesty'],
        $total_score
    );

    if ($stmt->execute()) {
        echo "<script>alert('Employee appraisal saved successfully!');</script>";
    } else {
        echo "<script>alert('Error saving appraisal. Please check table columns.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Appraisal - Form A</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-8 max-w-5xl mx-auto bg-white rounded-2xl shadow-lg">
    <h1 class="text-2xl font-bold text-blue-700 mb-6 text-center">Employee Appraisal - Form A (Work Related Behaviours)</h1>

    <form method="POST" class="space-y-6">
        <!-- Employee Selection -->
        <div>
            <label class="block font-semibold text-gray-700 mb-1">Select Employee (Name - National ID):</label>
            <select name="employee_id" required class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-200">
                <option value="">-- Select Employee --</option>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <option value="<?= $row['user_id'] ?>">
                        <?= htmlspecialchars($row['full_name']) ?> - <?= htmlspecialchars($row['national_id']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Evaluator Details -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block font-semibold text-gray-700 mb-1">Evaluator Name:</label>
                <input type="text" name="evaluator_name" required class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-200">
            </div>
            <div>
                <label class="block font-semibold text-gray-700 mb-1">Evaluator Department:</label>
                <input type="text" name="evaluator_department" required class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-200">
            </div>
            <div>
                <label class="block font-semibold text-gray-700 mb-1">Evaluation Date:</label>
                <input type="date" name="eval_date" required class="w-full border rounded-lg p-2 focus:ring focus:ring-blue-200">
            </div>
        </div>

        <!-- Criteria Table -->
        <h2 class="text-xl font-semibold text-blue-700 mt-8 mb-3">Form A: Work Related Behaviours (Weight 25%)</h2>
        <div class="overflow-x-auto">
            <table class="w-full border text-sm">
                <thead class="bg-blue-700 text-white">
                    <tr>
                        <th class="border p-2 text-left">Criteria</th>
                        <th class="border p-2">1<br>Unacceptable</th>
                        <th class="border p-2">2<br>Weak</th>
                        <th class="border p-2">3<br>Good</th>
                        <th class="border p-2">4<br>Very Good</th>
                        <th class="border p-2">5<br>Excellent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $criteria = [
                        'quality_of_work' => 'Quality of work',
                        'work_consistency' => 'Work consistency',
                        'communication' => 'Communication',
                        'independent_work' => 'Independent work',
                        'takes_initiative' => 'Takes initiative',
                        'exercises_teamwork' => 'Exercises teamwork',
                        'productivity' => 'Productivity',
                        'creativity' => 'Creativity',
                        'honesty' => 'Honesty'
                    ];

                    foreach ($criteria as $field => $label): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border p-2 text-left"><?= $label ?></td>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <td class="border p-2 text-center">
                                    <input type="radio" name="<?= $field ?>" value="<?= $i ?>" required>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-center mt-6">
            <button type="submit" class="bg-blue-700 text-white px-6 py-2 rounded-lg hover:bg-blue-800 transition">
                Save Appraisal
            </button>
        </div>
    </form>
    <?php
// Fetch existing appraisals joined with user details
$appraisal_query = "
    SELECT 
        u.full_name, 
        u.national_id, 
        a.eval_date, 
        a.evaluator_name
    FROM employee_appraisal a
    INNER JOIN users u ON a.user_id = u.user_id
    ORDER BY a.eval_date DESC
";
$appraisal_result = $conn->query($appraisal_query);
?>

<!-- Display Appraisal Records -->
<h2 class="text-xl font-semibold text-blue-700 mt-10 mb-4 border-b pb-2">Recent Employee Appraisals</h2>

<div class="overflow-x-auto">
    <table class="w-full border text-sm">
        <thead class="bg-blue-700 text-white">
            <tr>
                <th class="border p-2 text-left">Employee Name</th>
                <th class="border p-2 text-left">National ID</th>
                <th class="border p-2 text-left">Evaluation Date</th>
                <th class="border p-2 text-left">Evaluator Name</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($appraisal_result->num_rows > 0): ?>
                <?php while ($row = $appraisal_result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="border p-2"><?= htmlspecialchars($row['full_name']) ?></td>
                        <td class="border p-2"><?= htmlspecialchars($row['national_id']) ?></td>
                        <td class="border p-2"><?= htmlspecialchars($row['eval_date']) ?></td>
                        <td class="border p-2"><?= htmlspecialchars($row['evaluator_name']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center border p-3 text-gray-500">No appraisal records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</div>
</body>
</html>
