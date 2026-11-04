
<?php
require_once 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF protection
if (empty($_SESSION['sacco_csrf_token'])) {
    $_SESSION['sacco_csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'success';

// Escape output
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        if (
            empty($_POST['csrf_token']) ||
            !hash_equals($_SESSION['sacco_csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid request. Please refresh the page and try again.");
        }

        $action = $_POST['action'] ?? '';

        // ADD SACCO
        if ($action === 'add') {

            $saccoName = trim($_POST['sacco_name'] ?? '');
            $registrationNumber = trim($_POST['registration_number'] ?? '');
            $contactPerson = trim($_POST['contact_person'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($saccoName === '') {
                throw new Exception("Please enter the SACCO name.");
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Please enter a valid email address.");
            }

            $stmt = $conn->prepare("
                INSERT INTO hr_saccos
                (
                    sacco_name,
                    registration_number,
                    contact_person,
                    phone,
                    email,
                    address,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, 'Active')
            ");

            $stmt->bind_param(
                "ssssss",
                $saccoName,
                $registrationNumber,
                $contactPerson,
                $phone,
                $email,
                $address
            );

            $stmt->execute();
            $stmt->close();

            $_SESSION['sacco_flash'] = [
                'message' => 'SACCO organisation added successfully.',
                'type' => 'success'
            ];

            header("Location: manage_saccos.php");
            exit;
        }

        // UPDATE SACCO
        elseif ($action === 'update') {

            $saccoId = (int)($_POST['sacco_id'] ?? 0);

            $saccoName = trim($_POST['sacco_name'] ?? '');
            $registrationNumber = trim($_POST['registration_number'] ?? '');
            $contactPerson = trim($_POST['contact_person'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($saccoId <= 0 || $saccoName === '') {
                throw new Exception("Invalid SACCO details.");
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Please enter a valid email address.");
            }

            $stmt = $conn->prepare("
                UPDATE hr_saccos
                SET
                    sacco_name = ?,
                    registration_number = ?,
                    contact_person = ?,
                    phone = ?,
                    email = ?,
                    address = ?
                WHERE sacco_id = ?
            ");

            $stmt->bind_param(
                "ssssssi",
                $saccoName,
                $registrationNumber,
                $contactPerson,
                $phone,
                $email,
                $address,
                $saccoId
            );

            $stmt->execute();
            $stmt->close();

            $_SESSION['sacco_flash'] = [
                'message' => 'SACCO details updated successfully.',
                'type' => 'success'
            ];

            header("Location: manage_saccos.php");
            exit;
        }

        // ACTIVATE / DEACTIVATE SACCO
        elseif ($action === 'toggle_status') {

            $saccoId = (int)($_POST['sacco_id'] ?? 0);
            $newStatus = $_POST['new_status'] ?? '';

            if (
                $saccoId <= 0 ||
                !in_array($newStatus, ['Active', 'Inactive'], true)
            ) {
                throw new Exception("Invalid SACCO status request.");
            }

            $stmt = $conn->prepare("
                UPDATE hr_saccos
                SET status = ?
                WHERE sacco_id = ?
            ");

            $stmt->bind_param("si", $newStatus, $saccoId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['sacco_flash'] = [
                'message' => "SACCO organisation {$newStatus}.",
                'type' => 'success'
            ];

            header("Location: manage_saccos.php");
            exit;
        }

        else {
            throw new Exception("Invalid action.");
        }

    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Retrieve flash message
if (isset($_SESSION['sacco_flash'])) {
    $message = $_SESSION['sacco_flash']['message'];
    $messageType = $_SESSION['sacco_flash']['type'];
    unset($_SESSION['sacco_flash']);
}

// Load SACCO for editing
$editSacco = null;

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    if ($editId > 0) {
        $stmt = $conn->prepare("
            SELECT *
            FROM hr_saccos
            WHERE sacco_id = ?
        ");

        $stmt->bind_param("i", $editId);
        $stmt->execute();

        $result = $stmt->get_result();
        $editSacco = $result->fetch_assoc();

        $stmt->close();
    }
}

// Load SACCO organisations
$saccos = $conn->query("
    SELECT
        s.*,
        (
            SELECT COUNT(*)
            FROM hr_sacco_members m
            WHERE m.sacco_id = s.sacco_id
        ) AS member_count
    FROM hr_saccos s
    ORDER BY s.created_at DESC
");

// Dashboard statistics
$totalSaccos = 0;
$activeSaccos = 0;
$inactiveSaccos = 0;

$stats = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'Active') AS active,
        SUM(status = 'Inactive') AS inactive
    FROM hr_saccos
")->fetch_assoc();

$totalSaccos = (int)($stats['total'] ?? 0);
$activeSaccos = (int)($stats['active'] ?? 0);
$inactiveSaccos = (int)($stats['inactive'] ?? 0);

require_once 'navbar.php';
?>

<main class="ml-64 p-6 max-w-7xl mx-auto">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-7">

        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fa-solid fa-building-columns text-blue-600 mr-2"></i>
                SACCO Management
            </h1>

            <p class="text-sm text-gray-500 mt-1">
                Register and manage employee SACCO organisations.
            </p>
        </div>

        <a href="sacco_members.php"
           class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-lg text-sm font-semibold shadow">
            <i class="fa-solid fa-users"></i>
            Manage SACCO Members
        </a>

    </div>

    <!-- Notifications -->
    <?php if ($message !== ''): ?>
        <div class="mb-6 p-4 rounded-lg border
            <?= $messageType === 'success'
                ? 'bg-green-50 border-green-200 text-green-700'
                : 'bg-red-50 border-red-200 text-red-700' ?>">
            <i class="fa-solid <?= $messageType === 'success'
                ? 'fa-circle-check'
                : 'fa-circle-exclamation' ?> mr-2"></i>

            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-7">

        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total SACCOs</p>
                    <h2 class="text-3xl font-bold text-gray-800 mt-2">
                        <?= $totalSaccos ?>
                    </h2>
                </div>
                <div class="bg-blue-100 text-blue-600 p-4 rounded-xl">
                    <i class="fa-solid fa-building-columns text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Active SACCOs</p>
                    <h2 class="text-3xl font-bold text-green-600 mt-2">
                        <?= $activeSaccos ?>
                    </h2>
                </div>
                <div class="bg-green-100 text-green-600 p-4 rounded-xl">
                    <i class="fa-solid fa-circle-check text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Inactive SACCOs</p>
                    <h2 class="text-3xl font-bold text-red-600 mt-2">
                        <?= $inactiveSaccos ?>
                    </h2>
                </div>
                <div class="bg-red-100 text-red-600 p-4 rounded-xl">
                    <i class="fa-solid fa-ban text-xl"></i>
                </div>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Add / Edit Form -->
        <div class="lg:col-span-1">

            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">

                <div class="bg-gray-50 border-b px-5 py-4">
                    <h2 class="font-bold text-gray-800">
                        <i class="fa-solid <?= $editSacco
                            ? 'fa-pen-to-square'
                            : 'fa-plus-circle' ?> text-blue-600 mr-2"></i>

                        <?= $editSacco ? 'Edit SACCO' : 'Register SACCO' ?>
                    </h2>
                </div>

                <form method="POST" class="p-5 space-y-4">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e($_SESSION['sacco_csrf_token']) ?>">

                    <input type="hidden"
                           name="action"
                           value="<?= $editSacco ? 'update' : 'add' ?>">

                    <?php if ($editSacco): ?>
                        <input type="hidden"
                               name="sacco_id"
                               value="<?= (int)$editSacco['sacco_id'] ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            SACCO Name *
                        </label>

                        <input type="text"
                               name="sacco_name"
                               required
                               maxlength="150"
                               value="<?= e($editSacco['sacco_name'] ?? '') ?>"
                               placeholder="Enter SACCO name"
                               class="w-full border rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Registration Number
                        </label>

                        <input type="text"
                               name="registration_number"
                               maxlength="100"
                               value="<?= e($editSacco['registration_number'] ?? '') ?>"
                               placeholder="Registration number"
                               class="w-full border rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Contact Person
                        </label>

                        <input type="text"
                               name="contact_person"
                               maxlength="150"
                               value="<?= e($editSacco['contact_person'] ?? '') ?>"
                               placeholder="Contact person"
                               class="w-full border rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Phone Number
                        </label>

                        <input type="text"
                               name="phone"
                               maxlength="30"
                               value="<?= e($editSacco['phone'] ?? '') ?>"
                               placeholder="07XXXXXXXX"
                               class="w-full border rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Email Address
                        </label>

                        <input type="email"
                               name="email"
                               maxlength="150"
                               value="<?= e($editSacco['email'] ?? '') ?>"
                               placeholder="example@email.com"
                               class="w-full border rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Address
                        </label>

                        <textarea name="address"
                                  rows="3"
                                  placeholder="SACCO address"
                                  class="w-full border rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none"><?= e($editSacco['address'] ?? '') ?></textarea>
                    </div>

                    <div class="flex gap-2 pt-2">

                        <button type="submit"
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-semibold text-sm">
                            <i class="fa-solid fa-save mr-1"></i>
                            <?= $editSacco ? 'Update SACCO' : 'Save SACCO' ?>
                        </button>

                        <?php if ($editSacco): ?>
                            <a href="manage_saccos.php"
                               class="px-4 py-3 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm font-semibold text-gray-700">
                                Cancel
                            </a>
                        <?php endif; ?>

                    </div>

                </form>

            </div>

        </div>

        <!-- SACCO List -->
        <div class="lg:col-span-2">

            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">

                <div class="px-5 py-4 border-b bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                    <div>
                        <h2 class="font-bold text-gray-800">
                            Registered SACCO Organisations
                        </h2>

                        <p class="text-xs text-gray-500 mt-1">
                            View, edit and manage SACCO status.
                        </p>
                    </div>

                    <input type="text"
                           id="saccoSearch"
                           placeholder="Search SACCO..."
                           class="border rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500">

                </div>

                <div class="overflow-x-auto">

                    <table class="w-full text-sm">

                        <thead class="bg-gray-100 text-gray-600">
                            <tr>
                                <th class="text-left p-4">SACCO</th>
                                <th class="text-left p-4">Contact</th>
                                <th class="text-center p-4">Members</th>
                                <th class="text-center p-4">Status</th>
                                <th class="text-center p-4">Actions</th>
                            </tr>
                        </thead>

                        <tbody id="saccoTableBody">

                            <?php if ($saccos->num_rows > 0): ?>

                                <?php while ($sacco = $saccos->fetch_assoc()): ?>

                                    <tr class="border-t hover:bg-gray-50 sacco-row">

                                        <td class="p-4">
                                            <p class="font-bold text-gray-800 sacco-search-name">
                                                <?= e($sacco['sacco_name']) ?>
                                            </p>

                                            <p class="text-xs text-gray-500 mt-1">
                                                <?= e($sacco['registration_number'] ?: 'No registration number') ?>
                                            </p>
                                        </td>

                                        <td class="p-4">
                                            <p class="text-gray-700">
                                                <?= e($sacco['contact_person'] ?: 'Not specified') ?>
                                            </p>

                                            <p class="text-xs text-gray-500 mt-1">
                                                <?= e($sacco['phone'] ?: $sacco['email'] ?: 'No contact details') ?>
                                            </p>
                                        </td>

                                        <td class="p-4 text-center">
                                            <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full font-bold">
                                                <?= (int)$sacco['member_count'] ?>
                                            </span>
                                        </td>

                                        <td class="p-4 text-center">
                                            <?php if ($sacco['status'] === 'Active'): ?>
                                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-bold">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="p-4">
                                            <div class="flex items-center justify-center gap-2">

                                                <a href="manage_saccos.php?edit=<?= (int)$sacco['sacco_id'] ?>"
                                                   title="Edit SACCO"
                                                   class="bg-blue-50 text-blue-600 hover:bg-blue-100 p-2 rounded-lg">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>

                                                <form method="POST"
                                                      onsubmit="return confirm('Are you sure you want to <?= $sacco['status'] === 'Active' ? 'deactivate' : 'activate' ?> this SACCO?');">

                                                    <input type="hidden"
                                                           name="csrf_token"
                                                           value="<?= e($_SESSION['sacco_csrf_token']) ?>">

                                                    <input type="hidden"
                                                           name="action"
                                                           value="toggle_status">

                                                    <input type="hidden"
                                                           name="sacco_id"
                                                           value="<?= (int)$sacco['sacco_id'] ?>">

                                                    <input type="hidden"
                                                           name="new_status"
                                                           value="<?= $sacco['status'] === 'Active' ? 'Inactive' : 'Active' ?>">

                                                    <button type="submit"
                                                            title="<?= $sacco['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>"
                                                            class="<?= $sacco['status'] === 'Active'
                                                                ? 'bg-red-50 text-red-600 hover:bg-red-100'
                                                                : 'bg-green-50 text-green-600 hover:bg-green-100' ?> p-2 rounded-lg">

                                                        <i class="fa-solid <?= $sacco['status'] === 'Active'
                                                            ? 'fa-ban'
                                                            : 'fa-check' ?>"></i>

                                                    </button>

                                                </form>

                                            </div>
                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="5" class="p-10 text-center text-gray-500">
                                        <i class="fa-solid fa-building-columns text-3xl mb-3 text-gray-300"></i>
                                        <p>No SACCO organisations registered yet.</p>
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</main>

<script>
document.getElementById('saccoSearch').addEventListener('input', function () {

    const searchValue = this.value.toLowerCase().trim();

    document.querySelectorAll('.sacco-row').forEach(function (row) {

        const rowText = row.innerText.toLowerCase();

        row.style.display = rowText.includes(searchValue) ? '' : 'none';

    });

});
</script>
