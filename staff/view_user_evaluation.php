<?php
session_start();
require 'db_con.php';

if (!isset($_GET['id'])) {
    die("Missing evaluation ID.");
}

$eval_id = intval($_GET['id']);

// ✅ Fetch main evaluation + user details
$sql = "
SELECT e.*, u.full_name, u.national_id
FROM user_performance_evaluation e
JOIN users u ON e.user_id = u.user_id
WHERE e.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $eval_id);
$stmt->execute();
$evaluation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$evaluation) {
    die("Evaluation not found.");
}

// ✅ Fetch behavioural ratings
$ratings_stmt = $conn->prepare("SELECT category, rating FROM user_performance_behaviours WHERE evaluation_id = ?");
$ratings_stmt->bind_param("i", $eval_id);
$ratings_stmt->execute();
$ratings_result = $ratings_stmt->get_result();
$behaviours = [];
while ($r = $ratings_result->fetch_assoc()) {
    $behaviours[$r['category']] = $r['rating'];
}
$ratings_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Performance Evaluation</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-8 max-w-5xl mx-auto bg-white rounded-2xl shadow-lg">
  <h1 class="text-2xl font-bold text-blue-700 mb-6">Performance Evaluation Details</h1>

  <!-- User Info -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div>
      <p class="text-gray-600 font-semibold">Full Name:</p>
      <p class="text-lg"><?= htmlspecialchars($evaluation['full_name']) ?></p>
    </div>
    <div>
      <p class="text-gray-600 font-semibold">National ID:</p>
      <p class="text-lg"><?= htmlspecialchars($evaluation['national_id']) ?></p>
    </div>
    <div>
      <p class="text-gray-600 font-semibold">Evaluation Date:</p>
      <p class="text-lg"><?= htmlspecialchars($evaluation['eval_date']) ?></p>
    </div>
    <div>
      <p class="text-gray-600 font-semibold">Evaluator Name:</p>
      <p class="text-lg"><?= htmlspecialchars($evaluation['evaluator_name']) ?></p>
    </div>
  </div>

  <!-- Qualifications -->
  <h2 class="text-xl font-semibold text-blue-700 mb-2">Qualifications</h2>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div>
      <p class="text-gray-600 font-semibold">Academic Qualification:</p>
      <p><?= htmlspecialchars($evaluation['academic_qualification']) ?> (<?= htmlspecialchars($evaluation['academic_grade']) ?>)</p>
    </div>
    <div>
      <p class="text-gray-600 font-semibold">Professional Qualification:</p>
      <p><?= htmlspecialchars($evaluation['professional_qualification']) ?> (<?= htmlspecialchars($evaluation['professional_grade']) ?>)</p>
    </div>
  </div>

  <!-- Behavioural Ratings -->
  <h2 class="text-xl font-semibold text-blue-700 mb-2">Behavioural Ratings</h2>
  <table class="w-full border text-sm mb-8">
    <thead class="bg-gray-100">
      <tr>
        <th class="border p-2 text-left">Category</th>
        <th class="border p-2 text-center">Rating (1-5)</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $categories = ["Integrity", "Coworker Relations", "Client Relations", "Technical Skills", "Dependability", "Punctuality", "Attendance"];
      foreach ($categories as $cat): ?>
        <tr>
          <td class="border p-2"><?= $cat ?></td>
          <td class="border p-2 text-center"><?= htmlspecialchars($behaviours[$cat] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Text Areas -->
  <?php
  $areas = [
    "strengths" => "Strengths",
    "key_activities" => "Key Activities Undertaken",
    "accomplishments" => "Accomplishments",
    "challenges" => "Challenges and Solutions",
    "improvement_plan" => "Improvement Plan",
    "previous_goals" => "Previous Goals",
    "future_goals" => "Future Goals",
    "manager_support" => "Support Needed from Management",
    "employee_concerns" => "Employee Concerns or Suggestions"
  ];
  foreach ($areas as $key => $label): ?>
    <div class="mb-6">
      <h3 class="text-lg font-semibold text-gray-700"><?= $label ?></h3>
      <p class="border p-3 bg-gray-50 rounded"><?= nl2br(htmlspecialchars($evaluation[$key])) ?></p>
    </div>
  <?php endforeach; ?>

  <!-- Approval Section -->
  <h2 class="text-xl font-semibold text-blue-700 mb-2">Approval Details</h2>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div>
      <p class="font-semibold text-gray-600">Approved By:</p>
      <p><?= htmlspecialchars($evaluation['approved_by'] ?? '-') ?></p>
    </div>
    <div>
      <p class="font-semibold text-gray-600">Authorized By:</p>
      <p><?= htmlspecialchars($evaluation['authorized_by'] ?? '-') ?></p>
    </div>
    <div>
      <p class="font-semibold text-gray-600">Approved Date:</p>
      <p><?= htmlspecialchars($evaluation['approved_date'] ?? '-') ?></p>
    </div>
  </div>

  <div class="mt-10 text-right">
    <a href="user_performance_evaluation.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">← Back</a>
  </div>
</div>
</body>
</html>
-$_COOKIE