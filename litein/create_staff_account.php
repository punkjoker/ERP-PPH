<?php
// create_staff_account.php
require 'db_con.php';

$use_pdo = false;
$use_mysqli = false;
if (isset($pdo) && $pdo instanceof PDO) {
    $use_pdo = true;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $use_mysqli = true;
} else {
    die("Database connection not found. Make sure db_con.php sets either \$pdo (PDO) or \$conn (mysqli).");
}

// Fetch groups
$groups = [];
try {
    if ($use_pdo) {
        $stmt = $pdo->query("SELECT group_id, group_name FROM groups ORDER BY group_name");
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = $conn->query("SELECT group_id, group_name FROM groups ORDER BY group_name");
        while ($row = $res->fetch_assoc()) {
            $groups[] = $row;
        }
    }
} catch (Exception $e) {
    $error = "Failed to load groups: " . $e->getMessage();
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');

    $password  = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $group_id  = intval($_POST['group_id'] ?? 0);

    if ($full_name === '' || $email === '' || $password === '' || $confirm_password === '' || $group_id <= 0) {
        $error = "Please fill in all fields and choose a group.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            if ($use_pdo) {
                $insert = $pdo->prepare("INSERT INTO users (full_name, email, national_id, password, group_id) VALUES (?, ?, ?, ?, ?)");
                $insert->execute([$full_name, $email, $national_id, $hashedPassword, $group_id]);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, national_id, password, group_id) VALUES (?, ?, ?, ?, ?)");
                if ($stmt === false) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("ssssi", $full_name, $email, $national_id, $hashedPassword, $group_id);
                $stmt->execute();
                if ($stmt->errno) throw new Exception("Execute failed: " . $stmt->error);
            }
            $success = "Staff account created successfully!";
            $full_name = $email = $password = $confirm_password = '';
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'duplicate') !== false || stripos($msg, '1062') !== false) {
                $error = "That email is already registered.";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle status update
if (isset($_POST['update_status'])) {
    $user_id = intval($_POST['user_id']);
    $new_status = $_POST['new_status'] === 'inactive' ? 'inactive' : 'active';

    try {
        if ($use_pdo) {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->execute([$new_status, $user_id]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_status, $user_id);
            $stmt->execute();
        }
        $success = "User status updated successfully!";
    } catch (Exception $e) {
        $error = "Failed to update user status: " . $e->getMessage();
    }
}

// Fetch existing users
$users = [];
try {
    if ($use_pdo) {
        $stmt = $pdo->query("SELECT u.user_id, u.full_name, u.email, u.national_id, u.status, g.group_name 
                             FROM users u 
                             LEFT JOIN groups g ON u.group_id = g.group_id 
                             ORDER BY u.user_id DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $res = $conn->query("SELECT u.user_id, u.full_name, u.email, u.national_id, u.status, g.group_name 
                             FROM users u 
                             LEFT JOIN groups g ON u.group_id = g.group_id 
                             ORDER BY u.user_id DESC");
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
    }
} catch (Exception $e) {
    $error = "Failed to load users: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Create Staff Account - Lynntech</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="p-6 ml-64">

  <!-- Add Staff Form -->
  <div class="bg-white shadow-md rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-6 text-center text-blue-700">Create Staff Account</h2>

    <?php if (!empty($success)): ?>
      <div class="bg-green-100 text-green-800 p-3 mb-4 rounded"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="bg-red-100 text-red-800 p-3 mb-4 rounded"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4" novalidate>
      <div>
        <label class="block text-sm font-medium text-gray-700">Full Name</label>
        <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-lg p-2" required>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-lg p-2" required>
      </div>
      <div>
  <label class="block text-sm font-medium text-gray-700">National ID</label>
  <input type="text" name="national_id" value="<?php echo htmlspecialchars($national_id ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-lg p-2" placeholder="Enter National ID" required>
</div>

<div>
  <label class="block text-sm font-medium text-gray-700">Password</label>
  <input type="password" id="password" name="password" class="mt-1 block w-full border border-gray-300 rounded-lg p-2" required>
</div>

<div>
  <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
  <input type="password" id="confirm_password" name="confirm_password" class="mt-1 block w-full border border-gray-300 rounded-lg p-2" required>
  <p id="password_message" class="text-sm mt-1"></p>
</div>

<script>
  const password = document.getElementById('password');
  const confirmPassword = document.getElementById('confirm_password');
  const message = document.getElementById('password_message');

  function checkPasswordMatch() {
    if (confirmPassword.value === '') {
      message.textContent = '';
      confirmPassword.classList.remove('border-red-500', 'border-green-500');
      return;
    }

    if (password.value === confirmPassword.value) {
      message.textContent = 'Passwords match';
      message.className = 'text-sm mt-1 text-green-600';
      confirmPassword.classList.add('border-green-500');
      confirmPassword.classList.remove('border-red-500');
    } else {
      message.textContent = 'Passwords do not match';
      message.className = 'text-sm mt-1 text-red-600';
      confirmPassword.classList.add('border-red-500');
      confirmPassword.classList.remove('border-green-500');
    }
  }

  password.addEventListener('input', checkPasswordMatch);
  confirmPassword.addEventListener('input', checkPasswordMatch);
</script>


      <div>
        <label class="block text-sm font-medium text-gray-700">Select Group</label>
        <select name="group_id" class="mt-1 block w-full border border-gray-300 rounded-lg p-2" required>
          <option value="">-- Select Group --</option>
          <?php foreach ($groups as $g): ?>
            <option value="<?php echo $g['group_id']; ?>" <?php if (isset($group_id) && $group_id == $g['group_id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($g['group_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition">Create Staff</button>
    </form>
  </div>

  <!-- Existing Users -->
  <div class="bg-white shadow-md rounded-lg p-6">
    <h2 class="text-xl font-bold mb-4 text-blue-700">Existing Users</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">#</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Full Name</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Email</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">National ID</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Group</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Status</th>
            <th class="px-4 py-2 text-left text-sm font-medium text-gray-700">Action</th>

          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (!empty($users)): ?>
            <?php foreach ($users as $index => $u): ?>
              <tr>
                <td class="px-4 py-2"><?php echo $index + 1; ?></td>
                <td class="px-4 py-2"><?php echo htmlspecialchars($u['full_name']); ?></td>
                <td class="px-4 py-2"><?php echo htmlspecialchars($u['email']); ?></td>
                <td class="px-4 py-2"><?php echo htmlspecialchars($u['national_id']); ?></td>
                <td class="px-4 py-2"><?php echo htmlspecialchars($u['group_name']); ?></td>
                <td class="px-4 py-2">
  <?php if ($u['status'] === 'active'): ?>
    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">Active</span>
  <?php else: ?>
    <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-semibold">Inactive</span>
  <?php endif; ?>
</td>

                <td class="px-4 py-2">
  <button 
    onclick="openEditModal(<?php echo $u['user_id']; ?>, '<?php echo $u['full_name']; ?>', '<?php echo $u['status']; ?>')" 
    class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600">
    Edit
  </button>
</td>

              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="px-4 py-2 text-center text-gray-500">No users found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<!-- Edit Status Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
  <div class="bg-white p-6 rounded-lg shadow-lg w-96">
    <h2 class="text-xl font-bold mb-4 text-blue-700">Edit User Status</h2>

    <form method="POST" id="editForm">
      <input type="hidden" name="user_id" id="editUserId">

      <p class="mb-3 text-gray-700">User: <span id="editUserName" class="font-semibold"></span></p>

      <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
      <select name="new_status" id="editStatus" class="w-full border border-gray-300 rounded-lg p-2 mb-4">
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>

      <div class="flex justify-end space-x-3">
        <button type="button" onclick="closeEditModal()" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">Cancel</button>
        <button type="submit" name="update_status" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, name, status) {
  document.getElementById('editUserId').value = id;
  document.getElementById('editUserName').textContent = name;
  document.getElementById('editStatus').value = status;
  document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
  document.getElementById('editModal').classList.add('hidden');
}
</script>

</body>
</html>
