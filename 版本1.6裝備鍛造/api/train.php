<?php
/**
 * 訓練 API
 * 回傳格式：JSON
 * 支援 actions：cooldown_check / start_train / claim_reward / add_stat
 */
header('Content-Type: application/json; charset=utf-8');
$t_start = microtime(true);

require_once '../db.php';
require_once '../lib/session.php';
require_once '../lib/functions.php';

$user_id = get_player_id();
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => '未登入', 'code' => 401]);
    exit;
}

$action = trim($_REQUEST['action'] ?? '');

// 寫入操作需驗證 CSRF（cooldown_check 為唯讀，跳過）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'cooldown_check' && !csrf_verify()) {
    echo json_encode(['success' => false, 'message' => '安全驗證失敗', 'code' => 403]);
    exit;
}

$result = [];
$status = 'success';

try {
    switch ($action) {

        // ── 冷卻判定 ──
        case 'cooldown_check':
            $cooldown = check_training_cooldown($conn, $user_id);
            $stmt = $conn->prepare("SELECT level, exp, stat_points, last_train_time FROM users WHERE id=?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $result = [
                'success'           => true,
                'is_training'       => $cooldown['is_training'],
                'seconds_remaining' => $cooldown['seconds_remaining'],
                'cooldown_total'    => $cooldown['duration_sec'],
                'stat_points'       => (int)$user['stat_points'],
                'exp'               => (int)$user['exp'],
                'level'             => (int)$user['level'],
                'exp_needed'        => level_exp_required((int)$user['level']),
            ];
            break;

        // ── 開始訓練（立即發獎）──
        case 'start_train':
            $plan_key = $_POST['plan'] ?? 'short';
            if (!in_array($plan_key, ['short', 'medium', 'long'], true)) $plan_key = 'short';
            $r = start_training($conn, $user_id, $plan_key);
            if ($r['success']) {
                $lv = process_levelup($conn, $user_id);
                $r['leveled_up']    = $lv['leveled_up'];
                $r['new_level']     = $lv['new_level'];
                $r['levels_gained'] = $lv['levels_gained'];
            }
            $result = $r;
            if (!$r['success']) $status = 'fail';
            break;

        // ── 屬性配點 ──
        case 'add_stat':
            $stat = $_POST['stat'] ?? '';
            $map = ['dmg' => 'dmg=dmg+3', 'hp' => 'max_hp=max_hp+10,hp=hp+10', 'def' => 'def=def+1'];
            if (!isset($map[$stat])) {
                $result = ['success' => false, 'message' => '無效的屬性類型']; $status = 'fail'; break;
            }
            $stmt = $conn->prepare("SELECT stat_points FROM users WHERE id=?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ((int)$user['stat_points'] <= 0) {
                $result = ['success' => false, 'message' => '沒有可用的屬性點']; $status = 'fail'; break;
            }
            // $map[$stat] 僅含固定字串（已白名單驗證），$user_id 參數化
            $stmt2 = $conn->prepare("UPDATE users SET {$map[$stat]}, stat_points=stat_points-1 WHERE id=?");
            $stmt2->bind_param('i', $user_id);
            $stmt2->execute();
            $stmt2->close();
            $gains = ['dmg' => '傷害 +3', 'hp' => 'HP上限 +10', 'def' => '防禦 +1'];
            $result = ['success' => true, 'message' => "分配完成：{$gains[$stat]}", 'stat' => $stat];
            break;

        default:
            $result = ['success' => false, 'message' => '未知的 action', 'code' => 400];
            $status = 'fail';
    }
} catch (Exception $e) {
    error_log('train.php exception: ' . $e->getMessage());
    $result = ['success' => false, 'message' => '伺服器發生錯誤，請稍後再試'];
    $status = 'fail';
}

$ms = (int)(( microtime(true) - $t_start ) * 1000);
log_api($conn, 'train', $action, $user_id, $status, $ms, ['action' => $action], $result);
$result['_ms'] = $ms;

echo json_encode($result, JSON_UNESCAPED_UNICODE);
