<?php
require 'db_con.php';

if (!isset($_GET['stock_code'])) {
    die("Invalid request! Stock code missing.");
}

$stock_code = $_GET['stock_code'];

// Fetch product name
$stmt = $conn->prepare("SELECT stock_name FROM stock_in WHERE stock_code = ?");
$stmt->bind_param("s", $stock_code);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stock_name = $product['stock_name'] ?? 'Unknown';

// Fetch history
$stmt = $conn->prepare("SELECT * FROM stock_out_history WHERE stock_code = ? ORDER BY stock_date DESC");
$stmt->bind_param("s", $stock_code);
$stmt->execute();
$history = $stmt->get_result();

// Handle PDF download
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    require('fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();

    // Header
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,'STOCK CARDS - QF 18',0,1,'C');
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,6,'EFFECTIVE DATE: 01/11/2024   ISSUE DATE: 25/10/2024   REVIEW DATE: 10/2027',0,1,'C');
    $pdf->Cell(0,6,'ISSUE NO: 007   REVISION NO: 006   MANUAL NO: LYNNTECH-QP-22',0,1,'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(0,10,"History for: $stock_name ($stock_code)",0,1,'L');

    // Table Header
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(30,7,'Date',1);
    $pdf->Cell(30,7,'Stock Code',1);
    $pdf->Cell(40,7,'Stock Name',1);
    $pdf->Cell(30,7,'Qty Removed',1);
    $pdf->Cell(30,7,'Unit Cost',1);
    $pdf->Cell(30,7,'Remaining',1);
    $pdf->Ln();

    // Table Body
    $pdf->SetFont('Arial','',9);
    if ($history->num_rows > 0) {
        while ($row = $history->fetch_assoc()) {
            $pdf->Cell(30,6,$row['stock_date'],1);
            $pdf->Cell(30,6,$row['stock_code'],1);
            $pdf->Cell(40,6,$row['stock_name'],1);
            $pdf->Cell(30,6,$row['quantity_removed'],1);
            $pdf->Cell(30,6,number_format($row['unit_cost'],2),1);
            $pdf->Cell(30,6,$row['remaining_quantity'],1);
            $pdf->Ln();
        }
    } else {
        $pdf->Cell(190,10,"No history found for this product.",1,1,'C');
    }

    $pdf->Output('D',"stock_history_$stock_code.pdf");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View History - <?php echo htmlspecialchars($stock_name); ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function printHistory() {
      window.print();
    }
  </script>
  <style>
    @media print {
      .no-print { display: none; }
    }
  </style>
</head>
<body class="bg-gray-50 p-8">
  <div class="bg-white p-6 rounded shadow-md max-w-5xl mx-auto">

    <!-- Header Section -->
    <div class="mb-6 border-b pb-4">
      <h1 class="text-2xl font-bold text-center text-blue-700 mb-2">STOCK CARDS - QF 18</h1>
      <div class="grid grid-cols-2 gap-4 text-sm">
        <p><strong>EFFECTIVE DATE:</strong> 01/11/2024</p>
        <p><strong>ISSUE DATE:</strong> 25/10/2024</p>
        <p><strong>REVIEW DATE:</strong> 10/2027</p>
        <p><strong>ISSUE NO:</strong> 007</p>
        <p><strong>REVISION NO:</strong> 006</p>
        <p><strong>MANUAL NO:</strong> LYNNTECH-QP-22</p>
      </div>
    </div>

    <!-- Product Title -->
    <h2 class="text-xl font-semibold mb-4">History for: 
      <span class="text-blue-600"><?php echo htmlspecialchars($stock_name)." (".$stock_code.")"; ?></span>
    </h2>

    <!-- Buttons -->
    <div class="mb-4 flex gap-3 no-print">
      <a href="stock_out.php" 
         class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
        Back
      </a>
      <button onclick="printHistory()" 
              class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
        Print
      </button>
      <a href="view_history.php?stock_code=<?php echo urlencode($stock_code); ?>&download=pdf"
         class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
        Save as PDF
      </a>
    </div>

    <!-- History Table -->
    <div class="overflow-x-auto">
      <table class="w-full border-collapse border text-sm">
        <thead>
          <tr class="bg-blue-100 text-left">
            <th class="border p-2">Date</th>
            <th class="border p-2">Stock Code</th>
            <th class="border p-2">Stock Name</th>
            <th class="border p-2">Quantity Removed</th>
            <th class="border p-2">Unit Cost</th>
            <th class="border p-2">Remaining Qty</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($history->num_rows > 0): ?>
            <?php mysqli_data_seek($history, 0); while ($row = $history->fetch_assoc()): ?>
              <tr>
                <td class="border p-2"><?php echo $row['stock_date']; ?></td>
                <td class="border p-2"><?php echo $row['stock_code']; ?></td>
                <td class="border p-2"><?php echo $row['stock_name']; ?></td>
                <td class="border p-2"><?php echo $row['quantity_removed']; ?></td>
                <td class="border p-2"><?php echo number_format($row['unit_cost'], 2); ?></td>
                <td class="border p-2"><?php echo $row['remaining_quantity']; ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center border p-4 text-gray-500">No history found for this product.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
