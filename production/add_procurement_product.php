<?php
include 'db_con.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $_POST['product_name'];
    $department   = $_POST['department'];
    $requested_by = !empty($_POST['requested_by']) ? $_POST['requested_by'] : null;
    $description  = $_POST['description'];

    $stmt = $conn->prepare("INSERT INTO procurement_products (product_name, department, requested_by, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $product_name, $department, $requested_by, $description);
    $stmt->execute();

    $success = "Product added successfully!";
}

// Fetch all items
$result = $conn->query("SELECT * FROM procurement_products ORDER BY created_at DESC");
$items = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Procurement Product</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-8">
    <h1 class="text-2xl font-bold mb-6 text-blue-800">Add Procurement Product</h1>

    <?php if (!empty($success)): ?>
      <div class="bg-green-100 text-green-700 px-4 py-2 rounded mb-4">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" class="bg-white p-6 rounded-lg shadow-md mb-10 max-w-4xl">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    <!-- Product Name -->
    <div>
      <label class="block font-semibold mb-1">Product Name</label>
      <input type="text" name="product_name" required
             class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-300">
    </div>

    <!-- Department -->
    <div>
      <label class="block font-semibold mb-1">Department</label>
      <select name="department" required
              class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-300">
        <option value="">Select Department</option>
        <option value="HR">HR</option>
        <option value="Production">Production</option>
        <option value="Quality Control">Quality Control</option>
        <option value="Stores">Stores</option>
        <option value="Procurement">Procurement</option>
        <option value="Sales">Sales</option>
        <option value="Drivers">Drivers</option>
      </select>
    </div>

    <!-- Requested By -->
    <div>
      <label class="block font-semibold mb-1">Requested By (optional)</label>
      <input type="text" name="requested_by"
             class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-300">
    </div>

    <!-- Description (span both columns) -->
    <div class="md:col-span-2">
      <label class="block font-semibold mb-1">Description</label>
      <textarea name="description" rows="3"
                class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:ring-2 focus:ring-blue-300"></textarea>
    </div>
  </div>

  <!-- Buttons -->
  <div class="flex gap-4 mt-6">
    <button type="submit"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
      Save Product
    </button>
    <a href="procurement_dashboard.php"
       class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
      Back
    </a>
  </div>
</form>


    <!-- Items List -->
    <div class="bg-white p-6 rounded-lg shadow-md">
      <h2 class="text-xl font-semibold mb-4 text-gray-700">Procurement Items</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-blue-200 text-blue-900">
            <tr>
              <th class="px-4 py-2 border">#</th>
              <th class="px-4 py-2 border">Product Name</th>
              <th class="px-4 py-2 border">Department</th>
              <th class="px-4 py-2 border">Requested By</th>
              <th class="px-4 py-2 border">Description</th>
              <th class="px-4 py-2 border">Created At</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($items)): ?>
              <?php foreach ($items as $index => $item): ?>
                <tr class="hover:bg-gray-100">
                  <td class="px-4 py-2 border text-center"><?= $index + 1 ?></td>
                  <td class="px-4 py-2 border"><?= htmlspecialchars($item['product_name']) ?></td>
                  <td class="px-4 py-2 border"><?= htmlspecialchars($item['department']) ?></td>
                  <td class="px-4 py-2 border"><?= $item['requested_by'] ? htmlspecialchars($item['requested_by']) : '-' ?></td>
                  <td class="px-4 py-2 border"><?= htmlspecialchars($item['description']) ?></td>
                  <td class="px-4 py-2 border"><?= htmlspecialchars($item['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="px-4 py-2 border text-center text-gray-500">No procurement items added yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

</body>
</html>
