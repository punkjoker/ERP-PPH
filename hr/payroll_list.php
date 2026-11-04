
<?php
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// Selected month
$currentMonth = date('Y-m');
$selectedMonth = $_GET['month'] ?? $currentMonth;

// Validate selected month
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = $currentMonth;
}

$monthDate = DateTime::createFromFormat('!Y-m', $selectedMonth);
$monthName = $monthDate->format('F Y');
$payrollMonth = $monthDate->format('F');
$payrollYear = (int) $monthDate->format('Y');

$search = trim($_GET['search'] ?? '');

// Fetch active staff and payroll statuses
$query = "
    SELECT
        u.user_id,
        u.employee_id,
        u.full_name AS account_name,
        u.email,
        u.national_id,
        u.status AS user_status,
        e.status AS employee_status,
        e.first_name,
        e.last_name,

        COALESCE(pp.preparation_status, 'Pending')
            AS preparation_status,

        COALESCE(pp.approval_status, 'Pending')
            AS approval_status,

        COALESCE(pp.payment_status, 'Pending Pay')
            AS payment_status

    FROM users u

    INNER JOIN groups g
        ON g.group_id = u.group_id

    INNER JOIN employees e
        ON e.employee_id = u.employee_id

    LEFT JOIN payroll_records pp
        ON pp.user_id = u.user_id
        AND pp.month = ?
        AND pp.year = ?

    WHERE LOWER(TRIM(g.group_name)) = 'staff'
      AND LOWER(TRIM(e.status)) = 'active'
";

if ($search !== '') {
    $query .= "
        AND (
            CONCAT_WS(' ', TRIM(e.first_name), TRIM(e.last_name)) LIKE ?
            OR CAST(e.employee_id AS CHAR) LIKE ?
            OR u.email LIKE ?
            OR u.national_id LIKE ?
        )
    ";
}

$query .= " ORDER BY e.first_name ASC, e.last_name ASC";

$stmt = $conn->prepare($query);

if ($search !== '') {
    $searchTerm = '%' . $search . '%';

    $stmt->bind_param(
        "sissss",
        $payrollMonth,
        $payrollYear,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );
} else {
    $stmt->bind_param("si", $payrollMonth, $payrollYear);
}

$stmt->execute();
$result = $stmt->get_result();

// Status badge helper
function statusBadge($status, $type)
{
    $status = trim((string) $status);
    $classes = 'bg-gray-100 text-gray-700';

    if ($type === 'preparation') {
        if ($status === 'Prepared') {
            $classes = 'bg-green-100 text-green-700';
        } elseif ($status === 'Pending') {
            $classes = 'bg-yellow-100 text-yellow-700';
        }
    } elseif ($type === 'approval') {
        if ($status === 'Approved') {
            $classes = 'bg-green-100 text-green-700';
        } elseif ($status === 'Pending') {
            $classes = 'bg-yellow-100 text-yellow-700';
        }
    } elseif ($type === 'payment') {
        if ($status === 'Paid') {
            $classes = 'bg-green-100 text-green-700';
        } elseif ($status === 'Pending Pay') {
            $classes = 'bg-red-100 text-red-700';
        }
    }

    return '<span class="inline-block px-2 py-1 rounded-full text-xs font-semibold '
        . $classes . '">'
        . htmlspecialchars($status, ENT_QUOTES, 'UTF-8')
        . '</span>';
}

