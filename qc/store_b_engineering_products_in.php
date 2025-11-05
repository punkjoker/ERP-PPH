<?php 
include 'db_con.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Store B Engineering Products Receiving</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 w-[calc(100%-16rem)]"> <!-- full width minus sidebar width -->

    <h2 class="text-xl font-bold mb-4">Store B Engineering Products Receiving</h2>

    <form method="POST" action="" class="bg-blue-100 p-4 rounded-lg shadow-md space-y-3 text-sm">
        <div>
            <label class="block font-medium">Product Name</label>
            <input type="text" id="product_name" name="product_name" autocomplete="off"
                   class="w-full p-1 rounded border border-gray-300 text-sm">
            <input type="hidden" id="product_id" name="product_id">
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <label class="block font-medium">Main Category</label>
                <input type="text" id="main_category" name="main_category" readonly
                       class="w-full p-1 rounded border border-gray-300 bg-gray-200 text-sm">
            </div>
            <div>
                <label class="block font-medium">Group Name</label>
                <input type="text" id="group_name" name="group_name" readonly
                       class="w-full p-1 rounded border border-gray-300 bg-gray-200 text-sm">
            </div>
            <div>
                <label class="block font-medium">Group Code</label>
                <input type="text" id="group_code" name="group_code" readonly
                       class="w-full p-1 rounded border border-gray-300 bg-gray-200 text-sm">
            </div>
            <div>
                <label class="block font-medium">Product Code</label>
                <input type="text" id="product_code" name="product_code" readonly
                       class="w-full p-1 rounded border border-gray-300 bg-gray-200 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block font-medium">Category</label>
                <input type="text" id="category" name="category" readonly
                       class="w-full p-1 rounded border border-gray-300 bg-gray-200 text-sm">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <label class="block font-medium">Delivery Number</label>
                <input type="text" name="delivery_number" class="w-full p-1 rounded border border-gray-300">
            </div>
            <div>
                <label class="block font-medium">Quantity Received</label>
                <input type="text" name="quantity_received" class="w-full p-1 rounded border border-gray-300">
            </div>
            <div>
                <label class="block font-medium">Units</label>
                <input type="text" name="units" class="w-full p-1 rounded border border-gray-300">
            </div>
            <div>
                <label class="block font-medium">Pack Size</label>
                <input type="text" name="pack_size" class="w-full p-1 rounded border border-gray-300">
            </div>
            <div>
                <label class="block font-medium">Unit Cost</label>
                <input type="text" name="unit_cost" class="w-full p-1 rounded border border-gray-300">
            </div>
            <div>
                <label class="block font-medium">P.O Number</label>
                <input type="text" name="po_number" class="w-full p-1 rounded border border-gray-300">
            </div>
            <div>
                <label class="block font-medium">Received By</label>
                <input type="text" name="received_by" class="w-full p-1 rounded border border-gray-300">
            </div>
            <div>
                <label class="block font-medium">Receiving Date</label>
                <input type="date" name="receiving_date" class="w-full p-1 rounded border border-gray-300">
            </div>
        </div>

        <button type="submit" name="submit" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-sm">Save</button>
    </form>

