
<?php
include 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// Escape output
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Validate date
function validDate($date) {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// --- Handle new record submission ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_fuel'])) {

    $vehicle_number = trim($_POST['vehicle_number'] ?? '');
    $amount_refueled = (float)($_POST['amount_refueled'] ?? 0);
    $refueled_by = trim($_POST['refueled_by'] ?? '');
    $approved_by = trim($_POST['approved_by'] ?? '');
    $refuel_date = trim($_POST['refuel_date'] ?? '');

    if (
        $vehicle_number === '' ||
        $amount_refueled <= 0 ||
        $refueled_by === '' ||
        $approved_by === '' ||
        !validDate($refuel_date) ||
        $refuel_date > date('Y-m-d')
    ) {
        die("Invalid fuel record details or date selected.");
    }

    // Handle receipt upload
    $receipt_path = '';

    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            die("Receipt upload failed.");
        }

        $target_dir = "uploads/fuel_receipts/";

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $original_name = $_FILES['receipt']['name'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowed_extensions, true)) {
            die("Only JPG, JPEG, PNG and PDF receipts are allowed.");
        }

        $filename = bin2hex(random_bytes(12)) . '.' . $extension;
        $target_file = $target_dir . $filename;

        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $target_file)) {
            die("Could not save the receipt.");
        }

        $receipt_path = $target_file;
    }

    // Save selected date with current time
    $stmt = $conn->prepare("
        INSERT INTO fuel_cost
        (vehicle_number, amount_refueled, refueled_by, approved_by, receipt_path, created_at)
        VALUES (?, ?, ?, ?, ?, CONCAT(?, ' ', CURTIME()))
    ");

    $stmt->bind_param(
        "sdssss",
        $vehicle_number,
        $amount_refueled,
        $refueled_by,
        $approved_by,
        $receipt_path,
        $refuel_date
    );

    $stmt->execute();
    $stmt->close();

    header("Location: fuel.php");
    exit;
}

// --- Handle delete ---
if (isset($_GET['delete'])) {

    $id = (int)$_GET['delete'];

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM fuel_cost WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: fuel.php");
    exit;
}

// --- Handle update ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_fuel'])) {

    $id = (int)($_POST['id'] ?? 0);
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');
    $amount_refueled = (float)($_POST['amount_refueled'] ?? 0);
    $refueled_by = trim($_POST['refueled_by'] ?? '');
    $approved_by = trim($_POST['approved_by'] ?? '');
    $refuel_date = trim($_POST['refuel_date'] ?? '');

    if (
        $id <= 0 ||
        $vehicle_number === '' ||
        $amount_refueled <= 0 ||
        $refueled_by === '' ||
        $approved_by === '' ||
        !validDate($refuel_date) ||
        $refuel_date > date('Y-m-d')
    ) {
        die("Invalid fuel record details or date selected.");
    }

    // Get existing receipt
    $checkStmt = $conn->prepare("
        SELECT receipt_path
        FROM fuel_cost
        WHERE id = ?
        LIMIT 1
    ");

    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();

    $existingRecord = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$existingRecord) {
        die("Fuel record not found.");
    }

    $old_receipt = $existingRecord['receipt_path'] ?? '';
    $receipt_path = $old_receipt;

    // Handle optional replacement receipt
    if (
        isset($_FILES['receipt']) &&
        $_FILES['receipt']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            die("Receipt upload failed.");
        }

        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

        $extension = strtolower(
            pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION)
        );

        if (!in_array($extension, $allowed_extensions, true)) {
            die("Only JPG, JPEG, PNG and PDF receipts are allowed.");
        }

        // Maximum upload size: 5MB
        if ($_FILES['receipt']['size'] > 5 * 1024 * 1024) {
            die("Receipt must not exceed 5MB.");
        }

        $target_dir = "uploads/fuel_receipts/";

        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0775, true);
        }

        $filename = bin2hex(random_bytes(12)) . '.' . $extension;
        $target_file = $target_dir . $filename;

        if (!move_uploaded_file(
            $_FILES['receipt']['tmp_name'],
            $target_file
        )) {
            die("Could not save the new receipt.");
        }

        $receipt_path = $target_file;
    }

    // Update record
    $stmt = $conn->prepare("
        UPDATE fuel_cost SET
            vehicle_number = ?,
            amount_refueled = ?,
            refueled_by = ?,
            approved_by = ?,
            receipt_path = ?,
            created_at = CONCAT(?, ' ', TIME(created_at))
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sdssssi",
        $vehicle_number,
        $amount_refueled,
        $refueled_by,
        $approved_by,
        $receipt_path,
        $refuel_date,
        $id
    );

    $stmt->execute();
    $stmt->close();

    // Delete previous receipt only after successful database update
    if (
        $receipt_path !== $old_receipt &&
        !empty($old_receipt) &&
        is_file($old_receipt)
    ) {
        unlink($old_receipt);
    }

    header("Location: fuel.php");
    exit;
}


