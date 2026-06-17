<?php
// Database configuration
require_once __DIR__ . '/env.php';
loadEnv(__DIR__ . '/.env');

$servername = env('DB_HOST', 'localhost');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');
$port = env('DB_PORT', '3306');

// Create connection (without database)
$conn = new mysqli($servername, $username, $password, null, intval($port));

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
