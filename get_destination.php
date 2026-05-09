<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $destination = getDestinationById($conn, $id);

    if ($destination) {
        header('Content-Type: application/json');
        echo json_encode($destination);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Destination not found']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID parameter required']);
}
