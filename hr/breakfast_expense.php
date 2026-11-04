
<?php
include 'db_con.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function validDate($date) {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// --------------------------------------------------
// ADD BREAKFAST EXPENSE
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {

    $expense_date = trim($_POST['expense_date'] ?? '');
    $items = $_POST['items'] ?? [];
    $costs = $_POST['costs'] ?? [];
    $transport_cost = max(0, (float)($_POST['transport_cost'] ?? 0));
    $items_bought_by = trim($_POST['items_bought_by'] ?? '');

    if (!validDate($expense_date)) {
        die("Please select a valid expense date.");
    }

    $expense_array = [];
    $total_items = 0;

    foreach ($items as $i => $item) {
        $item = trim($item);
        $cost = (float)($costs[$i] ?? 0);

        if ($item !== '' && $cost > 0) {
            $expense_array[] = [
                'item' => $item,
                'cost' => $cost
            ];

            $total_items += $cost;
        }
    }

    if (empty($expense_array)) {
        die("Please enter at least one valid item and cost.");
    }

    $total_amount = $total_items + $transport_cost;
    $items_json = json_encode($expense_array, JSON_UNESCAPED_UNICODE);

    $stmt = $conn->prepare("
        INSERT INTO breakfast_expense
        (expense_date, items, total_amount, items_bought_by, transport_cost)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssdsd",
        $expense_date,
        $items_json,
        $total_amount,
        $items_bought_by,
        $transport_cost
    );

    $stmt->execute();
    $stmt->close();

    header("Location: breakfast_expense.php?success=1");
    exit;
}

// --------------------------------------------------
// FILTERS
// --------------------------------------------------
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

if ($from_date !== '' && !validDate($from_date)) {
    $from_date = '';
}

if ($to_date !== '' && !validDate($to_date)) {
    $to_date = '';
}

if ($from_date !== '' && $to_date !== '' && $from_date > $to_date) {
    die("The From Date cannot be later than the To Date.");
}

// --------------------------------------------------
// FETCH EXPENSES USING SELECTED FILTERS
// --------------------------------------------------
$query = "SELECT * FROM breakfast_expense WHERE 1=1";
$params = [];
$types = '';

if ($from_date !== '') {
    $query .= " AND expense_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}

if ($to_date !== '') {
    $query .= " AND expense_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}

$query .= " ORDER BY expense_date DESC, breakfast_id DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$breakfast_expenses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --------------------------------------------------
// CALCULATE REPORT TOTALS
// --------------------------------------------------
$grand_total = 0;
$total_transport = 0;
$total_items_cost = 0;
$total_records = count($breakfast_expenses);

foreach ($breakfast_expenses as $be) {

    $amount = (float)$be['total_amount'];
    $transport = (float)($be['transport_cost'] ?? 0);
    $items_cost = $amount - $transport;

    $grand_total += $amount;
    $total_transport += $transport;
    $total_items_cost += $items_cost;
}

// --------------------------------------------------
// EXPORT CSV REPORT
// --------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    $filename = 'Breakfast_Expense_Report_' .
        ($from_date ?: 'All') . '_to_' .
        ($to_date ?: 'All') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Excel-friendly UTF-8 BOM
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'Expense Date',
        'Items',
        'Items Cost (KES)',
        'Transport Cost (KES)',
        'Total Amount (KES)',
        'Items Bought By'
    ]);

    foreach ($breakfast_expenses as $be) {

        $items = json_decode($be['items'], true);
        $item_descriptions = [];

        if (is_array($items)) {
            foreach ($items as $item) {
                $item_descriptions[] =
                    ($item['item'] ?? '') . ' - KES ' .
                    number_format((float)($item['cost'] ?? 0), 2);
            }
        }

        $transport = (float)($be['transport_cost'] ?? 0);
        $amount = (float)$be['total_amount'];
        $items_cost = $amount - $transport;

        fputcsv($output, [
            $be['expense_date'],
            implode('; ', $item_descriptions),
            number_format($items_cost, 2, '.', ''),
            number_format($transport, 2, '.', ''),
            number_format($amount, 2, '.', ''),
            $be['items_bought_by']
        ]);
    }

    // Report totals
    fputcsv($output, []);
    fputcsv($output, [
        'REPORT TOTALS',
        '',
        number_format($total_items_cost, 2, '.', ''),
        number_format($total_transport, 2, '.', ''),
        number_format($grand_total, 2, '.', ''),
        ''
    ]);

    fputcsv($output, ['Number of Records', $total_records]);

    fclose($output);
    exit;
}