<?php
if(isset($_POST['submit'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $product_name = $_POST['product_name'] ?? '';
    $main_category = $_POST['main_category'] ?? '';
    $group_name = $_POST['group_name'] ?? '';
    $group_code = $_POST['group_code'] ?? '';
    $product_code = $_POST['product_code'] ?? '';
    $category = $_POST['category'] ?? '';
    $delivery_number = $_POST['delivery_number'] ?? '';
    $quantity_received = floatval($_POST['quantity_received'] ?? 0);
    $remaining_quantity = $quantity_received;
    $units = $_POST['units'] ?? '';
    $pack_size = $_POST['pack_size'] ?? '';
    $unit_cost = floatval($_POST['unit_cost'] ?? 0);
    $po_number = $_POST['po_number'] ?? '';
    $received_by = $_POST['received_by'] ?? '';
    $receiving_date = $_POST['receiving_date'] ?? '';

    $stmt = $conn->prepare("INSERT INTO store_b_engineering_products_in 
        (product_id, product_name, main_category, group_name, group_code, product_code, category, delivery_number, quantity_received, remaining_quantity, units, pack_size, unit_cost, po_number, received_by, receiving_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        // types: i = product_id, s = strings, d = doubles/floats
        // order: product_id(i), product_name(s), main_category(s), group_name(s), group_code(s),
        // product_code(s), category(s), delivery_number(s), quantity_received(d), remaining_quantity(d),
        // units(s), pack_size(s), unit_cost(d), po_number(s), received_by(s), receiving_date(s)
        $stmt->bind_param("isssssssddssdsss",
            $product_id, $product_name, $main_category, $group_name, $group_code,
            $product_code, $category, $delivery_number, $quantity_received, $remaining_quantity,
            $units, $pack_size, $unit_cost, $po_number, $received_by, $receiving_date
        );
        if ($stmt->execute()) {
            echo "<p class='text-green-600 font-semibold mt-2'>Data saved successfully!</p>";
        } else {
            echo "<p class='text-red-600 font-semibold mt-2'>Execute error: " . htmlspecialchars($stmt->error) . "</p>";
        }
        $stmt->close();
    } else {
        echo "<p class='text-red-600 font-semibold mt-2'>Prepare error: " . htmlspecialchars($conn->error) . "</p>";
    }
}
?>
<!-- Filter Section -->
<form method="GET" action="" class="mt-6 flex items-center space-x-3 bg-gray-100 p-3 rounded shadow-sm text-sm">
    <label>From:</label>
    <input type="date" name="from_date" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>" class="border rounded p-1">
    
    <label>To:</label>
    <input type="date" name="to_date" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>" class="border rounded p-1">
    
    <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">Filter</button>

    <a href="download_store_b_engineering.php?from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>" 
       class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
        Download PDF
    </a>

    <?php if (!empty($_GET['from_date']) || !empty($_GET['to_date'])): ?>
        <a href="store_b_engineering_products_in.php" class="text-blue-600 underline">Clear</a>
    <?php endif; ?>
</form>

<?php
$where = "";
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $from = $conn->real_escape_string($_GET['from_date']);
    $to = $conn->real_escape_string($_GET['to_date']);
    $where = "WHERE receiving_date BETWEEN '$from' AND '$to'";
}

$result = $conn->query("SELECT * FROM store_b_engineering_products_in $where ORDER BY receiving_date DESC");

if($result->num_rows > 0) {
    echo "<div class='overflow-x-auto mt-4'>
    <table class='min-w-full bg-white rounded-lg shadow-md text-sm'>
    <thead><tr class='bg-blue-200 text-left'>
        <th class='px-2 py-1'>Product Name</th>
        <th class='px-2 py-1'>Main Category</th>
        <th class='px-2 py-1'>Group Name</th>
        <th class='px-2 py-1'>Group Code</th>
        <th class='px-2 py-1'>Product Code</th>
        <th class='px-2 py-1'>Category</th>
        <th class='px-2 py-1'>Delivery No.</th>
        <th class='px-2 py-1'>Qty</th>
        <th class='px-2 py-1'>Remaining</th>
        <th class='px-2 py-1'>Units</th>
        <th class='px-2 py-1'>Pack Size</th>
        <th class='px-2 py-1'>Unit Cost</th>
        <th class='px-2 py-1'>PO Number</th>
        <th class='px-2 py-1'>Received By</th>
        <th class='px-2 py-1'>Receiving Date</th>
    </tr></thead><tbody>";
    while($row = $result->fetch_assoc()) {
        echo "<tr class='border-b hover:bg-gray-100'>
            <td class='px-2 py-1'>{$row['product_name']}</td>
            <td class='px-2 py-1'>{$row['main_category']}</td>
            <td class='px-2 py-1'>{$row['group_name']}</td>
            <td class='px-2 py-1'>{$row['group_code']}</td>
            <td class='px-2 py-1'>{$row['product_code']}</td>
            <td class='px-2 py-1'>{$row['category']}</td>
            <td class='px-2 py-1'>{$row['delivery_number']}</td>
            <td class='px-2 py-1'>{$row['quantity_received']}</td>
            <td class='px-2 py-1'>{$row['remaining_quantity']}</td>
            <td class='px-2 py-1'>{$row['units']}</td>
            <td class='px-2 py-1'>{$row['pack_size']}</td>
            <td class='px-2 py-1'>{$row['unit_cost']}</td>
            <td class='px-2 py-1'>{$row['po_number']}</td>
            <td class='px-2 py-1'>{$row['received_by']}</td>
            <td class='px-2 py-1'>{$row['receiving_date']}</td>
        </tr>";
    }
    echo "</tbody></table></div>";
}
?>

</div>

<script>
$("#product_name").autocomplete({
    source: "store_b_engineering_autocomplete.php",
    minLength: 1,
    select: function(event, ui) {
        $("#product_name").val(ui.item.value);
        $("#product_id").val(ui.item.id);
        $("#main_category").val(ui.item.main_category);
        $("#group_name").val(ui.item.group_name);
        $("#group_code").val(ui.item.group_code);
        $("#product_code").val(ui.item.chemical_code);
        $("#category").val(ui.item.category);
        return false;
    }
});
</script>

</body>
</html>
