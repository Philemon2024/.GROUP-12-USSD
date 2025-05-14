<?php
$conn = new mysqli("localhost", "root", "", "12GROUP");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
