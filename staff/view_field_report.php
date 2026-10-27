
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

$group = trim($_GET['group'] ?? '');
$errorMessage = '';
$report = null;
$companies = [];

if ($group === '') {
    $errorMessage = 'No field report was selected.';
} else {
    if (strpos($group, 'legacy-') === 0) {
        $scheduleId = (int)substr($group, 7);

        if ($scheduleId > 0) {
            $stmt = $conn->prepare("
                SELECT *
                FROM field_schedules
                WHERE schedule_id = ? AND user_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $scheduleId, $user_id);
        } else {
            $stmt = null;
        }
    } else {
        $stmt = $conn->prepare("
            SELECT *
            FROM field_schedules
            WHERE report_group_id = ? AND user_id = ?
            ORDER BY schedule_id ASC
        ");
        $stmt->bind_param("si", $group, $user_id);
    }

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $companies[] = $row;
        }

        $stmt->close();
    }

    if (empty($companies)) {
        $errorMessage = 'Report not found, or you do not have permission to view it.';
    } else {
        $report = $companies[0];
    }
}

$totalLunch = 0;
$totalTransport = 0;
$totalAccommodation = 0;

foreach ($companies as $company) {
    $totalLunch += (float)$company['lunch_claim'];
    $totalTransport += (float)$company['transport_claim'];
    $totalAccommodation += (float)$company['accommodation_claim'];
}

$totalClaims = $totalLunch + $totalTransport + $totalAccommodation;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Field Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <style>
        @media print {
            nav, aside, .no-print {
                display: none !important;
            }
            body {
                background: white !important;
            }
            .print-container {
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="print-container ml-64 p-6 max-w-6xl mx-auto">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-6 no-print">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Daily Field Report</h1>
            <p class="text-gray-600">View all company visits recorded for this day.</p>
        </div>
        <div class="flex gap-2">
            <a href="field_schedule.php"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                Back
            </a>
            <?php if ($report): ?>
                <a href="edit_field_report.php?group=<?= urlencode($group) ?>"
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                    Edit Report
                </a>
                <button onclick="window.print()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    Print Report
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="bg-red-100 text-red-800 p-4 rounded">
            <?= h($errorMessage) ?>
        </div>
    <?php else: ?>
        <?php
        $date = new DateTime($report['visit_date']);
        $paymentStatuses = array_unique(array_map(
            fn($item) => $item['payment_status'] ?? 'Pending',
            $companies
        ));
        ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="border-b pb-4 mb-5">
                <h2 class="text-xl font-bold text-gray-800">Daily Report Summary</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Report date: <?= h($date->format('l, d F Y')) ?>
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <p class="text-sm text-gray-500">Visit Purpose</p>
                    <p class="font-semibold text-gray-800"><?= h($report['visit_purpose']) ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Number of Companies</p>
                    <p class="font-semibold text-gray-800"><?= count($companies) ?></p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm text-gray-500">Daily Field Report / Planned Activity</p>
                    <div class="mt-1 whitespace-pre-wrap text-gray-800"><?= h($report['field_report']) ?: 'No daily report entered.' ?></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Companies Visited</h2>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Company</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Comments</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($companies as $index => $company): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm"><?= $index + 1 ?></td>
                                <td class="px-4 py-3 text-sm font-medium"><?= h($company['company_name']) ?></td>
                                <td class="px-4 py-3 text-sm"><?= h($company['company_location']) ?: '—' ?></td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                        <?= h($company['visit_status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm whitespace-pre-wrap"><?= h($company['visit_comments']) ?: '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Daily Expense Claims</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-blue-50 rounded p-4">
                    <p class="text-sm text-gray-600">Lunch</p>
                    <p class="text-xl font-bold">Ksh <?= number_format($totalLunch, 2) ?></p>
                </div>
                <div class="bg-blue-50 rounded p-4">
                    <p class="text-sm text-gray-600">Transport</p>
                    <p class="text-xl font-bold">Ksh <?= number_format($totalTransport, 2) ?></p>
                </div>
                <div class="bg-blue-50 rounded p-4">
                    <p class="text-sm text-gray-600">Accommodation</p>
                    <p class="text-xl font-bold">Ksh <?= number_format($totalAccommodation, 2) ?></p>
                </div>
                <div class="bg-green-50 rounded p-4">
                    <p class="text-sm text-gray-600">Total Claims</p>
                    <p class="text-xl font-bold text-green-800">Ksh <?= number_format($totalClaims, 2) ?></p>
                </div>
            </div>

            <div class="mt-5">
                <p class="text-sm text-gray-500 mb-2">Claim Payment Status</p>
                <?php foreach ($paymentStatuses as $status): ?>
                    <span class="inline-block bg-yellow-100 text-yellow-800 px-3 py-1 rounded mr-2 mb-2">
                        <?= h($status) ?>
                    </span>
                <?php endforeach; ?>
                <p class="text-xs text-gray-500 mt-2">
                    Claims are recorded once per day. Payment status is managed by the authorized reviewer.
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
