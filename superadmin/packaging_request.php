<?php
include 'db_con.php';

// Optional date filter
$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

// ✅ Fetch all products that already have a record in `packaging`
$query = "
SELECT 
    bom.id AS bom_id,
    bom.bom_date,
    bom.requested_by,
    bom.description,
    p.name AS product_name,
    pr.id AS production_run_id,
    pr.status AS production_status,
    pkg.status AS packaging_status,
    MAX(pkg.packaging_date) AS last_packaged_date
FROM bill_of_materials bom
JOIN products p ON bom.product_id = p.id
JOIN production_runs pr ON pr.request_id = bom.id
JOIN packaging pkg ON pkg.production_run_id = pr.id
WHERE 1=1
";

// ✅ Add date filters if set
if ($from_date && $to_date) {
    $query .= " AND bom.bom_date BETWEEN '$from_date' AND '$to_date'";
}

$query .= " 
GROUP BY pr.id
ORDER BY bom.bom_date DESC
";

$result = $conn->query($query);
if (!$result) {
    die('Query Error: ' . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Packaging Requests</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    table tr:nth-child(even) { background-color: #f9fafb; }
    table tr:nth-child(odd) { background-color: #ffffff; }
  </style>
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="p-6 ml-64">
  <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-4 text-gray-800">Packaging Requests</h2>

    <!-- Filter Form -->
    <form method="GET" class="flex space-x-4 mb-6">
      <div>
        <label class="block text-sm font-medium text-gray-700">From Date</label>
        <input type="date" name="from_date" value="<?= htmlspecialchars($from_date); ?>" 
               class="border p-2 rounded w-full focus:ring-blue-400 focus:border-blue-400">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">To Date</label>
        <input type="date" name="to_date" value="<?= htmlspecialchars($to_date); ?>" 
               class="border p-2 rounded w-full focus:ring-blue-400 focus:border-blue-400">
      </div>
      <div class="flex items-end">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
          Filter
        </button>
      </div>
    </form>

    <!-- Packaging Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-200 text-sm">
        <thead class="bg-gray-200 text-gray-700 uppercase">
          <tr>
            <th class="py-2 px-3 border">Date</th>
            <th class="py-2 px-3 border">Product Name</th>
            <th class="py-2 px-3 border">Requested By</th>
            <th class="py-2 px-3 border">Description</th>
            <th class="py-2 px-3 border">Production Status</th>
            <th class="py-2 px-3 border">Packaging Status</th>
            <th class="py-2 px-3 border">Last Packaged</th>
            <th class="py-2 px-3 border text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="text-gray-700">
          <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr class="hover:bg-blue-50 transition">
                <td class="py-2 px-3 border"><?= htmlspecialchars($row['bom_date']); ?></td>
                <td class="py-2 px-3 border"><?= htmlspecialchars($row['product_name']); ?></td>
                <td class="py-2 px-3 border"><?= htmlspecialchars($row['requested_by']); ?></td>
                <td class="py-2 px-3 border"><?= htmlspecialchars($row['description']); ?></td>
                <td class="py-2 px-3 border text-center font-semibold 
                    <?= $row['production_status'] === 'Completed' ? 'text-green-700' : 'text-yellow-700'; ?>">
                  <?= htmlspecialchars($row['production_status']); ?>
                </td>
                <td class="py-2 px-3 border text-center font-semibold
                    <?= $row['packaging_status'] === 'Completed' ? 'text-green-600' : 
                       ($row['packaging_status'] === 'In Progress' ? 'text-yellow-600' : 'text-gray-500'); ?>">
                  <?= htmlspecialchars($row['packaging_status'] ?? 'Pending'); ?>
                </td>
                <td class="py-2 px-3 border text-center">
                  <?= htmlspecialchars($row['last_packaged_date'] ?? '-'); ?>
                </td>
                <td class="py-2 px-3 border text-center">
                  <a href="approved_packaging.php?run_id=<?= $row['production_run_id']; ?>" 
                     class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition text-xs">
                     Update Packaging
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="text-center py-3 text-gray-500">
                No packaging records found.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>
