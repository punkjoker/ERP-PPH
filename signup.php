<?php
// signup.php
// NOTE: ensure db_con.php is in the same folder or update the path below.
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
    $password  = $_POST['password'] ?? '';
    $group_id  = intval($_POST['group_id'] ?? 0);

    if ($full_name === '' || $email === '' || $password === '' || $group_id <= 0) {
        $error = "Please fill in all fields and choose a group.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            if ($use_pdo) {
                $insert = $pdo->prepare("INSERT INTO users (full_name, email, password, group_id) VALUES (?, ?, ?, ?)");
                $insert->execute([$full_name, $email, $hashedPassword, $group_id]);
            } else {
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, group_id) VALUES (?, ?, ?, ?)");
                if ($stmt === false) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("sssi", $full_name, $email, $hashedPassword, $group_id);
                $stmt->execute();
                if ($stmt->errno) throw new Exception("Execute failed: " . $stmt->error);
            }
            $success = "User registered successfully!";
            // Clear submitted values (optional)
            $full_name = $email = '';
        } catch (Exception $e) {
            // Handle duplicate email nicely
            $msg = $e->getMessage();
            if (stripos($msg, 'duplicate') !== false || stripos($msg, '1062') !== false) {
                $error = "That email is already registered.";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Signup - Lynntech</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
  <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
    <h2 class="text-2xl font-bold mb-6 text-center text-blue-700">Create User</h2>

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
        <label class="block text-sm font-medium text-gray-700">Password</label>
        <input type="password" name="password" class="mt-1 block w-full border border-gray-300 rounded-lg p-2" required>
      </div>

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

      <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition">Create User</button>
    </form>
  </div>
</body>
</html>
