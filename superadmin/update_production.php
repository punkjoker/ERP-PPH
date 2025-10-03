<?php
include 'db_con.php';

$bom_id = $_GET['id'] ?? 0;
$production = null;

// ✅ Step 1: Ensure a production_run row exists for this BOM
$conn->query("INSERT IGNORE INTO production_runs (request_id, status) VALUES ($bom_id, 'In production')");

// ✅ Step 2: Fetch production run + product details
$sql = "
    SELECT pr.*, p.name AS product_name, bom.requested_by, bom.description
    FROM production_runs pr
    JOIN bill_of_materials bom ON pr.request_id = bom.id
    JOIN products p ON bom.product_id = p.id
    WHERE bom.id = $bom_id
    LIMIT 1
";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $production = $result->fetch_assoc();
}

// ✅ Step 3: Handle insert/update

   // ✅ Insert new procedures if not already existing
if (!empty($_POST['procedures'])) {
    foreach ($_POST['procedures'] as $proc) {
        $name = trim($proc['name']);
        $done_by = trim($proc['done_by']);
        $checked_by = trim($proc['checked_by']);

        // Skip if all fields are empty
        if ($name === '' && $done_by === '' && $checked_by === '') {
            continue;
        }

        $name = $conn->real_escape_string($name);
        $done_by = $conn->real_escape_string($done_by);
        $checked_by = $conn->real_escape_string($checked_by);

        // Check if this procedure already exists
        $check = $conn->query("
            SELECT id FROM production_procedures
            WHERE production_run_id = {$production['id']}
            AND procedure_name = '$name'
            AND done_by = '$done_by'
            AND checked_by = '$checked_by'
            LIMIT 1
        ");

        // ✅ Step 1: Ensure a production_run row exists for this BOM
$check = $conn->query("SELECT id FROM production_runs WHERE request_id = $bom_id LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO production_runs (request_id, status) VALUES ($bom_id, 'In production')");
}
    }


    echo "<script>alert('Production & Procedures saved successfully!');window.location='record_production_run.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Production</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        let procIndex = 1; // start from 1 because first row is index 0

        function addProcedureRow() {
            const container = document.getElementById("procedureContainer");
            const div = document.createElement("div");
            div.classList = "grid grid-cols-3 gap-4 bg-gray-50 p-3 rounded-lg border mb-2";
            div.innerHTML = `
                <input type="text" name="procedures[${procIndex}][name]" placeholder="Procedure Name" 
                    class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
                <input type="text" name="procedures[${procIndex}][done_by]" placeholder="Done By" 
                    class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
                <input type="text" name="procedures[${procIndex}][checked_by]" placeholder="Checked By" 
                    class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
            `;
            container.appendChild(div);
            procIndex++;
        }
    </script>
</head>
<body class="bg-gray-100 flex font-sans">

    <?php include 'navbar.php'; ?>

    <div class="flex-1 p-8 ml-64">
        <!-- Header Section -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-6 flex items-center border-b-4 border-blue-600">
            <img src="images/lynn_logo.png" alt="Logo" class="h-16 mr-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">BATCH MANUFACTURING (QF-27)</h1>
                <p class="text-sm text-gray-600">DOC. NO.: <span class="font-medium">DOC /BMR/194</span></p>
                <p class="text-sm text-gray-600">PRODUCT NAME: 
                    <span class="font-semibold"><?= $production['product_name'] ?></span> &nbsp; 
                    <p class="text-sm text-gray-600">PRODUCT CODE.: <span class="font-medium">209024</span></p>
                </p>
                <p class="text-sm text-gray-600">EDITION NO.: 007</p>
                <p class="text-sm text-gray-600">
                    EFFECTIVE DATE: 1ST SEPTEMBER 2024 &nbsp;&nbsp; 
                    REVIEW DATE: 1ST AUGUST 2027
                </p>
            </div>
        </div>

        <!-- Production Form -->
        <form method="POST" class="space-y-8 bg-white shadow-lg p-8 rounded-lg border">

            <!-- Manufacturing Details -->
            <section>
                <h3 class="text-lg font-semibold text-blue-700 border-b pb-2 mb-4">Manufacturing Details</h3>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-1">Expected Yield (Kg/L)</label>
                        <input type="text" name="expected_yield" value="<?= $production['expected_yield'] ?>" 
                               class="border p-3 rounded w-full focus:ring-2 focus:ring-blue-500" placeholder="Enter expected yield">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Obtained Yield (Kg/L)</label>
                        <input type="text" name="obtained_yield" value="<?= $production['obtained_yield'] ?>" 
                               class="border p-3 rounded w-full focus:ring-2 focus:ring-blue-500" placeholder="Enter obtained yield">
                    </div>
                </div>
            </section>

            <!-- Procedure Section -->
            <section>
                <h3 class="text-lg font-semibold text-blue-700 border-b pb-2 mb-4">Procedure</h3>
                <div id="procedureContainer" class="space-y-3">
                    <!-- Default row (index 0) -->
                    <div class="grid grid-cols-3 gap-4 bg-gray-50 p-3 rounded-lg border">
                        <input type="text" name="procedures[0][name]" placeholder="Procedure Name" 
                               class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
                        <input type="text" name="procedures[0][done_by]" placeholder="Done By" 
                               class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
                        <input type="text" name="procedures[0][checked_by]" placeholder="Checked By" 
                               class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <button type="button" onclick="addProcedureRow()" 
                        class="mt-4 bg-blue-600 text-white px-5 py-2 rounded shadow hover:bg-blue-700 transition">
                    + Add Procedure
                </button>
            </section>

            <!-- Status Section -->
            <section>
                <h3 class="text-lg font-semibold text-blue-700 border-b pb-2 mb-4">Update Status</h3>
                <select name="status" class="border p-3 rounded w-full focus:ring-2 focus:ring-green-500">
                    <option value="In production" <?= ($production['status'] == 'In production') ? 'selected' : '' ?>>In production</option>
                    <option value="Completed" <?= ($production['status'] == 'Completed') ? 'selected' : '' ?>>Completed</option>
                </select>
            </section>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <button type="submit" name="update_production" 
                        class="bg-green-600 text-white px-8 py-3 rounded-lg shadow hover:bg-green-700 transition">
                    ✅ Update Production
                </button>
            </div>
        </form>
    </div>
</body>
</html>
