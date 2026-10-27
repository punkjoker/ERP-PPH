
<?php
session_start();
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$reviewerId = (int)($_SESSION['user_id'] ?? $_SESSION['admin_id']);

function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function moneyValue($value)
{
    if ($value === '' || !is_numeric($value)) {
        throw new Exception('Enter a valid amount for every expense.');
    }

    $amount = (float)$value;

    if (!is_finite($amount) || $amount < 0) {
        throw new Exception('Expense amounts cannot be negative.');
    }

    return round($amount, 2);
}

/**
 * Load all rows belonging to a daily report.
 * Legacy reports are identified by legacy-{schedule_id}.
 */
function loadDailyReport($conn, $userId, $group, $forUpdate = false)
{
    if ($group === '') {
        return [];
    }

    if (strpos($group, 'legacy-') === 0) {
        $scheduleId = (int)substr($group, 7);

        if ($scheduleId <= 0) {
            return [];
        }

        $sql = "
            SELECT *
            FROM field_schedules
            WHERE schedule_id = ?
              AND user_id = ?
            LIMIT 1
        ";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $scheduleId, $userId);
    } else {
        $sql = "
            SELECT *
            FROM field_schedules
            WHERE report_group_id = ?
              AND user_id = ?
            ORDER BY schedule_id ASC
        ";

        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $group, $userId);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();

    return $rows;
}

$group = trim($_GET['group'] ?? $_POST['group'] ?? '');
$targetUserId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);

$errorMessage = '';
$successMessage = '';

$companies = [];
$report = null;

$totalLunch = 0.00;
$totalTransport = 0.00;
$totalAccommodation = 0.00;
$totalClaims = 0.00;

$paymentStatus = 'Pending';
$paymentComments = '';
$paymentStatuses = [];

