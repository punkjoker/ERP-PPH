
<?php
// create_staff_account.php
session_start();
require 'db_con.php';

$use_pdo = isset($pdo) && $pdo instanceof PDO;
$use_mysqli = isset($conn) && $conn instanceof mysqli;

if (!$use_pdo && !$use_mysqli) {
    die("Database connection not found. Check db_con.php.");
}

if ($use_pdo) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} else {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->set_charset("utf8mb4");
}

$error = '';
$success = '';
$employees = [];
$users = [];

// --------------------------------------------------
// FETCH ACTIVE EMPLOYEES
// Assumed columns: employee_id, full_name, email,
// national_id, status
// --------------------------------------------------

try {
    $sql = "
        SELECT
            employee_id,
            first_name,
            last_name,
            CONCAT_WS(' ', TRIM(first_name), TRIM(last_name)) AS full_name,
            email,
            national_id,
            status
        FROM employees
        WHERE LOWER(TRIM(status)) = 'active'
        ORDER BY first_name ASC, last_name ASC
    ";

    if ($use_pdo) {
        $stmt = $pdo->query($sql);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $result = $conn->query($sql);

        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }

        $result->free();
    }

} catch (Throwable $e) {
    $error = "Could not load employees: " . $e->getMessage();
}


// --------------------------------------------------
// HANDLE USER STATUS UPDATE
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_status'])) {

    $user_id = filter_var(
        $_POST['user_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $new_status = $_POST['new_status'] ?? '';

    if (!$user_id || !in_array($new_status, ['active', 'inactive'], true)) {
        $error = "Invalid user or status.";
    } else {
        try {
            if ($use_pdo) {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET status = ?
                    WHERE user_id = ? AND group_id = 9
                ");
                $stmt->execute([$new_status, $user_id]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE users
                    SET status = ?
                    WHERE user_id = ? AND group_id = 9
                ");
                $stmt->bind_param("si", $new_status, $user_id);
                $stmt->execute();
                $stmt->close();
            }

            $success = "User status updated successfully.";
        } catch (Throwable $e) {
            $error = "Could not update user status: " . $e->getMessage();
        }
    }
}

// --------------------------------------------------
// CREATE STAFF ACCOUNT
// Group ID is fixed to 9.
// Employee details are fetched again on the server.
// --------------------------------------------------
elseif ($_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['create_account'])) {

    $employee_id = filter_var(
        $_POST['employee_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $group_id = 9;

    if (!$employee_id || $employee_id < 1) {
        $error = "Please select an employee.";
    } elseif ($password === '' || $confirm_password === '') {
        $error = "Please enter and confirm the password.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        try {
            
            // Confirm the employee is still active.
            // Combine first_name and last_name because employees
            // does not have a full_name column.
            if ($use_pdo) {
                $stmt = $pdo->prepare("
                    SELECT
                        employee_id,
                        CONCAT_WS(
                            ' ',
                            TRIM(first_name),
                            TRIM(last_name)
                        ) AS full_name,
                        email,
                        national_id
                    FROM employees
                    WHERE employee_id = ?
                      AND LOWER(TRIM(status)) = 'active'
                    LIMIT 1
                ");

                $stmt->execute([$employee_id]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);

            } else {
                $stmt = $conn->prepare("
                    SELECT
                        employee_id,
                        CONCAT_WS(
                            ' ',
                            TRIM(first_name),
                            TRIM(last_name)
                        ) AS full_name,
                        email,
                        national_id
                    FROM employees
                    WHERE employee_id = ?
                      AND LOWER(TRIM(status)) = 'active'
                    LIMIT 1
                ");

                $stmt->bind_param("i", $employee_id);
                $stmt->execute();

                $result = $stmt->get_result();
                $employee = $result->fetch_assoc();

                $stmt->close();
            }

            if (!$employee) {
                throw new Exception(
                    "The selected employee is no longer active. Please select an active employee."
                );
            }

            $full_name = trim($employee['full_name'] ?? '');
            $email = trim($employee['email'] ?? '');
            $national_id = trim($employee['national_id'] ?? '');

            if ($full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception(
                    "This employee needs a valid name and email in the employees table before an account can be created."
                );
            }

            // Prevent duplicate accounts by email.
            
if ($use_pdo) {
    $insert = $pdo->prepare("
        INSERT INTO users
            (employee_id, full_name, email, national_id, password, group_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $insert->execute([
        $employee_id,
        $full_name,
        $email,
        $national_id,
        $hashedPassword,
        $group_id
    ]);

} else {
    $insert = $conn->prepare("
        INSERT INTO users
            (employee_id, full_name, email, national_id, password, group_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $insert->bind_param(
        "issssi",
        $employee_id,
        $full_name,
        $email,
        $national_id,
        $hashedPassword,
        $group_id
    );

    $insert->execute();
    $insert->close();
}


            if ($existing) {
                throw new Exception(
                    "An account already exists for this employee's email address."
                );
            }

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            if ($use_pdo) {
                $insert = $pdo->prepare("
                    INSERT INTO users
                        (full_name, email, national_id, password, group_id)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $insert->execute([
                    $full_name,
                    $email,
                    $national_id,
                    $hashedPassword,
                    $group_id
                ]);
            } else {
                $insert = $conn->prepare("
                    INSERT INTO users
                        (full_name, email, national_id, password, group_id)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $insert->bind_param(
                    "ssssi",
                    $full_name,
                    $email,
                    $national_id,
                    $hashedPassword,
                    $group_id
                );

                $insert->execute();
                $insert->close();
            }

            $success = "Staff account created successfully for " . $full_name . ".";

            // Clear submitted form values.
            $_POST = [];

        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

// --------------------------------------------------
// FETCH EXISTING GROUP 9 USERS
// --------------------------------------------------
try {
    $sql = "
        SELECT u.user_id, u.full_name, u.email,
               u.national_id, u.status, g.group_name
        FROM users u
        LEFT JOIN groups g ON u.group_id = g.group_id
        WHERE u.group_id = 9
        ORDER BY u.user_id DESC
    ";

    if ($use_pdo) {
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $result = $conn->query($sql);

        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
} catch (Throwable $e) {
    $error = $error ?: "Could not load existing users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Staff Account | Lynntech Manager</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body class="bg-gray-100 min-h-screen">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">

<main class="p-5 md:p-8 md:ml-0"
      style="margin-left:0;">

    <div class="max-w-7xl mx-auto">

        <div class="mb-7">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-12 h-12 rounded-xl bg-blue-600 text-white
                            flex items-center justify-center text-xl">
                    <i class="fa-solid fa-user-shield"></i>
                </div>

                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">
                        Create Staff Account
                    </h1>
                    <p class="text-slate-500 text-sm mt-1">
                        Select an active employee to create their system login.
                    </p>
                </div>
            </div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="mb-5 rounded-xl border border-green-200 bg-green-50
                        text-green-800 p-4">
                <i class="fa-solid fa-circle-check mr-2"></i>
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50
                        text-red-800 p-4 break-words">
                <i class="fa-solid fa-circle-exclamation mr-2"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- CREATE ACCOUNT FORM -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200
                        overflow-hidden mb-8">

            <div class="px-6 py-5 border-b border-slate-200">
                <h2 class="font-bold text-lg text-slate-800">
                    <i class="fa-solid fa-user-plus text-blue-600 mr-2"></i>
                    Staff Account Details
                </h2>

                <p class="text-sm text-slate-500 mt-1">
                    Employee information is populated automatically.
                </p>
            </div>

            <form method="POST" class="p-6 space-y-6" id="staffForm">

                <div>
                    <label for="employee_id"
                           class="block text-sm font-semibold text-slate-700 mb-2">
                        Select Active Employee <span class="text-red-500">*</span>
                    </label>

                    <select name="employee_id" id="employee_id" required
                            class="w-full border border-slate-300 rounded-xl p-3
                                   focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- Choose an active employee --</option>

                        <?php foreach ($employees as $employee): ?>
                            <option
                                value="<?= (int)$employee['employee_id'] ?>"
                                <?= (isset($_POST['employee_id']) &&
                                    (string)$_POST['employee_id'] ===
                                    (string)$employee['employee_id'])
                                    ? 'selected' : '' ?>>
                                <?= htmlspecialchars(
                                    $employee['full_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                                <?php if (!empty($employee['email'])): ?>
                                    — <?= htmlspecialchars(
                                        $employee['email'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (empty($employees)): ?>
                        <p class="text-sm text-amber-700 mt-2">
                            No active employees were found. Check the employee records
                            and their status values.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <div>
                        <label for="full_name"
                               class="block text-sm font-semibold text-slate-700 mb-2">
                            Full Name
                        </label>

                        <input type="text" id="full_name" readonly
                               class="w-full bg-slate-100 border border-slate-200
                                      rounded-xl p-3 text-slate-700"
                               placeholder="Select an employee first">
                    </div>

                    <div>
                        <label for="email"
                               class="block text-sm font-semibold text-slate-700 mb-2">
                            Email Address
                        </label>

                        <input type="email" id="email" readonly
                               class="w-full bg-slate-100 border border-slate-200
                                      rounded-xl p-3 text-slate-700"
                               placeholder="Employee email">
                    </div>

                    <div>
                        <label for="national_id"
                               class="block text-sm font-semibold text-slate-700 mb-2">
                            National ID
                        </label>

                        <input type="text" id="national_id" readonly
                               class="w-full bg-slate-100 border border-slate-200
                                      rounded-xl p-3 text-slate-700"
                               placeholder="Employee National ID">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">
                            Assigned Group
                        </label>

                        <div class="w-full bg-blue-50 border border-blue-100
                                    rounded-xl p-3 text-blue-800 font-semibold">
                            <i class="fa-solid fa-shield-halved mr-2"></i>
                            Group ID 9
                        </div>
                    </div>

                    <div>
                        <label for="password"
                               class="block text-sm font-semibold text-slate-700 mb-2">
                            Password <span class="text-red-500">*</span>
                        </label>

                        <input type="password" name="password" id="password"
                               minlength="8" autocomplete="new-password" required
                               class="w-full border border-slate-300 rounded-xl p-3
                                      focus:ring-2 focus:ring-blue-500"
                               placeholder="At least 8 characters">
                    </div>

                    <div>
                        <label for="confirm_password"
                               class="block text-sm font-semibold text-slate-700 mb-2">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>

                        <input type="password" name="confirm_password"
                               id="confirm_password" minlength="8"
                               autocomplete="new-password" required
                               class="w-full border border-slate-300 rounded-xl p-3
                                      focus:ring-2 focus:ring-blue-500"
                               placeholder="Re-enter password">

                        <p id="password_message" class="text-sm mt-2"></p>
                    </div>

                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit" name="create_account"
                            id="createButton"
                            class="bg-blue-600 hover:bg-blue-700 text-white
                                   font-semibold px-6 py-3 rounded-xl
                                   transition disabled:opacity-50">
                        <i class="fa-solid fa-user-check mr-2"></i>
                        Create Staff Account
                    </button>

                    <button type="reset"
                            class="bg-slate-100 hover:bg-slate-200 text-slate-700
                                   font-semibold px-6 py-3 rounded-xl transition">
                        <i class="fa-solid fa-rotate-left mr-2"></i>
                        Clear Form
                    </button>
                </div>

            </form>
        </section>

        <!-- EXISTING USERS -->
        <section class="bg-white rounded-2xl shadow-sm border border-slate-200
                        overflow-hidden">

            <div class="px-6 py-5 border-b border-slate-200">
                <h2 class="font-bold text-lg text-slate-800">
                    <i class="fa-solid fa-users text-blue-600 mr-2"></i>
                    Existing Staff Accounts
                </h2>

                <p class="text-sm text-slate-500 mt-1">
                    Accounts assigned to group ID 9.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-5 py-4 text-left">#</th>
                            <th class="px-5 py-4 text-left">Full Name</th>
                            <th class="px-5 py-4 text-left">Email</th>
                            <th class="px-5 py-4 text-left">National ID</th>
                            <th class="px-5 py-4 text-left">Group</th>
                            <th class="px-5 py-4 text-left">Status</th>
                            <th class="px-5 py-4 text-left">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">

                    <?php if ($users): ?>
                        <?php foreach ($users as $index => $user): ?>
                            <tr class="hover:bg-slate-50">

                                <td class="px-5 py-4">
                                    <?= $index + 1 ?>
                                </td>

                                <td class="px-5 py-4 font-semibold text-slate-800">
                                    <?= htmlspecialchars(
                                        $user['full_name'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td class="px-5 py-4">
                                    <?= htmlspecialchars(
                                        $user['email'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td class="px-5 py-4">
                                    <?= htmlspecialchars(
                                        $user['national_id'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td class="px-5 py-4">
                                    <?= htmlspecialchars(
                                        $user['group_name'] ?? 'Group 9',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td class="px-5 py-4">
                                    <?php if (($user['status'] ?? '') === 'active'): ?>
                                        <span class="inline-flex rounded-full bg-green-100
                                                     text-green-700 px-3 py-1 font-semibold">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full bg-red-100
                                                     text-red-700 px-3 py-1 font-semibold">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-5 py-4">
                                    <button type="button"
                                            class="bg-amber-100 hover:bg-amber-200
                                                   text-amber-800 px-3 py-2 rounded-lg"
                                            onclick="openEditModal(
                                                <?= (int)$user['user_id'] ?>,
                                                <?= htmlspecialchars(
                                                    json_encode(
                                                        $user['full_name'] ?? '',
                                                        JSON_HEX_APOS |
                                                        JSON_HEX_QUOT |
                                                        JSON_HEX_TAG |
                                                        JSON_HEX_AMP
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>,
                                                <?= htmlspecialchars(
                                                    json_encode(
                                                        $user['status'] ?? 'active',
                                                        JSON_HEX_APOS |
                                                        JSON_HEX_QUOT |
                                                        JSON_HEX_TAG |
                                                        JSON_HEX_AMP
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            )">
                                        <i class="fa-solid fa-pen mr-1"></i>
                                        Edit Status
                                    </button>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7"
                                class="px-5 py-10 text-center text-slate-500">
                                No staff accounts found in group 9.
                            </td>
                        </tr>
                    <?php endif; ?>

                    </tbody>
                </table>
            </div>
        </section>

    </div>
</main>

<!-- EDIT STATUS MODAL -->
<div id="editModal"
     class="fixed inset-0 bg-slate-900/50 hidden items-center
            justify-center p-4 z-[1200]">

    <div class="bg-white p-6 rounded-2xl shadow-xl w-full max-w-md">

        <h2 class="text-xl font-bold mb-5 text-slate-800">
            Edit User Status
        </h2>

        <form method="POST">
            <input type="hidden" name="user_id" id="editUserId">

            <p class="mb-5 text-slate-600">
                User:
                <span id="editUserName" class="font-semibold text-slate-900"></span>
            </p>

            <label for="editStatus"
                   class="block text-sm font-semibold text-slate-700 mb-2">
                Account Status
            </label>

            <select name="new_status" id="editStatus"
                    class="w-full border border-slate-300 rounded-xl p-3 mb-6">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeEditModal()"
                        class="bg-slate-100 hover:bg-slate-200
                               text-slate-700 px-4 py-2 rounded-lg">
                    Cancel
                </button>

                <button type="submit" name="update_status"
                        class="bg-blue-600 hover:bg-blue-700 text-white
                               px-4 py-2 rounded-lg">
                    Save Changes
                </button>
            </div>
        </form>

    </div>
</div>

<script>
const employees = <?= json_encode(
    $employees,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const employeeSelect = document.getElementById('employee_id');
const fullNameInput = document.getElementById('full_name');
const emailInput = document.getElementById('email');
const nationalIdInput = document.getElementById('national_id');

function fillEmployeeDetails() {
    const selectedId = employeeSelect.value;

    const employee = employees.find(function (item) {
        return String(item.employee_id) === String(selectedId);
    });

    fullNameInput.value = employee ? (employee.full_name || '') : '';
    emailInput.value = employee ? (employee.email || '') : '';
    nationalIdInput.value = employee ? (employee.national_id || '') : '';
}

employeeSelect.addEventListener('change', fillEmployeeDetails);

// Populate fields if the form is redisplayed after a validation error.
fillEmployeeDetails();

// Password matching.
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');
const passwordMessage = document.getElementById('password_message');
const createButton = document.getElementById('createButton');

function checkPasswordMatch() {
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    if (confirmPassword === '') {
        passwordMessage.textContent = '';
        confirmPasswordInput.classList.remove(
            'border-red-500',
            'border-green-500'
        );
        createButton.disabled = false;
        return;
    }

    if (password === confirmPassword) {
        passwordMessage.textContent = 'Passwords match.';
        passwordMessage.className = 'text-sm mt-2 text-green-600';
        confirmPasswordInput.classList.remove('border-red-500');
        confirmPasswordInput.classList.add('border-green-500');
        createButton.disabled = false;
    } else {
        passwordMessage.textContent = 'Passwords do not match.';
        passwordMessage.className = 'text-sm mt-2 text-red-600';
        confirmPasswordInput.classList.remove('border-green-500');
        confirmPasswordInput.classList.add('border-red-500');
        createButton.disabled = true;
    }
}

passwordInput.addEventListener('input', checkPasswordMatch);
confirmPasswordInput.addEventListener('input', checkPasswordMatch);

document.getElementById('staffForm').addEventListener('reset', function () {
    setTimeout(function () {
        fillEmployeeDetails();
        passwordMessage.textContent = '';
        confirmPasswordInput.classList.remove(
            'border-red-500',
            'border-green-500'
        );
        createButton.disabled = false;
    }, 0);
});

// Existing user status modal.
function openEditModal(id, name, status) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUserName').textContent = name;
    document.getElementById('editStatus').value = status;

    const modal = document.getElementById('editModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>

</body>
</html>
