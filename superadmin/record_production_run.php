<?php
include 'db_con.php';

// Date filter
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : '';

$query = "SELECT bom.id, bom.bom_date, bom.requested_by, bom.description, bom.status AS bom_status, 
                 p.name AS product_name
          FROM bill_of_materials bom
          JOIN products p ON bom.product_id = p.id
          WHERE bom.status = 'Approved'";

if ($from_date && $to_date) {
    $query .= " AND bom.bom_date BETWEEN '$from_date' AND '$to_date'";
}

$query .= " ORDER BY bom.bom_date DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Production Runs</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

    <!-- Navbar -->
    <?php include 'navbar.php'; ?>

    <!-- Page Content -->
    <div class="p-6 ml-64"> <!-- ml-64 pushes content right to avoid sidebar overlap -->
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-bold mb-4">Production Runs</h2>

            <!-- Filter Form -->
            <form method="GET" class="flex space-x-4 mb-6">
                <div>
                    <label class="block font-medium">From Date</label>
                    <input type="date" name="from_date" value="<?php echo $from_date; ?>" class="border p-2 rounded w-full">
                </div>
                <div>
                    <label class="block font-medium">To Date</label>
                    <input type="date" name="to_date" value="<?php echo $to_date; ?>" class="border p-2 rounded w-full">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
                </div>
            </form>

            <!-- Table -->
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-2 px-4 border">Date</th>
                        <th class="py-2 px-4 border">Product Name</th>
                        <th class="py-2 px-4 border">Requested By</th>
                        <th class="py-2 px-4 border">Description</th>
                        <th class="py-2 px-4 border">Production Status</th>
                        <th class="py-2 px-4 border">QC Status</th>
                        <th class="py-2 px-4 border">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="text-center">
                            <td class="py-2 px-4 border"><?php echo $row['bom_date']; ?></td>
                            <td class="py-2 px-4 border"><?php echo $row['product_name']; ?></td>
                            <td class="py-2 px-4 border"><?php echo $row['requested_by']; ?></td>
                            <td class="py-2 px-4 border"><?php echo $row['description']; ?></td>
                            <td class="py-2 px-4 border text-yellow-600 font-semibold">In Production</td>
                            <td class="py-2 px-4 border text-red-600 font-semibold">Pending</td>
                            <td class="py-2 px-4 border space-x-2">
                               <a href="update_production.php?id=<?php echo $row['id']; ?>" 
   class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">Update</a>
                
                                <a href="view_production.php?id=<?php echo $row['id']; ?>" 
                                   class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Back Button -->
            
        </div>
    </div>

</body>
</html>
