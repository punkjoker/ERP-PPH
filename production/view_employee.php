<?php
include 'db_con.php';

$employee_id = $_GET['id'] ?? 0;
$employee_id = intval($employee_id);

$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6">
    <div class="bg-white shadow rounded-lg p-6 max-w-4xl mx-auto">
        <!-- Logo and Title -->
        <div class="flex items-center mb-6">
            <img src="images/lynn_logo.png" alt="Logo" class="w-20 h-20 object-contain mr-4">
            <h1 class="text-3xl font-bold text-blue-700">Employee Profile</h1>
        </div>

        <div class="flex flex-col md:flex-row md:space-x-6">
            <!-- Passport -->
            <div class="flex-shrink-0 mb-4 md:mb-0">
                <?php if (!empty($employee['passport_path'])): ?>
                    <img src="<?= htmlspecialchars($employee['passport_path']) ?>" alt="Passport" class="w-40 h-40 object-cover rounded-lg border">
                <?php else: ?>
                    <div class="w-40 h-40 flex items-center justify-center bg-gray-200 text-gray-500 rounded-lg border">No Image</div>
                <?php endif; ?>
            </div>

            <!-- Employee Details -->
            <div class="flex-1">
                <h2 class="text-2xl font-bold mb-4 text-blue-700"><?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?></h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><span class="font-semibold">National ID:</span> <?= htmlspecialchars($employee['national_id']) ?></div>
                    <div><span class="font-semibold">KRA PIN:</span> <?= htmlspecialchars($employee['kra_pin']) ?></div>
                    <div><span class="font-semibold">NSSF Number:</span> <?= htmlspecialchars($employee['nssf_number']) ?></div>
                    <div><span class="font-semibold">NHIF Number:</span> <?= htmlspecialchars($employee['nhif_number']) ?></div>
                    <div><span class="font-semibold">Email:</span> <?= htmlspecialchars($employee['email']) ?></div>
                    <div><span class="font-semibold">Phone:</span> <?= htmlspecialchars($employee['phone']) ?></div>
                    <div><span class="font-semibold">Department:</span> <?= htmlspecialchars($employee['department']) ?></div>
                    <div><span class="font-semibold">Position:</span> <?= htmlspecialchars($employee['position']) ?></div>
                    <div><span class="font-semibold">Date of Hire:</span> <?= htmlspecialchars($employee['date_of_hire']) ?></div>
                    <div><span class="font-semibold">Status:</span> <?= htmlspecialchars($employee['status']) ?></div>
                    <div><span class="font-semibold">Employment Type:</span> <?= htmlspecialchars($employee['employment_type'] ?? 'Permanent') ?></div>
                    <?php if($employee['employment_type'] === 'Contract'): ?>
                        <div><span class="font-semibold">Contract Start:</span> <?= htmlspecialchars($employee['contract_start']) ?></div>
                        <div><span class="font-semibold">Contract End:</span> <?= htmlspecialchars($employee['contract_end']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- PDF Button -->
                <div class="mt-6">
                    <a href="export_employee_pdf.php?id=<?= $employee['employee_id'] ?>" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">Download PDF</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
