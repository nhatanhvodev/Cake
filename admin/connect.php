<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "banh_store";

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
?>