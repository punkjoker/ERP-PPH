<?php
include 'db_con.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Employee</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        function toggleContractFields(select) {
            const contractFields = document.getElementById('contract-fields');
            contractFields.style.display = select.value === 'Contract' ? 'block' : 'none';
        }
    </script>
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name      = trim($_POST['first_name']);
    $last_name       = trim($_POST['last_name']);
    $national_id     = $_POST['national_id'] ?: NULL;
    $kra_pin         = $_POST['kra_pin'] ?: NULL;
    $nssf_number     = $_POST['nssf_number'] ?: NULL;
    $nhif_number     = $_POST['nhif_number'] ?: NULL;
    $email           = $_POST['email'] ?: NULL;
    $phone           = $_POST['phone'] ?: NULL;
    $department      = $_POST['department'] ?: NULL;
    $position        = $_POST['position'] ?: NULL;
    $date_of_hire    = $_POST['date_of_hire'] ?: NULL;
    $employment_type = $_POST['employment_type'];
    $contract_start  = $_POST['contract_start'] ?: NULL;
    $contract_end    = $_POST['contract_end'] ?: NULL;

    // Passport upload
    $passport_path = NULL;
    if (!empty($_FILES['passport']['name'])) {
        $targetDir = "uploads/passports/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = time() . "_" . basename($_FILES['passport']['name']);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES['passport']['tmp_name'], $targetFile)) {
            $passport_path = $conn->real_escape_string($targetFile);
        }
    }

    $sql = "INSERT INTO employees 
        (first_name, last_name, national_id, kra_pin, nssf_number, nhif_number, passport_path, email, phone, department, position, date_of_hire, employment_type, contract_start, contract_end)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssssss",
        $first_name, $last_name, $national_id, $kra_pin, $nssf_number, $nhif_number,
        $passport_path, $email, $phone, $department, $position, $date_of_hire,
        $employment_type, $contract_start, $contract_end
    );

    if ($stmt->execute()) $success = "Employee added successfully!";
    else $error = "Error: " . $stmt->error;
    $stmt->close();
}

$result = $conn->query("SELECT * FROM employees ORDER BY created_at DESC");
$employees = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="ml-64 p-6">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">Add New Employee</h1>

    <?php if(isset($success)) echo "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>$success</div>"; ?>
    <?php if(isset($error)) echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>$error</div>"; ?>

    <!-- Employee Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8 max-w-4xl">
        <form method="POST" enctype="multipart/form-data" class="space-y-3 text-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input type="text" name="first_name" placeholder="First Name" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="last_name" placeholder="Last Name" required class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="national_id" placeholder="National ID" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="kra_pin" placeholder="KRA PIN" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="nssf_number" placeholder="NSSF Number" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="nhif_number" placeholder="NHIF/SHA Number" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="email" name="email" placeholder="Email" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="phone" placeholder="Phone" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="department" placeholder="Department" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="text" name="position" placeholder="Position" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="date" name="date_of_hire" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                <input type="file" name="passport" accept="image/*" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">

                <!-- Employment Type -->
                <select name="employment_type" onchange="toggleContractFields(this)" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                    <option value="Permanent">Permanent</option>
                    <option value="Contract">Contract</option>
                </select>

                <!-- Contract Dates -->
                <div id="contract-fields" class="hidden space-y-2 col-span-2">
                    <input type="date" name="contract_start" placeholder="Contract Start Date" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                    <input type="date" name="contract_end" placeholder="Contract End Date" class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                </div>
            </div>

            <button type="submit" class="mt-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Save Employee</button>
        </form>
    </div>

    <!-- Employee List -->
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-xl font-semibold mb-4 text-blue-700">Employee List</h2>
        <table class="w-full rounded overflow-hidden">
            <thead class="bg-gray-300 text-sm">
                <tr>
                    <th class="px-2 py-1">#</th>
                    <th class="px-2 py-1">Name</th>
                    <th class="px-2 py-1">Type</th>
                    <th class="px-2 py-1">National ID</th>
                    <th class="px-2 py-1">Department</th>
                    <th class="px-2 py-1">Position</th>
                    <th class="px-2 py-1">Date Hired</th>
                    <th class="px-2 py-1">Status</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <?php if(!empty($employees)): $count=1; ?>
                    <?php foreach($employees as $row): 
                        $status = $row['status'] ?? 'Active';
                        $statusColor = ($status==='Active') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                        $bg = $count % 2 == 0 ? 'bg-gray-50' : 'bg-white';
                    ?>
                        <tr class="<?= $bg ?> hover:bg-blue-50">
                            <td class="px-2 py-1"><?= $count++ ?></td>
                            <td class="px-2 py-1"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                            <td class="px-2 py-1"><?= htmlspecialchars($row['employment_type']) ?></td>
                            <td class="px-2 py-1"><?= htmlspecialchars($row['national_id']) ?></td>
                            <td class="px-2 py-1"><?= htmlspecialchars($row['department']) ?></td>
                            <td class="px-2 py-1"><?= htmlspecialchars($row['position']) ?></td>
                            <td class="px-2 py-1"><?= htmlspecialchars($row['date_of_hire']) ?></td>
                            <td class="px-2 py-1 text-center rounded <?= $statusColor ?>"><?= htmlspecialchars($status) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="px-2 py-2 text-center text-gray-500">No employees added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
