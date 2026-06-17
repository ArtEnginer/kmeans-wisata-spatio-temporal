<?php
ob_clean();
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['action'])) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Invalid request']));
    }

    $action = $input['action'];

    if ($action === 'update_evaluasi') {
        $results = runKMeansClustering($conn);
        if (!$results['success']) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => $results['message']]));
        }

        $saveRes = saveKMeansClusteringResults($conn, $results);
        if (!$saveRes['success']) {
            http_response_code(500);
            exit(json_encode(['success' => false, 'message' => $saveRes['message']]));
        }

        http_response_code(200);
        exit(json_encode(['success' => true, 'message' => $saveRes['message']]));
    } else {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Unknown action']));
    }
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
}
