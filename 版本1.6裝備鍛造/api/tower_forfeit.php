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
$accumulated_gold = max(0, (int)($run['gold'] ?? 0));
$accumulated_exp  = max(0, (int)($run['exp']  ?? 0));

// 若已有累積獎勵則先發放（中途離開不等於失敗）
if ($accumulated_gold > 0 || $accumulated_exp > 0) {
    $stmt = $conn->prepare("UPDATE users SET gold=gold+?, exp=exp+? WHERE id=?");
    $stmt->bind_param('iii', $accumulated_gold, $accumulated_exp, $user_id);
    $stmt->execute();
    $stmt->close();
}

// 冷卻只在「尚未贏得當層」時套用（非撤退、非通關）
$run_state = $run['state'] ?? 'auto';
if (!in_array($run_state, ['retreat', 'dead'], true)) {
    // 中途離開 → 算棄賽，套冷卻
    $stmt = $conn->prepare("UPDATE users SET tower_fail_until=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

unset($_SESSION['run']);
echo json_encode(['ok' => true]);
