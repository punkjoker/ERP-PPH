<?php
include 'db_con.php';

// Date filter
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// ✅ Only show products that have an approved packaging
$query = "
SELECT 
    bom.id, 
    bom.bom_date, 
    bom.requested_by, 
    bom.description,
    bom.batch_number,
    p.name AS product_name,
    pr.id AS production_run_id,
    pr.status AS production_status,
    
    -- ✅ QC inspection status
    COALESCE(
        MAX(CASE WHEN qi.qc_status = 'Approved Product' THEN 'Approved Product' END),
        'Not Inspected'
    ) AS qc_status,
    
    -- ✅ Quality Manager Review status
    CASE
        WHEN COUNT(qmr.id) = 0 THEN 'Pending'
        WHEN SUM(CASE WHEN qmr.response = 'No' THEN 1 ELSE 0 END) > 0 THEN 'Not Approved'
        ELSE 'Approved'
    END AS quality_review_status,

    -- Optional: Reviewed by (if you’ll add that field later)
    MAX(qmr.created_at) AS reviewed_at

FROM bill_of_materials bom
JOIN products p ON bom.product_id = p.id
LEFT JOIN production_runs pr ON pr.request_id = bom.id
LEFT JOIN qc_inspections qi ON qi.production_run_id = pr.id
LEFT JOIN quality_manager_review qmr ON qmr.production_run_id = pr.id

WHERE pr.id IN (
    SELECT DISTINCT production_run_id 
    FROM packaging 
    WHERE status = 'Approved'
)
AND pr.status = 'Completed'
";

// ✅ Add date filter if present
if ($from_date && $to_date) {
    $query .= " AND bom.bom_date BETWEEN '$from_date' AND '$to_date'";
}


$query .= "
GROUP BY 
    bom.id, 
    bom.bom_date, 
    bom.requested_by, 
    bom.description, 
    bom.batch_number,
    p.name, 
    pr.status
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
  <title>Inspect Finished Products</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

  <!-- Navbar -->
  <?php include 'navbar.php'; ?>

  <!-- Page Content -->
  <div class="p-6 ml-64">
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-lg">
      <h2 class="text-2xl font-bold mb-4 text-gray-800">Quality manager review</h2>

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
        <a 
  href="download_qc_reviews.php?from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>"
  class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 ml-2"
  target="_blank"
>
  Download PDF
</a>

      </form>

      <!-- Table -->
<div class="overflow-x-auto">
  <table class="min-w-full text-sm border-separate border-spacing-y-2">
    <thead>
      <tr class="bg-blue-100 text-gray-700 uppercase text-xs">
        <th class="py-2 px-3 text-left rounded-l-lg">Date</th>
        <th class="py-2 px-3 text-left">Product Name</th>
        <th class="py-2 px-3 text-left">Batch Number</th>
        <th class="py-2 px-3 text-left">Requested By</th>
        <th class="py-2 px-3 text-left">Description</th>
        <th class="py-2 px-3 text-left">Inspection Status</th>
        <th class="py-2 px-3 text-left">Quality Review Status</th>
        <th class="py-2 px-3 text-left rounded-r-lg">Actions</th>
      </tr>
    </thead>
    <tbody class="text-gray-700">
      <?php while ($row = $result->fetch_assoc()): ?>
        <?php
          $qc_status = $row['qc_status'];
          $quality_review_status = $row['quality_review_status'];

          // QC inspection status
          if ($qc_status === 'Approved Product') {
              $qc_text = "<span class='text-green-600 font-semibold'>Approved</span>";
          } else {
              $qc_text = "<span class='text-red-600 font-semibold'>Not Inspected</span>";
          }

          // Quality manager review status
          if ($quality_review_status === 'Approved') {
              $review_text = "<span class='text-green-600 font-semibold'>Approved</span>";
          } elseif ($quality_review_status === 'Pending') {
              $review_text = "<span class='text-yellow-600 font-semibold'>Pending</span>";
          } else {
              $review_text = "<span class='text-gray-600 font-semibold'>N/A</span>";
          }
        ?>
        <tr class="bg-white shadow-sm hover:shadow-md transition rounded-lg">
          <td class="py-2 px-3 rounded-l-lg"><?= htmlspecialchars($row['bom_date']); ?></td>
          <td class="py-2 px-3"><?= htmlspecialchars($row['product_name']); ?></td>
          <td class="py-2 px-3"><?= htmlspecialchars($row['batch_number']); ?></td>
          <td class="py-2 px-3"><?= htmlspecialchars($row['requested_by']); ?></td>
          <td class="py-2 px-3"><?= htmlspecialchars($row['description']); ?></td>
          <td class="py-2 px-3"><?= $qc_text; ?></td>
          <td class="py-2 px-3"><?= $review_text; ?></td>
          <td class="py-2 px-3 rounded-r-lg">
            <div class="flex items-center space-x-2">
              <a href="update_qc_review.php?id=<?= $row['id']; ?>" 
                 class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 text-xs shadow-sm transition">
                 Update
              </a>
              <a href="view_finished_product.php?id=<?= $row['id']; ?>" 
                 class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-xs shadow-sm transition">
                 View
              </a>
            </div>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

    </div>
  </div>

</body>
</html>
