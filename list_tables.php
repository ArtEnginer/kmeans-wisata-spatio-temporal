<?php
require_once __DIR__ . '/config.php';

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
