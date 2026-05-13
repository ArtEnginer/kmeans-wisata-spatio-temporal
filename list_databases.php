<?php
// Database configuration - try without specifying database first
$servername = "localhost";
$username = "root";
$password = "";

// Create connection (without database)
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// List all databases
$sql = "SHOW DATABASES";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Available databases:\n";
    while($row = $result->fetch_assoc()) {
        echo "- " . $row["Database"] . "\n";
    }
} else {
    echo "No databases found";
}

$conn->close();
?>
