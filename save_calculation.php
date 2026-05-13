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
        if (!isset($input['sc'], $input['dbi'], $input['chi'], $input['wcss'], $input['iter'])) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'Missing required fields']));
        }

        $sc = floatval($input['sc']);
        $dbi = floatval($input['dbi']);
        $chi = floatval($input['chi']);
        $wcss = floatval($input['wcss']);
        $iter = intval($input['iter']);

        if ($sc < -1 || $sc > 1) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'SC must be between -1 and 1']));
        }
        if ($dbi < 0) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'DBI must be non-negative']));
        }
        if ($wcss < 0) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'WCSS must be non-negative']));
        }
        if ($iter < 0) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'Iterations must be non-negative']));
        }

        $checkSql = "SELECT id FROM evaluasi ORDER BY id DESC LIMIT 1";
        $checkResult = $conn->query($checkSql);

        if ($checkResult && $checkResult->num_rows > 0) {
            $row = $checkResult->fetch_assoc();
            $latestId = $row['id'];

            $updateSql = "UPDATE evaluasi SET sc = ?, dbi = ?, chi = ?, wcss = ?, iter = ? WHERE id = ?";
            $stmt = $conn->prepare($updateSql);

            if (!$stmt) {
                http_response_code(500);
                exit(json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]));
            }

            $stmt->bind_param('ddddii', $sc, $dbi, $chi, $wcss, $iter, $latestId);

            if (!$stmt->execute()) {
                http_response_code(500);
                exit(json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]));
            }

            $affected = $stmt->affected_rows;
            $stmt->close();

            http_response_code(200);
            exit(json_encode(['success' => true, 'message' => 'Data evaluasi berhasil diperbarui', 'affected_rows' => $affected]));
        } else {
            $insertSql = "INSERT INTO evaluasi (sc, dbi, chi, wcss, iter) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertSql);

            if (!$stmt) {
                http_response_code(500);
                exit(json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]));
            }

            $stmt->bind_param('ddddi', $sc, $dbi, $chi, $wcss, $iter);

            if (!$stmt->execute()) {
                http_response_code(500);
                exit(json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]));
            }

            $insertId = $conn->insert_id;
            $stmt->close();

            http_response_code(201);
            exit(json_encode(['success' => true, 'message' => 'Data evaluasi berhasil disimpan', 'inserted_id' => $insertId]));
        }
    } else {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Unknown action']));
    }
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
}
