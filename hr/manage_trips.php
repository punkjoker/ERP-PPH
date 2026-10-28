<?php
include 'db_con.php';


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Handle trip approval updates
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_approval'])
) {
    $trip_id = (int)($_POST['trip_id'] ?? 0);
    $approved_by = trim($_POST['approved_by'] ?? '');
    $approval_status = trim($_POST['approval_status'] ?? '');

    if ($trip_id <= 0) {
        die('Invalid trip ID.');
    }

    if (!in_array($approval_status, ['Pending', 'Approved'], true)) {
        die('Invalid approval status.');
    }

    if ($approval_status === 'Approved' && $approved_by === '') {
        die('Please enter the name of the person approving this trip.');
    }

    // Pending trips should not retain an approver.
    if ($approval_status === 'Pending') {
        $approved_by = null;
    }

    $stmt = $conn->prepare("
        UPDATE trips
        SET approval_status = ?,
            approved_by = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        'ssi',
        $approval_status,
        $approved_by,
        $trip_id
    );

    $stmt->execute();
    $stmt->close();

    header('Location: ' . $_SERVER['PHP_SELF'] . '?updated=1');
    exit;
}


// --- Handle Trip Saving ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id'])) {
  $vehicle_id = $_POST['vehicle_id'];
  $driver_name = $_POST['driver_name'];

  for ($i = 0; $i < count($_POST['route_name']); $i++) {
    $route = $_POST['route_name'][$i];
    $from = $_POST['destination_from'][$i];
    $to = $_POST['destination_to'][$i];
    $distance = $_POST['distance_km'][$i];
    $delivery_name = $_POST['delivery_name'][$i];
    $delivery_date = $_POST['delivery_date'][$i];

   $stmt = $conn->prepare("INSERT INTO trips (driver_name, vehicle_id, route_name, destination_from, destination_to, distance_km, delivery_name, delivery_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssdss", $driver_name, $vehicle_id, $route, $from, $to, $distance, $delivery_name, $delivery_date);

    $stmt->execute();
  }

  echo "<p class='text-green-600 font-semibold mb-4'>Trip(s) recorded successfully!</p>";
}

// --- Filters ---
$where = [];
if (!empty($_GET['from'])) $where[] = "destination_from LIKE '%" . $conn->real_escape_string($_GET['from']) . "%'";
if (!empty($_GET['to'])) $where[] = "destination_to LIKE '%" . $conn->real_escape_string($_GET['to']) . "%'";
if (!empty($_GET['vehicle'])) $where[] = "vehicle_id LIKE '%" . $conn->real_escape_string($_GET['vehicle']) . "%'";
$where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// --- Fetch vehicles ---
$vehicles = $conn->query("SELECT id, driver_name, vehicle_number, vehicle_name FROM vehicles");

