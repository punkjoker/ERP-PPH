<?php
session_start();
require 'db_con.php';

// ✅ Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ✅ Fetch payroll records for this user
$stmt = $conn->prepare("
    SELECT id, month, year, base_salary, total_allowances, total_deductions, net_pay
    FROM payroll_records
    WHERE user_id = ?
    ORDER BY year DESC, FIELD(month,
        'January','February','March','April','May','June','July','August','September','October','November','December'
    )
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Payrolls</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-5xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold text-blue-700 mb-4">My Payroll History</h2>

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
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-2 px-4"><?php echo $row['month'] . ' ' . $row['year']; ?></td>
                    <td class="py-2 px-4"><?php echo number_format($row['base_salary'], 2); ?></td>
                    <td class="py-2 px-4 text-green-700 font-semibold"><?php echo number_format($row['total_allowances'], 2); ?></td>
                    <td class="py-2 px-4 text-red-700 font-semibold"><?php echo number_format($row['total_deductions'], 2); ?></td>
                    <td class="py-2 px-4 font-bold text-gray-800"><?php echo number_format($row['net_pay'], 2); ?></td>
                    <td class="py-2 px-4 text-center">
                        <a href="view_my_salary.php?id=<?php echo $row['id']; ?>" class="bg-blue-600 text-white py-1 px-3 rounded hover:bg-blue-700 transition">
                            View
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-gray-600">No payroll records found.</p>
        <?php endif; ?>
    </div>
</body>
</html>
