<?php
require_once __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $db = getDBConnection();
    
    $fileId = $_POST['file_id'];
    $reason = $_POST['reason'];
    
    $stmt = $db->prepare("INSERT INTO file_reports (file_id, reported_by, reason) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $fileId, $_SESSION['user_id'], $reason);
    $stmt->execute();
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