// --- Fetch trips ---
$trips = $conn->query("SELECT * FROM trips $where_sql ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Trips</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function addRoute() {
      const container = document.getElementById('routes');
      const routeDiv = document.createElement('div');
      routeDiv.className = 'grid grid-cols-6 gap-2 mb-2';
      routeDiv.innerHTML = `
        <input type="text" name="route_name[]" placeholder="Route Name" class="p-2 border rounded">
        <input type="text" name="destination_from[]" placeholder="From" class="p-2 border rounded">
        <input type="text" name="destination_to[]" placeholder="To" class="p-2 border rounded">
        <input type="number" step="0.01" name="distance_km[]" placeholder="Distance (km)" class="p-2 border rounded">
        <input type="text" name="delivery_name[]" placeholder="Delivery Name" class="p-2 border rounded">
        <input type="datetime-local" name="delivery_date[]" class="p-2 border rounded">
      `;
      container.appendChild(routeDiv);
    }

    function updateDriver() {
      const select = document.getElementById('vehicleSelect');
      const driverInput = document.getElementById('driverName');
      const selected = select.options[select.selectedIndex];
      driverInput.value = selected.getAttribute('data-driver');
    }

    function printTable() {
      const printContents = document.getElementById('tripTable').outerHTML;
      const printWindow = window.open('', '', 'width=1000,height=700');
      printWindow.document.write('<html><head><title>Print Trips</title>');
      printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">');
      printWindow.document.write('</head><body>');
      printWindow.document.write('<h1 class="text-center text-2xl font-bold mb-4">Trips Report</h1>');
      printWindow.document.write(printContents);
      printWindow.document.write('</body></html>');
      printWindow.document.close();
      printWindow.print();
    }

    function downloadCSV() {
      const table = document.getElementById("tripTable");
      let csv = [];
      for (let i = 0; i < table.rows.length; i++) {
        let row = [], cols = table.rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) row.push(cols[j].innerText);
        csv.push(row.join(","));
      }
      const blob = new Blob([csv.join("\n")], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.setAttribute("href", url);
      a.setAttribute("download", "Trips_Report.csv");
      a.click();
    }
  </script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-blue-100 min-h-screen">
  <?php include 'navbar.php'; ?>

  <div class="ml-64 p-6">
    <div class="max-w-7xl mx-auto bg-white shadow-md rounded-lg p-6">
      <h1 class="text-2xl font-bold mb-6 text-blue-700">Manage Trips</h1>

      <!-- Trip Entry Form -->
      <form method="POST" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block font-semibold mb-1">Vehicle</label>
            <select name="vehicle_id" id="vehicleSelect" class="w-full p-2 border rounded" required>
              <option value="">Select Vehicle</option>
              <?php
              $vehicleList = $conn->query("SELECT vehicle_number, vehicle_name FROM vehicles");
              while($v = $vehicleList->fetch_assoc()):
              ?>
                <option value="<?= htmlspecialchars($v['vehicle_number']) ?>">
                  <?= htmlspecialchars($v['vehicle_number']) ?> - <?= htmlspecialchars($v['vehicle_name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div>
            <label class="block font-semibold mb-1">Driver Name</label>
            <select name="driver_name" id="driverName" class="w-full p-2 border rounded" required>
              <option value="">Select Driver</option>
              <?php
              $drivers = $conn->query("SELECT DISTINCT driver_name FROM vehicles WHERE driver_name != ''");
              while($d = $drivers->fetch_assoc()):
              ?>
                <option value="<?= htmlspecialchars($d['driver_name']) ?>">
                  <?= htmlspecialchars($d['driver_name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div id="routes" class="mt-4">
          <div class="grid grid-cols-6 gap-2 mb-2">
            <input type="text" name="route_name[]" placeholder="Route Name" class="p-2 border rounded">
            <input type="text" name="destination_from[]" placeholder="From" class="p-2 border rounded">
            <input type="text" name="destination_to[]" placeholder="To" class="p-2 border rounded">
            <input type="number" step="0.01" name="distance_km[]" placeholder="Distance (km)" class="p-2 border rounded">
            <input type="text" name="delivery_name[]" placeholder="Delivery Name" class="p-2 border rounded">
            <input type="datetime-local" name="delivery_date[]" class="p-2 border rounded">
          </div>
        </div>

        <button type="button" onclick="addRoute()" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">+ Add Route</button>

        <div>
          <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Save Trip</button>
        </div>
      </form>


    <!-- Filters -->
    <h2 class="text-xl font-semibold mt-8 mb-3 text-gray-700">Filter Trips</h2>
    <form method="GET" class="grid grid-cols-4 gap-3 mb-4">
      <input type="text" name="from" placeholder="From..." value="<?= htmlspecialchars($_GET['from'] ?? '') ?>" class="p-2 border rounded">
      <input type="text" name="to" placeholder="To..." value="<?= htmlspecialchars($_GET['to'] ?? '') ?>" class="p-2 border rounded">
      <input type="text" name="vehicle" placeholder="Vehicle..." value="<?= htmlspecialchars($_GET['vehicle'] ?? '') ?>" class="p-2 border rounded">
      <button type="submit" class="bg-blue-600 text-white rounded px-4 py-2 hover:bg-blue-700">Filter</button>
    </form>

    <div class="flex justify-between mb-3">
      <button onclick="printTable()" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">Print</button>
      <button onclick="downloadCSV()" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">Download CSV</button>
    </div>

    <!-- Trip List -->
    <div class="overflow-x-auto">
      <table id="tripTable" class="w-full border-collapse border border-gray-300 text-sm">
        <thead>
          <tr class="bg-gray-200">
            <th class="border p-2">Driver</th>
            <th class="border p-2">Vehicle</th>
            <th class="border p-2">Route</th>
            <th class="border p-2">From</th>
            <th class="border p-2">To</th>
            <th class="border p-2">Distance (km)</th>
            <th class="border p-2">Delivery Name</th>
            <th class="border p-2">Delivery Date</th>
            <th class="border p-2">Created At</th>
            <th class="border p-2">Approval Status</th>
<th class="border p-2">Approved By</th>
<th class="border p-2">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $trips->fetch_assoc()): ?>
            <tr>
              <td class="border p-2"><?= htmlspecialchars($row['driver_name']) ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['vehicle_id']) ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['route_name']) ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['destination_from']) ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['destination_to']) ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['distance_km']) ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['delivery_name']) ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['delivery_date']) ?></td>
              <td class="border p-2"><?= htmlspecialchars($row['created_at']) ?></td>
              
