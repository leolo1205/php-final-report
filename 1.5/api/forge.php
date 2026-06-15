<?php
header('Content-Type: application/json; charset=utf-8');
$t_start = microtime(true);

require_once '../db.php';
require_once '../lib/session.php';
require_once '../lib/equipment.php';

$user_id = get_player_id();
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => '未登入', 'code' => 401]);
    exit;
}

// 寫入操作才驗證 CSRF（GET 狀態查詢不需要）
$action = trim($_REQUEST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    echo json_encode(['success' => false, 'message' => '安全驗證失敗', 'code' => 403]);
    exit;
}

$result = [];

try {
    switch ($action) {

        case 'get_status':
            $eq    = get_equipment($conn, $user_id);
            $user  = $conn->query("SELECT gold FROM users WHERE id=$user_id")->fetch_assoc();
            $table = get_upgrade_table();
            $data  = [];
            foreach (['weapon','armor','helmet'] as $t) {
                $lv = (int)$eq[$t]['level'];
                $data[$t] = [
                    'level'     => $lv,
                    'bonus'     => $lv * equip_bonus_per_level($t),
                    'attempts'  => (int)$eq[$t]['attempts'],
                    'successes' => (int)$eq[$t]['successes'],
                    'next_cost'   => $lv < 10 ? $table[$lv]['cost']   : null,
                    'next_chance' => $lv < 10 ? $table[$lv]['chance'] : null,
                    'maxed'     => ($lv >= 10),
                ];
            }
            $result = ['success' => true, 'equipment' => $data, 'gold' => (int)$user['gold']];
            break;

        case 'upgrade':
            $type   = $_REQUEST['type'] ?? '';
            $r      = upgrade_equipment($conn, $user_id, $type);
            $user   = $conn->query("SELECT gold FROM users WHERE id=$user_id")->fetch_assoc();
            $r['gold'] = (int)$user['gold'];
            $result = $r;
            break;

        default:
            $result = ['success' => false, 'message' => '未知的 action'];
    }
} catch (Exception $e) {
    error_log('forge.php exception: ' . $e->getMessage());
    $result = ['success' => false, 'message' => '伺服器發生錯誤，請稍後再試'];
}

$result['_ms'] = (int)((microtime(true) - $t_start) * 1000);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
