<?php
include 'db_con.php';

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
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>
