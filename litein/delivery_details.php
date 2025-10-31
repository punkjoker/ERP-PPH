<?php
include 'db_con.php';

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $address = trim($_POST['address']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);

    if (!empty($company_name) && !empty($address)) {
        $stmt = $conn->prepare("INSERT INTO delivery_details (company_name, address, contact, email) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $company_name, $address, $contact, $email);
        $stmt->execute();
        $stmt->close();
        $msg = "Delivery details saved successfully!";
    } else {
        $msg = "Please fill in all required fields.";
    }
}

// ✅ Fetch all existing records
$details = $conn->query("SELECT * FROM delivery_details ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Delivery Details</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
  <?php include 'navbar.php'; ?>

  <div class="p-6 ml-64">
    <h1 class="text-3xl font-bold text-gray-800 mb-4">🚚 Delivery Details</h1>

    <?php if (!empty($msg)): ?>
      <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
        <?= htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>

    <!-- 📝 Add Delivery Info Form -->
    <form method="POST" class="bg-white shadow-md rounded-lg p-6 mb-8">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-gray-700 font-medium mb-1">Company Name *</label>
          <input type="text" name="company_name" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-1">Address *</label>
          <input type="text" name="address" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-1">Contact Number</label>
          <input type="text" name="contact"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-1">Email</label>
          <input type="email" name="email"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <button type="submit" class="mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
        Save Details
      </button>
    </form>

    <!-- 📋 Existing Delivery Records -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
      <table class="min-w-full table-auto border-collapse">
        <thead class="bg-blue-600 text-white">
          <tr>
            <th class="py-3 px-4 text-left">#</th>
            <th class="py-3 px-4 text-left">Company Name</th>
            <th class="py-3 px-4 text-left">Address</th>
            <th class="py-3 px-4 text-left">Contact</th>
            <th class="py-3 px-4 text-left">Email</th>
            <th class="py-3 px-4 text-left">Date Added</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($details && $details->num_rows > 0): ?>
            <?php $i = 1; while ($row = $details->fetch_assoc()): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="py-3 px-4"><?= $i++; ?></td>
                <td class="py-3 px-4 font-medium text-gray-800"><?= htmlspecialchars($row['company_name']); ?></td>
                <td class="py-3 px-4 text-gray-700"><?= htmlspecialchars($row['address']); ?></td>
                <td class="py-3 px-4 text-gray-700"><?= htmlspecialchars($row['contact']); ?></td>
                <td class="py-3 px-4 text-gray-700"><?= htmlspecialchars($row['email']); ?></td>
                <td class="py-3 px-4 text-gray-500"><?= htmlspecialchars(date("Y-m-d", strtotime($row['created_at']))); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center text-gray-500 py-6">No delivery records found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
