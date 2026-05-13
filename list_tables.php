<?php
// Database configuration - try with muqorobin_wisata
$servername = "localhost";
$username = "root";
$password = "";
$database = "muqorobin_wisata";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// List all tables
$sql = "SHOW TABLES";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Available tables:\n";
    while($row = $result->fetch_assoc()) {
        echo "- " . reset($row) . "\n";
    }
} else {
    echo "No tables found";
}

$conn->close();
?>
