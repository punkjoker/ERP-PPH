<?php
include 'db_con.php';

$employee_id = $_GET['id'] ?? 0;
$employee_id = intval($employee_id);

// Fetch main employee info
$stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found.");
}

// Fetch additional info
$info_stmt = $conn->prepare("SELECT * FROM employee_information WHERE employee_id = ?");
$info_stmt->bind_param("i", $employee_id);
$info_stmt->execute();
$info_result = $info_stmt->get_result();
$info = $info_result->fetch_assoc();
$info_stmt->close();

// Fetch education, experience
$items_stmt = $conn->prepare("SELECT * FROM employee_information_items WHERE employee_id = ? ORDER BY category");
$items_stmt->bind_param("i", $employee_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$education = [];
$experience = [];

while ($row = $items_result->fetch_assoc()) {
    if ($row['category'] === 'education') $education[] = $row;
    elseif ($row['category'] === 'experience') $experience[] = $row;
}
$items_stmt->close();

function calculateDuration($start, $end) {
    if (empty($start) || empty($end)) return "";
    $startDate = new DateTime($start);
    $endDate = new DateTime($end);
    $diff = $startDate->diff($endDate);

    $years = $diff->y;
    $months = $diff->m;
    if ($years > 0 && $months > 0) return "({$years} yr {$months} mo)";
    elseif ($years > 0) return "({$years} yr)";
    elseif ($months > 0) return "({$months} mo)";
    return "";
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

<div class="ml-64 p-8">
    <div class="bg-white shadow-xl rounded-2xl p-8 max-w-5xl mx-auto border border-gray-200">
        <!-- Header Section -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center">
                <img src="images/lynn_logo.png" alt="Logo" class="w-20 h-20 object-contain mr-4">
                <h1 class="text-3xl font-bold text-blue-700">Employee Profile</h1>
            </div>
            <a href="employee_information.php" class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition">
                ← Back
            </a>
        </div>

        <!-- Profile Info -->
        <div class="flex flex-col md:flex-row md:space-x-6">
            <div class="flex-shrink-0 mb-6 md:mb-0">
                <?php if (!empty($employee['passport_path'])): ?>
                    <img src="<?= htmlspecialchars($employee['passport_path']) ?>" alt="Passport" class="w-44 h-44 object-cover rounded-xl border shadow">
                <?php else: ?>
                    <div class="w-44 h-44 flex items-center justify-center bg-gray-200 text-gray-500 rounded-xl border shadow">No Image</div>
                <?php endif; ?>
            </div>

            <div class="flex-1 bg-blue-50 rounded-xl p-4">
                <h2 class="text-2xl font-bold mb-3 text-blue-800"><?= htmlspecialchars($employee['first_name'].' '.$employee['last_name']) ?></h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div><span class="font-semibold">National ID:</span> <?= htmlspecialchars($employee['national_id']) ?></div>
                    <div><span class="font-semibold">KRA PIN:</span> <?= htmlspecialchars($employee['kra_pin']) ?></div>
                    <div><span class="font-semibold">Email:</span> <?= htmlspecialchars($employee['email']) ?></div>
                    <div><span class="font-semibold">Phone:</span> <?= htmlspecialchars($employee['phone']) ?></div>
                    <div><span class="font-semibold">Department:</span> <?= htmlspecialchars($employee['department']) ?></div>
                    <div><span class="font-semibold">Position:</span> <?= htmlspecialchars($employee['position']) ?></div>
                    <div><span class="font-semibold">Date of Hire:</span> <?= htmlspecialchars($employee['date_of_hire']) ?></div>
                    <div><span class="font-semibold">Status:</span> <?= htmlspecialchars($employee['status']) ?></div>
                </div>
            </div>
        </div>

        <!-- Education Section -->
        <div class="mt-10">
            <h3 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">Education Background</h3>
            <?php if (!empty($education)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full border text-sm rounded-lg overflow-hidden">
                        <thead class="bg-blue-100 text-blue-900">
                            <tr>
                                <th class="p-2 text-left">Institution</th>
                                <th class="p-2 text-left">Level</th>
                                <th class="p-2 text-left">Start</th>
                                <th class="p-2 text-left">End</th>
                                <th class="p-2 text-left">Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($education as $edu): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-2"><?= htmlspecialchars($edu['field1']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($edu['field2']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($edu['field3']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($edu['field4']) ?></td>
                                <td class="p-2"><?= htmlspecialchars($edu['field5']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500 italic">No education details added.</p>
            <?php endif; ?>
        </div>

        <!-- Experience Section -->
        <div class="mt-10">
            <h3 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">Work Experience</h3>
            <?php if (!empty($experience)): ?>
                <ul class="list-disc ml-6 text-gray-700">
                    <?php foreach ($experience as $exp): 
                        $duration = calculateDuration($exp['field3'], $exp['field4']); ?>
                        <li class="mb-3">
                            <span class="font-semibold text-gray-800"><?= htmlspecialchars($exp['field1']) ?></span>
                            <span class="text-sm text-gray-500">(<?= htmlspecialchars($exp['field3']) ?> - <?= htmlspecialchars($exp['field4']) ?>) <?= $duration ?></span><br>
                            <span class="text-gray-700"><?= htmlspecialchars($exp['field2']) ?> <?= htmlspecialchars($exp['field5']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500 italic">No work experience added.</p>
            <?php endif; ?>
        </div>

        <!-- Skills -->
        <div class="mt-10">
            <h3 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">Skills</h3>
            <p><?= !empty($info['skills']) ? htmlspecialchars($info['skills']) : '<span class="text-gray-500 italic">No skills added.</span>' ?></p>
        </div>

        <!-- Certifications -->
        <div class="mt-10">
            <h3 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">Certifications</h3>
            <p><?= !empty($info['certifications']) ? htmlspecialchars($info['certifications']) : '<span class="text-gray-500 italic">No certifications added.</span>' ?></p>
        </div>

        <!-- Salary Info -->
        <div class="mt-10 bg-blue-50 p-4 rounded-xl">
            <h3 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">Salary Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><span class="font-semibold">Expected Salary:</span> KES <?= htmlspecialchars($info['expected_salary'] ?? 'N/A') ?></div>
                <div><span class="font-semibold">Approved Salary:</span> KES <?= htmlspecialchars($info['approved_salary'] ?? 'N/A') ?></div>
            </div>
        </div>

        <!-- PDF Export -->
        <div class="mt-10 flex justify-end">
            <a href="export_employee_pdf.php?id=<?= $employee['employee_id'] ?>" 
               class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 transition">
                Download PDF
            </a>
        </div>
    </div>
</div>

</body>
</html>
