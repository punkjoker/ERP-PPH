
<?php
include 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// Escape output
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Employee ID
$employee_id = (int)($_GET['id'] ?? 0);

if ($employee_id <= 0) {
    die("Invalid employee ID.");
}

// Fetch main employee information
$stmt = $conn->prepare("
    SELECT *
    FROM employees
    WHERE employee_id = ?
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$employee) {
    die("Employee not found.");
}

// Fetch additional employee information
$info_stmt = $conn->prepare("
    SELECT *
    FROM employee_information
    WHERE employee_id = ?
");
$info_stmt->bind_param("i", $employee_id);
$info_stmt->execute();
$info = $info_stmt->get_result()->fetch_assoc();
$info_stmt->close();

// Fetch education and experience records
$items_stmt = $conn->prepare("
    SELECT *
    FROM employee_information_items
    WHERE employee_id = ?
    ORDER BY category ASC
");
$items_stmt->bind_param("i", $employee_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$education = [];
$experience = [];

while ($row = $items_result->fetch_assoc()) {
    if ($row['category'] === 'education') {
        $education[] = $row;
    } elseif ($row['category'] === 'experience') {
        $experience[] = $row;
    }
}
$items_stmt->close();

// Fetch attached education documents
$documents_stmt = $conn->prepare("
    SELECT document_id, employee_id, document_name, file_path, uploaded_at
    FROM employee_education_documents
    WHERE employee_id = ?
    ORDER BY uploaded_at DESC, document_id DESC
");
$documents_stmt->bind_param("i", $employee_id);
$documents_stmt->execute();
$documents_result = $documents_stmt->get_result();

$education_documents = [];

while ($document = $documents_result->fetch_assoc()) {
    $education_documents[] = $document;
}
$documents_stmt->close();

// Calculate work experience duration
function calculateDuration($start, $end) {
    if (empty($start) || empty($end)) {
        return "";
    }

    try {
        $startDate = new DateTime($start);
        $endDate = new DateTime($end);

        if ($endDate < $startDate) {
            return "";
        }

        $diff = $startDate->diff($endDate);

        $years = $diff->y;
        $months = $diff->m;

        if ($years > 0 && $months > 0) {
            return "({$years} yr {$months} mo)";
        } elseif ($years > 0) {
            return "({$years} yr)";
        } elseif ($months > 0) {
            return "({$months} mo)";
        }

        return "";

    } catch (Exception $e) {
        return "";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Employee Profile - <?= e($employee['first_name'] . ' ' . $employee['last_name']) ?></title>

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .document-card {
            transition: all 0.2s ease;
        }

        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
    </style>
</head>

<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-8">

    <div class="bg-white shadow-xl rounded-2xl p-8 max-w-5xl mx-auto border border-gray-200">

        <!-- Header -->
        <div class="flex justify-between items-center mb-8">

            <div class="flex items-center">
                <img src="images/lynn_logo.png"
                     alt="Logo"
                     class="w-20 h-20 object-contain mr-4">

                <div>
                    <h1 class="text-3xl font-bold text-blue-700">
                        Employee Profile
                    </h1>

                    <p class="text-sm text-gray-500">
                        Employee information and supporting documents
                    </p>
                </div>
            </div>

            <a href="employee_information.php"
               class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition">
                ← Back
            </a>

        </div>

        <!-- Profile Information -->
        <div class="flex flex-col md:flex-row md:space-x-6">

            <div class="flex-shrink-0 mb-6 md:mb-0">

                <?php if (!empty($employee['passport_path'])): ?>

                    <img src="<?= e($employee['passport_path']) ?>"
                         alt="Employee Passport"
                         class="w-44 h-44 object-cover rounded-xl border shadow">

                <?php else: ?>

                    <div class="w-44 h-44 flex items-center justify-center bg-gray-200 text-gray-500 rounded-xl border shadow">
                        No Image
                    </div>

                <?php endif; ?>

            </div>

            <div class="flex-1 bg-blue-50 rounded-xl p-4">

                <h2 class="text-2xl font-bold mb-3 text-blue-800">
                    <?= e($employee['first_name'] . ' ' . $employee['last_name']) ?>
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">

                    <div>
                        <span class="font-semibold">National ID:</span>
                        <?= e($employee['national_id']) ?>
                    </div>

                    <div>
                        <span class="font-semibold">KRA PIN:</span>
                        <?= e($employee['kra_pin']) ?>
                    </div>

                    <div>
                        <span class="font-semibold">Email:</span>
                        <?= e($employee['email']) ?>
                    </div>

                    <div>
                        <span class="font-semibold">Phone:</span>
                        <?= e($employee['phone']) ?>
                    </div>

                    <div>
                        <span class="font-semibold">Department:</span>
                        <?= e($employee['department']) ?>
                    </div>

                    <div>
                        <span class="font-semibold">Position:</span>
                        <?= e($employee['position']) ?>
                    </div>

                    <div>
                        <span class="font-semibold">Date of Hire:</span>
                        <?= e($employee['date_of_hire']) ?>
                    </div>

                    <div>
                        <span class="font-semibold">Status:</span>
                        <?= e($employee['status']) ?>
                    </div>

                </div>

            </div>

        </div>

        <!-- Education Background -->
        <div class="mt-10">

            <h3 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">
                Education Background
            </h3>

            <?php if (!empty($education)): ?>

                <div class="overflow-x-auto">

                    <table class="min-w-full border text-sm rounded-lg overflow-hidden">

                        <thead class="bg-blue-100 text-blue-900">
                            <tr>
                                <th class="p-3 text-left">Institution</th>
                                <th class="p-3 text-left">Level</th>
                                <th class="p-3 text-left">Start</th>
                                <th class="p-3 text-left">End</th>
                                <th class="p-3 text-left">Result</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($education as $edu): ?>

                                <tr class="border-b hover:bg-gray-50">

                                    <td class="p-3">
                                        <?= e($edu['field1']) ?>
                                    </td>

                                    <td class="p-3">
                                        <?= e($edu['field2']) ?>
                                    </td>

                                    <td class="p-3">
                                        <?= e($edu['field3']) ?>
                                    </td>

                                    <td class="p-3">
                                        <?= e($edu['field4']) ?>
                                    </td>

                                    <td class="p-3">
                                        <?= e($edu['field5']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <p class="text-gray-500 italic">
                    No education details added.
                </p>

            <?php endif; ?>

        </div>

        <!-- Education Supporting Documents -->
        <div class="mt-10">

            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center border-b pb-2 mb-4">

                <h3 class="text-xl font-semibold text-blue-700">
                    Education Supporting Documents
                </h3>

                <span class="text-sm text-gray-500 mt-1 sm:mt-0">
                    <?= count($education_documents) ?> document(s) attached
                </span>

            </div>

            <?php if (!empty($education_documents)): ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <?php foreach ($education_documents as $doc): ?>

                        <?php
                        $extension = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));

                        $icon = '📄';

                        if ($extension === 'pdf') {
                            $icon = '📕';
                        } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                            $icon = '🖼️';
                        } elseif (in_array($extension, ['doc', 'docx'])) {
                            $icon = '📝';
                        }
                        ?>

                        <div class="document-card border rounded-xl p-4 bg-gray-50 flex items-center justify-between">

                            <div class="flex items-center min-w-0">

                                <div class="text-3xl mr-3">
                                    <?= $icon ?>
                                </div>

                                <div class="min-w-0">

                                    <p class="font-semibold text-gray-800 break-words">
                                        <?= e($doc['document_name']) ?>
                                    </p>

                                    <p class="text-xs text-gray-500 mt-1">
                                        <?= e($extension ? strtoupper($extension) : 'FILE') ?>
                                        • Uploaded:
                                        <?= e(date('d M Y', strtotime($doc['uploaded_at']))) ?>
                                    </p>

                                </div>

                            </div>

                            <div class="ml-3 flex-shrink-0">

                                <a href="view_employee_education_document.php?id=<?= (int)$doc['document_id'] ?>"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700 transition inline-block">

                                    View

                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="bg-gray-50 border border-dashed rounded-xl p-6 text-center">

                    <div class="text-4xl mb-2">📂</div>

                    <p class="text-gray-500 italic">
                        No education supporting documents have been uploaded for this employee.
                    </p>

                </div>

            <?php endif; ?>

        </div>

       <!-- Work Experience -->
<div class="mt-10">

    <h3 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">
        Work Experience
    </h3>

    <?php if (!empty($experience)): ?>

        <div class="space-y-4">

            <?php foreach ($experience as $exp):

                $duration = calculateDuration(
                    $exp['field3'],
                    $exp['field4']
                );

                $organization = trim($exp['field1'] ?? '');
                $position = trim($exp['field2'] ?? '');
                $startDate = trim($exp['field3'] ?? '');
                $endDate = trim($exp['field4'] ?? '');

            ?>

                <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">

                    <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-2">

                        <div>

                            <h4 class="text-lg font-bold text-gray-800">
                                <?= e($organization ?: 'Organization not specified') ?>
                            </h4>

                            <p class="text-blue-700 font-semibold mt-1">
                                <?= e($position ?: 'Position not specified') ?>
                            </p>

                        </div>

                        <span class="text-sm text-gray-500 bg-white border px-3 py-2 rounded-lg">

                            <?= e($startDate ?: 'N/A') ?>
                            -
                            <?= e($endDate ?: 'Present') ?>

                        </span>

                    </div>

                    <?php if ($duration !== ''): ?>

                        <p class="text-sm text-gray-500 mt-3">
                            <span class="font-semibold">Duration:</span>
                            <?= e(trim($duration, '()')) ?>
                        </p>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <p class="text-gray-500 italic">
            No work experience added.
        </p>

    <?php endif; ?>

</div>

        <!-- Skills -->
        <div class="mt-10">

            <h3 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">
                Skills
            </h3>

            <?php if (!empty($info['skills'])): ?>

                <p class="text-gray-700 whitespace-pre-line">
                    <?= e($info['skills']) ?>
                </p>

            <?php else: ?>

                <p class="text-gray-500 italic">1
                    No skills added.
                </p>

            <?php endif; ?>

        </div>

        <!-- Certifications -->
        <div class="mt-10">

            <h3 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">
                Certifications
            </h3>

            <?php if (!empty($info['certifications'])): ?>

                <p class="text-gray-700 whitespace-pre-line">
                    <?= e($info['certifications']) ?>
                </p>

            <?php else: ?>

                <p class="text-gray-500 italic">
                    No certifications added.
                </p>

            <?php endif; ?>

        </div>

        <!-- Salary Information -->
        <div class="mt-10 bg-blue-50 p-4 rounded-xl">

            <h3 class="text-xl font-semibold text-blue-700 border-b pb-2 mb-4">
                Salary Information
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <span class="font-semibold">Expected Salary:</span>
                    KES <?= e($info['expected_salary'] ?? 'N/A') ?>
                </div>

                <div>
                    <span class="font-semibold">Approved Salary:</span>
                    KES <?= e($info['approved_salary'] ?? 'N/A') ?>
                </div>

            </div>

        </div>

        <!-- Action Buttons -->
        <div class="mt-10 flex justify-between items-center flex-wrap gap-3">

            <a href="update_employee.php?id=<?= (int)$employee['employee_id'] ?>"
               class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 transition">
                Edit Employee Information
            </a>

            <a href="export_employee_pdf.php?id=<?= (int)$employee['employee_id'] ?>"
               class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 transition">
                Download PDF
            </a>

        </div>

    </div>

</div>

</body>
</html>
