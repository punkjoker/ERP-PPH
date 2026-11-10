
<?php
include 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$message = '';
$errorMessage = '';

/* ==========================================
   RECEIPT UPLOAD HELPER
========================================== */

function uploadMaintenanceReceipt($field = 'receipt') {

    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Receipt upload failed. Please try again.");
    }

    // Maximum file size: 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException("Receipt must not exceed 5MB.");
    }

    $allowedTypes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'pdf'  => 'application/pdf'
    ];

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!isset($allowedTypes[$extension])) {
        throw new RuntimeException("Only JPG, JPEG, PNG and PDF receipts are allowed.");
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mime !== $allowedTypes[$extension]) {
        throw new RuntimeException("The uploaded file type does not match its extension.");
    }

    $targetDir = __DIR__ . '/uploads/receipts/';

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Unable to create receipt upload directory.");
        }
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;

    $absolutePath = $targetDir . $filename;
    $relativePath = 'uploads/receipts/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException("Unable to save the uploaded receipt.");
    }

    return $relativePath;
}

/* ==========================================
   DELETE MANAGED RECEIPT FILE
========================================== */

function deleteMaintenanceReceipt($path) {

    if (empty($path)) {
        return;
    }

    // Only delete files inside our receipt upload directory.
    $prefix = 'uploads/receipts/';

    if (strpos($path, $prefix) !== 0) {
        return;
    }

    $filename = basename($path);

    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $absolutePath = __DIR__ . '/' . $prefix . $filename;

    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }
}

/* ==========================================
   ADD NEW MAINTENANCE RECORD
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_record'])) {

    $driver_name = trim($_POST['driver_name'] ?? '');
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');
    $maintenance_name = trim($_POST['maintenance_name'] ?? '');
    $maintenance_company = trim($_POST['maintenance_company'] ?? '');
    $maintenance_cost = (float)($_POST['maintenance_cost'] ?? 0);
    $approved_by = trim($_POST['approved_by'] ?? '');

    $newReceipt = null;

    try {

        $newReceipt = uploadMaintenanceReceipt('receipt');
        $receipt_path = $newReceipt ?? '';

        $stmt = $conn->prepare("
            INSERT INTO vehicle_maintenance (
                driver_name,
                vehicle_number,
                maintenance_name,
                maintenance_company,
                maintenance_cost,
                approved_by,
                receipt_path
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssdss",
            $driver_name,
            $vehicle_number,
            $maintenance_name,
            $maintenance_company,
            $maintenance_cost,
            $approved_by,
            $receipt_path
        );

        $stmt->execute();
        $stmt->close();

        $message = "Maintenance record saved successfully.";

    } catch (Throwable $e) {

        // Remove newly uploaded file if the database insert fails.
        if ($newReceipt) {
            deleteMaintenanceReceipt($newReceipt);
        }

        $errorMessage = $e->getMessage();
    }
}

/* ==========================================
   UPDATE MAINTENANCE RECORD
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_record'])) {

    $id = (int)($_POST['record_id'] ?? 0);

    $maintenance_name = trim($_POST['maintenance_name'] ?? '');
    $maintenance_company = trim($_POST['maintenance_company'] ?? '');
    $maintenance_cost = (float)($_POST['maintenance_cost'] ?? 0);
    $approved_by = trim($_POST['approved_by'] ?? '');

    $newReceipt = null;
    $oldReceipt = '';

    try {

        if ($id <= 0) {
            throw new RuntimeException("Invalid maintenance record.");
        }

        // Get the existing receipt path.
        $checkStmt = $conn->prepare("
            SELECT receipt_path
            FROM vehicle_maintenance
            WHERE id = ?
            LIMIT 1
        ");

        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();

        $existingRecord = $checkStmt->get_result()->fetch_assoc();

        $checkStmt->close();

        if (!$existingRecord) {
            throw new RuntimeException("Maintenance record not found.");
        }

        $oldReceipt = $existingRecord['receipt_path'] ?? '';

        // Upload replacement receipt only if a new file was selected.
        $newReceipt = uploadMaintenanceReceipt('receipt');

        // If no new receipt is uploaded, preserve the existing one.
        $receiptPath = $newReceipt ?? $oldReceipt;

        $updateStmt = $conn->prepare("
            UPDATE vehicle_maintenance
            SET
                maintenance_name = ?,
                maintenance_company = ?,
                maintenance_cost = ?,
                approved_by = ?,
                receipt_path = ?
            WHERE id = ?
        ");

        $updateStmt->bind_param(
            "ssdssi",
            $maintenance_name,
            $maintenance_company,
            $maintenance_cost,
            $approved_by,
            $receiptPath,
            $id
        );

        $updateStmt->execute();
        $updateStmt->close();

        // Delete the old receipt only after successful database update.
        if ($newReceipt && $oldReceipt && $newReceipt !== $oldReceipt) {
            deleteMaintenanceReceipt($oldReceipt);
        }

        $message = "Maintenance record updated successfully.";

    } catch (Throwable $e) {

        // Remove new upload if the update fails.
        if ($newReceipt) {
            deleteMaintenanceReceipt($newReceipt);
        }

        $errorMessage = $e->getMessage();
    }
}

/* ==========================================
   DELETE MAINTENANCE RECORD
========================================== */

