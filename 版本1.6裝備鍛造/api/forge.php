<?php
header('Content-Type: application/json; charset=utf-8');
$t_start = microtime(true);

require_once '../db.php';
require_once '../lib/session.php';
require_once '../lib/functions.php';

$user_id = get_player_id();
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => '未登入', 'code' => 401], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim($_REQUEST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    echo json_encode(['success' => false, 'message' => '安全驗證失敗', 'code' => 403], JSON_UNESCAPED_UNICODE);
    exit;
}


$status = 'success';
$result = ['success' => false, 'message' => '未知的 action'];

try {
    switch ($action) {
        case 'get_status':
            $eq = get_equipment($conn, $user_id);
            $user = $conn->query("SELECT gold FROM users WHERE id=$user_id")->fetch_assoc();

            $max_level = forge_max_level();
            $equipment = [];

            foreach (['weapon', 'armor', 'helmet'] as $type) {
                $level = (int)$eq[$type]['level'];
                $attempts = (int)$eq[$type]['attempts'];
                $successes = (int)$eq[$type]['successes'];
                $multiplier = equipment_multiplier($level);

                $equipment[$type] = [
                    'level' => $level,
                    'bonus' => 0,
                    'multiplier' => $multiplier,
                    'multiplier_percent' => round(($multiplier - 1) * 100, 2),
                    'attempts' => $attempts,
                    'successes' => $successes,
                    'fails' => max(0, $attempts - $successes),
                    'next_cost' => $level < $max_level ? forge_upgrade_cost($level) : null,
                    'next_chance' => $level < $max_level ? forge_upgrade_chance($level) : null,
                    'maxed' => ($level >= $max_level),
                ];
            }

            $result = [
                'success' => true,
                'gold' => (int)($user['gold'] ?? 0),
                'max_level' => $max_level,
                'equipment' => $equipment,
            ];
            break;

        case 'upgrade':
            $type = $_REQUEST['type'] ?? '';
            $valid_types = ['weapon', 'armor', 'helmet'];

            if (!in_array($type, $valid_types, true)) {
                $result = ['success' => false, 'message' => '無效的裝備類型'];
                $status = 'fail';
                break;
            }

            $r = upgrade_equipment($conn, $user_id, $type);

            $user = $conn->query("SELECT gold FROM users WHERE id=$user_id")->fetch_assoc();
            $r['gold'] = (int)($user['gold'] ?? 0);

            $eq = get_equipment($conn, $user_id);
            $level = (int)$eq[$type]['level'];
            $attempts = (int)$eq[$type]['attempts'];
            $successes = (int)$eq[$type]['successes'];
            $max_level = forge_max_level();
            $multiplier = equipment_multiplier($level);

            $r['equipment'] = [
                'type' => $type,
                'level' => $level,
                'bonus' => 0,
                'multiplier' => $multiplier,
                'multiplier_percent' => round(($multiplier - 1) * 100, 2),
                'attempts' => $attempts,
                'successes' => $successes,
                'fails' => max(0, $attempts - $successes),
                'next_cost' => $level < $max_level ? forge_upgrade_cost($level) : null,
                'next_chance' => $level < $max_level ? forge_upgrade_chance($level) : null,
                'maxed' => ($level >= $max_level),
            ];

            $result = $r;
            if (empty($r['success'])) {
                $status = 'fail';
            }
            break;

        default:
            $result = ['success' => false, 'message' => '未知的 action'];
            $status = 'fail';
            break;
    }
} catch (Throwable $e) {
    error_log('api/forge.php exception: ' . $e->getMessage());
    $result = ['success' => false, 'message' => '伺服器發生錯誤，請稍後再試'];
    $status = 'fail';
}

$ms = (int)((microtime(true) - $t_start) * 1000);
if (function_exists('log_api')) {
    log_api($conn, 'forge', $action, $user_id, $status, $ms, $_REQUEST, $result);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);