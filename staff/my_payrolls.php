<?php
session_start();
require 'db_con.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle filter submission
$filter_month = $_GET['month'] ?? '';
$filter_year = $_GET['year'] ?? '';

// Prepare query with optional filtering
$query = "
    SELECT payroll_id, month, year, base_salary, total_allowances, total_deductions, net_pay
    FROM payroll_records
    WHERE user_id = ?
";

$params = [$user_id];
$types = "i";

// Apply filters if set
if ($filter_month !== '') {
    $query .= " AND month = ?";
    $params[] = $filter_month;
    $types .= "s";
}
if ($filter_year !== '') {
    $query .= " AND year = ?";
    $params[] = $filter_year;
    $types .= "i";
}

// Order by year descending and month order
$query .= " ORDER BY year DESC, FIELD(UPPER(month),
    'JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE','JULY','AUGUST','SEPTEMBER','October','NOVEMBER','DECEMBER')";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Fetch distinct months and years for filter dropdown
$months_result = $conn->query("SELECT DISTINCT month FROM payroll_records WHERE user_id = '$user_id' ORDER BY FIELD(UPPER(month),
    'JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE','JULY','AUGUST','SEPTEMBER','October','NOVEMBER','DECEMBER')");
$years_result = $conn->query("SELECT DISTINCT year FROM payroll_records WHERE user_id = '$user_id' ORDER BY year DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Payrolls</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-4xl mx-auto">
        <h2 class="text-2xl font-bold text-blue-700 mb-4">My Payroll History</h2>

        <!-- Filter Form -->
        <form method="GET" class="flex gap-4 mb-6">
            <select name="month" class="border p-2 rounded">
                <option value="">All Months</option>
                <?php while ($month = $months_result->fetch_assoc()): ?>
                    <option value="<?php echo $month['month']; ?>" <?php if($filter_month == $month['month']) echo 'selected'; ?>>
                        <?php echo $month['month']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="year" class="border p-2 rounded">
                <option value="">All Years</option>
                <?php while ($year = $years_result->fetch_assoc()): ?>
                    <option value="<?php echo $year['year']; ?>" <?php if($filter_year == $year['year']) echo 'selected'; ?>>
                        <?php echo $year['year']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">Filter</button>
        </form>

        <?php if ($result->num_rows > 0): ?>
            <table class="min-w-full border border-gray-300">
                <thead class="bg-blue-600 text-white">
                    <tr>
                        <th class="py-2 px-4 text-left">Month</th>
                        <th class="py-2 px-4 text-left">Base Salary (Ksh)</th>
                        <th class="py-2 px-4 text-left">Allowances (Ksh)</th>
                        <th class="py-2 px-4 text-left">Deductions (Ksh)</th>
                        <th class="py-2 px-4 text-left">Net Pay (Ksh)</th>
                        <th class="py-2 px-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="border-b hover:bg-gray-50 transition">
                            <td class="py-2 px-4 font-semibold"><?php echo htmlspecialchars($row['month'] . ' ' . $row['year']); ?></td>
                            <td class="py-2 px-4"><?php echo number_format($row['base_salary'], 2); ?></td>
                            <td class="py-2 px-4 text-green-700 font-semibold"><?php echo number_format($row['total_allowances'], 2); ?></td>
                            <td class="py-2 px-4 text-red-700 font-semibold"><?php echo number_format($row['total_deductions'], 2); ?></td>
                            <td class="py-2 px-4 font-bold text-gray-800"><?php echo number_format($row['net_pay'], 2); ?></td>
                            <td class="py-2 px-4 text-center">
                                <a href="view_my_salary.php?id=<?php echo $row['payroll_id']; ?>" 
                                   class="bg-blue-600 text-white py-1 px-3 rounded hover:bg-blue-700 transition">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-gray-600 text-center">No payroll records found for your account.</p>
        <?php endif; ?>
    </div>
</body>
</html>
