<?php
include 'db_con.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['training_id']);
    $name = $_POST['training_name'];
    $date = $_POST['training_date'];
    $status = $_POST['status'];
    $done_by = $_POST['done_by'];
    $approved_by = $_POST['approved_by'];

    $stmt = $conn->prepare("UPDATE trainings SET training_name=?, training_date=?, status=?, done_by=?, approved_by=? WHERE training_id=?");
    $stmt->bind_param("sssssi", $name, $date, $status, $done_by, $approved_by, $id);

    if ($stmt->execute()) {
        header("Location: view_training.php?id=" . $id);
        exit;
    } else {
        echo "Error updating training.";
    }
}
