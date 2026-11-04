<?php
echo "PHP Version: " . PHP_VERSION . "<br>";

if (class_exists('ZipArchive')) {
    echo "SUCCESS: ZipArchive is available.";
} else {
    echo "ERROR: ZipArchive class is missing.";
}

echo "<br>Loaded extensions:<br>";
echo extension_loaded('zip') ? "ZIP extension loaded" : "ZIP extension NOT loaded";
?>