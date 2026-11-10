
<?php
include 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

$success = '';
$error = '';

/**
 * Upload a training certificate.
 * Returns the relative path or null if no file was selected.
 */
function uploadTrainingCertificate($file): ?string
{
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Certificate upload failed. Please try again.");
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("Certificate must not exceed 5MB.");
    }

    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!isset($allowed[$mime])) {
        throw new Exception("Only PDF, JPG, JPEG and PNG certificates are allowed.");
    }

    $uploadDir = __DIR__ . '/uploads/training_certificates/';

    if (!is_dir($uploadDir) &&
        !mkdir($uploadDir, 0755, true) &&
        !is_dir($uploadDir)) {
        throw new Exception("Could not create certificate upload folder.");
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Could not save the uploaded certificate.");
    }

    return 'uploads/training_certificates/' . $filename;
}

// Handle training submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'])) {
    try {
        $employee_id   = $_POST['employee_id'];
        $training_name = trim($_POST['training_name'] ?? '');
        $training_date = $_POST['training_date'] ?? '';
        $status        = $_POST['status'] ?? 'Pending';
        $done_by       = trim($_POST['done_by'] ?? '');
        $approved_by   = trim($_POST['approved_by'] ?? '');

        if ($training_name === '' || $training_date === '') {
            throw new Exception("Training name and date are required.");
        }

        if (!in_array($status, ['Pending', 'Done'], true)) {
            throw new Exception("Invalid training status.");
        }

        // Upload once. The same certificate is associated with all
        // employee records when All Employees is selected.
        $certificatePath = uploadTrainingCertificate($_FILES['certificate'] ?? null);

        if ($employee_id === "all") {
            $result = $conn->query("
                SELECT employee_id
                FROM employees
                WHERE LOWER(TRIM(status)) = 'active'
            ");

            $stmt = $conn->prepare("
                INSERT INTO trainings
                (employee_id, training_name, training_date, status,
                 done_by, approved_by, certificate_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $inserted = 0;

            while ($row = $result->fetch_assoc()) {
                $emp_id = (int)$row['employee_id'];

                $stmt->bind_param(
                    "issssss",
                    $emp_id,
                    $training_name,
                    $training_date,
                    $status,
                    $done_by,
                    $approved_by,
                    $certificatePath
                );

                $stmt->execute();
                $inserted++;
            }

            $stmt->close();

            if ($inserted === 0) {
                throw new Exception("No active employees were found.");
            }

            $success = "Training recorded for {$inserted} active employees successfully!";
        } else {
            $emp_id = filter_var($employee_id, FILTER_VALIDATE_INT);

            if (!$emp_id) {
                throw new Exception("Please select a valid employee.");
            }

            $stmt = $conn->prepare("
                INSERT INTO trainings
                (employee_id, training_name, training_date, status,
                 done_by, approved_by, certificate_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "issssss",
                $emp_id,
                $training_name,
                $training_date,
                $status,
                $done_by,
                $approved_by,
                $certificatePath
            );

            $stmt->execute();
            $stmt->close();

            $success = "Training recorded successfully!";
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// Fetch active employees
$employees_res = $conn->query("
    SELECT employee_id, first_name, last_name
    FROM employees
    WHERE LOWER(TRIM(status)) = 'active'
    ORDER BY first_name
");

$employees = $employees_res->fetch_all(MYSQLI_ASSOC);

// Fetch trainings
$result = $conn->query("
    SELECT t.*, e.first_name, e.last_name
    FROM trainings t
    JOIN employees e ON t.employee_id = e.employee_id
    ORDER BY t.training_date DESC
");

$trainings = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Training</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto">

    <h1 class="text-3xl font-bold mb-6 text-blue-700">Record Training</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Add Training Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">

        <form method="POST" enctype="multipart/form-data" class="space-y-4">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <select name="employee_id" required
                        class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">

                    <option value="">Select Employee</option>
                    <option value="all">All Employees</option>

                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= (int)$emp['employee_id'] ?>">
                            <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <input type="text"
                       name="training_name"
                       placeholder="Training Name"
                       class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300"
                       required>

                <input type="date"
                       name="training_date"
                       class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300"
                       required>

                <select name="status"
                        class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                    <option value="Pending">Pending</option>
                    <option value="Done">Done</option>
                </select>

                <input type="text"
                       name="done_by"
                       placeholder="Training Done By"
                       class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">

                <input type="text"
                       name="approved_by"
                       placeholder="Approved By"
                       class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">

            </div>

            <!-- Certificate Upload -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Upload Training Certificate
                </label>

                <input type="file"
                       name="certificate"
                       accept=".pdf,.jpg,.jpeg,.png"
                       class="border p-2 rounded w-full bg-white">

                <p class="text-xs text-gray-500 mt-1">
                    Optional. PDF, JPG or PNG. Maximum 5MB.
                    When recording for all employees, the same certificate
                    will be attached to each record.
                </p>
            </div>

            <button type="submit"
                    class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Record Training
            </button>

        </form>
    </div>

    <!-- Trainings List -->
    <div class="bg-white shadow rounded-lg p-6">

        <h2 class="text-xl font-semibold mb-4 text-blue-700">Training Records</h2>

        <div class="flex justify-end mb-4">
            <a href="download_training_records.php"
               class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                ⬇️ Download Training Records
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border border-gray-300 rounded">

                <thead class="bg-gray-200">
                    <tr>
                        <th class="border px-3 py-2">#</th>
                        <th class="border px-3 py-2">Employee</th>
                        <th class="border px-3 py-2">Training Name</th>
                        <th class="border px-3 py-2">Date</th>
                        <th class="border px-3 py-2">Status</th>
                        <th class="border px-3 py-2">Done By</th>
                        <th class="border px-3 py-2">Approved By</th>
                        <th class="border px-3 py-2">Certificate</th>
                        <th class="border px-3 py-2">Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!empty($trainings)): ?>
                    <?php $count = 1; ?>

                    <?php foreach ($trainings as $tr): ?>

                        <tr class="<?= ($count % 2 === 0) ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">

                            <td class="border px-3 py-2"><?= $count++ ?></td>

                            <td class="border px-3 py-2">
                                <?= htmlspecialchars($tr['first_name'] . ' ' . $tr['last_name']) ?>
                            </td>

                            <td class="border px-3 py-2">
                                <?= htmlspecialchars($tr['training_name']) ?>
                            </td>

                            <td class="border px-3 py-2">
                                <?= htmlspecialchars($tr['training_date']) ?>
                            </td>

                            <td class="border px-3 py-2 text-center">
                                <?php if ($tr['status'] === 'Done'): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded">Done</span>
                                <?php else: ?>
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Pending</span>
                                <?php endif; ?>
                            </td>

                            <td class="border px-3 py-2">
                                <?= htmlspecialchars($tr['done_by'] ?? '') ?>
                            </td>

                            <td class="border px-3 py-2">
                                <?= htmlspecialchars($tr['approved_by'] ?? '') ?>
                            </td>

                            <td class="border px-3 py-2 text-center">
                                <?php if (!empty($tr['certificate_path'])): ?>
                                    <a href="<?= htmlspecialchars($tr['certificate_path']) ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="text-blue-600 hover:underline font-semibold">
                                        View Certificate
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">Not uploaded</span>
                                <?php endif; ?>
                            </td>

                            <td class="border px-3 py-2 whitespace-nowrap space-x-1">

                                <button onclick="openModal('modal-<?= (int)$tr['training_id'] ?>')"
                                        class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600">
                                    Edit
                                </button>

                                <a href="view_training.php?id=<?= (int)$tr['training_id'] ?>"
                                   class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                    View
                                </a>

                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div id="modal-<?= (int)$tr['training_id'] ?>"
                             class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden overflow-auto p-4"
                             style="z-index: 1000;">

                            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-lg">

                                <h2 class="text-xl font-bold mb-4">Edit Training</h2>

                                <form method="POST"
                                      action="edit_training.php"
                                      enctype="multipart/form-data">

                                    <input type="hidden"
                                           name="training_id"
                                           value="<?= (int)$tr['training_id'] ?>">

                                    <label class="block mb-2 font-semibold">Training Name</label>
                                    <input type="text"
                                           name="training_name"
                                           value="<?= htmlspecialchars($tr['training_name']) ?>"
                                           class="border p-2 rounded w-full mb-4"
                                           required>

                                    <label class="block mb-2 font-semibold">Training Date</label>
                                    <input type="date"
                                           name="training_date"
                                           value="<?= htmlspecialchars($tr['training_date']) ?>"
                                           class="border p-2 rounded w-full mb-4"
                                           required>

                                    <label class="block mb-2 font-semibold">Status</label>
                                    <select name="status"
                                            class="border p-2 rounded w-full mb-4">
                                        <option value="Pending" <?= $tr['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Done" <?= $tr['status'] === 'Done' ? 'selected' : '' ?>>Done</option>
                                    </select>

                                    <label class="block mb-2 font-semibold">Training Done By</label>
                                    <input type="text"
                                           name="done_by"
                                           value="<?= htmlspecialchars($tr['done_by'] ?? '') ?>"
                                           class="border p-2 rounded w-full mb-4">

                                    <label class="block mb-2 font-semibold">Approved By</label>
                                    <input type="text"
                                           name="approved_by"
                                           value="<?= htmlspecialchars($tr['approved_by'] ?? '') ?>"
                                           class="border p-2 rounded w-full mb-4">

                                    <!-- Existing Certificate -->
                                    <div class="mb-4">
                                        <label class="block mb-2 font-semibold">Current Certificate</label>

                                        <?php if (!empty($tr['certificate_path'])): ?>
                                            <a href="<?= htmlspecialchars($tr['certificate_path']) ?>"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="text-blue-600 hover:underline">
                                                View Current Certificate
                                            </a>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-500">No certificate uploaded.</p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Replace Certificate -->
                                    <div class="mb-5">
                                        <label class="block mb-2 font-semibold">
                                            Upload New Certificate
                                        </label>

                                        <input type="file"
                                               name="certificate"
                                               accept=".pdf,.jpg,.jpeg,.png"
                                               class="border p-2 rounded w-full bg-white">

                                        <p class="text-xs text-gray-500 mt-1">
                                            Optional. PDF, JPG or PNG. Maximum 5MB.
                                            Leave blank to retain the existing certificate.
                                        </p>
                                    </div>

                                    <div class="flex justify-end gap-2">

                                        <button type="button"
                                                onclick="closeModal('modal-<?= (int)$tr['training_id'] ?>')"
                                                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                            Cancel
                                        </button>

                                        <button type="submit"
                                                class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                                            Save Changes
                                        </button>

                                    </div>

                                </form>
                            </div>
                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="border px-3 py-2 text-center text-gray-500">
                            No trainings found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}
</script>

</body>
</html>
