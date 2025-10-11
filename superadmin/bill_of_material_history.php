<?php
require 'db_con.php';

// --- Fetch dropdown data ---
$chemicalOptions = $conn->query("SELECT DISTINCT chemical_name FROM chemicals_in ORDER BY chemical_name ASC");
$lotOptions = $conn->query("SELECT DISTINCT rm_lot_no FROM chemicals_in ORDER BY rm_lot_no ASC");

// --- Handle filters ---
$where = "1=1";
$params = [];
$types = "";

// Date filter (From & To)
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where .= " AND b.issue_date BETWEEN ? AND ?";
    $params[] = $_GET['from_date'];
    $params[] = $_GET['to_date'];
    $types .= "ss";
}

// Chemical filter (dropdown)
if (!empty($_GET['chemical_name'])) {
    $where .= " AND ci.chemical_name = ?";
    $params[] = $_GET['chemical_name'];
    $types .= "s";
}

// Lot No filter (dropdown)
if (!empty($_GET['rm_lot_no'])) {
    $where .= " AND ci.rm_lot_no = ?";
    $params[] = $_GET['rm_lot_no'];
    $types .= "s";
}

// --- Query ---
$query = "
    SELECT 
        ci.chemical_name,
        ci.rm_lot_no,
        ci.std_quantity,
        ci.remaining_quantity,
        ci.unit_price,
        ci.total_cost,
        ci.date_added,
        b.id AS bom_id,
        b.description AS bom_description,
        b.requested_by,
        b.issued_by,
        b.issue_date,
        bi.quantity_requested,
        bi.total_cost AS used_cost
    FROM bill_of_material_items bi
    JOIN chemicals_in ci ON bi.chemical_id = ci.id
    JOIN bill_of_materials b ON bi.bom_id = b.id
    WHERE $where
    ORDER BY ci.chemical_name, b.issue_date DESC
";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// --- Fetch all records ---
$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>BOM History | Lynntech</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 mt-24 p-6 bg-white rounded shadow max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold text-center text-blue-700 mb-4">Bill of Material Usage History</h1>

    <!-- Filters -->
    <form method="GET" class="flex flex-wrap gap-4 items-end mb-6">

      <div>
        <label class="block text-sm font-semibold">From Date</label>
        <input type="date" name="from_date" value="<?php echo $_GET['from_date'] ?? ''; ?>" 
               class="border p-2 rounded w-44">
      </div>

      <div>
        <label class="block text-sm font-semibold">To Date</label>
        <input type="date" name="to_date" value="<?php echo $_GET['to_date'] ?? ''; ?>" 
               class="border p-2 rounded w-44">
      </div>

      <div>
        <label class="block text-sm font-semibold">Chemical Name</label>
        <select name="chemical_name" class="border p-2 rounded w-52">
          <option value="">All Chemicals</option>
          <?php while ($chem = $chemicalOptions->fetch_assoc()): ?>
            <option value="<?php echo $chem['chemical_name']; ?>"
              <?php echo ($_GET['chemical_name'] ?? '') == $chem['chemical_name'] ? 'selected' : ''; ?>>
              <?php echo $chem['chemical_name']; ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold">Lot No (RM Code)</label>
        <select name="rm_lot_no" class="border p-2 rounded w-44">
          <option value="">All Lots</option>
          <?php while ($lot = $lotOptions->fetch_assoc()): ?>
            <option value="<?php echo $lot['rm_lot_no']; ?>"
              <?php echo ($_GET['rm_lot_no'] ?? '') == $lot['rm_lot_no'] ? 'selected' : ''; ?>>
              <?php echo $lot['rm_lot_no']; ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
    </form>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-300 text-sm">
        <thead class="bg-gray-200 text-gray-700">
          <tr>
            <th class="p-2 border">#</th>
            <th class="p-2 border">Chemical Name</th>
            <th class="p-2 border">Lot No</th>
            <th class="p-2 border">Original Qty</th>
            <th class="p-2 border">Used Qty</th>
            <th class="p-2 border">Remaining Qty</th>
            <th class="p-2 border">Unit Price</th>
            <th class="p-2 border">Used Cost (Kshs)</th>
            <th class="p-2 border">Requested By</th>
            <th class="p-2 border">Issued By</th>
            <th class="p-2 border">Issue Date</th>
            <th class="p-2 border">BOM Description</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($records) > 0): ?>
            <?php $i = 1; foreach ($records as $r): ?>
              <tr class="hover:bg-gray-50">
                <td class="border p-2 text-center"><?php echo $i++; ?></td>
                <td class="border p-2"><?php echo htmlspecialchars($r['chemical_name']); ?></td>
                <td class="border p-2"><?php echo htmlspecialchars($r['rm_lot_no']); ?></td>
                <td class="border p-2 text-right"><?php echo number_format($r['std_quantity'], 2); ?></td>
                <td class="border p-2 text-right text-red-600"><?php echo number_format($r['quantity_requested'], 2); ?></td>
                <td class="border p-2 text-right text-green-700 font-semibold"><?php echo number_format($r['remaining_quantity'], 2); ?></td>
                <td class="border p-2 text-right"><?php echo number_format($r['unit_price'], 2); ?></td>
                <td class="border p-2 text-right"><?php echo number_format($r['used_cost'], 2); ?></td>
                <td class="border p-2"><?php echo htmlspecialchars($r['requested_by']); ?></td>
                <td class="border p-2"><?php echo htmlspecialchars($r['issued_by']); ?></td>
                <td class="border p-2 text-center"><?php echo htmlspecialchars($r['issue_date']); ?></td>
                <td class="border p-2"><?php echo htmlspecialchars($r['bom_description']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="12" class="text-center p-3 text-gray-500">No BOM history found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Summary -->
    <?php if (count($records) > 0): 
      $total_used = array_sum(array_column($records, 'used_cost'));
      $total_chemicals = count(array_unique(array_column($records, 'chemical_name')));
    ?>
      <div class="mt-4 flex justify-between text-sm font-semibold">
        <p>Total Chemicals Used: <?php echo $total_chemicals; ?></p>
        <p>Total Used Cost: <span class="text-blue-700">Kshs <?php echo number_format($total_used, 2); ?></span></p>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