if (isset($_GET['delete'])) {

    $id = (int)$_GET['delete'];

    try {

        $checkStmt = $conn->prepare("
            SELECT receipt_path
            FROM vehicle_maintenance
            WHERE id = ?
        ");

        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();

        $record = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($record) {

            $deleteStmt = $conn->prepare("
                DELETE FROM vehicle_maintenance
                WHERE id = ?
            ");

            $deleteStmt->bind_param("i", $id);
            $deleteStmt->execute();
            $deleteStmt->close();

            deleteMaintenanceReceipt($record['receipt_path'] ?? '');
        }

    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }

    if (!$errorMessage) {
        header("Location: vehicle_maintenance.php");
        exit;
    }
}

/* ==========================================
   FILTERS
========================================== */

$where = [];
$params = [];
$types = '';

$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$filter_vehicle = trim($_GET['vehicle_number'] ?? '');

if ($from_date !== '' && $to_date !== '') {

    $where[] = "DATE(created_at) BETWEEN ? AND ?";

    $params[] = $from_date;
    $params[] = $to_date;

    $types .= 'ss';
}

if ($filter_vehicle !== '') {

    $where[] = "vehicle_number = ?";

    $params[] = $filter_vehicle;
    $types .= 's';
}

$sql = "SELECT * FROM vehicle_maintenance";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY id DESC";

$recordsStmt = $conn->prepare($sql);

if ($params) {
    $recordsStmt->bind_param($types, ...$params);
}

$recordsStmt->execute();
$records = $recordsStmt->get_result();

$total_cost = 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vehicle Maintenance Costs</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script>

        function printSection() {

            const printContent = document.getElementById('print-area').innerHTML;

            const win = window.open('', '', 'height=800,width=1000');

            win.document.write('<html><head><title>Maintenance Report</title>');
            win.document.write('<script src="https://cdn.tailwindcss.com"><\/script>');
            win.document.write('</head><body>');
            win.document.write(printContent);
            win.document.write('</body></html>');

            win.document.close();
            win.focus();

            win.onload = function () {
                win.print();
            };
        }

        function openEdit(button) {

            document.getElementById('editModal').classList.remove('hidden');

            document.getElementById('record_id').value = button.dataset.id;

            document.getElementById('edit_maintenance_name').value =
                button.dataset.name;

            document.getElementById('edit_maintenance_company').value =
                button.dataset.company;

            document.getElementById('edit_maintenance_cost').value =
                button.dataset.cost;

            document.getElementById('edit_approved_by').value =
                button.dataset.approved;

            // Display existing receipt.
            const receiptContainer = document.getElementById('currentReceipt');

            if (button.dataset.receipt) {

                receiptContainer.innerHTML =
                    '<a href="' + encodeURI(button.dataset.receipt) +
                    '" target="_blank" rel="noopener noreferrer" ' +
                    'class="text-blue-600 underline font-medium">' +
                    'View Current Receipt</a>';

            } else {

                receiptContainer.innerHTML =
                    '<span class="text-gray-500">No receipt uploaded.</span>';
            }

            document.getElementById('edit_receipt').value = '';
        }

        function closeModal() {

            document.getElementById('editModal').classList.add('hidden');

        }

        function confirmReceiptReplacement(input) {

            if (input.files.length > 0) {

                const file = input.files[0];

                const allowed = [
                    'application/pdf',
                    'image/jpeg',
                    'image/png'
                ];

                if (!allowed.includes(file.type)) {
                    alert('Only JPG, PNG and PDF receipts are allowed.');
                    input.value = '';
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    alert('Receipt must not exceed 5MB.');
                    input.value = '';
                    return;
                }
            }
        }

    </script>

</head>

<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">

    <?php include 'navbar.php'; ?>

    <div class="ml-72 p-10 transition-all duration-300">

        <h1 class="text-3xl font-bold text-blue-800 mb-8">
            Vehicle Maintenance Costs
        </h1>

        <!-- SUCCESS / ERROR MESSAGES -->

        <?php if ($message): ?>

            <div class="bg-green-100 border border-green-300 text-green-800 p-4 rounded-lg mb-5">
                <?=htmlspecialchars($message)?>
            </div>

        <?php endif; ?>

        <?php if ($errorMessage): ?>

            <div class="bg-red-100 border border-red-300 text-red-800 p-4 rounded-lg mb-5">
                <?=htmlspecialchars($errorMessage)?>
            </div>

        <?php endif; ?>


        <!-- ADD MAINTENANCE FORM -->

        <div class="bg-white/70 p-6 rounded-2xl shadow-lg border border-blue-200 mb-10 w-full max-w-3xl">

            <h2 class="text-xl font-semibold text-blue-700 mb-4">
                Add New Maintenance Record
            </h2>

            <form method="POST"
                  enctype="multipart/form-data"
                  class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <input type="hidden" name="save_record" value="1">

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Driver Name
                    </label>

                    <input type="text"
                           name="driver_name"
                           required
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Vehicle Number
                    </label>

                    <input type="text"
                           name="vehicle_number"
                           required
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Maintenance Name
                    </label>

                    <input type="text"
                           name="maintenance_name"
                           required
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Maintenance Company/Person
                    </label>

                    <input type="text"
                           name="maintenance_company"
                           required
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Cost (KSh)
                    </label>

                    <input type="number"
                           name="maintenance_cost"
                           step="0.01"
                           min="0"
                           required
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Approved By
                    </label>

                    <input type="text"
                           name="approved_by"
                           required
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400">
                </div>

                <div class="md:col-span-2">

                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Attach Receipt
                    </label>

                    <input type="file"
                           name="receipt"
                           accept=".jpg,.jpeg,.png,.pdf"
                           class="w-full p-2 border rounded-lg bg-white">

                    <p class="text-xs text-gray-500 mt-1">
                        Accepted formats: JPG, PNG and PDF. Maximum size: 5MB.
                    </p>

                </div>

                <div class="md:col-span-2 text-right">

                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">

                        Save Record

                    </button>

                </div>

            </form>

        </div>


        <!-- FILTERS -->

        <form method="GET"
              class="bg-white/70 p-4 rounded-xl shadow border border-blue-200 mb-6 flex flex-wrap gap-4 items-end">

            <div>

                <label class="block text-sm text-blue-800 mb-1">
                    From Date
                </label>

                <input type="date"
                       name="from_date"
                       value="<?=htmlspecialchars($from_date)?>"
                       class="border rounded p-2">

            </div>

            <div>

                <label class="block text-sm text-blue-800 mb-1">
                    To Date
                </label>

                <input type="date"
                       name="to_date"
                       value="<?=htmlspecialchars($to_date)?>"
                       class="border rounded p-2">

            </div>

            <div>

                <label class="block text-sm text-blue-800 mb-1">
                    Vehicle
                </label>

                <input type="text"
                       name="vehicle_number"
                       value="<?=htmlspecialchars($filter_vehicle)?>"
                       placeholder="e.g., KDD 123A"
                       class="border rounded p-2">

            </div>

            <button class="bg-blue-500 hover:bg-blue-600 text-white font-semibold px-4 py-2 rounded-lg shadow">
                Filter
            </button>

            <button type="button"
                    onclick="printSection()"
                    class="bg-green-500 hover:bg-green-600 text-white font-semibold px-4 py-2 rounded-lg shadow">

                Print

            </button>

        </form>


        <!-- MAINTENANCE RECORDS -->

        <div id="print-area"
             class="bg-white/80 p-6 rounded-2xl shadow-lg border border-blue-200">

            <h2 class="text-xl font-semibold text-blue-700 mb-4">
                Maintenance Records
            </h2>

            <div class="overflow-x-auto">

                <table class="min-w-full text-sm text-left border border-blue-200">

                    <thead class="bg-blue-100 text-blue-900 uppercase text-xs">

                        <tr>
                            <th class="px-4 py-2 border">#</th>
                            <th class="px-4 py-2 border">Driver</th>
                            <th class="px-4 py-2 border">Vehicle</th>
                            <th class="px-4 py-2 border">Maintenance</th>
                            <th class="px-4 py-2 border">Company/Person</th>
                            <th class="px-4 py-2 border">Cost</th>
                            <th class="px-4 py-2 border">Approved By</th>
                            <th class="px-4 py-2 border">Receipt</th>
                            <th class="px-4 py-2 border">Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php
                        $count = 1;

                        while ($row = mysqli_fetch_assoc($records)):

                            $total_cost += (float)$row['maintenance_cost'];

                            $receiptPath = $row['receipt_path'] ?? '';

                            $safe = function ($value) {
                                return htmlspecialchars(
                                    (string)$value,
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            };
                        ?>

                            <tr class="hover:bg-blue-50 border-b">

                                <td class="px-4 py-2 border">
                                    <?=$count?>
                                </td>

                                <td class="px-4 py-2 border">
                                    <?=$safe($row['driver_name'])?>
                                </td>

                                <td class="px-4 py-2 border">
                                    <?=$safe($row['vehicle_number'])?>
                                </td>

                                <td class="px-4 py-2 border">
                                    <?=$safe($row['maintenance_name'])?>
                                </td>

                                <td class="px-4 py-2 border">
                                    <?=$safe($row['maintenance_company'])?>
                                </td>

                                <td class="px-4 py-2 border font-medium text-green-700">
                                    KSh <?=number_format((float)$row['maintenance_cost'], 2)?>
                                </td>

                                <td class="px-4 py-2 border">
                                    <?=$safe($row['approved_by'])?>
                                </td>

                                <td class="px-4 py-2 border">

                                    <?php if ($receiptPath): ?>

                                        <a href="<?=$safe($receiptPath)?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="text-blue-600 hover:underline">

                                            <i class="fa-solid fa-file-arrow-up mr-1"></i>
                                            View

                                        </a>

                                    <?php else: ?>

                                        <span class="text-gray-400">N/A</span>

                                    <?php endif; ?>

                                </td>

                                <td class="px-4 py-2 border whitespace-nowrap">

                                    <button
                                        type="button"
                                        data-id="<?=$safe($row['id'])?>"
                                        data-name="<?=$safe($row['maintenance_name'])?>"
                                        data-company="<?=$safe($row['maintenance_company'])?>"
                                        data-cost="<?=$safe($row['maintenance_cost'])?>"
                                        data-approved="<?=$safe($row['approved_by'])?>"
                                        data-receipt="<?=$safe($receiptPath)?>"
                                        onclick="openEdit(this)"
                                        class="text-blue-600 hover:underline">

                                        <i class="fa-solid fa-pen-to-square mr-1"></i>
                                        Edit

                                    </button>

                                    |

                                    <a href="?delete=<?=(int)$row['id']?>"
                                       onclick="return confirm('Delete this maintenance record?')"
                                       class="text-red-600 hover:underline">

                                        Delete

                                    </a>

                                </td>

                            </tr>

                        <?php
                            $count++;
                        endwhile;
                        ?>

                    </tbody>

                    <tfoot class="bg-blue-50 font-semibold">

                        <tr>

                            <td colspan="5"
                                class="text-right px-4 py-2 border">

                                Total Cost:

                            </td>

                            <td class="px-4 py-2 border text-green-700">

                                KSh <?=number_format($total_cost, 2)?>

                            </td>

                            <td colspan="3" class="border"></td>

                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

    </div>


    <!-- ==========================================
         EDIT MAINTENANCE MODAL
    =========================================== -->

    <div id="editModal"
         class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">

        <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">

            <div class="flex justify-between items-center mb-5">

                <h2 class="text-xl font-bold text-blue-700">
                    Edit Maintenance Record
                </h2>

                <button type="button"
                        onclick="closeModal()"
                        class="text-gray-500 hover:text-red-600 text-xl">

                    <i class="fa-solid fa-xmark"></i>

                </button>

            </div>

            <form method="POST"
                  enctype="multipart/form-data"
                  class="space-y-4">

                <input type="hidden" name="update_record" value="1">

                <input type="hidden"
                       id="record_id"
                       name="record_id">

                <div>

                    <label class="block text-sm text-blue-800 mb-1">
                        Maintenance Name
                    </label>

                    <input type="text"
                           id="edit_maintenance_name"
                           name="maintenance_name"
                           required
                           class="w-full p-2 border rounded-lg">

                </div>

                <div>

                    <label class="block text-sm text-blue-800 mb-1">
                        Maintenance Company/Person
                    </label>

                    <input type="text"
                           id="edit_maintenance_company"
                           name="maintenance_company"
                           required
                           class="w-full p-2 border rounded-lg">

                </div>

                <div>

                    <label class="block text-sm text-blue-800 mb-1">
                        Cost (KSh)
                    </label>

                    <input type="number"
                           step="0.01"
                           min="0"
                           id="edit_maintenance_cost"
                           name="maintenance_cost"
                           required
                           class="w-full p-2 border rounded-lg">

                </div>

                <div>

                    <label class="block text-sm text-blue-800 mb-1">
                        Approved By
                    </label>

                    <input type="text"
                           id="edit_approved_by"
                           name="approved_by"
                           required
                           class="w-full p-2 border rounded-lg">

                </div>


                <!-- CURRENT RECEIPT -->

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">

                    <label class="block text-sm font-semibold text-blue-800 mb-2">
                        Current Receipt
                    </label>

                    <div id="currentReceipt" class="text-sm">
                        No receipt uploaded.
                    </div>

                </div>


                <!-- UPLOAD REPLACEMENT RECEIPT -->

                <div>

                    <label class="block text-sm font-semibold text-blue-800 mb-1">
                        Upload / Replace Receipt
                    </label>

                    <input type="file"
                           id="edit_receipt"
                           name="receipt"
                           accept=".jpg,.jpeg,.png,.pdf"
                           onchange="confirmReceiptReplacement(this)"
                           class="w-full p-2 border rounded-lg bg-white">

                    <p class="text-xs text-gray-500 mt-1">
                        Leave blank to keep the current receipt.
                        Upload a new file only when you want to replace it.
                    </p>

                    <p class="text-xs text-gray-500">
                        Accepted formats: JPG, PNG and PDF. Maximum size: 5MB.
                    </p>

                </div>


                <!-- MODAL BUTTONS -->

                <div class="flex justify-end gap-2 pt-3 border-t">

                    <button type="button"
                            onclick="closeModal()"
                            class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-lg">

                        Cancel

                    </button>

                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg">

                        <i class="fa-solid fa-floppy-disk mr-1"></i>
                        Update Record

                    </button>

                </div>

            </form>

        </div>

    </div>

</body>
</html>
