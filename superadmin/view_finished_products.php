<?php
include 'db_con.php';

// Date filter
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// ✅ Base query — show only completed runs with Approved QC
$query = "
SELECT 
    bom.id, 
    bom.created_at, 
    bom.requested_by, 
    bom.description, 
    bom.batch_number,
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
    $query .= " AND bom.created_at BETWEEN '$from_date' AND '$to_date'";
}

// ✅ Grouping and ordering
$query .= "
GROUP BY 
    bom.id, 
    bom.created_at, 
    bom.requested_by, 
    bom.description,
    bom.batch_number, 
    p.name, 
    pr.status
HAVING qc_status = 'Approved Product'
ORDER BY bom.created_at DESC
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
          <input type="date" name="from_date" value="<?= $from_date ?>" class="border p-1.5 rounded w-full text-sm focus:ring-blue-400 focus:border-blue-400">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">To Date</label>
          <input type="date" name="to_date" value="<?= $to_date ?>" class="border p-1.5 rounded w-full text-sm focus:ring-blue-400 focus:border-blue-400">
        </div>
        <div class="flex items-end">
          <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-700 text-sm transition">Filter</button>
        </div>
        <a href="download_finished_product_out.php?from_date=<?= $from_date ?>&to_date=<?= $to_date ?>"
   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow-sm">
   Download List
</a>

      </form>

      <!-- Table -->
      <div class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
        <table class="min-w-full border-collapse text-xs">
          <thead>
            <tr class="bg-gray-100 text-gray-700 uppercase text-[11px] tracking-wide">
              <th class="py-1 px-2 border">Date</th>
              <th class="py-1 px-2 border">Product Name</th>
              <th class="py-1 px-2 border">Batch #</th>
              <th class="py-1 px-2 border">Requested By</th>
              <th class="py-1 px-2 border">Description</th>
              <th class="py-1 px-2 border">QC Status</th>
              <th class="py-1 px-2 border text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="text-gray-700">
            <?php if ($result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr class="hover:shadow-md hover:bg-gray-50 transition duration-150 border-b border-gray-200">
                  <td class="py-1 px-2 border-r"><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
                  <td class="py-1 px-2 border-r"><?= htmlspecialchars($row['product_name']) ?></td>
                  <td class="py-1 px-2 border-r"><?= htmlspecialchars($row['batch_number']) ?></td>
                  <td class="py-1 px-2 border-r"><?= htmlspecialchars($row['requested_by']) ?></td>
                  <td class="py-1 px-2 border-r truncate max-w-[200px]"><?= htmlspecialchars($row['description']) ?></td>
                  <td class="py-1 px-2 border-r text-green-600 font-semibold text-xs">Approved</td>
                  <td class="py-[1px] px-2 text-center">
                    <div class="flex justify-center gap-1.5">
                      <a href="bom_final.php?id=<?= $row['id'] ?>" 
                         class="bg-yellow-500 text-white px-2 py-[2px] rounded hover:bg-yellow-600 text-[11px] shadow-sm">BOM</a>
                      <a href="view_finished_product.php?id=<?= $row['id'] ?>" 
                         class="bg-blue-500 text-white px-2 py-[2px] rounded hover:bg-blue-600 text-[11px] shadow-sm">Product</a>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" class="text-center py-3 text-gray-500 text-sm">No approved finished products found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</body>
</html>

