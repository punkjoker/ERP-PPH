<?php
include 'db_con.php';
require('fpdf.php');

$lunch_id = intval($_GET['id'] ?? 0);

// Fetch lunch expense
$stmt = $conn->prepare("SELECT * FROM lunch_expense WHERE lunch_id=?");
$stmt->bind_param("i",$lunch_id);
$stmt->execute();
$result = $stmt->get_result();
$expense = $result->fetch_assoc();
$stmt->close();

if(!$expense) die("Lunch expense not found.");

// Decode items JSON
$items = json_decode($expense['items'], true);

// Handle PDF download
if(isset($_GET['download_pdf'])){
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont("Arial","B",16);
    $pdf->Cell(0,10,"Lunch Expense Report",0,1,'C');
    $pdf->SetFont("Arial","",12);
    $pdf->Ln(5);
    $pdf->Cell(0,8,"Week No: ".$expense['week_no'],0,1);
    $pdf->Cell(0,8,"From: ".$expense['start_date']." To: ".$expense['end_date'],0,1);
    $pdf->Cell(0,8,"Items Bought By: ".$expense['items_bought_by'],0,1);
    $pdf->Cell(0,8,"Transport Cost: KES ".number_format($expense['transport_cost'],2),0,1);
    $pdf->Ln(5);

    $pdf->SetFont("Arial","B",12);
    $pdf->Cell(10,8,"#",1);
    $pdf->Cell(100,8,"Item",1);
    $pdf->Cell(40,8,"Cost",1,1);

    $pdf->SetFont("Arial","",12);
    $count=1;
    foreach($items as $it){
        $pdf->Cell(10,8,$count++,1);
        $pdf->Cell(100,8,$it['item'],1);
        $pdf->Cell(40,8,"KES ".number_format($it['cost'],2),1,1);
    }
    $pdf->Cell(110,8,"Transport Cost",1);
    $pdf->Cell(40,8,"KES ".number_format($expense['transport_cost'],2),1,1);
    $pdf->Cell(110,8,"Total Amount",1);
    $pdf->Cell(40,8,"KES ".number_format($expense['total_amount'],2),1,1);

    $pdf->Output("D","Lunch_Expense_Week".$expense['week_no'].".pdf");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Lunch Expense</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-5xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-blue-700">View Lunch Expense</h1>

    <div class="bg-white shadow rounded-lg p-6 mb-4">
        <div class="flex justify-between items-center mb-4">
            <div>
                <span class="font-semibold">Week No:</span> <?= $expense['week_no'] ?><br>
                <span class="font-semibold">From:</span> <?= $expense['start_date'] ?> 
                <span class="font-semibold">To:</span> <?= $expense['end_date'] ?><br>
                <span class="font-semibold">Items Bought By:</span> <?= htmlspecialchars($expense['items_bought_by']) ?>
            </div>
            <div>
                <span class="font-semibold">Transport Cost:</span> KES <?= number_format($expense['transport_cost'],2) ?><br>
                <span class="font-bold text-lg">Total Amount:</span> KES <?= number_format($expense['total_amount'],2) ?>
            </div>
        </div>

        <table class="w-full border border-gray-300 rounded mb-4">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border px-3 py-2">#</th>
                    <th class="border px-3 py-2">Item</th>
                    <th class="border px-3 py-2">Cost (KES)</th>
                </tr>
            </thead>
            <tbody>
                <?php $count=1; foreach($items as $it): ?>
                    <tr class="<?= ($count%2==0)?'bg-gray-50':'bg-white' ?>">
                        <td class="border px-3 py-2 text-center"><?= $count++ ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($it['item']) ?></td>
                        <td class="border px-3 py-2 text-right"><?= number_format($it['cost'],2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="bg-gray-100 font-semibold">
                    <td colspan="2" class="border px-3 py-2 text-right">Transport</td>
                    <td class="border px-3 py-2 text-right"><?= number_format($expense['transport_cost'],2) ?></td>
                </tr>
                <tr class="bg-gray-200 font-bold">
                    <td colspan="2" class="border px-3 py-2 text-right">Total Amount</td>
                    <td class="border px-3 py-2 text-right"><?= number_format($expense['total_amount'],2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="flex gap-2">
            <a href="add_lunch_expense.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">Back</a>
            <a href="?id=<?= $expense['lunch_id'] ?>&download_pdf=1" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">Download PDF</a>
        </div>
    </div>
</div>

</body>
</html>