try {
    if ($group === '' || $targetUserId <= 0) {
        throw new Exception('The selected field report is invalid.');
    }

    /*
     * Save expense amounts and payment status only.
     */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && ($_POST['action'] ?? '') === 'update_claim'
    ) {
        $newStatus = trim($_POST['payment_status'] ?? '');
        $newComments = trim($_POST['payment_comments'] ?? '');

        $allowedPaymentStatuses = ['Pending', 'Paid', 'Denied'];

        if (!in_array($newStatus, $allowedPaymentStatuses, true)) {
            throw new Exception('Select a valid payment status.');
        }

        if (strlen($newComments) > 2000) {
            throw new Exception('Payment comments must not exceed 2,000 characters.');
        }

        $lunch = moneyValue($_POST['lunch_claim'] ?? '');
        $transport = moneyValue($_POST['transport_claim'] ?? '');
        $accommodation = moneyValue($_POST['accommodation_claim'] ?? '');

        $conn->begin_transaction();

        try {
            /*
             * Lock and verify the selected report.
             */
            $existingRows = loadDailyReport(
                $conn,
                $targetUserId,
                $group,
                true
            );

            if (empty($existingRows)) {
                throw new Exception('Report not found or access denied.');
            }

            /*
             * Convert a legacy report to a new group ID.
             */
            if (strpos($group, 'legacy-') === 0) {
                $legacyId = (int)substr($group, 7);
                $newGroupId = bin2hex(random_bytes(16));

                $stmt = $conn->prepare("
                    UPDATE field_schedules
                    SET report_group_id = ?
                    WHERE schedule_id = ?
                      AND user_id = ?
                ");

                $stmt->bind_param(
                    "sii",
                    $newGroupId,
                    $legacyId,
                    $targetUserId
                );

                $stmt->execute();
                $stmt->close();

                $group = $newGroupId;
                $claimHolderId = $legacyId;
            } else {
                $claimHolderId = (int)$existingRows[0]['schedule_id'];
            }

            /*
             * Clear the daily claims from all rows in this group.
             */
            $stmt = $conn->prepare("
                UPDATE field_schedules
                SET lunch_claim = 0,
                    transport_claim = 0,
                    accommodation_claim = 0
                WHERE user_id = ?
                  AND report_group_id = ?
            ");

            $stmt->bind_param(
                "is",
                $targetUserId,
                $group
            );

            $stmt->execute();
            $stmt->close();

            /*
             * Store the daily claims on the first row only.
             *
             * Six placeholders:
             * 3 decimal amounts, schedule ID, user ID, group ID.
             * Types: d d d i i s = dddiis
             */
            $stmt = $conn->prepare("
                UPDATE field_schedules
                SET lunch_claim = ?,
                    transport_claim = ?,
                    accommodation_claim = ?
                WHERE schedule_id = ?
                  AND user_id = ?
                  AND report_group_id = ?
            ");

            $stmt->bind_param(
                "dddiis",
                $lunch,
                $transport,
                $accommodation,
                $claimHolderId,
                $targetUserId,
                $group
            );

            $stmt->execute();

            /*
             * Verify the claim row exists if MySQL reports no changed rows.
             */
            $stmt->close();

            $check = $conn->prepare("
                SELECT schedule_id
                FROM field_schedules
                WHERE schedule_id = ?
                  AND user_id = ?
                  AND report_group_id = ?
                LIMIT 1
            ");

            $check->bind_param(
                "iis",
                $claimHolderId,
                $targetUserId,
                $group
            );

            $check->execute();
            $checkResult = $check->get_result();
            $exists = $checkResult->num_rows > 0;
            $check->close();

            if (!$exists) {
                throw new Exception('Could not save the expense amounts.');
            }

            /*
             * Update payment status and comments for every row.
             */
            if ($newStatus === 'Pending') {
                $stmt = $conn->prepare("
                    UPDATE field_schedules
                    SET payment_status = ?,
                        payment_comments = ?,
                        reviewed_by = NULL,
                        reviewed_at = NULL
                    WHERE user_id = ?
                      AND report_group_id = ?
                ");

                // 4 placeholders: string, string, integer, string.
                $stmt->bind_param(
                    "ssis",
                    $newStatus,
                    $newComments,
                    $targetUserId,
                    $group
                );
            } else {
                $stmt = $conn->prepare("
                    UPDATE field_schedules
                    SET payment_status = ?,
                        payment_comments = ?,
                        reviewed_by = ?,
                        reviewed_at = NOW()
                    WHERE user_id = ?
                      AND report_group_id = ?
                ");

                // 5 placeholders: string, string, integer, integer, string.
                $stmt->bind_param(
                    "ssiis",
                    $newStatus,
                    $newComments,
                    $reviewerId,
                    $targetUserId,
                    $group
                );
            }

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            header(
                'Location: admin_edit_field_report.php?group=' .
                urlencode($group) .
                '&user_id=' . $targetUserId .
                '&success=updated'
            );
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /*
     * Load the report for display.
     */
    $companies = loadDailyReport(
        $conn,
        $targetUserId,
        $group
    );

    if (empty($companies)) {
        throw new Exception('Report not found or access denied.');
    }

    $report = $companies[0];

    foreach ($companies as $company) {
        $totalLunch += (float)($company['lunch_claim'] ?? 0);
        $totalTransport += (float)($company['transport_claim'] ?? 0);
        $totalAccommodation += (float)($company['accommodation_claim'] ?? 0);

        $status = trim($company['payment_status'] ?? 'Pending');

        if ($status === '') {
            $status = 'Pending';
        }

        $paymentStatuses[] = $status;
    }

    $paymentStatuses = array_values(array_unique($paymentStatuses));
    $paymentStatus = $paymentStatuses[0] ?? 'Pending';
    $paymentComments = $report['payment_comments'] ?? '';

    $totalClaims = $totalLunch + $totalTransport + $totalAccommodation;

    if (isset($_GET['success']) && $_GET['success'] === 'updated') {
        $successMessage = 'Expense amounts and payment status were updated successfully.';
    }

} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Field Claim Payment</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"
    >
</head>

<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 max-w-5xl mx-auto p-6">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Edit Field Claim Payment
            </h1>
            <p class="text-gray-600">
                Update expense amounts and payment status only.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a
                href="admin_view_field_report.php?group=<?= urlencode($group) ?>&user_id=<?= $targetUserId ?>"
                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded"
            >
                Back to Report
            </a>

            <a
                href="admin_field_schedule_report.php"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded"
            >
                All Field Reports
            </a>
        </div>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="bg-red-100 border border-red-300 text-red-800 p-4 rounded mb-5">
            <?= h($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?>
        <div class="bg-green-100 border border-green-300 text-green-800 p-4 rounded mb-5">
            <?= h($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($report): ?>

        <?php
        $employeeName = trim(
            ($report['first_name'] ?? '') . ' ' .
            ($report['last_name'] ?? '')
        );
        ?>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                Report Summary
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Visit Date</p>
                    <p class="font-semibold">
                        <?= h($report['visit_date']) ?>
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Visit Purpose</p>
                    <p class="font-semibold">
                        <?= h($report['visit_purpose']) ?>
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Companies in This Report</p>
                    <p class="font-semibold">
                        <?= count($companies) ?>
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500">Current Payment Status</p>

                    <?php if (count($paymentStatuses) > 1): ?>
                        <span class="inline-block bg-yellow-100 text-yellow-800 px-3 py-1 rounded">
                            Mixed statuses
                        </span>
                    <?php else: ?>
                        <?php
                        $badgeClass = 'bg-yellow-100 text-yellow-800';

                        if ($paymentStatus === 'Paid') {
                            $badgeClass = 'bg-green-100 text-green-800';
                        } elseif ($paymentStatus === 'Denied') {
                            $badgeClass = 'bg-red-100 text-red-800';
                        }
                        ?>

                        <span class="inline-block <?= $badgeClass ?> px-3 py-1 rounded">
                            <?= h($paymentStatus) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form
            method="POST"
            id="claimForm"
            class="bg-white rounded-lg shadow p-6 space-y-6"
        >
            <input type="hidden" name="action" value="update_claim">
            <input type="hidden" name="group" value="<?= h($group) ?>">
            <input type="hidden" name="user_id" value="<?= $targetUserId ?>">

            <div>
                <h2 class="text-xl font-semibold text-gray-800">
                    Daily Expense Amounts
                </h2>

                <p class="text-sm text-gray-600 mt-1">
                    These amounts apply to the entire daily report, not to each company separately.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">
                        Lunch (Ksh)
                    </label>

                    <input
                        type="number"
                        name="lunch_claim"
                        min="0"
                        step="0.01"
                        required
                        value="<?= h(number_format($totalLunch, 2, '.', '')) ?>"
                        class="w-full border rounded px-3 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Transport (Ksh)
                    </label>

                    <input
                        type="number"
                        name="transport_claim"
                        min="0"
                        step="0.01"
                        required
                        value="<?= h(number_format($totalTransport, 2, '.', '')) ?>"
                        class="w-full border rounded px-3 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Accommodation (Ksh)
                    </label>

                    <input
                        type="number"
                        name="accommodation_claim"
                        min="0"
                        step="0.01"
                        required
                        value="<?= h(number_format($totalAccommodation, 2, '.', '')) ?>"
                        class="w-full border rounded px-3 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400"
                    >
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-800">Total Daily Claim</p>

                <p class="text-2xl font-bold text-blue-900 mt-1">
                    Ksh <span id="totalClaim"><?= number_format($totalClaims, 2) ?></span>
                </p>
            </div>

            <div class="border-t pt-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    Payment Review
                </h2>

                <div class="mb-5">
                    <label class="block text-sm font-medium mb-1">
                        Payment Status *
                    </label>

                    <select
                        name="payment_status"
                        id="paymentStatus"
                        required
                        class="w-full border rounded px-3 py-3 bg-white"
                    >
                        <option value="Pending"
                            <?= $paymentStatus === 'Pending' ? 'selected' : '' ?>>
                            Pending
                        </option>

                        <option value="Paid"
                            <?= $paymentStatus === 'Paid' ? 'selected' : '' ?>>
                            Paid
                        </option>

                        <option value="Denied"
                            <?= $paymentStatus === 'Denied' ? 'selected' : '' ?>>
                            Denied
                        </option>
                    </select>

                    <?php if (count($paymentStatuses) > 1): ?>
                        <p class="text-sm text-orange-700 mt-2">
                            The company rows currently have different payment statuses.
                            Select the status you want to apply to the entire daily report.
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Payment Comments / Reason
                    </label>

                    <textarea
                        name="payment_comments"
                        id="paymentComments"
                        rows="4"
                        maxlength="2000"
                        placeholder="Add a payment note or explain why a claim was denied..."
                        class="w-full border rounded px-3 py-3"
                    ><?= h($paymentComments) ?></textarea>

                    <p class="text-xs text-gray-500 mt-1">
                        This comment will be saved against all company rows in the daily report.
                    </p>
                </div>
            </div>

            <div class="border-t pt-5 flex flex-wrap gap-3">
                <button
                    type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded font-semibold"
                >
                    Save Amounts and Status
                </button>

                <a
                    href="admin_view_field_report.php?group=<?= urlencode($group) ?>&user_id=<?= $targetUserId ?>"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded"
                >
                    Cancel
                </a>
            </div>
        </form>

    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('claimForm');

    if (!form) return;

    const lunchInput = form.querySelector('[name="lunch_claim"]');
    const transportInput = form.querySelector('[name="transport_claim"]');
    const accommodationInput = form.querySelector('[name="accommodation_claim"]');
    const totalDisplay = document.getElementById('totalClaim');
    const statusSelect = document.getElementById('paymentStatus');
    const commentsInput = document.getElementById('paymentComments');

    function updateTotal() {
        const lunch = Math.max(0, parseFloat(lunchInput.value) || 0);
        const transport = Math.max(0, parseFloat(transportInput.value) || 0);
        const accommodation = Math.max(0, parseFloat(accommodationInput.value) || 0);

        totalDisplay.textContent = (lunch + transport + accommodation).toLocaleString(
            'en-KE',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );
    }

    [lunchInput, transportInput, accommodationInput].forEach(function (input) {
        input.addEventListener('input', updateTotal);
    });

    form.addEventListener('submit', function (event) {
        const status = statusSelect.value;

        if (status === 'Denied' && commentsInput.value.trim() === '') {
            const proceed = confirm(
                'You selected Denied without entering a reason. Do you want to continue?'
            );

            if (!proceed) {
                event.preventDefault();
                commentsInput.focus();
                return;
            }
        }

        const message = 'Save the expense amounts and set payment status to "' +
            status + '" for all companies in this daily report?';

        if (!confirm(message)) {
            event.preventDefault();
        }
    });

    updateTotal();
});
</script>

</body>
</html>