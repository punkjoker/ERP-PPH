<?php
session_start();
require 'db_con.php';

// ✅ Get filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$search_name = trim($_GET['search_name'] ?? '');

// ✅ Base query
$sql = "
SELECT 
    e.id AS eval_id,
    u.full_name,
    u.national_id,
    YEAR(e.eval_date) AS eval_year,
    e.eval_date
FROM user_performance_evaluation e
JOIN users u ON e.user_id = u.user_id
JOIN groups g ON u.group_id = g.group_id
WHERE g.group_name = 'Staff'
";

// ✅ Apply filters dynamically
if (!empty($from_date) && !empty($to_date)) {
    $sql .= " AND DATE(e.eval_date) BETWEEN '" . $conn->real_escape_string($from_date) . "' 
              AND '" . $conn->real_escape_string($to_date) . "'";
} elseif (!empty($from_date)) {
    $sql .= " AND DATE(e.eval_date) >= '" . $conn->real_escape_string($from_date) . "'";
} elseif (!empty($to_date)) {
    $sql .= " AND DATE(e.eval_date) <= '" . $conn->real_escape_string($to_date) . "'";
}

if (!empty($search_name)) {
    $sql .= " AND u.full_name LIKE '%" . $conn->real_escape_string($search_name) . "%'";
}

$sql .= " ORDER BY e.eval_date DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>All Staff Evaluations</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-8 max-w-7xl mx-auto bg-white rounded-2xl shadow-lg">
  <h1 class="text-2xl font-bold text-blue-700 mb-6">All Staff Performance Evaluations</h1>

  <!-- ✅ Filter Section -->
  <form method="GET" class="mb-6 bg-gray-50 p-4 rounded-lg shadow-sm flex flex-wrap gap-4 items-end">
    <div>
      <label class="block text-gray-600 font-semibold mb-1">From Date</label>
      <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="border rounded p-2">
    </div>
    <div>
      <label class="block text-gray-600 font-semibold mb-1">To Date</label>
      <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="border rounded p-2">
    </div>
    <div>
      <label class="block text-gray-600 font-semibold mb-1">Search by Name</label>
      <input type="text" name="search_name" value="<?= htmlspecialchars($search_name) ?>" placeholder="Enter staff name" class="border rounded p-2 w-60">
    </div>
    <div>
      <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700">Filter</button>
      <a href="view_all_evaluations.php" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Reset</a>
    </div>
  </form>

  <!-- ✅ Table Section -->
  <?php if ($result && $result->num_rows > 0): ?>
    <table class="w-full border text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="border p-2 text-left">#</th>
          <th class="border p-2 text-left">Full Name</th>
          <th class="border p-2 text-left">National ID</th>
          <th class="border p-2 text-left">Year</th>
          <th class="border p-2 text-left">Evaluation Date</th>
          <th class="border p-2 text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $count = 1;
        while ($row = $result->fetch_assoc()): ?>
          <tr class="hover:bg-gray-50">
            <td class="border p-2"><?= $count++ ?></td>
            <td class="border p-2"><?= htmlspecialchars($row['full_name']) ?></td>
            <td class="border p-2"><?= htmlspecialchars($row['national_id']) ?></td>
            <td class="border p-2"><?= htmlspecialchars($row['eval_year']) ?></td>
            <td class="border p-2"><?= htmlspecialchars($row['eval_date']) ?></td>
            <td class="border p-2 text-center">
              <a href="view_user_evaluation.php?id=<?= $row['eval_id'] ?>" 
                 class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                 View Evaluation
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="text-gray-600 text-center">No evaluations found for the selected filters.</p>
  <?php endif; ?>
</div>
</body>
</html>
