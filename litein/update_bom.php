<?php
session_start();
require 'db_con.php';

// Get product ID
if (!isset($_GET['id'])) {
    die("Product ID missing.");
}
$product_id = intval($_GET['id']);

// Fetch product info
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    die("Product not found.");
}

// Fetch approved chemicals for dropdown
$chemicals_result = $conn->query("
    SELECT id, chemical_name, chemical_code 
    FROM chemical_names 
    ORDER BY chemical_name ASC
");


// Handle BOM submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $std_quantity = floatval($_POST['std_quantity']);
    $unit = $_POST['unit'] ?? 'kg';
    $chemicals_data = $_POST['chemicals'] ?? [];

    if ($std_quantity <= 0) {
        $error = "Please enter a valid standard quantity.";
    } elseif (empty($chemicals_data)) {
        $error = "Please add at least one chemical component.";
    } else {
        // Delete existing BOM for this product
        $conn->query("DELETE FROM bom WHERE product_id = $product_id");

        // Insert BOM with std_quantity + unit
        $stmtBom = $conn->prepare("INSERT INTO bom (product_id, std_quantity, unit) VALUES (?, ?, ?)");
        $stmtBom->bind_param("ids", $product_id, $std_quantity, $unit);
        $stmtBom->execute();
        $bom_id = $stmtBom->insert_id;
        $stmtBom->close();

        // Insert chemicals for this BOM
        $stmt = $conn->prepare("INSERT INTO bom_materials (bom_id, chemical_id, std_quantity, unit) VALUES (?, ?, ?, ?)");
foreach ($chemicals_data as $row) {
    $chemical_id = intval($row['id'] ?? 0);
    $qty = floatval($row['qty'] ?? 0);
    $unit = $row['unit'] ?? '';
    if ($chemical_id > 0 && $qty > 0 && $unit !== '') {
        $stmt->bind_param("iids", $bom_id, $chemical_id, $qty, $unit);
        $stmt->execute();
    }
}

        $stmt->close();

        $success = "✅ BOM updated successfully for " . htmlspecialchars($product['name']);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Update BOM</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
</head>

<body class="bg-gray-100">
  <?php include 'navbar.php'; ?>
  <div class="p-6 ml-64">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">
      Update BOM for <?= htmlspecialchars($product['name']) ?>
    </h1>

    <?php if (isset($success)): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= $error ?></div>
    <?php endif; ?>

    <div class="bg-white shadow-lg rounded p-6">
      <form method="POST" class="space-y-4">
        <div>
          <label class="block font-semibold text-gray-700 mb-1">Product Name</label>
          <input type="text" value="<?= htmlspecialchars($product['name']) ?>" disabled class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2">
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-1">Standard Quantity</label>
          <input type="number" step="0.01" name="std_quantity" placeholder="Enter standard batch quantity" required class="w-full border border-gray-300 rounded px-3 py-2">
        </div>

        <div>
          <label class="block font-semibold text-gray-700 mb-1">Unit</label>
          <select name="unit" class="w-full border border-gray-300 rounded px-3 py-2">
            <option value="kg">Kilograms (kg)</option>
            <option value="L">Litres (L)</option>
            <option value="g">Grams (g)</option>
            <option value="ml">Millilitres (ml)</option>
          </select>
        </div>

        <h3 class="text-lg font-semibold text-blue-700 mt-4 mb-2">Add Chemicals (Raw Materials)</h3>
        <div id="chemicals-container"></div>

        <button type="button" onclick="addRow()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">+ Add Chemical</button>

        <div class="mt-6">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Save BOM</button>
          <a href="add_product.php" class="ml-3 text-gray-700 underline">← Back to Products</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    let index = 0;

    function addRow() {
      const container = document.getElementById('chemicals-container');
      const row = document.createElement('div');
      row.className = "grid grid-cols-2 md:grid-cols-3 gap-3 mb-3 items-center";
      row.innerHTML = `
  <select id="chem_${index}" name="chemicals[${index}][id]" class="border rounded p-2" required>
    <option value="">-- Select Chemical --</option>
    <?php
    $chemicals_result->data_seek(0);
    while ($c = $chemicals_result->fetch_assoc()):
        echo '<option value="' . $c['id'] . '">' .
            htmlspecialchars($c['chemical_name']) . ' (' .
            htmlspecialchars($c['chemical_code']) . ')</option>';
    endwhile;
    ?>
  </select>
  <input type="number" step="0.01" name="chemicals[${index}][qty]" placeholder="Quantity" class="border rounded p-2" required>
  <input type="text" name="chemicals[${index}][unit]" placeholder="Unit (e.g. kg, L)" class="border rounded p-2 w-24" required>
  <button type="button" onclick="this.parentElement.remove()" class="bg-red-500 text-white px-2 py-1 rounded text-sm">Remove</button>
`;

      container.appendChild(row);

      // ✅ Apply Tom Select for search/filter
      new TomSelect(`#chem_${index}`, {
        create: false,
        sortField: { field: "text", direction: "asc" },
        placeholder: "Search by name or code...",
      });

      index++;
    }
  </script>
</body>
</html>

