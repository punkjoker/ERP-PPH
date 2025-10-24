<?php
include 'db_con.php';

$run_id = intval($_POST['production_run_id'] ?? 0);

if ($run_id <= 0) {
    die("Invalid production run ID.");
}

// ✅ Step 1: Define checklist items
$checklist_items = [
  0 => "All production processes have been fully followed and complied",
  1 => "Quality Control Processes have been fully followed",
  2 => "All QC reports are duly filled, recorded, and signed",
  3 => "Final product complies with standard specifications",
  4 => "Final product complies with packaging specifications",
  5 => "Retain sample collected and stored",
  6 => "All blank spaces have been fully filled",
  7 => "Certificate of Analysis complies with test results",
  8 => "Product released for sale"
];

// ✅ Step 2: Delete any previous review for this production run (to avoid duplicates)
$conn->query("DELETE FROM quality_manager_review WHERE production_run_id = $run_id");

// ✅ Step 3: Insert checklist responses
foreach ($_POST as $key => $val) {
    if (strpos($key, 'checklist_') === 0) {
        $num = intval(str_replace('checklist_', '', $key));
        $item = $checklist_items[$num] ?? '';
        $response = ($val === 'Yes') ? 'Yes' : 'No';

        $stmt = $conn->prepare("
  INSERT INTO quality_manager_review (production_run_id, checklist_no, checklist_item, response, status)
  VALUES (?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE response = VALUES(response), status = VALUES(status)
");

        $status = ($response === 'Yes') ? 'Approved' : 'Pending';
        $stmt->bind_param("iisss", $run_id, $num, $item, $response, $status);
        $stmt->execute();
        $stmt->close();
    }
}

// ✅ Step 4: Redirect with success message
header("Location: quality_manager_review.php?msg=Review+saved+successfully");
exit;
?>