<!-- Approval Status -->
<td class="border p-2 text-center">
    <?php
    $status = $row['approval_status'] ?? 'Pending';
    ?>

    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold
        <?= $status === 'Approved'
            ? 'bg-green-100 text-green-700'
            : 'bg-yellow-100 text-yellow-700' ?>">

        <?= e($status) ?>
    </span>
</td>

<!-- Approved By -->
<td class="border p-2">
    <?= e($row['approved_by'] ?: 'Not Approved') ?>
</td>

<!-- Edit Button -->
<td class="border p-2 text-center">

    <button
        type="button"
        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs"
        onclick='openApprovalModal(
            <?= (int)$row["id"] ?>,
            <?= json_encode($row["approval_status"] ?? "Pending", JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
            <?= json_encode($row["approved_by"] ?? "", JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
        )'
    >
        Edit
    </button>

</td>

            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

<!-- Edit Trip Approval Modal -->
<div
    id="approvalModal"
    class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4"
>
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-md">

        <h2 class="text-xl font-semibold text-blue-700 mb-5">
            Update Trip Approval
        </h2>

        <form method="POST">

            <input
                type="hidden"
                name="trip_id"
                id="editTripId"
            >

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Approval Status
                </label>

                <select
                    name="approval_status"
                    id="editApprovalStatus"
                    required
                    onchange="toggleApproverRequired()"
                    class="w-full p-2 border rounded-lg"
                >
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                </select>
            </div>

            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Approved By
                </label>

                <input
                    type="text"
                    name="approved_by"
                    id="editApprovedBy"
                    placeholder="Enter approver's name"
                    class="w-full p-2 border rounded-lg"
                >
            </div>

            <div class="flex justify-end gap-3">

                <button
                    type="button"
                    onclick="closeApprovalModal()"
                    class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="update_approval"
                    value="1"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded"
                >
                    Update Approval
                </button>

            </div>

        </form>
    </div>
</div>

<script>
function openApprovalModal(id, status, approvedBy) {
    document.getElementById('editTripId').value = id;

    document.getElementById('editApprovalStatus').value =
        status || 'Pending';

    document.getElementById('editApprovedBy').value =
        approvedBy || '';

    toggleApproverRequired();

    const modal = document.getElementById('approvalModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeApprovalModal() {
    const modal = document.getElementById('approvalModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function toggleApproverRequired() {
    const status = document.getElementById('editApprovalStatus').value;
    const approvedBy = document.getElementById('editApprovedBy');

    approvedBy.required = status === 'Approved';
    approvedBy.disabled = status === 'Pending';

    if (status === 'Pending') {
        approvedBy.value = '';
    }
}

// Close by clicking outside
document.getElementById('approvalModal').addEventListener(
    'click',
    function(event) {
        if (event.target === this) {
            closeApprovalModal();
        }
    }
);

// Close using Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeApprovalModal();
    }
});
</script>

</body>
</html>
