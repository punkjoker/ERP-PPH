<?php
include 'db_con.php';

$product_id = intval($_GET['product_id'] ?? 0);

// Fetch product name
$product = $conn->query("SELECT name FROM products WHERE id = $product_id")->fetch_assoc();

// Fetch batches
$batches = $conn->query("
    SELECT batch_number, obtained_yield, pack_size, remaining_size, unit, created_at
    FROM finished_products
    WHERE product_id = $product_id
    ORDER BY created_at DESC
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
    <h2 class="text-2xl font-bold mb-4 text-gray-800">Batches for <?= htmlspecialchars($product['name']); ?></h2>
    <div class="bg-white shadow rounded-lg overflow-hidden">
      <table class="min-w-full border-collapse">
        <thead class="bg-blue-600 text-white">
          <tr>
            <th class="py-3 px-4 text-left">Batch Number</th>
            <th class="py-3 px-4 text-left">Obtained Yield</th>
            <th class="py-3 px-4 text-left">Pack Size</th>
            <th class="py-3 px-4 text-left">Remaining Packs</th>
            <th class="py-3 px-4 text-left">Unit</th>
            <th class="py-3 px-4 text-left">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($batches && $batches->num_rows > 0): ?>
            <?php while ($b = $batches->fetch_assoc()): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="py-3 px-4"><?= htmlspecialchars($b['batch_number']); ?></td>
                <td class="py-3 px-4"><?= number_format($b['obtained_yield'], 2); ?></td>
                <td class="py-3 px-4"><?= number_format($b['pack_size'], 0); ?></td>
                <td class="py-3 px-4 font-semibold"><?= number_format($b['remaining_size'], 0); ?></td>
                <td class="py-3 px-4"><?= htmlspecialchars($b['unit']); ?></td>
                <td class="py-3 px-4 text-gray-500"><?= htmlspecialchars(date('Y-m-d', strtotime($b['created_at']))); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" class="text-center text-gray-500 py-6">No batches found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
