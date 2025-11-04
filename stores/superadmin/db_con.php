<?php
$host = "localhost";      // XAMPP default
$user = "root";           // XAMPP default
$pass = "";               // no password by default
$dbname = "lynntech_manager";     // your DB name (create in phpMyAdmin)

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
