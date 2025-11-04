<?php
include 'db_con.php';
require('fpdf.php');
include 'navbar.php';

// Get training ID
$training_id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT t.*, e.first_name, e.last_name, e.department, e.position 
                        FROM trainings t 
                        JOIN employees e ON t.employee_id = e.employee_id 
                        WHERE t.training_id = ?");
$stmt->bind_param("i", $training_id);
$stmt->execute();
$result = $stmt->get_result();
$training = $result->fetch_assoc();
$stmt->close();

if (!$training) die("Training record not found.");

// Handle PDF download
if (isset($_GET['download_pdf'])) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont("Arial", "B", 16);
    $pdf->Cell(0, 10, "Training Report", 0, 1, 'C');
    $pdf->SetFont("Arial", "", 12);
    $pdf->Ln(5);

    $pdf->Cell(50, 10, "Employee:", 0, 0);
    $pdf->Cell(0, 10, $training['first_name'] . " " . $training['last_name'], 0, 1);

    $pdf->Cell(50, 10, "Department:", 0, 0);
    $pdf->Cell(0, 10, $training['department'], 0, 1);

    $pdf->Cell(50, 10, "Position:", 0, 0);
    $pdf->Cell(0, 10, $training['position'], 0, 1);

    $pdf->Cell(50, 10, "Training Name:", 0, 0);
    $pdf->Cell(0, 10, $training['training_name'], 0, 1);

    $pdf->Cell(50, 10, "Training Date:", 0, 0);
    $pdf->Cell(0, 10, $training['training_date'], 0, 1);

    $pdf->Cell(50, 10, "Status:", 0, 0);
    $pdf->Cell(0, 10, $training['status'], 0, 1);

    $pdf->Cell(50, 10, "Done By:", 0, 0);
    $pdf->Cell(0, 10, $training['done_by'], 0, 1);

    $pdf->Cell(50, 10, "Approved By:", 0, 0);
    $pdf->Cell(0, 10, $training['approved_by'], 0, 1);

    $pdf->Output("D", "Training_Report.pdf");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Training</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<div class="ml-64 p-6 max-w-5xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">View Training</h1>

    <div class="bg-white shadow rounded-lg p-6 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <p><span class="font-semibold">Employee:</span> <?= $training['first_name'] . " " . $training['last_name'] ?></p>
                <p><span class="font-semibold">Department:</span> <?= $training['department'] ?></p>
                <p><span class="font-semibold">Position:</span> <?= $training['position'] ?></p>
            </div>
            <div>
                <p><span class="font-semibold">Training Name:</span> <?= $training['training_name'] ?></p>
                <p><span class="font-semibold">Training Date:</span> <?= $training['training_date'] ?></p>
                <p>
                    <span class="font-semibold">Status:</span>
                    <?php if ($training['status'] == 'Done'): ?>
                        <span class="bg-green-100 text-green-700 px-2 py-1 rounded">Done</span>
                    <?php else: ?>
                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded">Pending</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="mb-4">
            <p><span class="font-semibold">Done By:</span> <?= $training['done_by'] ?></p>
            <p><span class="font-semibold">Approved By:</span> <?= $training['approved_by'] ?></p>
        </div>

        <div class="flex gap-2">
            <a href="record_training.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Back</a>
            <a href="?id=<?= $training['training_id'] ?>&download_pdf=1" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">Download PDF</a>
        </div>
    </div>
</div>

</body>
</html>
