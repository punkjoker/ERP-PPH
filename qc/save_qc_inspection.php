<?php
include 'db_con.php';

$run_id = intval($_POST['production_run_id']);
$qc_status = trim($_POST['qc_status']);

// ✅ Step 1: Check if this production run already has a QC inspection
$stmt = $conn->prepare("SELECT id FROM qc_inspections WHERE production_run_id = ?");
$stmt->bind_param("i", $run_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // If already exists, reuse it (no new blank insert)
    $stmt->bind_result($qc_id);
    $stmt->fetch();

    // Optionally update status
    $update = $conn->prepare("UPDATE qc_inspections SET qc_status = ? WHERE id = ?");
    $update->bind_param("si", $qc_status, $qc_id);
    $update->execute();
    $update->close();
} else {
    // Only insert a new record if none exists yet
    $insert = $conn->prepare("INSERT INTO qc_inspections (production_run_id, qc_status) VALUES (?, ?)");
    $insert->bind_param("is", $run_id, $qc_status);
    $insert->execute();
    $qc_id = $insert->insert_id;
    $insert->close();
}
$stmt->close();

// ✅ Step 2: Insert QC tests into qc_tests
if (!empty($_POST['tests'])) {
    foreach ($_POST['tests'] as $i => $test) {
        $test = trim($_POST['tests'][$i] ?? '');
        $spec = trim($_POST['specs'][$i] ?? '');
        $proc = trim($_POST['procedures'][$i] ?? '');

        if ($test === '' && $spec === '' && $proc === '') continue;

        $stmt = $conn->prepare("
            INSERT INTO qc_tests (qc_inspection_id, test_name, specification, procedure_done)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $qc_id, $test, $spec, $proc);
        $stmt->execute();
        $stmt->close();
    }
}

// ✅ Step 3: Packaging (only if Approved)
if ($qc_status === 'Approved Product' && isset($_POST['item'])) {
    foreach ($_POST['item'] as $i => $item) {
        $item = trim($item ?? '');
        if ($item === '') continue;

        $issued = $_POST['issued'][$i] ?? 0;
        $used = $_POST['used'][$i] ?? 0;
        $wasted = $_POST['wasted'][$i] ?? 0;
        $balance = $_POST['balance'][$i] ?? 0;
        $qty = $_POST['qty'][$i] ?? 0;
        $yield = $_POST['yield'][$i] ?? 0;
        $unit = $_POST['unit'][$i] ?? '';
        $cost = $_POST['cost'][$i] ?? 0;
        $total = $_POST['total'][$i] ?? 0;

        $stmt = $conn->prepare("
            INSERT INTO packaging_reconciliation 
            (qc_inspection_id, item_name, issued, used, wasted, balance, quantity_achieved, yield_percent, units, cost_per_unit, total_cost)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isddddddssd", $qc_id, $item, $issued, $used, $wasted, $balance, $qty, $yield, $unit, $cost, $total);
        $stmt->execute();
        $stmt->close();
    }
}

// ✅ Step 4: Quality Manager Review
$checklist_items = [
  "All production processes have been fully followed and complied",
  "Quality Control Processes have been fully followed.",
  "All QC reports are duly filled, recorded, and signed.",
  "Final product complies with standard specifications.",
  "Final product complies with packaging specifications.",
  "Retain sample collected and stored.",
  "All blank spaces have been fully filled.",
  "Certificate of Analysis complies with test results.",
  "Product released for sale."
];

foreach ($_POST as $key => $val) {
    if (strpos($key, 'checklist_') === 0) {
        $num = intval(explode('_', $key)[1]);
        $item = $checklist_items[$num] ?? '';
        $stmt = $conn->prepare("
            INSERT INTO quality_manager_review (qc_inspection_id, checklist_no, checklist_item, response)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiss", $qc_id, $num, $item, $val);
        $stmt->execute();
        $stmt->close();
    }
}

// ✅ Redirect
header("Location: inspect_finished_products.php?msg=QC+inspection+saved");
exit;
?>
