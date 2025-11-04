<?php
require 'db_con.php'; // adjust path if needed

// Fetch all users in 'staff' group
$query = "
    SELECT u.user_id, u.full_name, u.email, u.national_id, u.status, g.group_name
    FROM users u
    INNER JOIN groups g ON u.group_id = g.group_id
    WHERE g.group_name = 'staff'
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Details - Staff</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">Payroll Details - Staff</h1>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full table-auto">
            <thead class="bg-blue-500 text-white">
                <tr>
                    <th class="px-4 py-2 text-left">#</th>
                    <th class="px-4 py-2 text-left">Full Name</th>
                    <th class="px-4 py-2 text-left">Email</th>
                    <th class="px-4 py-2 text-left">National ID</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result && $result->num_rows > 0) {
                    $i = 1;
                    while ($row = $result->fetch_assoc()) {
                        $statusColor = ($row['status'] === 'active') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                        echo "<tr class='border-b hover:bg-gray-50'>
                                <td class='px-4 py-2'>{$i}</td>
                                <td class='px-4 py-2'>" . htmlspecialchars($row['full_name']) . "</td>
                                <td class='px-4 py-2'>" . htmlspecialchars($row['email']) . "</td>
                                <td class='px-4 py-2'>" . htmlspecialchars($row['national_id']) . "</td>
                                <td class='px-4 py-2'><span class='px-2 py-1 rounded-full text-sm font-medium {$statusColor}'>" . htmlspecialchars($row['status']) . "</span></td>
                                <td class='px-4 py-2'>
                                    <a href='update_salary.php?id={$row['user_id']}' class='bg-yellow-400 hover:bg-yellow-500 text-white px-3 py-1 rounded-lg text-sm'>Update Salary</a>
                                    <a href='view_salary.php?id={$row['user_id']}' class='bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-lg text-sm ml-2'>View Salary</a>
                                </td>
                              </tr>";
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='6' class='text-center py-4 text-gray-500'>No staff found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
