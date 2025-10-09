<?php
include 'db_con.php';

// Date filter
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// ✅ Base query — show only completed runs with Approved QC
$query = "
SELECT 
    bom.id, 
    bom.bom_date, 
    bom.requested_by, 
    bom.description, 
    p.name AS product_name,
    pr.status AS production_status,
    MAX(CASE WHEN qi.qc_status = 'Approved Product' THEN 'Approved Product' END) AS qc_status
FROM bill_of_materials bom
JOIN products p ON bom.product_id = p.id
LEFT JOIN production_runs pr ON pr.request_id = bom.id
LEFT JOIN qc_inspections qi ON qi.production_run_id = pr.id
WHERE pr.status = 'Completed'
";

// ✅ Add date filter if applied
if ($from_date && $to_date) {
    $query .= " AND bom.bom_date BETWEEN '$from_date' AND '$to_date'";
}

// ✅ Grouping and ordering
$query .= "
GROUP BY 
    bom.id, 
    bom.bom_date, 
    bom.requested_by, 
    bom.description, 
    p.name, 
    pr.status
HAVING qc_status = 'Approved Product'
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
  <title>View Finished Products</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

  <!-- Navbar -->
  <?php include 'navbar.php'; ?>

  <!-- Page Content -->
  <div class="p-6 ml-64">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-lg">
      <h2 class="text-2xl font-bold mb-4 text-gray-800">Approved Finished Products</h2>

      <!-- Filter Form -->
      <form method="GET" class="flex space-x-4 mb-6">
        <div>
          <label class="block text-sm font-medium text-gray-700">From Date</label>
          <input type="date" name="from_date" value="<?php echo $from_date; ?>" class="border p-2 rounded w-full focus:ring-blue-400 focus:border-blue-400">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">To Date</label>
          <input type="date" name="to_date" value="<?php echo $to_date; ?>" class="border p-2 rounded w-full focus:ring-blue-400 focus:border-blue-400">
        </div>
        <div class="flex items-end">
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Filter</button>
        </div>
      </form>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 text-sm">
          <thead>
            <tr class="bg-gray-100 text-gray-700 uppercase text-xs">
              <th class="py-2 px-3 border">Date</th>
              <th class="py-2 px-3 border">Product Name</th>
              <th class="py-2 px-3 border">Requested By</th>
              <th class="py-2 px-3 border">Description</th>
              <th class="py-2 px-3 border">QC Status</th>
              <th class="py-2 px-3 border">Actions</th>
            </tr>
          </thead>
          <tbody class="text-gray-700">
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50 transition">
                  <td class="py-2 px-3 border"><?php echo htmlspecialchars($row['bom_date']); ?></td>
                  <td class="py-2 px-3 border"><?php echo htmlspecialchars($row['product_name']); ?></td>
                  <td class="py-2 px-3 border"><?php echo htmlspecialchars($row['requested_by']); ?></td>
                  <td class="py-2 px-3 border"><?php echo htmlspecialchars($row['description']); ?></td>
                  <td class="py-2 px-3 border text-green-600 font-semibold">Approved</td>
                  <td class="py-2 px-3 border space-x-2">
                    <a href="bom_final.php?id=<?php echo $row['id']; ?>" 
                       class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 text-xs">View BOM</a>
                    <a href="view_finished_product.php?id=<?php echo $row['id']; ?>" 
                       class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-xs">View Finished Product</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center py-4 text-gray-500">No approved finished products found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</body>
</html>
