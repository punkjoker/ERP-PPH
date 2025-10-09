<?php
session_start();
require 'db_con.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_chemical'])) {
    $id = intval($_POST['id']);
    $chemical_name = trim($_POST['chemical_name']);
    $batch_no = trim($_POST['batch_no']);
    $rm_lot_no = trim($_POST['rm_lot_no']);
    $std_quantity = floatval($_POST['std_quantity']);
    $remaining_quantity = floatval($_POST['remaining_quantity']);
    $total_cost = floatval($_POST['total_cost']);
    $unit_price = floatval($_POST['unit_price']);
    $date_added = $_POST['date_added'];

    $stmt = $conn->prepare("UPDATE chemicals_in 
        SET chemical_name = ?, 
            batch_no = ?, 
            rm_lot_no = ?, 
            std_quantity = ?, 
            remaining_quantity = ?, 
            total_cost = ?, 
            unit_price = ?, 
            date_added = ?
        WHERE id = ?");
    $stmt->bind_param("sssddddsi", $chemical_name, $batch_no, $rm_lot_no, $std_quantity, $remaining_quantity, $total_cost, $unit_price, $date_added, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Chemical updated successfully!'); window.location.href='chemicals_in.php';</script>";
    } else {
        echo "<script>alert('Error updating chemical. Please try again.'); window.history.back();</script>";
    }
}
?>