$bulkMessage = $_GET['bulk_message'] ?? '';
$bulkType = $_GET['bulk_type'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payroll List - Staff</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"
    >
</head>

<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-full mx-auto">

    <h1 class="text-2xl font-bold text-gray-700 mb-6">
        Payroll List - Staff
    </h1>

    <?php if ($bulkMessage !== ''): ?>
        <div class="mb-5 p-4 rounded-lg
            <?= $bulkType === 'success'
                ? 'bg-green-100 text-green-800'
                : 'bg-red-100 text-red-800' ?>">
            <?= htmlspecialchars($bulkMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Month Filter and Search -->
    <div class="bg-white shadow-sm rounded-lg p-4 mb-6">

        <form
            method="GET"
            action="payroll_list.php"
            class="flex flex-col lg:flex-row lg:items-end justify-between gap-4"
        >

            <div>
                <label for="monthFilter"
                       class="block font-medium text-gray-700 mb-2">
                    Select Month
                </label>

                <input
                    type="month"
                    id="monthFilter"
                    name="month"
                    value="<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>"
                    class="border border-gray-300 px-3 py-2 rounded-lg w-full"
                >
            </div>

            <div class="flex-1">
                <label for="search"
                       class="block font-medium text-gray-700 mb-2">
                    Search Employee
                </label>

                <input
                    type="search"
                    id="search"
                    name="search"
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Name, employee ID, email or National ID"
                    class="border border-gray-300 px-4 py-2 rounded-lg w-full"
                >
            </div>

            <div class="flex flex-wrap gap-2">

                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg"
                >
                    Search
                </button>

                <a
                    href="payroll_list.php"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-5 py-2 rounded-lg text-center"
                >
                    Clear
                </a>

            </div>
        </form>

        <div class="text-gray-600 mt-4">
            Showing payroll for:
            <span class="font-semibold text-blue-600">
                <?= htmlspecialchars($monthName, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

    </div>

    <!-- Bulk Workflow Form -->
    <form
        method="POST"
        action="bulk_update_payroll.php"
        id="bulkPayrollForm"
        onsubmit="return validateBulkAction();"
    >

        <input
            type="hidden"
            name="month"
            value="<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>"
        >

        <!-- Bulk Approval -->
        <div class="bg-white shadow-md rounded-lg p-5 mb-5">

            <h2 class="text-lg font-bold text-gray-700 mb-3">
                Bulk Payroll Approval
            </h2>

            <p class="text-sm text-gray-500 mb-4">
                Select prepared payroll records below, then enter the approval details.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Approved By
                    </label>

                    <input
                        type="text"
                        name="approved_by"
                        id="approved_by"
                        maxlength="150"
                        placeholder="Enter approver's name"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2"
                    >
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Approval Date
                    </label>

                    <input
                        type="date"
                        name="approval_date"
                        id="approval_date"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2"
                    >
                </div>

            </div>

            <button
                type="submit"
                name="operation"
                value="approve"
                class="mt-4 bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-2 rounded-lg"
            >
                Approve Selected Payroll
            </button>

        </div>

        <!-- Bulk Payment -->
        <div class="bg-white shadow-md rounded-lg p-5 mb-5">

            <h2 class="text-lg font-bold text-gray-700 mb-3">
                Bulk Payroll Payment
            </h2>

            <p class="text-sm text-gray-500 mb-4">
                Select approved payroll records below, then enter the payment details.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Paid By / Accountant Name
                    </label>

                    <input
                        type="text"
                        name="accountant_name"
                        id="accountant_name"
                        maxlength="150"
                        placeholder="Enter accountant's name"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2"
                    >
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Payment Date
                    </label>

                    <input
                        type="date"
                        name="payment_date"
                        id="payment_date"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2"
                    >
                </div>

            </div>

            <button
                type="submit"
                name="operation"
                value="pay"
                class="mt-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg"
            >
                Mark Selected Payroll as Paid
            </button>

        </div>

        <!-- Payroll Table -->
        <div class="bg-white shadow-md rounded-lg overflow-x-auto">

            <table class="min-w-full table-auto">

                <thead class="bg-blue-600 text-white">

                    <tr>
                        <th class="px-4 py-3 text-left">
                            <input
                                type="checkbox"
                                id="selectAll"
                                class="w-4 h-4"
                                title="Select all employees"
                            >
                        </th>

                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Full Name</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">National ID</th>
                        <th class="px-4 py-3 text-left">Employee Status</th>
                        <th class="px-4 py-3 text-left">Preparation Status</th>
                        <th class="px-4 py-3 text-left">Approval Status</th>
                        <th class="px-4 py-3 text-left">Payment Status</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php $i = 1; ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <?php
                        $userId = (int) $row['user_id'];

                        $fullName = trim(
                            ($row['first_name'] ?? '') . ' ' .
                            ($row['last_name'] ?? '')
                        );

                        $monthParam = rawurlencode($selectedMonth);

                        $hasPreparedPayroll =
                            $row['preparation_status'] === 'Prepared'
                            && $row['approval_status'] !== 'Approved'
                            && $row['payment_status'] !== 'Paid';

                        $hasApprovedPayroll =
                            $row['approval_status'] === 'Approved'
                            && $row['payment_status'] !== 'Paid';
                        ?>

                        <tr class="border-b hover:bg-gray-50">

                            <td class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    name="employee_ids[]"
                                    value="<?= $userId ?>"
                                    class="employee-checkbox w-4 h-4"
                                >
                            </td>

                            <td class="px-4 py-3"><?= $i ?></td>

                            <td class="px-4 py-3 font-medium">
                                <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td class="px-4 py-3">
                                <?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td class="px-4 py-3">
                                <?= htmlspecialchars($row['national_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                    Active
                                </span>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <?= statusBadge($row['preparation_status'], 'preparation') ?>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <?= statusBadge($row['approval_status'], 'approval') ?>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <?= statusBadge($row['payment_status'], 'payment') ?>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">

                                <a
                                    href="process_payroll.php?id=<?= $userId ?>&month=<?= $monthParam ?>"
                                    class="inline-block bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-sm mb-1"
                                >
                                    Process Payroll
                                </a>

                                <a
                                    href="view_payroll.php?id=<?= $userId ?>&month=<?= $monthParam ?>"
                                    class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg text-sm mb-1"
                                >
                                    View Payroll
                                </a>

                            </td>

                        </tr>

                        <?php $i++; ?>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="10" class="text-center py-8 text-gray-500">
                            <?php if ($search !== ''): ?>
                                No active staff matched your search.
                            <?php else: ?>
                                No staff accounts linked to active employees were found.
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>
            </table>

        </div>

    </form>

    <p class="text-sm text-gray-500 mt-3">
        <?= $result->num_rows ?> active employee(s) found.
    </p>

</div>

<script>
const selectAll = document.getElementById('selectAll');

selectAll.addEventListener('change', function () {
    document.querySelectorAll('.employee-checkbox').forEach(function (checkbox) {
        checkbox.checked = selectAll.checked;
    });
});

document.querySelectorAll('.employee-checkbox').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
        const all = document.querySelectorAll('.employee-checkbox');
        const checked = document.querySelectorAll('.employee-checkbox:checked');

        selectAll.checked = all.length > 0 && all.length === checked.length;
        selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
    });
});

function validateBulkAction() {
    const selected = document.querySelectorAll('.employee-checkbox:checked');

    if (selected.length === 0) {
        alert('Please select at least one employee.');
        return false;
    }

    const submitter = document.activeElement;
    const operation = submitter ? submitter.value : '';

    if (operation === 'approve') {
        const approver = document.getElementById('approved_by').value.trim();
        const approvalDate = document.getElementById('approval_date').value;

        if (!approver || !approvalDate) {
            alert('Please enter the approver name and approval date.');
            return false;
        }

        return confirm(
            'Approve payroll for ' + selected.length +
            ' selected employee(s)?'
        );
    }

    if (operation === 'pay') {
        const accountant = document.getElementById('accountant_name').value.trim();
        const paymentDate = document.getElementById('payment_date').value;

        if (!accountant || !paymentDate) {
            alert('Please enter the accountant name and payment date.');
            return false;
        }

        return confirm(
            'Mark payroll as paid for ' + selected.length +
            ' selected employee(s)?'
        );
    }

    return false;
}
</script>

</body>
</html>