// Filtered report label
if ($from_date !== '' && $to_date !== '') {
    $report_period = date('d M Y', strtotime($from_date)) .
        ' to ' . date('d M Y', strtotime($to_date));
} elseif ($from_date !== '') {
    $report_period = 'From ' . date('d M Y', strtotime($from_date));
} elseif ($to_date !== '') {
    $report_period = 'Up to ' . date('d M Y', strtotime($to_date));
} else {
    $report_period = 'All Records';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daily Breakfast Expense</title>

    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <script>
        let itemCount = 1;

        function addNewItem() {
            itemCount++;

            const container = document.getElementById('items-container');
            const div = document.createElement('div');

            div.classList.add('flex', 'gap-2', 'mb-2');

            div.innerHTML = `
                <input type="text" name="items[]" placeholder="Item ${itemCount}"
                    class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300" required>

                <input type="number" step="0.01" min="0.01" name="costs[]" placeholder="Cost"
                    class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300"
                    required oninput="calculateTotal()">
            `;

            container.appendChild(div);
        }

        function calculateTotal() {
            let total = 0;

            document.querySelectorAll('input[name="costs[]"]').forEach(c => {
                let val = parseFloat(c.value) || 0;
                total += val;
            });

            let transport = parseFloat(
                document.getElementById('transport_cost').value
            ) || 0;

            total += transport;

            document.getElementById('total_amount').innerText =
                total.toLocaleString('en-KE', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
        }

        function printReport() {
            window.print();
        }
    </script>

    <style>
        @media print {
            body {
                background: white !important;
            }

            body * {
                visibility: hidden;
            }

            #reportSection,
            #reportSection * {
                visibility: visible;
            }

            #reportSection {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white !important;
                box-shadow: none !important;
                padding: 10px !important;
            }

            .no-print {
                display: none !important;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
            }

            th, td {
                border: 1px solid #555 !important;
                padding: 6px !important;
            }

            h2, h3 {
                color: black !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100">

<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <h1 class="text-3xl font-bold mb-6 text-blue-700">
        Daily Breakfast Expense
    </h1>

    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            Breakfast expense added successfully!
        </div>
    <?php endif; ?>

    <!-- ADD BREAKFAST EXPENSE -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">

        <h2 class="text-xl font-semibold mb-4 text-blue-700">
            Add Breakfast Expense
        </h2>

        <form method="POST" class="space-y-4">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block font-semibold mb-1">Expense Date</label>

                    <input type="date" name="expense_date"
                        value="<?= date('Y-m-d') ?>"
                        max="<?= date('Y-m-d') ?>"
                        required
                        class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                </div>

                <label class="md:col-span-2 font-semibold mt-2">
                    Items & Costs
                </label>

                <div id="items-container" class="md:col-span-2 flex flex-col gap-2">

                    <div class="flex gap-2 mb-2">

                        <input type="text" name="items[]" placeholder="Item 1"
                            class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300"
                            required>

                        <input type="number" step="0.01" min="0.01"
                            name="costs[]" placeholder="Cost"
                            class="border p-2 rounded w-1/2 focus:ring-2 focus:ring-blue-300"
                            required oninput="calculateTotal()">

                    </div>

                </div>

                <button type="button" onclick="addNewItem()"
                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition md:col-span-2">

                    Add New Item

                </button>

                <div>
                    <label class="block font-semibold mb-1">Items Bought By</label>

                    <input type="text" name="items_bought_by"
                        placeholder="Items Bought By"
                        class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300">
                </div>

                <div>
                    <label class="block font-semibold mb-1">Transport Cost (KES)</label>

                    <input type="number" step="0.01" min="0"
                        name="transport_cost" id="transport_cost"
                        placeholder="Transport Cost"
                        value="0"
                        class="border p-2 rounded w-full focus:ring-2 focus:ring-blue-300"
                        oninput="calculateTotal()">
                </div>

            </div>

            <div class="text-right font-bold text-lg mt-2">
                Total Amount: KES
                <span id="total_amount">0.00</span>
            </div>

            <button type="submit" name="add_expense"
                class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">

                Add Breakfast Expense

            </button>

        </form>

    </div>

    <!-- REPORT SECTION -->
    <div class="bg-white shadow rounded-lg p-6">

        <div class="flex flex-wrap justify-between items-center mb-4 gap-3">

            <h2 class="text-xl font-semibold text-blue-700">
                Breakfast Expenses Report
            </h2>

            <span class="text-sm text-gray-600">
                <?= e($report_period) ?>
            </span>

        </div>

        <!-- FILTERS -->
        <form method="GET"
            class="flex flex-col md:flex-row gap-4 items-end mb-6 no-print">

            <div>
                <label class="block text-sm font-semibold mb-1">From Date</label>

                <input type="date" name="from_date"
                    value="<?= e($from_date) ?>"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">To Date</label>

                <input type="date" name="to_date"
                    value="<?= e($to_date) ?>"
                    class="border p-2 rounded focus:ring-2 focus:ring-blue-300">
            </div>

            <button type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">

                Filter Report

            </button>

            <a href="breakfast_expense.php"
                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition">

                Reset

            </a>

            <button type="button" onclick="printReport()"
                class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">

                Print Report

            </button>

            <a href="?<?= http_build_query(array_filter([
                'from_date' => $from_date,
                'to_date' => $to_date,
                'export' => 'csv'
            ], function($value) {
                return $value !== '';
            })) ?>"
                class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition">

                Export Excel / CSV

            </a>

        </form>

        <!-- SUMMARY TOTALS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-gray-600 font-medium">
                    Number of Records
                </p>

                <h3 class="text-2xl font-bold text-blue-700">
                    <?= number_format($total_records) ?>
                </h3>
            </div>

            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <p class="text-sm text-gray-600 font-medium">
                    Items Cost
                </p>

                <h3 class="text-xl font-bold text-purple-700">
                    KES <?= number_format($total_items_cost, 2) ?>
                </h3>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-gray-600 font-medium">
                    Transport Cost
                </p>

                <h3 class="text-xl font-bold text-yellow-700">
                    KES <?= number_format($total_transport, 2) ?>
                </h3>
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <p class="text-sm text-gray-600 font-medium">
                    Grand Total
                </p>

                <h3 class="text-xl font-bold text-green-700">
                    KES <?= number_format($grand_total, 2) ?>
                </h3>
            </div>

        </div>

        <!-- REPORT TABLE -->
        <div id="reportSection">

            <div class="mb-4">
                <h2 class="text-xl font-bold text-blue-700">
                    BREAKFAST EXPENSE REPORT
                </h2>

                <p class="text-sm text-gray-600">
                    Reporting Period: <?= e($report_period) ?>
                </p>

                <p class="text-sm text-gray-600">
                    Generated: <?= date('d M Y, H:i') ?>
                </p>
            </div>

            <div class="overflow-x-auto">

                <table class="w-full border border-gray-300 rounded text-sm">

                    <thead class="bg-gray-200">

                        <tr>
                            <th class="border px-3 py-2">#</th>
                            <th class="border px-3 py-2">Expense Date</th>
                            <th class="border px-3 py-2">Items Bought By</th>
                            <th class="border px-3 py-2">Items Cost (KES)</th>
                            <th class="border px-3 py-2">Transport (KES)</th>
                            <th class="border px-3 py-2">Total Amount (KES)</th>
                            <th class="border px-3 py-2 no-print">Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (!empty($breakfast_expenses)): ?>

                            <?php $count = 1; ?>

                            <?php foreach ($breakfast_expenses as $be): ?>

                                <?php
                                    $amount = (float)$be['total_amount'];
                                    $transport = (float)($be['transport_cost'] ?? 0);
                                    $items_cost = $amount - $transport;
                                ?>

                                <tr class="<?= ($count % 2 == 0) ? 'bg-gray-50' : 'bg-white' ?> hover:bg-blue-50">

                                    <td class="border px-3 py-2">
                                        <?= $count++ ?>
                                    </td>

                                    <td class="border px-3 py-2 whitespace-nowrap">
                                        <?= e(date('d M Y', strtotime($be['expense_date']))) ?>
                                    </td>

                                    <td class="border px-3 py-2">
                                        <?= e($be['items_bought_by']) ?>
                                    </td>

                                    <td class="border px-3 py-2 text-right">
                                        <?= number_format($items_cost, 2) ?>
                                    </td>

                                    <td class="border px-3 py-2 text-right">
                                        <?= number_format($transport, 2) ?>
                                    </td>

                                    <td class="border px-3 py-2 text-right font-semibold text-green-700">
                                        <?= number_format($amount, 2) ?>
                                    </td>

                                    <td class="border px-3 py-2 space-x-2 no-print">

                                        <a href="edit_breakfast_expense.php?id=<?= (int)$be['breakfast_id'] ?>"
                                            class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition">

                                            Edit

                                        </a>

                                        <a href="view_breakfast_expense.php?id=<?= (int)$be['breakfast_id'] ?>"
                                            class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">

                                            View

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7"
                                    class="border px-3 py-6 text-center text-gray-500">

                                    No breakfast expenses found for this period.

                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                    <!-- TOTALS FOOTER -->
                    <tfoot>

                        <tr class="bg-gray-200 font-bold">

                            <td colspan="3" class="border px-3 py-3 text-right">
                                REPORT TOTAL:
                            </td>

                            <td class="border px-3 py-3 text-right">
                                <?= number_format($total_items_cost, 2) ?>
                            </td>

                            <td class="border px-3 py-3 text-right">
                                <?= number_format($total_transport, 2) ?>
                            </td>

                            <td class="border px-3 py-3 text-right text-green-700">
                                KES <?= number_format($grand_total, 2) ?>
                            </td>

                            <td class="border px-3 py-3 no-print"></td>

                        </tr>

                    </tfoot>

                </table>

            </div>

            <div class="mt-4 text-right font-bold text-lg">
                Total Breakfast Expenses:
                <span class="text-green-700">
                    KES <?= number_format($grand_total, 2) ?>
                </span>
            </div>

        </div>

    </div>

</div>

</body>
</html>
