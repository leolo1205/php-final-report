<?php
session_start();
require_once '../db.php';
require_once '../lib/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false]); exit;
}

$user_id = get_player_id();
if (!$user_id || empty($_SESSION['run'])) {
    echo json_encode(['ok' => false]); exit;
}

if (!csrf_verify()) {
    echo json_encode(['ok' => false, 'error' => 'csrf']); exit;
}

$run = $_SESSION['run'];

// 中途關閉視窗不給任何獎勵（exp/gold 需按結算按鈕才領取）
// 套用失敗冷卻（非撤退、非通關時）
$run_state = $run['state'] ?? 'auto';
if (!in_array($run_state, ['retreat', 'dead'], true)) {
    $stmt = $conn->prepare("UPDATE users SET tower_fail_until=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

unset($_SESSION['run']);
echo json_encode(['ok' => true]);
