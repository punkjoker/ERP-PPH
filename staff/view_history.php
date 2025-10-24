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
    $pdf->Cell(0,8,"History for: $stock_name ($stock_code)",0,1,'L');

    // Table Header (Compact)
    // Table Header (Compact)
$pdf->SetFont('Arial','B',9);
$headers = ['Date','Code','Name','Qty Out','Unit','Remain','Reason','Req. By','Appr. By'];
$widths  = [20,20,32,18,18,18,22,20,20]; // total ≈188mm fits A4

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i],6,$header,1,0,'C');
}
$pdf->Ln();

// Table Body (Compact)
$pdf->SetFont('Arial','',8);
if ($history->num_rows > 0) {
    while ($row = $history->fetch_assoc()) {
        $pdf->Cell(20,5,$row['stock_date'],1);
        $pdf->Cell(20,5,$row['stock_code'],1);
        $pdf->Cell(32,5,substr($row['stock_name'],0,14),1);
        $pdf->Cell(18,5,$row['quantity_removed'],1);
        $pdf->Cell(18,5,number_format($row['unit_cost'],2),1);
        $pdf->Cell(18,5,$row['remaining_quantity'],1);
        $pdf->Cell(22,5,substr($row['reason'],0,12).'...',1);
        $pdf->Cell(20,5,substr($row['requested_by'],0,10).'...',1);
        $pdf->Cell(20,5,substr($row['approved_by'],0,10).'...',1);
        $pdf->Ln();
    }
} else {
    $pdf->Cell(array_sum($widths),8,"No history found for this product.",1,1,'C');
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
    /* Compact table styling */
    table th, table td {
      padding: 3px 6px !important;
      font-size: 12px;
      line-height: 1.2;
    }

    thead th {
      font-weight: 600;
      background-color: #e0ecff;
    }

    tbody tr:nth-child(even) {
      background-color: #f8f9fa;
    }

    table {
      border-collapse: collapse;
      width: 100%;
    }

    /* Print optimization */
    @media print {
      .no-print { display: none; }
      body {
        font-size: 11px;
      }
      table th, table td {
        padding: 2px 4px !important;
        font-size: 10px;
      }
    }
  </style>
</head>
<body class="bg-gray-50 p-8">
  <div class="bg-white p-6 rounded shadow-md max-w-6xl mx-auto">

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
    <h2 class="text-lg font-semibold mb-4">
      History for: <span class="text-blue-600"><?php echo htmlspecialchars($stock_name)." (".$stock_code.")"; ?></span>
    </h2>

    <!-- Buttons -->
    <div class="mb-4 flex gap-3 no-print">
      
      <button onclick="printHistory()" class="bg-green-600 text-white px-3 py-1.5 rounded hover:bg-green-700 text-sm">Print</button>
      <a href="view_history.php?stock_code=<?php echo urlencode($stock_code); ?>&download=pdf"
         class="bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700 text-sm">Save as PDF</a>
    </div>

    <!-- History Table -->
    <div class="overflow-x-auto">
      <table class="border text-sm">
        <thead>
          <tr class="bg-blue-100 text-left">
            <th class="border">Date</th>
            <th class="border">Stock Code</th>
            <th class="border">Stock Name</th>
            <th class="border">Qty Removed</th>
            <th class="border">Unit Cost</th>
            <th class="border">Remaining Qty</th>
            <th class="border">Reason</th>
            <th class="border">Requested By</th>
            <th class="border">Approved By</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($history->num_rows > 0): ?>
            <?php mysqli_data_seek($history, 0); while ($row = $history->fetch_assoc()): ?>
              <tr>
                <td class="border"><?php echo $row['stock_date']; ?></td>
                <td class="border"><?php echo $row['stock_code']; ?></td>
                <td class="border"><?php echo $row['stock_name']; ?></td>
                <td class="border"><?php echo $row['quantity_removed']; ?></td>
                <td class="border"><?php echo number_format($row['unit_cost'], 2); ?></td>
                <td class="border"><?php echo $row['remaining_quantity']; ?></td>
                <td class="border"><?php echo htmlspecialchars($row['reason']); ?></td>
                <td class="border"><?php echo htmlspecialchars($row['requested_by']); ?></td>
                <td class="border"><?php echo htmlspecialchars($row['approved_by']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" class="text-center border p-4 text-gray-500">No history found for this product.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
