<?php
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "carrental";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>