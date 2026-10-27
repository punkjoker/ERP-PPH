
<?php
session_start();
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function moneyValue($value) {
    if (!is_numeric($value) || (float)$value < 0) {
        throw new Exception('Claim amounts must be valid non-negative numbers.');
    }

    return round((float)$value, 2);
}

$visitStatuses = [
    'Scheduled',
    'Order Placed',
    'Feedback Required',
    'Follow-up Required',
    'Completed',
    'Cancelled'
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        /*
         * ADD MULTIPLE COMPANIES FOR ONE DAY
         */
        if ($action === 'add') {
            $visitDate = $_POST['visit_date'] ?? '';
            $purpose = trim($_POST['visit_purpose'] ?? '');
            $report = trim($_POST['field_report'] ?? '');

            $companies = $_POST['companies'] ?? [];

            $companyNames = $companies['company_name'] ?? [];
            $companyLocations = $companies['company_location'] ?? [];
            $companyStatuses = $companies['visit_status'] ?? [];
            $companyComments = $companies['visit_comments'] ?? [];

            $lunch = moneyValue($_POST['lunch_claim'] ?? '0');
            $transport = moneyValue($_POST['transport_claim'] ?? '0');
            $accommodation = moneyValue($_POST['accommodation_claim'] ?? '0');

            $dateObject = DateTime::createFromFormat('!Y-m-d', $visitDate);

            if (!$dateObject || $dateObject->format('Y-m-d') !== $visitDate) {
                throw new Exception('Please select a valid visit date.');
            }

            if ($purpose === '') {
                throw new Exception('Visit purpose is required.');
            }

            if (!is_array($companyNames) || count($companyNames) === 0) {
                throw new Exception('Please add at least one company.');
            }

            $conn->begin_transaction();
            $stmt = null;

            try {
                $reportGroupId = bin2hex(random_bytes(16));

                $stmt = $conn->prepare("
                    INSERT INTO field_schedules (
                        user_id,
                        report_group_id,
                        visit_date,
                        company_name,
                        company_location,
                        visit_purpose,
                        field_report,
                        visit_status,
                        visit_comments,
                        lunch_claim,
                        transport_claim,
                        accommodation_claim
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $savedCount = 0;

                foreach ($companyNames as $i => $companyValue) {
                    $company = trim((string)$companyValue);

                    if ($company === '') {
                        continue;
                    }

                    if (strlen($company) > 200) {
                        throw new Exception('Company names must not exceed 200 characters.');
                    }

                    $location = trim((string)($companyLocations[$i] ?? ''));
                    $visitStatus = $companyStatuses[$i] ?? 'Scheduled';
                    $comments = trim((string)($companyComments[$i] ?? ''));

                    if (!in_array($visitStatus, $visitStatuses, true)) {
                        throw new Exception('Invalid visit status for ' . $company . '.');
                    }

                    // Save daily claims on the first non-empty company only.
                    if ($savedCount === 0) {
                        $companyLunch = $lunch;
                        $companyTransport = $transport;
                        $companyAccommodation = $accommodation;
                    } else {
                        $companyLunch = 0.00;
                        $companyTransport = 0.00;
                        $companyAccommodation = 0.00;
                    }

                    $stmt->bind_param(
                        "issssssssddd",
                        $user_id,
                        $reportGroupId,
                        $visitDate,
                        $company,
                        $location,
                        $purpose,
                        $report,
                        $visitStatus,
                        $comments,
                        $companyLunch,
                        $companyTransport,
                        $companyAccommodation
                    );

                    $stmt->execute();
                    $savedCount++;
                }

                $stmt->close();
                $stmt = null;

                if ($savedCount === 0) {
                    throw new Exception('Enter at least one company name before saving.');
                }

                $conn->commit();

                header('Location: field_schedule.php?success=added');
                exit;

            } catch (Throwable $e) {
                if ($stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }

                $conn->rollback();
                throw $e;
            }
        }

        /*
         * UPDATE AN EXISTING COMPANY VISIT
         *
         * This preserves the existing single-company update form.
         */
        if ($action === 'update') {
            $scheduleId = (int)($_POST['schedule_id'] ?? 0);
            $visitDate = $_POST['visit_date'] ?? '';
            $company = trim($_POST['company_name'] ?? '');
            $location = trim($_POST['company_location'] ?? '');
            $purpose = trim($_POST['visit_purpose'] ?? '');
            $report = trim($_POST['field_report'] ?? '');
            $comments = trim($_POST['visit_comments'] ?? '');
            $visitStatus = $_POST['visit_status'] ?? 'Scheduled';

            if ($scheduleId <= 0) {
                throw new Exception('Invalid visit selected.');
            }

            if (!in_array($visitStatus, $visitStatuses, true)) {
                throw new Exception('Invalid visit status.');
            }

            $dateObject = DateTime::createFromFormat('!Y-m-d', $visitDate);

            if (!$dateObject || $dateObject->format('Y-m-d') !== $visitDate) {
                throw new Exception('Please select a valid visit date.');
            }

            if ($company === '' || $purpose === '') {
                throw new Exception('Company name and visit purpose are required.');
            }

            $stmt = $conn->prepare("
                SELECT payment_status
                FROM field_schedules
                WHERE schedule_id = ? AND user_id = ?
                LIMIT 1
            ");

            $stmt->bind_param("ii", $scheduleId, $user_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$existing) {
                throw new Exception('Visit not found or access denied.');
            }

            if (($existing['payment_status'] ?? 'Pending') === 'Pending') {
                // Only update claims when the form explicitly submits them.
                $hasClaims = isset(
                    $_POST['lunch_claim'],
                    $_POST['transport_claim'],
                    $_POST['accommodation_claim']
                );

                if ($hasClaims) {
                    $lunch = moneyValue($_POST['lunch_claim']);
                    $transport = moneyValue($_POST['transport_claim']);
                    $accommodation = moneyValue($_POST['accommodation_claim']);

                    $stmt = $conn->prepare("
                        UPDATE field_schedules
                        SET visit_date = ?,
                            company_name = ?,
                            company_location = ?,
                            visit_purpose = ?,
                            field_report = ?,
                            visit_status = ?,
                            visit_comments = ?,
                            lunch_claim = ?,
                            transport_claim = ?,
                            accommodation_claim = ?
                        WHERE schedule_id = ? AND user_id = ?
                    ");

                    $stmt->bind_param(
                        "sssssssdddii",
                        $visitDate,
                        $company,
                        $location,
                        $purpose,
                        $report,
                        $visitStatus,
                        $comments,
                        $lunch,
                        $transport,
                        $accommodation,
                        $scheduleId,
                        $user_id
                    );
                } else {
                    $stmt = $conn->prepare("
                        UPDATE field_schedules
                        SET visit_date = ?,
                            company_name = ?,
                            company_location = ?,
                            visit_purpose = ?,
                            field_report = ?,
                            visit_status = ?,
                            visit_comments = ?
                        WHERE schedule_id = ? AND user_id = ?
                    ");

                    $stmt->bind_param(
                        "sssssssii",
                        $visitDate,
                        $company,
                        $location,
                        $purpose,
                        $report,
                        $visitStatus,
                        $comments,
                        $scheduleId,
                        $user_id
                    );
                }
            } else {
                // Paid or denied claims cannot be changed by staff.
                $stmt = $conn->prepare("
                    UPDATE field_schedules
                    SET visit_date = ?,
                        company_name = ?,
                        company_location = ?,
                        visit_purpose = ?,
                        field_report = ?,
                        visit_status = ?,
                        visit_comments = ?
                    WHERE schedule_id = ? AND user_id = ?
                ");

                $stmt->bind_param(
                    "sssssssii",
                    $visitDate,
                    $company,
                    $location,
                    $purpose,
                    $report,
                    $visitStatus,
                    $comments,
                    $scheduleId,
                    $user_id
                );
            }

            $stmt->execute();
            $stmt->close();

            header('Location: field_schedule.php?success=updated');
            exit;
        }

    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}
/*
 * LOAD THE STAFF MEMBER'S VISITS
 */
$stmt = $conn->prepare("
    SELECT *
    FROM field_schedules
    WHERE user_id = ?
    ORDER BY visit_date DESC, schedule_id DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$visits = $stmt->get_result();
$stmt->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Schedule & Marketing Visits</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>

<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto">
    <div class="flex flex-wrap justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Field Schedule & Marketing Visits</h1>
            <p class="text-gray-600 mt-1">Schedule company visits, record outcomes and submit expense claims.</p>
        </div>
        
    </div>

    <?php if (!empty($errorMessage)): ?>
        <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
            <?= h($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
            <?= $_GET['success'] === 'added'
                ? 'Field visit added successfully.'
                : 'Field visit updated successfully.' ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow p-6 mb-8">
        
<form method="POST" class="space-y-5">
    <input type="hidden" name="action" value="add">

    <!-- Shared fields: apply to every company -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">
                Visit Date *
            </label>
            <input type="date" name="visit_date" required
                   value="<?= date('Y-m-d') ?>"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">
                Visit Purpose *
            </label>
            <input type="text" name="visit_purpose" required
                   maxlength="255"
                   placeholder="Marketing, product demonstration..."
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">
                Daily Field Report / Planned Activity
            </label>
            <textarea name="field_report" rows="3"
                      placeholder="Enter the day's planned activities or report"
                      class="w-full border rounded px-3 py-2"></textarea>
        </div>
    </div>

<!-- Daily expense claims: entered once for the whole day -->
<div class="border rounded-lg bg-blue-50 p-4">
    <h3 class="text-lg font-semibold text-gray-800 mb-1">
        Daily Expense Claims
    </h3>
    <p class="text-sm text-gray-600 mb-4">
        Enter each expense once, regardless of how many companies you visit.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">
                Daily Lunch Claim (Ksh)
            </label>
            <input type="number" name="lunch_claim"
                   min="0" step="0.01" value="0"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">
                Daily Transport Claim (Ksh)
            </label>
            <input type="number" name="transport_claim"
                   min="0" step="0.01" value="0"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">
                Daily Accommodation Claim (Ksh)
            </label>
            <input type="number" name="accommodation_claim"
                   min="0" step="0.01" value="0"
                   class="w-full border rounded px-3 py-2">
        </div>
    </div>
</div>

    <div class="border-t pt-5">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">
                    Companies to Visit
                </h3>
                <p class="text-sm text-gray-500">
                    Add multiple companies without changing the shared daily details.
                </p>
            </div>

            <button type="button" id="addCompanyButton"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                + Add Company
            </button>
        </div>

        <div id="companyRows" class="space-y-4"></div>
    </div>

    <button type="submit"
            class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded font-semibold">
        Save All Company Visits
    </button>
</form>

<!-- Template for each company row -->
<template id="companyTemplate">
    <div class="company-row border rounded-lg p-4 bg-gray-50">
        <div class="flex justify-between items-center mb-4">
            <h4 class="company-heading font-semibold text-gray-800">
                Company 1
            </h4>
            <button type="button"
                    class="remove-company text-red-600 hover:text-red-800 font-medium">
                Remove
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">
                    Company Name *
                </label>
                <input type="text" name="companies[company_name][]"
                       maxlength="200" required
                       placeholder="Company name"
                       class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">
                    Company Location
                </label>
                <input type="text" name="companies[company_location][]"
                       placeholder="Town or address"
                       class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">
                    Visit Status
                </label>
                <select name="companies[visit_status][]"
                        class="w-full border rounded px-3 py-2">
                    <option value="Scheduled">Scheduled</option>
                    <option value="Order Placed">Order Placed</option>
                    <option value="Feedback Required">Feedback Required</option>
                    <option value="Follow-up Required">Follow-up Required</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>

           
            <div class="md:col-span-2 lg:col-span-3">
                <label class="block text-sm font-medium mb-1">
                    Company Feedback / Comments
                </label>
                <textarea name="companies[visit_comments][]" rows="2"
                          placeholder="Optional comments for this company"
                          class="w-full border rounded px-3 py-2"></textarea>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.getElementById('companyRows');
    const template = document.getElementById('companyTemplate');
    const addButton = document.getElementById('addCompanyButton');

    function updateCompanyHeadings() {
        rows.querySelectorAll('.company-row').forEach((row, index) => {
            row.querySelector('.company-heading').textContent =
                'Company ' + (index + 1);
        });
    }

    function addCompanyRow() {
        const fragment = template.content.cloneNode(true);
        rows.appendChild(fragment);
        updateCompanyHeadings();
    }

    addButton.addEventListener('click', addCompanyRow);

    rows.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-company');

        if (!button) return;

        const companyRows = rows.querySelectorAll('.company-row');

        if (companyRows.length <= 1) {
            alert('At least one company row is required.');
            return;
        }

        button.closest('.company-row').remove();
        updateCompanyHeadings();
    });

    // Start with one company row.
    addCompanyRow();
});
</script>

    </div>
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">
        My Field Schedule
    </h2>

    <?php
    $stmt = $conn->prepare("
        SELECT
            COALESCE(
                report_group_id,
                CONCAT('legacy-', schedule_id)
            ) AS report_key,
            MIN(visit_date) AS visit_date,
            MIN(visit_purpose) AS visit_purpose,
            COUNT(*) AS company_count,
            MAX(created_at) AS created_at
        FROM field_schedules
        WHERE user_id = ?
        GROUP BY COALESCE(
            report_group_id,
            CONCAT('legacy-', schedule_id)
        )
        ORDER BY MIN(visit_date) DESC, MAX(schedule_id) DESC
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $dailyReports = $stmt->get_result();
    ?>

    <?php if ($dailyReports->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                            Visit Date
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                            Day
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">
                            Purpose
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">
                            Companies
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($dayReport = $dailyReports->fetch_assoc()): ?>
                        <?php
                        $date = new DateTime($dayReport['visit_date']);
                        $reportKey = $dayReport['report_key'];
                        ?>

                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-800">
                                <?= h($date->format('d M Y')) ?>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                                <?= h($date->format('l')) ?>
                            </td>

                            <td class="px-4 py-4 text-sm text-gray-800">
                                <?= h($dayReport['visit_purpose']) ?>
                            </td>

                            <td class="px-4 py-4 text-sm text-center">
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full">
                                    <?= (int)$dayReport['company_count'] ?>
                                </span>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                <a
                                    href="view_field_report.php?group=<?= urlencode($reportKey) ?>"
                                    class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm mr-1"
                                >
                                    View Report
                                </a>

                                <a
                                    href="edit_field_report.php?group=<?= urlencode($reportKey) ?>"
                                    class="inline-block bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm"
                                >
                                    Edit Report
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-gray-600">
            You have not added any field reports yet.
        </p>
    <?php endif; ?>

    <?php $stmt->close(); ?>
</div>
</div>
</body>
</html>
