
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

$group = trim($_GET['group'] ?? $_POST['group'] ?? '');
$errorMessage = '';
$successMessage = '';

$report = null;
$companies = [];
$claimsEditable = false;

/*
 * Load the daily report.
 * Legacy reports use their individual schedule ID.
 */
function loadDailyReport($conn, $user_id, $group) {
    $rows = [];

    if ($group === '') {
        return $rows;
    }

    if (strpos($group, 'legacy-') === 0) {
        $scheduleId = (int)substr($group, 7);

        if ($scheduleId <= 0) {
            return $rows;
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
        $rows[] = $row;
    }

    $stmt->close();

    return $rows;
}

try {
    if ($group === '') {
        throw new Exception('No field report was selected.');
    }

    /*
     * SAVE THE COMPLETE DAILY REPORT
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && ($_POST['action'] ?? '') === 'update_daily_report') {

        $visitDate = trim($_POST['visit_date'] ?? '');
        $purpose = trim($_POST['visit_purpose'] ?? '');
        $fieldReport = trim($_POST['field_report'] ?? '');

        $companyNames = $_POST['company_name'] ?? [];
        $companyLocations = $_POST['company_location'] ?? [];
        $companyStatuses = $_POST['visit_status'] ?? [];
        $companyComments = $_POST['visit_comments'] ?? [];

        $dateObject = DateTime::createFromFormat('!Y-m-d', $visitDate);

        if (!$dateObject || $dateObject->format('Y-m-d') !== $visitDate) {
            throw new Exception('Please select a valid visit date.');
        }

        if ($purpose === '') {
            throw new Exception('Visit purpose is required.');
        }

        if (strlen($purpose) > 255) {
            throw new Exception('Visit purpose must not exceed 255 characters.');
        }

        if (!is_array($companyNames)
            || !is_array($companyLocations)
            || !is_array($companyStatuses)
            || !is_array($companyComments)) {
            throw new Exception('Invalid company form data.');
        }

        if (count($companyNames) < 1) {
            throw new Exception('Add at least one company to the report.');
        }

        if (
            count($companyNames) !== count($companyLocations) ||
            count($companyNames) !== count($companyStatuses) ||
            count($companyNames) !== count($companyComments)
        ) {
            throw new Exception('Company form data is incomplete. Please reload and try again.');
        }

        /*
         * Validate every submitted company before starting changes.
         */
        $cleanCompanies = [];

        foreach ($companyNames as $i => $companyValue) {
            $companyName = trim((string)$companyValue);
            $location = trim((string)($companyLocations[$i] ?? ''));
            $status = (string)($companyStatuses[$i] ?? 'Scheduled');
            $comments = trim((string)($companyComments[$i] ?? ''));

            if ($companyName === '') {
                throw new Exception('Every company row must have a company name.');
            }

            if (strlen($companyName) > 200) {
                throw new Exception('Company names must not exceed 200 characters.');
            }

            if (!in_array($status, $visitStatuses, true)) {
                throw new Exception('Invalid visit status for ' . $companyName . '.');
            }

            $cleanCompanies[] = [
                'company_name' => $companyName,
                'company_location' => $location,
                'visit_status' => $status,
                'visit_comments' => $comments
            ];
        }

        $conn->begin_transaction();

        try {
            /*
             * Lock the existing rows while updating the daily report.
             * Payment status is never updated by this page.
             */
            if (strpos($group, 'legacy-') === 0) {
                $scheduleId = (int)substr($group, 7);

                if ($scheduleId <= 0) {
                    throw new Exception('Invalid report selected.');
                }

                $stmt = $conn->prepare("
                    SELECT *
                    FROM field_schedules
                    WHERE schedule_id = ?
                      AND user_id = ?
                    FOR UPDATE
                ");

                $stmt->bind_param("ii", $scheduleId, $user_id);
            } else {
                $stmt = $conn->prepare("
                    SELECT *
                    FROM field_schedules
                    WHERE report_group_id = ?
                      AND user_id = ?
                    ORDER BY schedule_id ASC
                    FOR UPDATE
                ");

                $stmt->bind_param("si", $group, $user_id);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $existingRows = [];

            while ($row = $result->fetch_assoc()) {
                $existingRows[] = $row;
            }

            $stmt->close();

            if (empty($existingRows)) {
                throw new Exception('Report not found or access denied.');
            }

            /*
             * Claims are editable only if every row is Pending.
             * If claims are locked, retain the existing claim values.
             */
            $allPending = true;

            foreach ($existingRows as $existingRow) {
                if (($existingRow['payment_status'] ?? 'Pending') !== 'Pending') {
                    $allPending = false;
                    break;
                }
            }

            $claimsEditable = $allPending;

            if ($claimsEditable) {
                $lunch = moneyValue($_POST['lunch_claim'] ?? '0');
                $transport = moneyValue($_POST['transport_claim'] ?? '0');
                $accommodation = moneyValue($_POST['accommodation_claim'] ?? '0');
            } else {
                $lunch = 0.00;
                $transport = 0.00;
                $accommodation = 0.00;

                foreach ($existingRows as $existingRow) {
                    $lunch += (float)$existingRow['lunch_claim'];
                    $transport += (float)$existingRow['transport_claim'];
                    $accommodation += (float)$existingRow['accommodation_claim'];
                }
            }

            /*
             * Update the shared daily details on all existing rows.
             * Payment-related columns are deliberately excluded.
             */
            $firstExisting = $existingRows[0];

            $stmt = $conn->prepare("
                UPDATE field_schedules
                SET visit_date = ?,
                    visit_purpose = ?,
                    field_report = ?
                WHERE user_id = ?
                  AND (
                    (? = 1 AND report_group_id = ?)
                    OR
                    (? = 0 AND schedule_id = ?)
                  )
            ");

            $isGrouped = strpos($group, 'legacy-') !== 0;
            $legacyId = $isGrouped ? 0 : (int)substr($group, 7);

            $stmt->bind_param(
    "sssiisis",
    $visitDate,
    $purpose,
    $fieldReport,
    $user_id,
    $isGrouped,
    $group,
    $isGrouped,
    $legacyId
);
            $stmt->execute();
            $stmt->close();

            /*
             * Update existing company rows, add new ones, and remove
             * rows omitted from the submitted form.
             *
             * Retain existing row IDs in hidden fields to identify
             * company rows safely.
             */
            $submittedIds = $_POST['schedule_id'] ?? [];

            if (!is_array($submittedIds)) {
                throw new Exception('Invalid company row identifiers.');
            }

            if (count($submittedIds) !== count($cleanCompanies)) {
                throw new Exception('Company form data is inconsistent. Please reload and try again.');
            }

            $existingById = [];

            foreach ($existingRows as $existingRow) {
                $existingById[(int)$existingRow['schedule_id']] = $existingRow;
            }

            $usedIds = [];
            $newCompanyIds = [];

            foreach ($cleanCompanies as $i => $companyData) {
                $rowId = (int)($submittedIds[$i] ?? 0);

                if ($rowId > 0) {
                    if (!isset($existingById[$rowId])) {
                        throw new Exception('A company row does not belong to this report.');
                    }

                    if (in_array($rowId, $usedIds, true)) {
                        throw new Exception('Duplicate company row detected.');
                    }

                    $usedIds[] = $rowId;

                    $stmt = $conn->prepare("
                        UPDATE field_schedules
                        SET company_name = ?,
                            company_location = ?,
                            visit_status = ?,
                            visit_comments = ?
                        WHERE schedule_id = ?
                          AND user_id = ?
                    ");

                    $stmt->bind_param(
                        "ssssii",
                        $companyData['company_name'],
                        $companyData['company_location'],
                        $companyData['visit_status'],
                        $companyData['visit_comments'],
                        $rowId,
                        $user_id
                    );

                    $stmt->execute();
                    $stmt->close();

                    $newCompanyIds[] = $rowId;
                } else {
                    /*
                     * A new company is attached to the same daily report.
                     * Its claims are zero so daily claims are not duplicated.
                     */
                    $reportGroupId = $isGrouped
                        ? $group
                        : null;

                    if (!$isGrouped) {
                        $reportGroupId = bin2hex(random_bytes(16));

                        /*
                         * Convert a legacy single-row report to a grouped report.
                         * The original row and its claims are preserved.
                         */
                        $legacyId = (int)substr($group, 7);

                        $stmt = $conn->prepare("
                            UPDATE field_schedules
                            SET report_group_id = ?
                            WHERE schedule_id = ?
                              AND user_id = ?
                        ");

                        $stmt->bind_param(
                            "sii",
                            $reportGroupId,
                            $legacyId,
                            $user_id
                        );

                        $stmt->execute();
                        $stmt->close();

                        $group = $reportGroupId;
                        $isGrouped = true;
                    }

                    $zeroLunch = 0.00;
                    $zeroTransport = 0.00;
                    $zeroAccommodation = 0.00;

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

                    $stmt->bind_param(
                        "issssssssddd",
                        $user_id,
                        $reportGroupId,
                        $visitDate,
                        $companyData['company_name'],
                        $companyData['company_location'],
                        $purpose,
                        $fieldReport,
                        $companyData['visit_status'],
                        $companyData['visit_comments'],
                        $zeroLunch,
                        $zeroTransport,
                        $zeroAccommodation
                    );

                    $stmt->execute();
                    $newCompanyIds[] = $conn->insert_id;
                    $stmt->close();
                }
            }

            /*
             * Remove omitted company rows only while claims are Pending.
             * This prevents deletion of rows linked to reviewed claims.
             */
            $idsToDelete = [];

            foreach ($existingRows as $existingRow) {
                $existingId = (int)$existingRow['schedule_id'];

                if (!in_array($existingId, $usedIds, true)) {
                    $idsToDelete[] = $existingId;
                }
            }

            if (!empty($idsToDelete)) {
                if (!$allPending) {
                    throw new Exception(
                        'Companies cannot be removed after a claim has been paid or denied.'
                    );
                }

                /*
                 * Do not allow the user to remove every original row.
                 */
                if (count($newCompanyIds) === 0) {
                    throw new Exception('A daily report must contain at least one company.');
                }

                $deleteStmt = $conn->prepare("
                    DELETE FROM field_schedules
                    WHERE schedule_id = ?
                      AND user_id = ?
                ");

                foreach ($idsToDelete as $deleteId) {
                    $deleteStmt->bind_param("ii", $deleteId, $user_id);
                    $deleteStmt->execute();
                }

                $deleteStmt->close();
            }

            /*
             * Keep the daily claims on one existing row only.
             * Do not modify claims if they have already been reviewed.
             */
            if ($allPending) {
                $claimHolderId = $newCompanyIds[0] ?? 0;

                if ($claimHolderId <= 0) {
                    throw new Exception('Unable to assign the daily claims.');
                }

                $stmt = $conn->prepare("
                    UPDATE field_schedules
                    SET lunch_claim = 0,
                        transport_claim = 0,
                        accommodation_claim = 0
                    WHERE user_id = ?
                      AND (
                        (? = 1 AND report_group_id = ?)
                        OR
                        (? = 0 AND schedule_id = ?)
                      )
                ");

                $stmt->bind_param(
                    "iisis",
                    $user_id,
                    $isGrouped,
                    $group,
                    $isGrouped,
                    $legacyId
                );

                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("
                    UPDATE field_schedules
                    SET lunch_claim = ?,
                        transport_claim = ?,
                        accommodation_claim = ?
                    WHERE schedule_id = ?
                      AND user_id = ?
                ");

                $stmt->bind_param(
                    "dddii",
                    $lunch,
                    $transport,
                    $accommodation,
                    $claimHolderId,
                    $user_id
                );

                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            header('Location: view_field_report.php?group=' . urlencode($group) . '&success=updated');
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /*
     * Load the report for display.
     */
    $companies = loadDailyReport($conn, $user_id, $group);

    if (empty($companies)) {
        throw new Exception('Report not found or you do not have permission to edit it.');
    }

    $report = $companies[0];
    $claimsEditable = true;

    foreach ($companies as $company) {
        if (($company['payment_status'] ?? 'Pending') !== 'Pending') {
            $claimsEditable = false;
            break;
        }
    }

} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}

/*
 * Calculate existing daily claims.
 */
$totalLunch = 0;
$totalTransport = 0;
$totalAccommodation = 0;

foreach ($companies as $company) {
    $totalLunch += (float)$company['lunch_claim'];
    $totalTransport += (float)$company['transport_claim'];
    $totalAccommodation += (float)$company['accommodation_claim'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Daily Field Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 max-w-6xl mx-auto p-6">
    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Edit Daily Field Report</h1>
            <p class="text-gray-600">Edit the shared daily details and company visits.</p>
        </div>
        <div class="flex gap-2">
            <a href="field_schedule.php"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                Back to Schedule
            </a>

            <a href="view_field_report.php?group=<?= urlencode($group) ?>"
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                View Report
            </a>
        </div>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="bg-red-100 text-red-800 p-4 rounded mb-5">
            <?= h($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($report): ?>
        <?php
        $paymentStatuses = array_unique(array_map(
            fn($item) => $item['payment_status'] ?? 'Pending',
            $companies
        ));
        ?>
        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" id="editReportForm" class="space-y-6">
                <input type="hidden" name="action" value="update_daily_report">
                <input type="hidden" name="group" value="<?= h($group) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Visit Date *</label>
                        <input type="date" name="visit_date"
                               value="<?= h($report['visit_date']) ?>" required
                               class="w-full border rounded px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Visit Purpose *</label>
                        <input type="text" name="visit_purpose"
                               maxlength="255"
                               value="<?= h($report['visit_purpose']) ?>" required
                               class="w-full border rounded px-3 py-2">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">Daily Field Report / Planned Activity</label>
                        <textarea name="field_report" rows="4"
                                  class="w-full border rounded px-3 py-2"><?= h($report['field_report']) ?></textarea>
                    </div>
                </div>

                <div class="border rounded-lg p-4 bg-blue-50">
                    <h2 class="text-lg font-semibold mb-3">Daily Expense Claims</h2>

                    <?php if ($claimsEditable): ?>
                        <p class="text-sm text-gray-600 mb-4">
                            Claims are entered once for the entire day, not separately for each company.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Lunch (Ksh)</label>
                                <input type="number" name="lunch_claim" min="0" step="0.01" required
                                       value="<?= h(number_format($totalLunch, 2, '.', '')) ?>"
                                       class="w-full border rounded px-3 py-2">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Transport (Ksh)</label>
                                <input type="number" name="transport_claim" min="0" step="0.01" required
                                       value="<?= h(number_format($totalTransport, 2, '.', '')) ?>"
                                       class="w-full border rounded px-3 py-2">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">Accommodation (Ksh)</label>
                                <input type="number" name="accommodation_claim" min="0" step="0.01" required
                                       value="<?= h(number_format($totalAccommodation, 2, '.', '')) ?>"
                                       class="w-full border rounded px-3 py-2">
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-orange-800 mb-3">
                            Claims are locked because at least one company row has been paid or denied.
                            You can still edit the report details and company information.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white rounded p-3">
                                <p class="text-sm text-gray-500">Lunch</p>
                                <p class="font-bold">Ksh <?= number_format($totalLunch, 2) ?></p>
                            </div>
                            <div class="bg-white rounded p-3">
                                <p class="text-sm text-gray-500">Transport</p>
                                <p class="font-bold">Ksh <?= number_format($totalTransport, 2) ?></p>
                            </div>
                            <div class="bg-white rounded p-3">
                                <p class="text-sm text-gray-500">Accommodation</p>
                                <p class="font-bold">Ksh <?= number_format($totalAccommodation, 2) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <p class="text-sm text-gray-600 mb-1">Current Payment Status</p>
                        <?php foreach ($paymentStatuses as $status): ?>
                            <span class="inline-block bg-yellow-100 text-yellow-800 px-3 py-1 rounded mr-2 mb-1">
                                <?= h($status) ?>
                            </span>
                        <?php endforeach; ?>
                        <p class="text-xs text-gray-500 mt-2">
                            Payment status is read-only and cannot be changed by staff.
                        </p>
                    </div>
                </div>

                <div>
                    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                        <div>
                            <h2 class="text-xl font-semibold">Company Visits</h2>
                            <p class="text-sm text-gray-500">Update, add, or remove company entries.</p>
                        </div>
                        <button type="button" id="addCompanyButton"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                            + Add Company
                        </button>
                    </div>

                    <div id="companyRows" class="space-y-4">
                        <?php foreach ($companies as $company): ?>
                            <div class="company-row border rounded-lg bg-gray-50 p-4">
                                <input type="hidden" name="schedule_id[]"
                                       value="<?= (int)$company['schedule_id'] ?>">

                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="company-heading font-semibold text-gray-800">Company</h3>
                                    <button type="button"
                                            class="remove-company text-red-600 hover:text-red-800 font-medium">
                                        Remove
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Company Name *</label>
                                        <input type="text" name="company_name[]"
                                               maxlength="200" required
                                               value="<?= h($company['company_name']) ?>"
                                               class="w-full border rounded px-3 py-2">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-1">Company Location</label>
                                        <input type="text" name="company_location[]"
                                               value="<?= h($company['company_location']) ?>"
                                               class="w-full border rounded px-3 py-2">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-1">Visit Status</label>
                                        <select name="visit_status[]" class="w-full border rounded px-3 py-2">
                                            <?php foreach ($visitStatuses as $status): ?>
                                                <option value="<?= h($status) ?>"
                                                    <?= $company['visit_status'] === $status ? 'selected' : '' ?>>
                                                    <?= h($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-1">Company Feedback / Comments</label>
                                        <textarea name="visit_comments[]" rows="2"
                                                  class="w-full border rounded px-3 py-2"><?= h($company['visit_comments']) ?></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 border-t pt-5">
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded font-semibold">
                        Save Daily Report
                    </button>

                    <a href="view_field_report.php?group=<?= urlencode($group) ?>"
                       class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<template id="companyTemplate">
    <div class="company-row border rounded-lg bg-gray-50 p-4">
        <input type="hidden" name="schedule_id[]" value="0">

        <div class="flex justify-between items-center mb-4">
            <h3 class="company-heading font-semibold text-gray-800">New Company</h3>
            <button type="button" class="remove-company text-red-600 hover:text-red-800 font-medium">
                Remove
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Company Name *</label>
                <input type="text" name="company_name[]" maxlength="200" required
                       class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Company Location</label>
                <input type="text" name="company_location[]"
                       class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Visit Status</label>
                <select name="visit_status[]" class="w-full border rounded px-3 py-2">
                    <option value="Scheduled">Scheduled</option>
                    <option value="Order Placed">Order Placed</option>
                    <option value="Feedback Required">Feedback Required</option>
                    <option value="Follow-up Required">Follow-up Required</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Company Feedback / Comments</label>
                <textarea name="visit_comments[]" rows="2"
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
    const form = document.getElementById('editReportForm');

    if (!rows || !template || !addButton || !form) return;

    function updateHeadings() {
        rows.querySelectorAll('.company-row').forEach((row, index) => {
            row.querySelector('.company-heading').textContent =
                'Company ' + (index + 1);
        });
    }

    addButton.addEventListener('click', function () {
        rows.appendChild(template.content.cloneNode(true));
        updateHeadings();
    });

    rows.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-company');

        if (!button) return;

        const companyRows = rows.querySelectorAll('.company-row');

        if (companyRows.length <= 1) {
            alert('A daily report must contain at least one company.');
            return;
        }

        if (!confirm('Remove this company from the daily report?')) return;

        button.closest('.company-row').remove();
        updateHeadings();
    });

    form.addEventListener('submit', function (event) {
        if (rows.querySelectorAll('.company-row').length < 1) {
            event.preventDefault();
            alert('Add at least one company before saving.');
            return;
        }

        if (!confirm('Save the changes to this daily field report?')) {
            event.preventDefault();
        }
    });

    updateHeadings();
});
</script>
</body>
</html>
