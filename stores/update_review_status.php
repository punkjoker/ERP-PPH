<?php
include 'db_con.php';

if (isset($_POST['review_id'], $_POST['response'])) {
    $review_id = intval($_POST['review_id']);
    $response = $_POST['response'] === 'Yes' ? 'Yes' : 'No';

    // If review exists, update it; if not, insert new
    $check = $conn->prepare("SELECT id FROM quality_manager_review WHERE qc_inspection_id = ?");
    $check->bind_param("i", $review_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE quality_manager_review SET response = ? WHERE qc_inspection_id = ?");
        $stmt->bind_param("si", $response, $review_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO quality_manager_review (qc_inspection_id, response) VALUES (?, ?)");
        $stmt->bind_param("is", $review_id, $response);
    }

    if ($stmt->execute()) {
        echo "Status updated successfully.";
    } else {
        echo "Error updating status: " . $conn->error;
    }

    $stmt->close();
}
?>
