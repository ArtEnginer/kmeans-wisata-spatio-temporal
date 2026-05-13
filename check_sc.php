<?php
// Database configuration
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

// Query
$sql = "SELECT sc FROM evaluasi LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Output data
    while($row = $result->fetch_assoc()) {
        echo "Nilai SC: " . $row["sc"] . "\n";
    }
} else {
    echo "No results found";
}

$conn->close();
?>
