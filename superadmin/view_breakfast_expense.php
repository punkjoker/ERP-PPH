<?php
include 'db_con.php';
require_once('fpdf.php');

$id = $_GET['id'] ?? 0;

// Fetch data
$stmt = $conn->prepare("SELECT * FROM breakfast_expense WHERE breakfast_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$expense = $result->fetch_assoc();
$stmt->close();

if (!$expense) {
    die("Breakfast expense not found.");
}

// Decode items JSON
$items = json_decode($expense['items'], true) ?? [];

// Handle PDF download
if (isset($_GET['download_pdf'])) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont("Arial","B",16);
    $pdf->Cell(0,10,"Breakfast Expense Report",0,1,'C');
    $pdf->SetFont("Arial","",12);
    $pdf->Ln(5);
    $pdf->Cell(0,8,"Date: ".$expense['expense_date'],0,1);
    $pdf->Cell(0,8,"Items Bought By: ".$expense['items_bought_by'],0,1);
    $pdf->Cell(0,8,"Transport Cost: KES ".number_format($expense['transport_cost'],2),0,1);
    $pdf->Cell(0,8,"Petty Cash No: ".$expense['petty_cash_no'],0,1);
    $pdf->Cell(0,8,"Approved By: ".$expense['approved_by'],0,1);
    $pdf->Cell(0,8,"Payment Status: ".$expense['payment_status'],0,1);
    $pdf->Ln(5);

    $pdf->SetFont("Arial","B",12);
    $pdf->Cell(10,8,"#",1);
    $pdf->Cell(100,8,"Item",1);
    $pdf->Cell(40,8,"Cost (KES)",1,1);

    $pdf->SetFont("Arial","",12);
    $count=1;
    foreach ($items as $it) {
        $pdf->Cell(10,8,$count++,1);
        $pdf->Cell(100,8,$it['item'],1);
        $pdf->Cell(40,8,number_format($it['cost'],2),1,1);
    }

    $pdf->Cell(110,8,"Transport Cost",1);
    $pdf->Cell(40,8,"KES ".number_format($expense['transport_cost'],2),1,1);

    $pdf->Cell(110,8,"Total Amount",1);
    $pdf->Cell(40,8,"KES ".number_format($expense['total_amount'],2),1,1);

    $pdf->Output("D","Breakfast_Expense_".$expense['expense_date'].".pdf");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Breakfast Expense</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-6xl mx-auto">
  <a href="breakfast_expense.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 mb-4 inline-block">← Back</a>

  <div class="bg-white shadow-lg rounded-lg p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-blue-700 mb-4">Breakfast Expense Details</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div>
        <p><strong>Date:</strong> <?= htmlspecialchars($expense['expense_date']) ?></p>
        <p><strong>Items Bought By:</strong> <?= htmlspecialchars($expense['items_bought_by']) ?></p>
        <p><strong>Petty Cash No:</strong> <?= htmlspecialchars($expense['petty_cash_no'] ?? '-') ?></p>
      </div>
      <div>
        <p><strong>Approved By:</strong> <?= htmlspecialchars($expense['approved_by'] ?? '-') ?></p>
        <p><strong>Payment Status:</strong> 
          <span class="<?= ($expense['payment_status'] === 'Paid') ? 'text-green-600 font-semibold' : 'text-yellow-600 font-semibold' ?>">
            <?= htmlspecialchars($expense['payment_status']) ?>
          </span>
        </p>
        <p><strong>Transport Cost:</strong> KES <?= number_format($expense['transport_cost'], 2) ?></p>
      </div>
    </div>

    <h2 class="text-lg font-semibold mt-4 mb-2 text-blue-600">Items Purchased</h2>
    <table class="w-full border border-gray-300 rounded mb-4">
      <thead class="bg-gray-200">
        <tr>
          <th class="border px-3 py-2">#</th>
          <th class="border px-3 py-2">Item</th>
          <th class="border px-3 py-2 text-right">Cost (KES)</th>
        </tr>
      </thead>
      <tbody>
        <?php $count=1; foreach ($items as $it): ?>
        <tr class="<?= ($count%2==0)?'bg-gray-50':'bg-white' ?>">
          <td class="border px-3 py-2 text-center"><?= $count++ ?></td>
          <td class="border px-3 py-2"><?= htmlspecialchars($it['item']) ?></td>
          <td class="border px-3 py-2 text-right"><?= number_format($it['cost'], 2) ?></td>
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

    <div class="flex justify-end gap-2">
      <a href="?id=<?= $expense['breakfast_id'] ?>&download_pdf=1" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">Download PDF</a>
    </div>
  </div>
</div>

</body>
</html>
