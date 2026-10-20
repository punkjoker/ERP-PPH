
<?php
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// Selected month
$currentMonth = date('Y-m');
$selectedMonth = $_GET['month'] ?? $currentMonth;

// Validate month
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = $currentMonth;
}

$monthName = date('F Y', strtotime($selectedMonth . '-01'));

// Search input
$search = trim($_GET['search'] ?? '');

// Fetch active employees with linked staff accounts
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
        g.group_name
    FROM users u
    INNER JOIN groups g
        ON g.group_id = u.group_id
    INNER JOIN employees e
        ON e.employee_id = u.employee_id
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
        "ssss",
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );
}

$stmt->execute();
$result = $stmt->get_result();
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

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <h1 class="text-2xl font-bold text-gray-700 mb-6">
        Payroll List - Staff
    </h1>

    <!-- Month Filter and Search -->
    <div class="bg-white shadow-sm rounded-lg p-4 mb-6">

        <form method="GET" action="payroll_list.php"
              class="flex flex-col lg:flex-row lg:items-end
                     justify-between gap-4">

            <div>
                <label for="monthFilter"
                       class="block font-medium text-gray-700 mb-2">
                    Select Month
                </label>

                <input
                    type="month"
                    id="monthFilter"
                    name="month"
                    value="<?= htmlspecialchars(
                        $selectedMonth,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
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
                    value="<?= htmlspecialchars(
                        $search,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="Name, employee ID, email or National ID"
                    class="border border-gray-300 px-4 py-2 rounded-lg w-full"
                >
            </div>

            <div class="flex flex-wrap gap-2">

                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white
                           font-semibold px-5 py-2 rounded-lg"
                >
                    🔍 Search
                </button>

                <a
                    href="payroll_list.php"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-700
                           font-semibold px-5 py-2 rounded-lg text-center"
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

    <!-- Payroll Table -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">

        <table class="min-w-full table-auto">

            <thead class="bg-blue-500 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Employee ID</th>
                    <th class="px-4 py-3 text-left">Full Name</th>
                    <th class="px-4 py-3 text-left">Email</th>
                    <th class="px-4 py-3 text-left">National ID</th>
                    <th class="px-4 py-3 text-left">Employee Status</th>
                    <th class="px-4 py-3 text-left">Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php $i = 1; ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <?php
                    $userId = (int)$row['user_id'];
                    $employeeId = (int)$row['employee_id'];

                    $fullName = trim(
                        ($row['first_name'] ?? '') . ' ' .
                        ($row['last_name'] ?? '')
                    );

                    $displayName = htmlspecialchars(
                        $fullName,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $email = htmlspecialchars(
                        $row['email'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $nationalId = htmlspecialchars(
                        $row['national_id'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $monthParam = rawurlencode($selectedMonth);
                    ?>

                    <tr class="border-b hover:bg-gray-50">

                        <td class="px-4 py-3">
                            <?= $i ?>
                        </td>

                        <td class="px-4 py-3">
                            <?= $employeeId ?>
                        </td>

                        <td class="px-4 py-3 font-medium">
                            <?= $displayName ?>
                        </td>

                        <td class="px-4 py-3">
                            <?= $email ?>
                        </td>

                        <td class="px-4 py-3">
                            <?= $nationalId ?>
                        </td>

                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-sm
                                         font-medium bg-green-100 text-green-700">
                                Active
                            </span>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">

                            <a
                                href="process_payroll.php?id=<?= $userId ?>&month=<?= $monthParam ?>"
                                class="inline-block bg-green-600 hover:bg-green-700
                                       text-white px-3 py-2 rounded-lg text-sm mb-1"
                            >
                                Process Payroll
                            </a>

                            <a
                                href="view_payroll.php?id=<?= $userId ?>&month=<?= $monthParam ?>"
                                class="inline-block bg-blue-500 hover:bg-blue-600
                                       text-white px-3 py-2 rounded-lg text-sm mb-1"
                            >
                                View Payroll
                            </a>

                        </td>

                    </tr>

                    <?php $i++; ?>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="7" class="text-center py-8 text-gray-500">
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

    <p class="text-sm text-gray-500 mt-3">
        <?= $result->num_rows ?> active employee(s) found.
    </p>

</div>

</body>
</html>
