<?php
include 'db_con.php';

$run_id = $_POST['production_run_id'];
$qc_status = $_POST['qc_status'];

// ✅ Step 1: Insert QC header only once
$conn->query("INSERT INTO qc_inspections (production_run_id, qc_status) VALUES ($run_id, '$qc_status')");
$qc_id = $conn->insert_id;

// ✅ Step 2: Insert Tests (skip blanks)
if (isset($_POST['tests'])) {
  foreach ($_POST['tests'] as $i => $test) {
    $test = trim($test ?? '');
    $spec = trim($_POST['specs'][$i] ?? '');
    $proc = trim($_POST['procedures'][$i] ?? '');

    // 🔒 Skip empty entries
    if ($test === '' && $spec === '' && $proc === '') continue;

    // 👇 Insert into a *separate table* or reuse qc_inspections if no table yet
    $conn->query("INSERT INTO qc_inspections (production_run_id, test_name, specification, procedure_done, qc_status)
                  VALUES ($run_id, '$test', '$spec', '$proc', '$qc_status')");
  }
}

// ✅ Step 3: Insert Packaging if Approved
if ($qc_status === 'Approved Product' && isset($_POST['item'])) {
  foreach ($_POST['item'] as $i => $item) {
    $item = trim($item ?? '');
    if ($item === '') continue; // skip empty rows

    $issued = $_POST['issued'][$i] ?? 0;
    $used = $_POST['used'][$i] ?? 0;
    $wasted = $_POST['wasted'][$i] ?? 0;
    $balance = $_POST['balance'][$i] ?? 0;
    $qty = $_POST['qty'][$i] ?? 0;
    $yield = $_POST['yield'][$i] ?? 0;
    $unit = $_POST['unit'][$i] ?? '';
    $cost = $_POST['cost'][$i] ?? 0;
    $total = $_POST['total'][$i] ?? 0;

    $conn->query("INSERT INTO packaging_reconciliation 
      (qc_inspection_id, item_name, issued, used, wasted, balance, quantity_achieved, yield_percent, units, cost_per_unit, total_cost)
      VALUES ($qc_id, '$item', '$issued', '$used', '$wasted', '$balance', '$qty', '$yield', '$unit', '$cost', '$total')");
  }
}

// ✅ Step 4: Insert Checklist responses
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
    $conn->query("INSERT INTO quality_manager_review (qc_inspection_id, checklist_no, checklist_item, response)
                  VALUES ($qc_id, $num+1, '$item', '$val')");
  }
}

// ✅ Step 5: Redirect
header("Location: inspect_finished_products.php?msg=QC+inspection+saved");
exit;
?>
