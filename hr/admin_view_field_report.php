
<?php
session_start();

require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// --------------------------------------------------
// ACCESS CHECK
// --------------------------------------------------
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// --------------------------------------------------
// HELPERS
// --------------------------------------------------
function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function paymentBadgeClass($status): string
{
    $status = strtolower(trim((string)$status));

    if (strpos($status, 'denied') !== false) {
        return 'bg-red-100 text-red-800';
    }

    if (strpos($status, 'paid') !== false) {
        return 'bg-green-100 text-green-800';
    }

    if (strpos($status, 'pending') !== false) {
        return 'bg-yellow-100 text-yellow-800';
    }

    return 'bg-gray-100 text-gray-700';
}

// --------------------------------------------------
// REPORT IDENTIFICATION
// --------------------------------------------------
$group = trim($_GET['group'] ?? '');
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

$errorMessage = '';
$report = null;
$companies = [];
$employee = null;

$totalLunch = 0;
$totalTransport = 0;
$totalAccommodation = 0;
$totalClaims = 0;

if ($group === '' || !$user_id || $user_id < 1) {
    $errorMessage = 'No valid field report was selected.';
} else {
    try {
        // --------------------------------------------------
        // FETCH REPORT COMPANIES
        // --------------------------------------------------
        if (strpos($group, 'legacy-') === 0) {
            // Older reports may not have a report_group_id.
            $scheduleId = (int) substr($group, 7);

            if ($scheduleId < 1) {
                throw new RuntimeException('Invalid report reference.');
            }

            $stmt = $conn->prepare("
                SELECT *
                FROM field_schedules
                WHERE schedule_id = ?
                  AND user_id = ?
                LIMIT 1
            ");

            $stmt->bind_param("ii", $scheduleId, $user_id);

        } else {
            $stmt = $conn->prepare("
                SELECT *
                FROM field_schedules
                WHERE report_group_id = ?
                  AND user_id = ?
                ORDER BY schedule_id ASC
            ");

            $stmt->bind_param("si", $group, $user_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $companies[] = $row;
        }

        $stmt->close();

        if (empty($companies)) {
            throw new RuntimeException(
                'Report not found. It may have been deleted or the selected staff member is incorrect.'
            );
        }

        $report = $companies[0];

        // --------------------------------------------------
        // FETCH EMPLOYEE DETAILS
        // users.user_id -> field_schedules.user_id
        // users.employee_id -> employees.employee_id
        // --------------------------------------------------
        $employeeStmt = $conn->prepare("
            SELECT
                u.user_id,
                u.employee_id,
                e.first_name,
                e.last_name,
                e.national_id
            FROM users u
            INNER JOIN employees e
                ON e.employee_id = u.employee_id
            WHERE u.user_id = ?
            LIMIT 1
        ");

        $employeeStmt->bind_param("i", $user_id);
        $employeeStmt->execute();

        $employeeResult = $employeeStmt->get_result();
        $employee = $employeeResult->fetch_assoc();

        $employeeStmt->close();

        if (!$employee) {
            throw new RuntimeException(
                'The employee information associated with this report could not be found.'
            );
        }

        // --------------------------------------------------
        // CALCULATE DAILY CLAIMS
        // --------------------------------------------------
        foreach ($companies as $company) {
            $totalLunch += (float)($company['lunch_claim'] ?? 0);
            $totalTransport += (float)($company['transport_claim'] ?? 0);
            $totalAccommodation += (float)($company['accommodation_claim'] ?? 0);
        }

        $totalClaims = $totalLunch + $totalTransport + $totalAccommodation;

    } catch (Throwable $e) {
        error_log('Admin view field report: ' . $e->getMessage());
        $errorMessage = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'An error occurred while loading the field report.';
    }
}

// --------------------------------------------------
// SUMMARY VALUES
// --------------------------------------------------
$fullName = $employee
    ? trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))
    : '';

$paymentStatuses = [];

foreach ($companies as $company) {
    $status = trim((string)($company['payment_status'] ?? ''));

    if ($status === '') {
        $status = 'Pending';
    }

    $paymentStatuses[] = $status;
}

$paymentStatuses = array_values(array_unique($paymentStatuses));

$reportDate = null;

if ($report && !empty($report['visit_date'])) {
    try {
        $reportDate = new DateTime($report['visit_date']);
    } catch (Throwable $e) {
        $reportDate = null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Staff Field Report | HR</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"
    >

    <style>
        body {
            background: #f3f4f6;
        }

        .report-container {
            max-width: 1152px;
        }

        @media print {
            nav,
            aside,
            .no-print {
                display: none !important;
            }

            body {
                background: #ffffff !important;
            }

            .report-container {
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
                width: 100% !important;
            }

            .print-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
            }

            table {
                width: 100% !important;
            }

            th,
            td {
                color: #000 !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-full mx-auto">

    <!-- PAGE HEADER -->
    <div class="flex flex-wrap justify-between items-center gap-4 mb-6 no-print">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                Staff Field Report
            </h1>

            <p class="text-gray-600 mt-1">
                Review the daily field activities and expense claims submitted by staff.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a
                href="admin_field_schedule_report.php"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg"
            >
                Back to Reports
            </a>

            <?php if ($report): ?>
                <a
                    href="admin_edit_field_report.php?group=<?= urlencode($group) ?>&user_id=<?= (int)$user_id ?>"
                    class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg"
                >
                    Edit Report
                </a>

                <button
                    type="button"
                    onclick="window.print()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg"
                >
                    Print Report
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($errorMessage !== ''): ?>

        <div class="bg-red-100 border border-red-200 text-red-800 p-5 rounded-lg">
            <h2 class="font-bold mb-1">Unable to display report</h2>
            <p><?= h($errorMessage) ?></p>
        </div>

    <?php else: ?>

        <!-- EMPLOYEE DETAILS -->
        <section class="print-card bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="border-b border-gray-200 pb-4 mb-5">
                <h2 class="text-xl font-bold text-gray-900">
                    Staff Information
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    Employee details associated with this field report.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                <div>
                    <p class="text-sm text-gray-500">Staff Name</p>
                    <p class="font-semibold text-gray-900">
                        <?= h($fullName !== '' ? $fullName : 'Not available') ?>
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Employee ID</p>
                    <p class="font-semibold text-gray-900">
                        <?= h($employee['employee_id'] ?? '—') ?>
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">National ID</p>
                    <p class="font-semibold text-gray-900">
                        <?= h($employee['national_id'] ?? '—') ?>
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">System User ID</p>
                    <p class="font-semibold text-gray-900">
                        <?= (int)$user_id ?>
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Report Date</p>
                    <p class="font-semibold text-gray-900">
                        <?= $reportDate ? h($reportDate->format('d M Y')) : '—' ?>
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Companies Visited</p>
                    <p class="font-semibold text-gray-900">
                        <?= count($companies) ?>
                    </p>
                </div>

            </div>
        </section>

        <!-- DAILY REPORT SUMMARY -->
        <section class="print-card bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="border-b border-gray-200 pb-4 mb-5">
                <h2 class="text-xl font-bold text-gray-900">
                    Daily Report Summary
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    <?= $reportDate
                        ? h($reportDate->format('l, d F Y'))
                        : 'Report date unavailable' ?>
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                    <p class="text-sm text-gray-500">Visit Purpose</p>
                    <p class="font-semibold text-gray-800">
                        <?= h($report['visit_purpose'] ?? '—') ?>
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Number of Companies</p>
                    <p class="font-semibold text-gray-800">
                        <?= count($companies) ?>
                    </p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-sm text-gray-500">
                        Daily Field Report / Planned Activity
                    </p>

                    <div class="mt-2 whitespace-pre-wrap text-gray-800 leading-relaxed"><?= h($report['field_report'] ?? '') !== '' ? h($report['field_report']) : 'No daily report entered.' ?></div>
                </div>

            </div>
        </section>

        <!-- COMPANIES VISITED -->
        <section class="print-card bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="border-b border-gray-200 pb-4 mb-5">
                <h2 class="text-xl font-bold text-gray-900">
                    Companies Visited
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">

                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                #
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Company
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Location
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Visit Status
                            </th>

                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Comments
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 bg-white">

                        <?php foreach ($companies as $index => $company): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <?= $index + 1 ?>
                                </td>

                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <?= h($company['company_name'] ?? '—') ?>
                                </td>

                                <td class="px-4 py-3 text-sm">
                                    <?= h($company['company_location'] ?? '') !== ''
                                        ? h($company['company_location'])
                                        : '—' ?>
                                </td>

                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-semibold">
                                        <?= h($company['visit_status'] ?? 'Not specified') ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-sm whitespace-pre-wrap">
                                    <?= h($company['visit_comments'] ?? '') !== ''
                                        ? h($company['visit_comments'])
                                        : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </section>

        <!-- DAILY EXPENSE CLAIMS -->
        <section class="print-card bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="border-b border-gray-200 pb-4 mb-5">
                <h2 class="text-xl font-bold text-gray-900">
                    Daily Expense Claims
                </h2>

                <p class="text-sm text-gray-500 mt-1">
                    Amounts are displayed in Kenyan shillings (KSh).
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <div class="bg-blue-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">Lunch</p>
                    <p class="text-xl font-bold text-gray-900">
                        KSh <?= number_format($totalLunch, 2) ?>
                    </p>
                </div>

                <div class="bg-blue-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">Transport</p>
                    <p class="text-xl font-bold text-gray-900">
                        KSh <?= number_format($totalTransport, 2) ?>
                    </p>
                </div>

                <div class="bg-blue-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">Accommodation</p>
                    <p class="text-xl font-bold text-gray-900">
                        KSh <?= number_format($totalAccommodation, 2) ?>
                    </p>
                </div>

                <div class="bg-green-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">Total Claims</p>
                    <p class="text-xl font-bold text-green-800">
                        KSh <?= number_format($totalClaims, 2) ?>
                    </p>
                </div>

            </div>

            <div class="mt-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">
                    Claim Payment Status
                </h3>

                <?php foreach ($paymentStatuses as $status): ?>
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold mr-2 mb-2 <?= h(paymentBadgeClass($status)) ?>">
                        <?= h($status) ?>
                    </span>
                <?php endforeach; ?>

                <p class="text-xs text-gray-500 mt-2">
                    Payment status is displayed for review only. This page does not change payment or approval records.
                </p>
            </div>
        </section>

        <!-- BOTTOM ACTIONS -->
        <div class="flex flex-wrap justify-between gap-3 mb-8 no-print">

            <a
                href="admin_field_schedule_report.php"
                class="bg-gray-600 hover:bg-gray-700 text-white px-5 py-2 rounded-lg"
            >
                Back to Staff Reports
            </a>

            <div class="flex gap-2">
                <a
                    href="admin_edit_field_report.php?group=<?= urlencode($group) ?>&user_id=<?= (int)$user_id ?>"
                    class="bg-amber-500 hover:bg-amber-600 text-white px-5 py-2 rounded-lg"
                >
                    Edit Report
                </a>

                <button
                    type="button"
                    onclick="window.print()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg"
                >
                    Print Report
                </button>
            </div>

        </div>

    <?php endif; ?>

</main>

</body>
</html>
