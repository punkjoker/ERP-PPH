<?php
session_start();
require 'db_con.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$payroll_id = intval($_GET['id'] ?? 0);

// ✅ Fetch payroll record
$stmt = $conn->prepare("
    SELECT pr.*, u.full_name, u.email, u.national_id
    FROM payroll_records pr
    INNER JOIN users u ON pr.user_id = u.user_id
    WHERE pr.id = ? AND pr.user_id = ?
");
$stmt->bind_param("ii", $payroll_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$payroll = $result->fetch_assoc();

if (!$payroll) {
    die("No payroll record found.");
}

// Decode allowances and deductions
$details = json_decode($payroll['details'], true);
$allowances = $details['allowances'] ?? [];
$deductions = $details['deductions'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Breakdown</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-lg">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-blue-700">Salary Breakdown - <?php echo $payroll['month'] . ' ' . $payroll['year']; ?></h2>
            <a href="my_payrolls.php" class="text-sm text-blue-500 hover:underline">← Back</a>
        </div>

        <p><strong>Name:</strong> <?php echo htmlspecialchars($payroll['full_name']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($payroll['email']); ?></p>
        <p><strong>National ID:</strong> <?php echo htmlspecialchars($payroll['national_id']); ?></p>

        <div class="mt-6">
            <h3 class="font-semibold text-lg mb-2 text-gray-800">Summary</h3>
            <table class="w-full border border-gray-300">
                <tr><td class="p-2">Base Salary</td><td class="p-2 text-right">Ksh <?php echo number_format($payroll['base_salary'], 2); ?></td></tr>
                <tr><td class="p-2">Total Allowances</td><td class="p-2 text-right text-green-700">Ksh <?php echo number_format($payroll['total_allowances'], 2); ?></td></tr>
                <tr><td class="p-2">Total Deductions</td><td class="p-2 text-right text-red-700">Ksh <?php echo number_format($payroll['total_deductions'], 2); ?></td></tr>
                <tr class="bg-gray-100 font-bold"><td class="p-2">Net Pay</td><td class="p-2 text-right">Ksh <?php echo number_format($payroll['net_pay'], 2); ?></td></tr>
            </table>
        </div>

        <div class="mt-6">
            <h3 class="font-semibold text-lg mb-2 text-gray-800">Allowances</h3>
            <?php if ($allowances): ?>
            <table class="w-full border border-gray-300">
                <?php foreach ($allowances as $a): ?>
                    <tr>
                        <td class="p-2"><?php echo htmlspecialchars($a['name']); ?></td>
                        <td class="p-2 text-right">Ksh <?php echo number_format(floatval(preg_replace('/[^0-9.\-]/', '', $a['amount'])), 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
                <p>No allowances available.</p>
            <?php endif; ?>
        </div>

        <div class="mt-6">
            <h3 class="font-semibold text-lg mb-2 text-gray-800">Deductions</h3>
            <?php if ($deductions): ?>
            <table class="w-full border border-gray-300">
                <?php foreach ($deductions as $d): 
                    $raw = $d['amount'];
                    $isPercentage = strpos($raw, '%') !== false;
                    $deductionAmount = $isPercentage
                        ? (floatval(preg_replace('/[^0-9.\-]/', '', $raw)) / 100) * $payroll['base_salary']
                        : floatval(preg_replace('/[^0-9.\-]/', '', $raw));
                ?>
                    <tr>
                        <td class="p-2"><?php echo htmlspecialchars($d['name']); ?> <?php echo $isPercentage ? '(' . $raw . ')' : ''; ?></td>
                        <td class="p-2 text-right text-red-700">Ksh <?php echo number_format($deductionAmount, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
                <p>No deductions available.</p>
            <?php endif; ?>
        </div>

        <div class="mt-6 text-right">
            <a href="generate_payslip.php?id=<?php echo $user_id; ?>&month=<?php echo $payroll['year'] . '-' . date('m', strtotime($payroll['month'])); ?>" 
               class="bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700 transition">
               Download Payslip (PDF)
            </a>
        </div>
    </div>
</body>
</html>
