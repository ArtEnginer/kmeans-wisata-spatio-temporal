<?php
require_once __DIR__ . '/config.php';

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
