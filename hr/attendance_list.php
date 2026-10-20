
<?php
session_start();
include 'db_con.php';

date_default_timezone_set('Africa/Nairobi');

$current_year = (int)date('Y');
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
$year = ($year && $year >= 2000 && $year <= $current_year + 1)
    ? $year
    : $current_year;

$view_user_id = filter_input(INPUT_GET, 'view_user_id', FILTER_VALIDATE_INT);
if (!$view_user_id || $view_user_id < 1) {
    $view_user_id = 0;
}

$message = '';
$staff = [];
$selected_staff = null;
$monthly = [];
$totals = [
    'Present' => 0,
    'Absent' => 0,
    'Leave' => 0
];

// Get staff list
$staff_result = $conn->query("
    SELECT u.user_id, u.full_name, u.national_id
    FROM users u
    JOIN groups g ON u.group_id = g.group_id
    WHERE LOWER(g.group_name) = 'staff'
    ORDER BY u.full_name ASC
");

if ($staff_result) {
    $staff = $staff_result->fetch_all(MYSQLI_ASSOC);
}

// Get available attendance years
$years = [$current_year];

$year_result = $conn->query("
    SELECT DISTINCT YEAR(attendance_date) AS attendance_year
    FROM attendance
    WHERE YEAR(attendance_date) BETWEEN 2000 AND $current_year
    ORDER BY attendance_year DESC
");

if ($year_result) {
    while ($row = $year_result->fetch_assoc()) {
        $years[] = (int)$row['attendance_year'];
    }
}

$years = array_values(array_unique($years));
rsort($years);

// Load selected staff's annual attendance
if ($view_user_id > 0) {
    $stmt = $conn->prepare("
        SELECT u.user_id, u.full_name, u.national_id
        FROM users u
        JOIN groups g ON u.group_id = g.group_id
        WHERE u.user_id = ?
          AND LOWER(g.group_name) = 'staff'
        LIMIT 1
    ");

    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $selected_staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$selected_staff) {
        $message = 'The selected staff member could not be found.';
        $view_user_id = 0;
    } else {
        // Initialize all 12 months, including months with no records.
        for ($month = 1; $month <= 12; $month++) {
            $monthly[$month] = [
                'Present' => 0,
                'Absent' => 0,
                'Leave' => 0,
                'total' => 0
            ];
        }

        $stmt = $conn->prepare("
            SELECT
                MONTH(attendance_date) AS month_number,
                status,
                COUNT(*) AS days_count
            FROM attendance
            WHERE user_id = ?
              AND YEAR(attendance_date) = ?
              AND DAYOFWEEK(attendance_date) <> 1
            GROUP BY MONTH(attendance_date), status
        ");

        $stmt->bind_param("ii", $view_user_id, $year);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $month_number = (int)$row['month_number'];
            $status = $row['status'];
            $days = (int)$row['days_count'];

            if (isset($monthly[$month_number][$status])) {
                $monthly[$month_number][$status] = $days;
            }
        }

        $stmt->close();

        foreach ($monthly as $month_number => $data) {
            $monthly[$month_number]['total'] =
                $data['Present'] + $data['Absent'] + $data['Leave'];

            $totals['Present'] += $data['Present'];
            $totals['Absent'] += $data['Absent'];
            $totals['Leave'] += $data['Leave'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Attendance Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen">
<?php include 'navbar.php'; ?>

<div class="ml-64 p-6 max-w-7xl mx-auto">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-blue-700">
                Staff Attendance Reports
            </h1>
            <p class="text-gray-600 mt-1">
                Review monthly and annual staff attendance.
            </p>
        </div>

        <a href="attendance.php"
           class="bg-green-600 text-white px-5 py-3 rounded-lg hover:bg-green-700">
            + Record Attendance
        </a>
    </div>

    <?php if ($message): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg mb-5">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Year filter -->
    <form method="GET"
          class="bg-white shadow rounded-xl p-5 mb-6 flex flex-wrap items-end gap-4">

        <div class="w-full sm:w-64">
            <label for="year" class="block font-semibold mb-2 text-gray-700">
                Filter by Year
            </label>

            <select name="year" id="year"
                    class="border p-3 rounded-lg w-full">
                <?php foreach ($years as $available_year): ?>
                    <option value="<?= $available_year ?>"
                        <?= $year === $available_year ? 'selected' : '' ?>>
                        <?= $available_year ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($view_user_id > 0): ?>
            <input type="hidden" name="view_user_id"
                   value="<?= $view_user_id ?>">
        <?php endif; ?>

        <button type="submit"
                class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
            Apply Filter
        </button>

        <a href="attendance_list.php"
           class="bg-gray-200 text-gray-800 px-6 py-3 rounded-lg hover:bg-gray-300">
            All Staff
        </a>
    </form>

    <!-- Staff directory -->
    <div class="bg-white shadow rounded-xl p-5 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            Staff List
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead class="bg-gray-200 text-gray-700">
                    <tr>
                        <th class="border p-3 text-left">#</th>
                        <th class="border p-3 text-left">Staff Name</th>
                        <th class="border p-3 text-left">National ID</th>
                        <th class="border p-3 text-center">Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($staff)): ?>
                    <tr>
                        <td colspan="4" class="p-5 text-center text-gray-500">
                            No staff members found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($staff as $index => $person): ?>
                        <tr class="<?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?> hover:bg-blue-50">
                            <td class="border p-3">
                                <?= $index + 1 ?>
                            </td>

                            <td class="border p-3 font-medium">
                                <?= htmlspecialchars($person['full_name']) ?>
                            </td>

                            <td class="border p-3">
                                <?= htmlspecialchars($person['national_id'] ?? '') ?>
                            </td>

                            <td class="border p-3 text-center">
                                <a href="attendance_list.php?year=<?= $year ?>&view_user_id=<?= (int)$person['user_id'] ?>"
                                   class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                    View Attendance
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Selected staff annual report -->
    <?php if ($selected_staff): ?>

        <div class="bg-white shadow rounded-xl p-5">

            <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
                <div>
                    <h2 class="text-2xl font-bold text-blue-700">
                        Annual Attendance Report
                    </h2>

                    <p class="text-gray-600 mt-1">
                        <?= htmlspecialchars($selected_staff['full_name']) ?>
                        &mdash; <?= $year ?>
                    </p>

                    <p class="text-gray-500 text-sm">
                        National ID:
                        <?= htmlspecialchars($selected_staff['national_id'] ?? '') ?>
                    </p>
                </div>

                <button type="button"
        onclick="downloadAttendancePDF()"
        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
    Download PDF
</button>
            </div>

            <!-- Annual summary cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-green-700 text-sm font-semibold">Present Days</p>
                    <p class="text-3xl font-bold text-green-800">
                        <?= $totals['Present'] ?>
                    </p>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-700 text-sm font-semibold">Absent Days</p>
                    <p class="text-3xl font-bold text-red-800">
                        <?= $totals['Absent'] ?>
                    </p>
                </div>

                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <p class="text-orange-700 text-sm font-semibold">Leave Days</p>
                    <p class="text-3xl font-bold text-orange-800">
                        <?= $totals['Leave'] ?>
                    </p>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-blue-700 text-sm font-semibold">Recorded Days</p>
                    <p class="text-3xl font-bold text-blue-800">
                        <?= $totals['Present'] + $totals['Absent'] + $totals['Leave'] ?>
                    </p>
                </div>

            </div>

            <!-- January to December -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead class="bg-gray-200 text-gray-700">
                        <tr>
                            <th class="border p-3 text-left">Month</th>
                            <th class="border p-3 text-center">Present</th>
                            <th class="border p-3 text-center">Absent</th>
                            <th class="border p-3 text-center">Leave</th>
                            <th class="border p-3 text-center">Recorded Days</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($monthly as $month_number => $data): ?>
                        <tr class="<?= $month_number % 2 === 0 ? 'bg-gray-50' : 'bg-white' ?>">
                            <td class="border p-3 font-semibold">
                                <?= date('F', mktime(0, 0, 0, $month_number, 1)) ?>
                            </td>

                            <td class="border p-3 text-center text-green-700 font-semibold">
                                <?= $data['Present'] ?>
                            </td>

                            <td class="border p-3 text-center text-red-700 font-semibold">
                                <?= $data['Absent'] ?>
                            </td>

                            <td class="border p-3 text-center text-orange-700 font-semibold">
                                <?= $data['Leave'] ?>
                            </td>

                            <td class="border p-3 text-center font-bold">
                                <?= $data['total'] ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>

                    <tfoot class="bg-gray-100 font-bold">
                        <tr>
                            <td class="border p-3">YEAR TOTAL</td>
                            <td class="border p-3 text-center text-green-700">
                                <?= $totals['Present'] ?>
                            </td>
                            <td class="border p-3 text-center text-red-700">
                                <?= $totals['Absent'] ?>
                            </td>
                            <td class="border p-3 text-center text-orange-700">
                                <?= $totals['Leave'] ?>
                            </td>
                            <td class="border p-3 text-center">
                                <?= $totals['Present'] + $totals['Absent'] + $totals['Leave'] ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="text-sm text-gray-500 mt-4">
                The report counts recorded attendance entries. Unrecorded
                working days are not included in the absent total.
            </p>

        </div>

    <?php endif; ?>

</div>

<style>
@media print {
    body {
        background: white !important;
    }

    .ml-64 {
        margin-left: 0 !important;
    }

    nav, aside, header, .print\:hidden {
        display: none !important;
    }

    body * {
        visibility: visible;
    }

    table {
        width: 100%;
    }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>

<script>
async function downloadAttendancePDF() {
    if (!window.jspdf || !window.jspdf.jsPDF) {
        alert('PDF library failed to load. Check your internet connection and try again.');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');

    const staffName = <?= json_encode($selected_staff['full_name'] ?? '') ?>;
    const nationalId = <?= json_encode($selected_staff['national_id'] ?? '') ?>;
    const reportYear = <?= json_encode($year) ?>;

    const monthlyData = <?= json_encode(array_map(
        function ($monthNumber, $data) {
            return [
                date('F', mktime(0, 0, 0, $monthNumber, 1)),
                $data['Present'],
                $data['Absent'],
                $data['Leave'],
                $data['total']
            ];
        },
        array_keys($monthly),
        array_values($monthly)
    )) ?>;

    const totals = <?= json_encode([
        'Present' => $totals['Present'],
        'Absent' => $totals['Absent'],
        'Leave' => $totals['Leave'],
        'Recorded' => $totals['Present'] + $totals['Absent'] + $totals['Leave']
    ]) ?>;

    const green = [22, 101, 52];
    const dark = [31, 41, 55];

    // Logo file is in the same folder as attendance_list.php.
    function loadLogo() {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = 'images/lynn_logo.png';
        });
    }

    const logo = await loadLogo();

    // Logo and report heading.
    if (logo) {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = logo.naturalWidth;
            canvas.height = logo.naturalHeight;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(logo, 0, 0);

            const imageData = canvas.toDataURL('image/png');

            const maxWidth = 35;
            const maxHeight = 22;
            const ratio = Math.min(
                maxWidth / logo.naturalWidth,
                maxHeight / logo.naturalHeight
            );

            const width = logo.naturalWidth * ratio;
            const height = logo.naturalHeight * ratio;

            doc.addImage(imageData, 'PNG', 15, 10, width, height);
        } catch (error) {
            console.warn('Could not add logo to PDF:', error);
        }
    }

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(17);
    doc.setTextColor(...green);
    doc.text('STAFF ATTENDANCE REPORT', 105, 20, {
        align: 'center'
    });

    doc.setFontSize(11);
    doc.setTextColor(...dark);
    doc.text('Annual Report - ' + reportYear, 105, 28, {
        align: 'center'
    });

    doc.setDrawColor(...green);
    doc.setLineWidth(0.7);
    doc.line(15, 37, 195, 37);

    // Staff details.
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.text('Staff Name:', 15, 47);

    doc.setFont('helvetica', 'normal');
    doc.text(String(staffName), 43, 47);

    doc.setFont('helvetica', 'bold');
    doc.text('National ID:', 15, 54);

    doc.setFont('helvetica', 'normal');
    doc.text(String(nationalId || 'N/A'), 43, 54);

    doc.setFont('helvetica', 'bold');
    doc.text('Report Year:', 125, 47);

    doc.setFont('helvetica', 'normal');
    doc.text(String(reportYear), 151, 47);

    // Annual summary.
    doc.autoTable({
        startY: 62,
        head: [['Present Days', 'Absent Days', 'Leave Days', 'Recorded Days']],
        body: [[
            totals.Present,
            totals.Absent,
            totals.Leave,
            totals.Recorded
        ]],
        theme: 'grid',
        headStyles: {
            fillColor: green,
            textColor: [255, 255, 255],
            halign: 'center'
        },
        bodyStyles: {
            halign: 'center',
            fontStyle: 'bold'
        },
        margin: { left: 15, right: 15 }
    });

    // Monthly attendance.
    doc.autoTable({
        startY: doc.lastAutoTable.finalY + 10,
        head: [[
            'Month',
            'Present',
            'Absent',
            'Leave',
            'Recorded Days'
        ]],
        body: monthlyData,
        foot: [[
            'YEAR TOTAL',
            totals.Present,
            totals.Absent,
            totals.Leave,
            totals.Recorded
        ]],
        theme: 'grid',
        styles: {
            fontSize: 9,
            cellPadding: 3
        },
        headStyles: {
            fillColor: green,
            textColor: [255, 255, 255],
            halign: 'center'
        },
        columnStyles: {
            0: { halign: 'left', cellWidth: 48 },
            1: { halign: 'center' },
            2: { halign: 'center' },
            3: { halign: 'center' },
            4: { halign: 'center' }
        },
        footStyles: {
            fillColor: [229, 231, 235],
            textColor: dark,
            fontStyle: 'bold'
        },
        margin: { left: 15, right: 15 }
    });

    // Footer on every PDF page.
    const pageCount = doc.internal.getNumberOfPages();

    for (let page = 1; page <= pageCount; page++) {
        doc.setPage(page);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(100, 100, 100);

        doc.text(
            'Generated on ' + new Date().toLocaleDateString(),
            15,
            287
        );

        doc.text(
            'Page ' + page + ' of ' + pageCount,
            195,
            287,
            { align: 'right' }
        );
    }

    const safeName = String(staffName)
        .replace(/[^a-z0-9_-]+/gi, '_');

    doc.save('Attendance_' + safeName + '_' + reportYear + '.pdf');
}
</script>
</body>
</html>