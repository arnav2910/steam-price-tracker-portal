<?php

$conn = mysqli_connect("localhost", "root", "", "steam_tracker");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>