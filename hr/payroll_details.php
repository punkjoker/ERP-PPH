
<?php
require 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

// Search input
$search = trim($_GET['search'] ?? '');

// Fetch staff, with optional search
$query = "
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.national_id,
        u.status,
        g.group_name
    FROM users u
    INNER JOIN groups g ON u.group_id = g.group_id
    WHERE LOWER(TRIM(g.group_name)) = 'staff'
";

if ($search !== '') {
    $query .= "
        AND (
            u.full_name LIKE ?
            OR u.email LIKE ?
            OR u.national_id LIKE ?
        )
    ";
}

$query .= " ORDER BY u.full_name ASC";

$stmt = $conn->prepare($query);

if ($search !== '') {
    $searchTerm = '%' . $search . '%';
    $stmt->bind_param(
        "sss",
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

    <title>Payroll Details - Staff</title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css"
    >
</head>

<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <h1 class="text-2xl font-bold text-gray-700 mb-6">
        Payroll Details - Staff
    </h1>

    <!-- Search Form -->
    <form method="GET"
          action="payroll_details.php"
          class="bg-white shadow-sm rounded-lg p-4 mb-6">

        <div class="flex flex-col sm:flex-row gap-3">

            <input
                type="search"
                name="search"
                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="Search by name, email or National ID..."
                class="flex-1 border border-gray-300 rounded-lg px-4 py-3
                       focus:outline-none focus:ring-2 focus:ring-blue-500"
            >

            <button
                type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white
                       font-semibold px-6 py-3 rounded-lg"
            >
                🔍 Search
            </button>

            <?php if ($search !== ''): ?>
                <a
                    href="payroll_details.php"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-700
                           font-semibold px-6 py-3 rounded-lg text-center"
                >
                    Clear
                </a>
            <?php endif; ?>

        </div>
    </form>

    <!-- Staff Table -->
    <div class="bg-white shadow-md rounded-lg overflow-x-auto">

        <table class="min-w-full table-auto">

            <thead class="bg-blue-500 text-white">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Full Name</th>
                    <th class="px-4 py-3 text-left">Email</th>
                    <th class="px-4 py-3 text-left">National ID</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php $i = 1; ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <?php
                    $status = strtolower(trim($row['status'] ?? ''));

                    $statusColor = ($status === 'active')
                        ? 'bg-green-100 text-green-700'
                        : 'bg-red-100 text-red-700';
                    ?>

                    <tr class="border-b hover:bg-gray-50">

                        <td class="px-4 py-3">
                            <?= $i ?>
                        </td>

                        <td class="px-4 py-3 font-medium">
                            <?= htmlspecialchars(
                                $row['full_name'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td class="px-4 py-3">
                            <?= htmlspecialchars(
                                $row['email'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td class="px-4 py-3">
                            <?= htmlspecialchars(
                                $row['national_id'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-sm
                                         font-medium <?= $statusColor ?>">
                                <?= htmlspecialchars(
                                    $row['status'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">

                            <a
                                href="update_salary.php?id=<?= (int)$row['user_id'] ?>"
                                class="inline-block bg-yellow-400 hover:bg-yellow-500
                                       text-white px-3 py-2 rounded-lg text-sm mb-1"
                            >
                                Update Salary
                            </a>

                            <a
                                href="view_salary.php?id=<?= (int)$row['user_id'] ?>"
                                class="inline-block bg-blue-500 hover:bg-blue-600
                                       text-white px-3 py-2 rounded-lg text-sm mb-1"
                            >
                                View Salary
                            </a>

                        </td>

                    </tr>

                    <?php $i++; ?>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6"
                        class="text-center py-8 text-gray-500">
                        <?= $search !== ''
                            ? 'No staff matched your search.'
                            : 'No staff found.' ?>
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

    </div>

    <p class="text-sm text-gray-500 mt-3">
        <?= $result->num_rows ?> staff account(s) found.
    </p>

</div>

</body>
</html>