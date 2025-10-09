<?php
include 'db_con.php';
require_once('fpdf.php'); // or dompdf if you prefer

$id = $_GET['id'] ?? 0;

// Fetch data
$stmt = $conn->prepare("SELECT * FROM breakfast_expense WHERE breakfast_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$expense = $result->fetch_assoc();
$stmt->close();

if (!$expense) {
    die("Expense not found.");
}

$items = json_decode($expense['items'], true);
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

  <p><strong>Date:</strong> <?= htmlspecialchars($expense['expense_date']) ?></p>
  <p><strong>Items Bought By:</strong> <?= htmlspecialchars($expense['items_bought_by']) ?></p>
  <p><strong>Transport Cost:</strong> KES <?= number_format($expense['transport_cost'], 2) ?></p>

  <h2 class="text-lg font-semibold mt-4 mb-2">Items Purchased</h2>
  <table class="w-full border">
    <thead class="bg-gray-200">
      <tr><th class="border px-3 py-2">Item</th><th class="border px-3 py-2">Cost (KES)</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td class="border px-3 py-2"><?= htmlspecialchars($it['item']) ?></td>
        <td class="border px-3 py-2"><?= number_format($it['cost'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="text-right font-bold text-xl mt-4">Total: KES <?= number_format($expense['total_amount'], 2) ?></div>

  <form method="POST" action="download_breakfast_pdf.php" class="mt-6 text-right">
    <input type="hidden" name="id" value="<?= $expense['breakfast_id'] ?>">
    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">Download PDF</button>
  </form>
</div>
</body>
</html>
