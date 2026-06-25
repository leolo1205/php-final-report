<?php
/**
 * 技能樹 API
 * actions: get_status / select_archetype / unlock_node
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'get_status' && !csrf_verify()) {
    echo json_encode(['success' => false, 'message' => '安全驗證失敗', 'code' => 403]);
    exit;
}

$result = [];

try {
    switch ($action) {

        // ── 取得目前技能樹狀態 ──
        case 'get_status':
            $build = get_skill_build($conn, $user_id);
            $bonus = get_skill_stat_bonus($build);
            $costs = get_node_costs();
            $user  = $conn->query("SELECT gold FROM users WHERE id=$user_id")->fetch_assoc();
            $next_node = (int)$build['nodes_unlocked'] + 1;
            $result = [
                'success'        => true,
                'archetype'      => $build['archetype'],
                'nodes_unlocked' => (int)$build['nodes_unlocked'],
                'stat_bonus'     => $bonus,
                'gold'           => (int)$user['gold'],
                'next_cost'      => $next_node <= 9 ? $costs[$next_node] : null,
                'nodes'          => $build['archetype'] ? get_archetype_nodes()[$build['archetype']] : null,
            ];
            break;

        // ── 選擇（或更換）流派 ──
        case 'select_archetype':
            $arch  = $_POST['archetype'] ?? '';
            $valid = ['assault', 'guardian', 'vitality'];
            if (!in_array($arch, $valid, true)) {
                $result = ['success' => false, 'message' => '無效的流派'];
                break;
            }
            $build = get_skill_build($conn, $user_id);
            $user  = $conn->query("SELECT gold FROM users WHERE id=$user_id")->fetch_assoc();
            $gold  = (int)$user['gold'];

            // 已有流派 → 換流派需 2000 金且重置節點
            if ($build['archetype'] !== null && $build['archetype'] !== $arch) {
                if ($gold < 2000) {
                    $result = ['success' => false, 'message' => '金幣不足（需要 2000 金更換流派）'];
                    break;
                }
                $conn->query("UPDATE users SET gold=gold-2000 WHERE id=$user_id");
                $stmt = $conn->prepare("INSERT INTO user_skill_build (user_id,archetype,nodes_unlocked) VALUES (?,?,0) ON DUPLICATE KEY UPDATE archetype=?,nodes_unlocked=0");
                $stmt->bind_param('iss', $user_id, $arch, $arch);
                $stmt->execute();
                $result = ['success' => true, 'message' => "已更換為 {$arch} 流派（扣除 2000 金，節點重置）", 'gold' => $gold - 2000];
            } else {
                // 首次選擇，免費
                $stmt = $conn->prepare("INSERT INTO user_skill_build (user_id,archetype,nodes_unlocked) VALUES (?,?,0) ON DUPLICATE KEY UPDATE archetype=?");
                $stmt->bind_param('iss', $user_id, $arch, $arch);
                $stmt->execute();
                $result = ['success' => true, 'message' => "已選擇 {$arch} 流派", 'gold' => $gold];
            }
            break;

        // ── 解鎖下一個節點 ──
        case 'unlock_node':
            $build = get_skill_build($conn, $user_id);
            if (!$build['archetype']) {
                $result = ['success' => false, 'message' => '請先選擇流派'];
                break;
            }
            $current = (int)$build['nodes_unlocked'];
            if ($current >= 9) {
                $result = ['success' => false, 'message' => '已解鎖所有節點'];
                break;
            }
            $next  = $current + 1;
            $costs = get_node_costs();
            $cost  = $costs[$next];
            $user  = $conn->query("SELECT gold FROM users WHERE id=$user_id")->fetch_assoc();
            $gold  = (int)$user['gold'];

            if ($gold < $cost) {
                $result = ['success' => false, 'message' => "金幣不足（需要 {$cost} 金）"];
                break;
            }
            $conn->query("UPDATE users SET gold=gold-$cost WHERE id=$user_id");
            $stmt = $conn->prepare("UPDATE user_skill_build SET nodes_unlocked=? WHERE user_id=?");
            $stmt->bind_param('ii', $next, $user_id);
            $stmt->execute();

            $nodes  = get_archetype_nodes()[$build['archetype']];
            $node   = $nodes[$next];
            $result = [
                'success'        => true,
                'message'        => "節點 {$next} 解鎖：{$node['label']}",
                'nodes_unlocked' => $next,
                'gold'           => $gold - $cost,
                'node'           => $node,
            ];
            break;

        default:
            $result = ['success' => false, 'message' => '未知的 action', 'code' => 400];
    }
} catch (Exception $e) {
    error_log('skill_build.php exception: ' . $e->getMessage());
    $result = ['success' => false, 'message' => '伺服器發生錯誤，請稍後再試'];
}

$result['_ms'] = (int)((microtime(true) - $t_start) * 1000);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