// --- Filter Section ---
$vehicle_filter = trim($_GET['vehicle_number'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$sql = "SELECT * FROM fuel_cost WHERE 1=1";
$params = [];
$types = "";

// Vehicle filter
if ($vehicle_filter !== '') {
    $sql .= " AND vehicle_number = ?";
    $params[] = $vehicle_filter;
    $types .= "s";
}

// Date range filter
if (
    $from_date !== '' &&
    $to_date !== '' &&
    validDate($from_date) &&
    validDate($to_date) &&
    $from_date <= $to_date
) {
    $sql .= " AND DATE(created_at) BETWEEN ? AND ?";
    $params[] = $from_date;
    $params[] = $to_date;
    $types .= "ss";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$records = $stmt->get_result();

// Fetch vehicles for dropdown
$vehicles = $conn->query("
    SELECT DISTINCT vehicle_number
    FROM vehicles
    ORDER BY vehicle_number ASC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Fuel Records</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <!-- jsPDF + html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        @media print {
            body * {
                visibility: hidden;
            }

            #reportSection,
            #reportSection * {
                visibility: visible;
            }

            #reportSection {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
                color: black;
                box-shadow: none;
            }

            table {
                border-collapse: collapse;
                width: 100%;
            }

            th,
            td {
                border: 1px solid #333 !important;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>

    <script>
        function openEditModal(id, vehicle, amount, refueled_by, approved_by, date) {

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_vehicle').value = vehicle;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_refueled_by').value = refueled_by;
            document.getElementById('edit_approved_by').value = approved_by;
            document.getElementById('edit_date').value = date;

            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function printPage() {
            window.print();
        }

        async function downloadPDF() {

            const { jsPDF } = window.jspdf;
            const reportSection = document.getElementById('reportSection');

            const canvas = await html2canvas(reportSection, {
                scale: 2,
                backgroundColor: '#ffffff'
            });

            const imgData = canvas.toDataURL('image/png');
            const pdf = new jsPDF('p', 'pt', 'a4');

            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();

            const margin = 20;
            const imgWidth = pageWidth - (margin * 2);
            const imgHeight = canvas.height * imgWidth / canvas.width;

            let heightLeft = imgHeight;
            let position = margin;

            pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight);
            heightLeft -= (pageHeight - margin * 2);

            while (heightLeft > 0) {
                position = margin - (imgHeight - heightLeft);
                pdf.addPage();
                pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight);
                heightLeft -= (pageHeight - margin * 2);
            }

            pdf.save("Fuel_Records_Report.pdf");
        }
    </script>
</head>

<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">

    <?php include 'navbar.php'; ?>

    <div class="ml-72 p-10 transition-all duration-300">

        <h1 class="text-3xl font-bold text-blue-800 mb-8">
            Fuel Records
        </h1>

        <!-- Add Fuel Form -->
        <div class="bg-white/70 backdrop-blur-md p-6 rounded-2xl shadow-lg border border-blue-200 mb-10 w-full max-w-3xl">

            <h2 class="text-xl font-semibold text-blue-700 mb-4">
                Add Fuel Record
            </h2>

            <form method="POST" enctype="multipart/form-data"
                  class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Vehicle Number
                    </label>

                    <select name="vehicle_number" required class="w-full p-2 border rounded-lg">
                        <option value="">Select Vehicle</option>

                        <?php while ($v = mysqli_fetch_assoc($vehicles)): ?>
                            <option value="<?= e($v['vehicle_number']) ?>">
                                <?= e($v['vehicle_number']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Amount Refueled (KSh)
                    </label>

                    <input type="number" name="amount_refueled" min="0.01" step="0.01"
                           required class="w-full p-2 border rounded-lg">
                </div>

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Refueled By
                    </label>

                    <input type="text" name="refueled_by" required
                           class="w-full p-2 border rounded-lg">
                </div>

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Approved By
                    </label>

                    <input type="text" name="approved_by" required
                           class="w-full p-2 border rounded-lg">
                </div>

                <!-- NEW DATE SELECTOR -->
                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Refueling Date
                    </label>

                    <input type="date"
                           name="refuel_date"
                           value="<?= date('Y-m-d') ?>"
                           max="<?= date('Y-m-d') ?>"
                           required
                           class="w-full p-2 border rounded-lg">
                </div>

                <div>
                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Attach Receipt
                    </label>

                    <input type="file" name="receipt"
                           accept=".jpg,.jpeg,.png,.pdf"
                           class="w-full p-2 border rounded-lg bg-white">
                </div>

                <div class="md:col-span-2 text-right">
                    <button type="submit" name="add_fuel"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">
                        Save Record
                    </button>
                </div>

            </form>
        </div>

        <!-- Filter Section -->
        <div class="bg-white/80 p-4 rounded-lg border border-blue-200 mb-6">

            <form method="GET" class="flex flex-wrap gap-4 items-end">

                <div>
                    <label class="text-sm font-medium text-blue-800">
                        From
                    </label>

                    <input type="date" name="from_date"
                           value="<?= e($from_date) ?>"
                           class="p-2 border rounded-lg">
                </div>

                <div>
                    <label class="text-sm font-medium text-blue-800">
                        To
                    </label>

                    <input type="date" name="to_date"
                           value="<?= e($to_date) ?>"
                           class="p-2 border rounded-lg">
                </div>

                <div>
                    <label class="text-sm font-medium text-blue-800">
                        Vehicle
                    </label>

                    <input type="text" name="vehicle_number"
                           value="<?= e($vehicle_filter) ?>"
                           placeholder="Vehicle No."
                           class="p-2 border rounded-lg">
                </div>

                <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Filter
                </button>

                <a href="fuel.php"
                   class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                    Reset
                </a>

                <button type="button" onclick="printPage()"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    Print
                </button>

                <button type="button" onclick="downloadPDF()"
                        class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                    Download PDF
                </button>

            </form>
        </div>

        <!-- Fuel List / Report Section -->
        <div id="reportSection"
             class="bg-white/80 p-6 rounded-2xl shadow-lg border border-blue-200">

            <h2 class="text-xl font-semibold text-blue-700 mb-4">
                Fuel Records List
            </h2>

            <div class="overflow-x-auto">

                <table class="min-w-full text-sm text-left border border-blue-200">

                    <thead class="bg-blue-100 text-blue-900 uppercase text-xs">

                        <tr>
                            <th class="px-4 py-2 border">#</th>
                            <th class="px-4 py-2 border">Date</th>
                            <th class="px-4 py-2 border">Vehicle</th>
                            <th class="px-4 py-2 border">Amount (KSh)</th>
                            <th class="px-4 py-2 border">Refueled By</th>
                            <th class="px-4 py-2 border">Approved By</th>
                            <th class="px-4 py-2 border">Receipt</th>
                            <th class="px-4 py-2 border no-print">Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php
                        $count = 1;
                        $total = 0;

                        while ($row = mysqli_fetch_assoc($records)):

                            $total += (float)$row['amount_refueled'];

                            $formatted_date = !empty($row['created_at'])
                                ? date("d M Y, H:i", strtotime($row['created_at']))
                                : 'N/A';

                            $edit_date = !empty($row['created_at'])
                                ? date("Y-m-d", strtotime($row['created_at']))
                                : date('Y-m-d');

                            $receipt_path = $row['receipt_path'] ?? '';
                        ?>

                            <tr class="hover:bg-blue-50 border-b">

                                <td class="px-4 py-2 border">
                                    <?= $count ?>
                                </td>

                                <td class="px-4 py-2 border">
                                    <?= e($formatted_date) ?>
                                </td>

                                <td class="px-4 py-2 border">
                                    <?= e($row['vehicle_number']) ?>
                                </td>

                                <td class="px-4 py-2 border text-green-700 font-semibold">
                                    KSh <?= number_format((float)$row['amount_refueled'], 2) ?>
                                </td>

                                <td class="px-4 py-2 border">
                                    <?= e($row['refueled_by']) ?>
                                </td>

                                <td class="px-4 py-2 border">
                                    <?= e($row['approved_by']) ?>
                                </td>

                                <td class="px-4 py-2 border">

                                    <?php if ($receipt_path): ?>

                                        <a href="<?= e($receipt_path) ?>"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="text-blue-600 hover:underline">
                                            View
                                        </a>

                                    <?php else: ?>

                                        N/A

                                    <?php endif; ?>

                                </td>

                                <td class="px-4 py-2 border no-print">

                                    <div class="flex gap-2">

                                        <button
                                            type="button"
                                            onclick='openEditModal(
                                                <?= (int)$row['id'] ?>,
                                                <?= json_encode($row['vehicle_number']) ?>,
                                                <?= json_encode($row['amount_refueled']) ?>,
                                                <?= json_encode($row['refueled_by']) ?>,
                                                <?= json_encode($row['approved_by']) ?>,
                                                <?= json_encode($edit_date) ?>
                                            )'
                                            class="text-blue-600 hover:underline">

                                            Edit

                                        </button>

                                        <a href="?delete=<?= (int)$row['id'] ?>"
                                           class="text-red-600 hover:underline"
                                           onclick="return confirm('Delete this record?')">

                                            Delete

                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php
                            $count++;
                        endwhile;
                        ?>

                        <?php if ($count === 1): ?>

                            <tr>
                                <td colspan="8" class="text-center py-6 text-gray-500">
                                    No fuel records found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                    <tfoot>

                        <tr class="bg-blue-100 font-semibold">

                            <td colspan="3" class="px-4 py-2 text-right">
                                Total:
                            </td>

                            <td class="px-4 py-2 text-green-700">
                                KSh <?= number_format($total, 2) ?>
                            </td>

                            <td colspan="4"></td>

                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

    </div>

    <!-- Edit Modal -->
    <div id="editModal"
         class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">

        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md relative">

            <button type="button" onclick="closeModal()"
                    class="absolute top-2 right-2 text-gray-600 hover:text-red-600 text-2xl">
                &times;
            </button>

            <h3 class="text-xl font-semibold mb-4 text-blue-700">
                Edit Fuel Record
            </h3>

            <form method="POST" enctype="multipart/form-data">

                <input type="hidden" name="id" id="edit_id">

                <div class="mb-3">

                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Vehicle Number
                    </label>

                    <input type="text" name="vehicle_number"
                           id="edit_vehicle" required
                           class="w-full p-2 border rounded-lg">

                </div>

                <div class="mb-3">

                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Amount (KSh)
                    </label>

                    <input type="number" name="amount_refueled"
                           id="edit_amount" min="0.01" step="0.01" required
                           class="w-full p-2 border rounded-lg">

                </div>

                <div class="mb-3">

                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Refueled By
                    </label>

                    <input type="text" name="refueled_by"
                           id="edit_refueled_by" required
                           class="w-full p-2 border rounded-lg">

                </div>

                <div class="mb-3">

                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Approved By
                    </label>

                    <input type="text" name="approved_by"
                           id="edit_approved_by" required
                           class="w-full p-2 border rounded-lg">

                </div>

                <!-- NEW EDIT DATE SELECTOR -->
                <div class="mb-4">

                    <label class="block text-sm font-medium text-blue-800 mb-1">
                        Refueling Date
                    </label>

                    <input type="date"
                           name="refuel_date"
                           id="edit_date"
                           max="<?= date('Y-m-d') ?>"
                           required
                           class="w-full p-2 border rounded-lg">

                </div>
<!-- Upload Replacement Receipt -->
<div class="mb-4">

    <label class="block text-sm font-medium text-blue-800 mb-1">
        Upload New Receipt
    </label>

    <input
        type="file"
        name="receipt"
        accept=".jpg,.jpeg,.png,.pdf"
        class="w-full p-2 border rounded-lg bg-white"
    >

    <p class="text-xs text-gray-500 mt-1">
        Optional. JPG, JPEG, PNG or PDF. Maximum 5MB.
        Leave blank to retain the existing receipt.
    </p>

</div>
                <div class="text-right">

                    <button type="submit" name="update_fuel"
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        Update Record
                    </button>

                </div>

            </form>

        </div>

    </div>

</body>
</html>
