<?php
include 'db_con.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Store B Chemical Receiving</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-4xl">
    <h2 class="text-xl font-bold mb-4">Store B Chemical Receiving</h2>

    <form method="POST" action="" class="bg-blue-100 p-4 rounded-lg shadow-md space-y-3 text-sm">
        <div>
            <label class="block font-medium">Chemical Name</label>
            <input type="text" id="chemical_name" name="chemical_name" autocomplete="off"
                   class="w-full p-1 rounded border border-gray-300 text-sm">
            <input type="hidden" id="chemical_id" name="chemical_id">
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
                <label class="block font-medium">Chemical Code</label>
                <input type="text" id="chemical_code" name="chemical_code" readonly
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

    <!-- Display Table -->
    <?php
   if(isset($_POST['submit'])) {
    $chemical_id = intval($_POST['chemical_id'] ?? 0);
    $chemical_name = $_POST['chemical_name'] ?? '';
    $main_category = $_POST['main_category'] ?? '';
    $group_name = $_POST['group_name'] ?? '';
    $group_code = $_POST['group_code'] ?? '';
    $chemical_code = $_POST['chemical_code'] ?? '';
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

$stmt = $conn->prepare("INSERT INTO store_b_chemicals_in 
    (chemical_id, chemical_name, main_category, group_name, group_code, chemical_code, category, delivery_number, remaining_quantity, quantity_received, units, pack_size, unit_cost, po_number, received_by, receiving_date) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if(!$stmt){
    echo "<p class='text-red-600 font-semibold mt-2'>Prepare failed: " . htmlspecialchars($conn->error) . "</p>";
} else {
    $stmt->bind_param(
        "isssssssddssdsss",
        $chemical_id,
        $chemical_name,
        $main_category,
        $group_name,
        $group_code,
        $chemical_code,
        $category,
        $delivery_number,
        $quantity_received,
        $quantity_received,
        $units,
        $pack_size,
        $unit_cost,
        $po_number,
        $received_by,
        $receiving_date
    );

    $stmt->execute();
    $stmt->close();
}
    echo "<p class='text-green-600 font-semibold mt-2'>Data saved successfully!</p>";
}

    $result = $conn->query("SELECT * FROM store_b_chemicals_in ORDER BY created_at DESC");
    if($result->num_rows > 0) {
        echo "<div class='overflow-x-auto mt-4'>
        <table class='min-w-full bg-white rounded-lg shadow-md text-sm'>
        <thead>
            <tr class='bg-blue-200 text-left'>
                <th class='px-2 py-1'>Chemical Name</th>
                <th class='px-2 py-1'>Main Category</th>
                <th class='px-2 py-1'>Group Name</th>
                <th class='px-2 py-1'>Group Code</th>
                <th class='px-2 py-1'>Chemical Code</th>
                <th class='px-2 py-1'>Category</th>
                <th class='px-2 py-1'>Delivery No.</th>
                <th class='px-2 py-1'>Qty</th>
                <th class='px-2 py-1'>Units</th>
                <th class='px-2 py-1'>Pack Size</th>
                <th class='px-2 py-1'>Unit Cost</th>
                <th class='px-2 py-1'>PO Number</th>
                <th class='px-2 py-1'>Received By</th>
                <th class='px-2 py-1'>Receiving Date</th>
            </tr>
        </thead>
        <tbody>";
        while($row = $result->fetch_assoc()) {
            echo "<tr class='border-b hover:bg-gray-100'>
                <td class='px-2 py-1'>{$row['chemical_name']}</td>
                <td class='px-2 py-1'>{$row['main_category']}</td>
                <td class='px-2 py-1'>{$row['group_name']}</td>
                <td class='px-2 py-1'>{$row['group_code']}</td>
                <td class='px-2 py-1'>{$row['chemical_code']}</td>
                <td class='px-2 py-1'>{$row['category']}</td>
                <td class='px-2 py-1'>{$row['delivery_number']}</td>
                <td class='px-2 py-1'>{$row['quantity_received']}</td>
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
$("#chemical_name").autocomplete({
    source: "store_b_chemical_autocomplete.php",
    minLength: 1,
    select: function(event, ui) {
        $("#chemical_name").val(ui.item.value);
        $("#chemical_id").val(ui.item.id);
        $("#main_category").val(ui.item.main_category);
        $("#group_name").val(ui.item.group_name);
        $("#group_code").val(ui.item.group_code);
        $("#chemical_code").val(ui.item.chemical_code);
        $("#category").val(ui.item.category);
        return false;
    }
});

</script>


</body>
</html>
