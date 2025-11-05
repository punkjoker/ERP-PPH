<?php
include 'db_con.php';

$product_id = intval($_GET['product_id'] ?? 0);
if ($product_id <= 0) {
    die("Invalid product id.");
}

// Fetch product name (guard against missing)
$productRes = $conn->query("SELECT name FROM products WHERE id = $product_id");
$product = $productRes ? $productRes->fetch_assoc() : null;
$productName = $product['name'] ?? 'Unknown Product';

// Fetch batches
$batches = $conn->query("
    SELECT 
        f.batch_number, 
        f.obtained_yield, 
        f.pack_size, 
        f.remaining_size, 
        f.unit, 
        f.packaged_quantity, 
        f.created_at,
        m.material_name
    FROM finished_products f
    LEFT JOIN materials m ON f.material_id = m.id
    WHERE f.product_id = $product_id
    ORDER BY f.created_at DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Batches</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
  <?php include 'navbar.php'; ?>
  <div class="p-6 ml-64">
    <h2 class="text-2xl font-bold mb-4 text-gray-800">Batches for <?= htmlspecialchars($productName); ?></h2>
    <div class="flex gap-3 mb-4">
    <a href="products_inventory.php" 
       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow transition">
       ← Back 
    </a>

    <a href="download_packaging_batches.php?product_id=<?= $product_id ?>" 
       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded shadow transition"
       target="_blank">
       ⬇ Download Report
    </a>
</div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
  <table class="min-w-full border-collapse">
    <thead class="bg-blue-600 text-white text-sm">
      <tr>
        <th class="py-2 px-3 text-left font-semibold">Batch Number</th>
        <th class="py-2 px-3 text-left font-semibold">Obtained Yield</th>
        <th class="py-2 px-3 text-left font-semibold">Packed IN</th>
        <th class="py-2 px-3 text-left font-semibold">Packaged Quantity</th>
        <th class="py-2 px-3 text-left font-semibold">Pack Size</th>
        <th class="py-2 px-3 text-left font-semibold">Remaining Packs</th>
        <th class="py-2 px-3 text-left font-semibold">Unit</th>
        <th class="py-2 px-3 text-left font-semibold">Date</th>
      </tr>
    </thead>

    <tbody class="text-sm text-gray-700">
      <?php if ($batches && $batches->num_rows > 0): ?>
        <?php while ($b = $batches->fetch_assoc()): ?>
          <tr class="hover:bg-gray-50 transition-shadow duration-200 shadow-sm hover:shadow-md rounded-lg">
            <td class="py-2 px-3"><?= htmlspecialchars($b['batch_number']); ?></td>

            <td class="py-2 px-3">
              <?= number_format(floatval($b['obtained_yield']), 2) ?>
              <?= $b['unit'] ? ' ' . htmlspecialchars($b['unit']) : '' ?>
            </td>

            <td class="py-2 px-3"><?= htmlspecialchars($b['material_name'] ?? 'N/A'); ?></td>

            <td class="py-2 px-3">
              <?= number_format(floatval($b['packaged_quantity']), 2) ?>
              <?= $b['unit'] ? ' ' . htmlspecialchars($b['unit']) : '' ?>
            </td>

            <td class="py-2 px-3">
              <?= $b['pack_size'] !== null ? number_format(floatval($b['pack_size']), 2) : '-' ?>
              <?= $b['unit'] ? ' ' . htmlspecialchars($b['unit']) : '' ?>
            </td>

            <td class="py-2 px-3 font-semibold">
              <?= $b['remaining_size'] !== null ? number_format(floatval($b['remaining_size']), 0) : '-' ?>
            </td>

            <td class="py-2 px-3"><?= htmlspecialchars($b['unit'] ?? '-'); ?></td>

            <td class="py-2 px-3 text-gray-500">
              <?= htmlspecialchars($b['created_at'] ? date('Y-m-d', strtotime($b['created_at'])) : '-') ?>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="8" class="text-center text-gray-500 py-4">No batches found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

  </div>
</body>
</html>
