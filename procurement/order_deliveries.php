<?php
include 'db_con.php';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_day = $_POST['delivery_day'];
    $delivery_date = $_POST['delivery_date'];
    $status = 'Pending';

    // Insert into order_deliveries table
    $stmt = $conn->prepare("INSERT INTO order_deliveries (delivery_day, delivery_date, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $delivery_day, $delivery_date, $status);
    $stmt->execute();
    $delivery_id = $conn->insert_id;

    // Insert destinations + products
    foreach ($_POST['destination'] as $i => $destination) {
        $product = $_POST['product_name'][$i];
        $quantity = $_POST['quantity'][$i];
        if (!empty($destination) && !empty($product) && !empty($quantity)) {
            $stmt2 = $conn->prepare("INSERT INTO order_delivery_items (delivery_id, destination, product_name, quantity) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("isss", $delivery_id, $destination, $product, $quantity);
            $stmt2->execute();
        }
    }
}

// --- Fetch Deliveries ---
$deliveries = $conn->query("SELECT * FROM order_deliveries ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Deliveries</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-8">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">🚚 Manage Order Deliveries</h2>

    <!-- Delivery Form -->
    <form method="post" class="bg-white shadow-lg rounded-2xl p-6 mb-10">
      <div class="grid md:grid-cols-2 gap-6 mb-4">
        <div>
          <label class="font-semibold text-gray-700">Delivery Day</label>
          <input type="text" name="delivery_day" required placeholder="e.g. Monday Dispatch"
                 class="w-full border rounded-md p-2 focus:ring focus:ring-blue-300 mt-1">
        </div>

        <div>
          <label class="font-semibold text-gray-700">Delivery Date</label>
          <input type="date" name="delivery_date" required
                 class="w-full border rounded-md p-2 focus:ring focus:ring-blue-300 mt-1">
        </div>
      </div>

      <h3 class="text-lg font-semibold text-gray-700 mb-2">Destinations & Products</h3>

      <div id="delivery-container">
        <div class="grid md:grid-cols-3 gap-4 mb-3 delivery-row">
          <input type="text" name="destination[]" placeholder="Destination" class="border rounded-md p-2" required>
          <input type="text" name="product_name[]" placeholder="Product" class="border rounded-md p-2" required>
          <input type="text" name="quantity[]" placeholder="Quantity" class="border rounded-md p-2" required>
        </div>
      </div>

      <button type="button" id="add-delivery" class="bg-green-600 text-white px-3 py-2 rounded-md shadow hover:bg-green-700 transition mb-4">
        + Add More Destinations
      </button>

      <div>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md shadow hover:bg-blue-700 transition">
          Save Delivery
        </button>
      </div>
    </form>

    <!-- Delivery List -->
    <div class="bg-white shadow-lg rounded-2xl p-6">
      <h3 class="text-xl font-semibold mb-4 text-gray-700">📦 Delivery Batches</h3>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="py-2 px-4 border-b text-left">#</th>
              <th class="py-2 px-4 border-b text-left">Delivery Day</th>
              <th class="py-2 px-4 border-b text-left">Delivery Date</th>
              <th class="py-2 px-4 border-b text-left">Status</th>
              <th class="py-2 px-4 border-b text-left">Created</th>
              <th class="py-2 px-4 border-b text-left">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            if ($deliveries->num_rows > 0): 
              $count = 1;
              while ($row = $deliveries->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50">
                <td class="py-2 px-4 border-b"><?= $count++ ?></td>
                <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['delivery_day']) ?></td>
                <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['delivery_date']) ?></td>
                <td class="py-2 px-4 border-b">
                  <span class="px-3 py-1 rounded-full text-sm 
                    <?= $row['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' ?>">
                    <?= htmlspecialchars($row['status']) ?>
                  </span>
                </td>
                <td class="py-2 px-4 border-b"><?= htmlspecialchars($row['created_at']) ?></td>
                <td class="py-2 px-4 border-b">
                  <a href="view_order_delivery.php?id=<?= $row['id'] ?>" 
                     class="bg-blue-500 text-white px-3 py-1 rounded-md hover:bg-blue-600">View</a>
                </td>
              </tr>
              <?php endwhile;
            else: ?>
              <tr><td colspan="6" class="text-center py-4 text-gray-500">No deliveries recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
  document.getElementById('add-delivery').addEventListener('click', () => {
      const container = document.getElementById('delivery-container');
      const row = document.createElement('div');
      row.classList.add('grid', 'md:grid-cols-3', 'gap-4', 'mb-3', 'delivery-row');
      row.innerHTML = `
        <input type="text" name="destination[]" placeholder="Destination" class="border rounded-md p-2" required>
        <input type="text" name="product_name[]" placeholder="Product" class="border rounded-md p-2" required>
        <input type="text" name="quantity[]" placeholder="Quantity" class="border rounded-md p-2" required>
      `;
      container.appendChild(row);
  });
  </script>

</body>
</html>
