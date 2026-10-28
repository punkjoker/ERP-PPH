
<?php
include 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$error = '';
$success = '';

// Escape output safely
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Display expiry date with appropriate styling
function expiryDisplay($date) {
    if (empty($date) || $date === '0000-00-00') {
        return '<span class="text-gray-400">Not Set</span>';
    }

    $today = date('Y-m-d');

    if ($date < $today) {
        $class = 'bg-red-100 text-red-700';
        $label = 'Expired';
    } elseif ($date <= date('Y-m-d', strtotime('+30 days'))) {
        $class = 'bg-yellow-100 text-yellow-800';
        $label = 'Expiring Soon';
    } else {
        $class = 'bg-green-100 text-green-700';
        $label = 'Valid';
    }

    return '<div class="flex flex-col gap-1">
        <span>' . e($date) . '</span>
        <span class="inline-block w-fit px-2 py-1 rounded text-xs font-medium ' .
        $class . '">' . $label . '</span>
    </div>';
}

// Validate a submitted date
function validDate($value) {
    if ($value === '') {
        return true;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);

    return $date &&
        $date->format('Y-m-d') === $value;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        // ADD NEW VEHICLE
        if (isset($_POST['add_vehicle'])) {

            $driver_name = trim($_POST['driver_name'] ?? '');
            $vehicle_number = trim($_POST['vehicle_number'] ?? '');
            $model = trim($_POST['model'] ?? '');
            $vehicle_name = trim($_POST['vehicle_name'] ?? '');

            $license_expiry = trim($_POST['license_expiry'] ?? '');
            $speed_governor_expiry = trim($_POST['speed_governor_expiry'] ?? '');
            $ntsa_expiry = trim($_POST['ntsa_expiry'] ?? '');

            if (
                $driver_name === '' ||
                $vehicle_number === '' ||
                $model === '' ||
                $vehicle_name === '' ||
                $license_expiry === '' ||
                $speed_governor_expiry === '' ||
                $ntsa_expiry === ''
            ) {
                throw new Exception('Please fill in all vehicle fields.');
            }

            foreach ([
                $license_expiry,
                $speed_governor_expiry,
                $ntsa_expiry
            ] as $date) {
                if (!validDate($date)) {
                    throw new Exception('Invalid expiry date provided.');
                }
            }

            $stmt = $conn->prepare("
                INSERT INTO vehicles (
                    driver_name,
                    vehicle_number,
                    model,
                    license_expiry,
                    speed_governor_expiry,
                    ntsa_expiry,
                    vehicle_name
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                'sssssss',
                $driver_name,
                $vehicle_number,
                $model,
                $license_expiry,
                $speed_governor_expiry,
                $ntsa_expiry,
                $vehicle_name
            );

            $stmt->execute();
            $stmt->close();

            header('Location: ' . $_SERVER['PHP_SELF'] . '?success=added');
            exit;
        }

        // UPDATE VEHICLE EXPIRY DATES
        if (isset($_POST['update_expiry'])) {

            $id = (int)($_POST['id'] ?? 0);

            $license_expiry = trim($_POST['license_expiry'] ?? '');
            $speed_governor_expiry = trim($_POST['speed_governor_expiry'] ?? '');
            $ntsa_expiry = trim($_POST['ntsa_expiry'] ?? '');

            if ($id <= 0) {
                throw new Exception('Invalid vehicle ID.');
            }

            if ($license_expiry === '') {
                throw new Exception('Insurance expiry date is required.');
            }

            foreach ([
                $license_expiry,
                $speed_governor_expiry,
                $ntsa_expiry
            ] as $date) {
                if (!validDate($date)) {
                    throw new Exception('Invalid expiry date provided.');
                }
            }

            // Allow optional dates to remain empty
            $speed_governor_expiry = $speed_governor_expiry ?: null;
            $ntsa_expiry = $ntsa_expiry ?: null;

            $stmt = $conn->prepare("
                UPDATE vehicles
                SET
                    license_expiry = ?,
                    speed_governor_expiry = ?,
                    ntsa_expiry = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                'sssi',
                $license_expiry,
                $speed_governor_expiry,
                $ntsa_expiry,
                $id
            );

            $stmt->execute();
            $stmt->close();

            header('Location: ' . $_SERVER['PHP_SELF'] . '?success=updated');
            exit;
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') {
        $success = 'Vehicle added successfully.';
    } elseif ($_GET['success'] === 'updated') {
        $success = 'Vehicle expiry dates updated successfully.';
    }
}

// Fetch all vehicles
$vehicles = $conn->query("
    SELECT
        id,
        driver_name,
        vehicle_number,
        model,
        license_expiry,
        speed_governor_expiry,
        ntsa_expiry,
        vehicle_name
    FROM vehicles
    ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Vehicles</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">

<?php include 'navbar.php'; ?>

<div class="ml-72 p-10 transition-all duration-300">

    <h1 class="text-3xl font-bold text-blue-800 mb-8">
        Manage Vehicles
    </h1>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-300 text-green-700 p-4 rounded-lg mb-6">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-300 text-red-700 p-4 rounded-lg mb-6">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Add Vehicle Form -->
    <div class="bg-white/70 backdrop-blur-md p-6 rounded-2xl shadow-lg border border-blue-200 mb-10 w-full max-w-4xl">

        <h2 class="text-xl font-semibold text-blue-700 mb-4">
            Add New Vehicle
        </h2>

        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <div>
                <label class="block text-sm font-medium text-blue-800 mb-1">
                    Driver Name
                </label>

                <input
                    type="text"
                    name="driver_name"
                    required
                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-blue-800 mb-1">
                    Vehicle Number Plate
                </label>

                <input
                    type="text"
                    name="vehicle_number"
                    required
                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-blue-800 mb-1">
                    Model
                </label>

                <input
                    type="text"
                    name="model"
                    required
                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-blue-800 mb-1">
                    Vehicle Name
                </label>

                <input
                    type="text"
                    name="vehicle_name"
                    required
                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-blue-800 mb-1">
                    Insurance Expiration Date
                </label>

                <input
                    type="date"
                    name="license_expiry"
                    required
                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-blue-800 mb-1">
                    Speed Governor Expiry
                </label>

                <input
                    type="date"
                    name="speed_governor_expiry"
                    required
                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-blue-800 mb-1">
                    NTSA Expiry
                </label>

                <input
                    type="date"
                    name="ntsa_expiry"
                    required
                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
                >
            </div>

            <div class="md:col-span-2 text-right">

                <button
                    type="submit"
                    name="add_vehicle"
                    value="1"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow"
                >
                    Add Vehicle
                </button>

            </div>

        </form>

    </div>

    <!-- Vehicle List -->
    <div class="bg-white/80 backdrop-blur-md p-6 rounded-2xl shadow-lg border border-blue-200">

        <h2 class="text-xl font-semibold text-blue-700 mb-4">
            Vehicle List
        </h2>

        <div class="overflow-x-auto">

            <table class="min-w-full text-sm text-left border border-blue-200">

                <thead class="bg-blue-100 text-blue-900 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-2 border">#</th>
                        <th class="px-4 py-2 border">Driver Name</th>
                        <th class="px-4 py-2 border">Vehicle Number</th>
                        <th class="px-4 py-2 border">Model</th>
                        <th class="px-4 py-2 border">Vehicle Name</th>
                        <th class="px-4 py-2 border">Insurance Expiry</th>
                        <th class="px-4 py-2 border">Speed Governor Expiry</th>
                        <th class="px-4 py-2 border">NTSA Expiry</th>
                        <th class="px-4 py-2 border">Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php
                $count = 1;

                while ($row = $vehicles->fetch_assoc()):
                ?>

                    <tr class="hover:bg-blue-50 border-b">

                        <td class="px-4 py-2 border">
                            <?= $count++ ?>
                        </td>

                        <td class="px-4 py-2 border">
                            <?= e($row['driver_name']) ?>
                        </td>

                        <td class="px-4 py-2 border font-medium">
                            <?= e($row['vehicle_number']) ?>
                        </td>

                        <td class="px-4 py-2 border">
                            <?= e($row['model']) ?>
                        </td>

                        <td class="px-4 py-2 border">
                            <?= e($row['vehicle_name']) ?>
                        </td>

                        <td class="px-4 py-2 border">
                            <?= expiryDisplay($row['license_expiry']) ?>
                        </td>

                        <td class="px-4 py-2 border">
                            <?= expiryDisplay($row['speed_governor_expiry']) ?>
                        </td>

                        <td class="px-4 py-2 border">
                            <?= expiryDisplay($row['ntsa_expiry']) ?>
                        </td>

                        <td class="px-4 py-2 border text-center">

                            <button
                                type="button"
                                class="editVehicleBtn bg-yellow-500 hover:bg-yellow-600 text-white text-xs px-3 py-2 rounded"
                                data-id="<?= (int)$row['id'] ?>"
                                data-insurance="<?= e($row['license_expiry']) ?>"
                                data-governor="<?= e($row['speed_governor_expiry']) ?>"
                                data-ntsa="<?= e($row['ntsa_expiry']) ?>"
                            >
                                Edit
                            </button>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- Edit Modal -->
<div
    id="editModal"
    class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4"
>

    <div class="bg-white p-6 rounded-2xl shadow-xl w-full max-w-md">

        <h2 class="text-xl font-semibold text-blue-700 mb-5">
            Update Vehicle Expiry Dates
        </h2>

        <form method="POST">

            <input type="hidden" name="id" id="editId">

            <div class="mb-4">

                <label class="block text-sm font-medium text-blue-800 mb-1">
                    Insurance Expiry
                </label>

                <input
                    type="date"
                    name="license_expiry"
                    id="editInsurance"
                    required
                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
                >

            </div>

            <div class="mb-4">

                <label class="block text-sm font-medium text-blue-800 mb-1">
                    Speed Governor Expiry
                </label>

                <input
                    type="date"
                    name="speed_governor_expiry"
                    id="editGovernor"
                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
                >

            </div>

            <div class="mb-5">

                <label class="block text-sm font-medium text-blue-800 mb-1">
                    NTSA Expiry
                </label>

                <input
                    type="date"
                    name="ntsa_expiry"
                    id="editNTSA"
                    class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-400"
                >

            </div>

            <div class="flex justify-end gap-3">

                <button
                    type="button"
                    onclick="closeModal()"
                    class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="update_expiry"
                    value="1"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded"
                >
                    Update
                </button>

            </div>

        </form>

    </div>

</div>

<script>
document.querySelectorAll('.editVehicleBtn').forEach(function(button) {

    button.addEventListener('click', function() {

        document.getElementById('editId').value =
            this.dataset.id;

        document.getElementById('editInsurance').value =
            this.dataset.insurance || '';

        document.getElementById('editGovernor').value =
            this.dataset.governor || '';

        document.getElementById('editNTSA').value =
            this.dataset.ntsa || '';

        const modal = document.getElementById('editModal');

        modal.classList.remove('hidden');
        modal.classList.add('flex');

    });

});

function closeModal() {
    const modal = document.getElementById('editModal');

    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener(
    'click',
    function(event) {
        if (event.target === this) {
            closeModal();
        }
    }
);

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>

</body>
</html>
