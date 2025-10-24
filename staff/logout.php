<?php
session_start();
session_destroy(); // destroy all sessions
header("Location: ../login.php"); // adjust path to your login page
exit;
?>
