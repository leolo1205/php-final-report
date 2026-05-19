<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
$user = require_login();
$char = get_character($user['id']);

session_start_once();
if (empty($_SESSION['battle'])) {
    echo json_encode(['error' => 'no battle']); exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$action  = $data['action'] ?? '';
$battle  = &$_SESSION['battle'];
$monster = &$battle['monster'];
$logs    = [];

function log_msg(string $msg, string $type = ''): array { return ['msg' => $msg, 'type' => $type]; }

// ── 敵人先手 ─────────────────────────────────────────────────
if ($action === 'enemy_first') {
    [$logs, $over] = enemy_turn($battle, $char, $logs, false);
    if ($over) { echo finish($battle, $char, $logs); exit; }
    echo json_encode(['logs'=>$logs,'char_hp'=>$battle['char_hp'],'monster_hp'=>$monster['hp'],'over'=>false]);
    exit;
}

// ── 玩家行動 ─────────────────────────────────────────────────
$defending = false;

if ($action === 'flee') {
    $chance = 0.30 + $char['cha'] * 0.02;
    if (mt_rand(1,100) <= $chance * 100) {
        $logs[] = log_msg('成功逃脫！', 'system');
        unset($_SESSION['battle']);
        echo json_encode(['logs'=>$logs,'char_hp'=>$battle['char_hp'],'monster_hp'=>$monster['hp'],'over'=>true,'result'=>'flee','result_title'=>'逃跑成功','result_desc'=>'你安全撤退了。']);
        exit;
    }
    $logs[] = log_msg('逃跑失敗！敵人反擊！', 'enemy');
    [$logs, $over] = enemy_turn($battle, $char, $logs, false);
    if ($over) { echo finish($battle, $char, $logs); exit; }
    echo json_encode(['logs'=>$logs,'char_hp'=>$battle['char_hp'],'monster_hp'=>$monster['hp'],'over'=>false]);
    exit;
}

if ($action === 'defend') {
    $defending = true;
    $logs[] = log_msg('你擺出防禦姿態，本回合傷害減半。', 'player');
} else {
    // 普通攻擊
    if (mt_rand(1,1000) <= dodge_chance($monster['spd']) * 1000) {
        $logs[] = log_msg("{$monster['name']} 閃避了你的攻擊！", 'enemy');
    } else {
        ['dmg'=>$dmg,'crit'=>$crit] = calc_damage($char['str'], $monster['def'], $char['per']);
        $monster['hp'] = max(0, $monster['hp'] - $dmg);
        $logs[] = $crit
            ? log_msg("暴擊！你對 {$monster['name']} 造成 {$dmg} 點傷害！", 'crit')
            : log_msg("你對 {$monster['name']} 造成 {$dmg} 點傷害。", 'player');
    }

    if ($monster['hp'] <= 0) {
        // 勝利
        $exp = $monster['exp_reward'];
        $leveled = apply_exp($user['id'], $exp);
        $newChar = get_character($user['id']);
        $newFloor = $char['max_floor'] + 1;
        db()->prepare('UPDATE users SET max_floor=?, hp=? WHERE id=?')
             ->execute([$newFloor, $battle['char_hp'], $user['id']]);
        db()->prepare('INSERT INTO battle_records (user_id,floor,result,exp_gain,gold_gain) VALUES (?,?,?,?,?)')
             ->execute([$user['id'], $char['max_floor'], 'win', $exp, 0]);
        unset($_SESSION['battle']);
        $desc = $leveled
            ? "獲得 {$exp} EXP 並升級至 Lv.{$newChar['level']}！進入第 {$newFloor} 層。"
            : "獲得 {$exp} EXP！進入第 {$newFloor} 層。";
        echo json_encode(['logs'=>$logs,'char_hp'=>$battle['char_hp'],'monster_hp'=>0,'over'=>true,'result'=>'win','result_title'=>'勝利！','result_desc'=>$desc]);
        exit;
    }
}

$battle['rounds']++;
[$logs, $over] = enemy_turn($battle, $char, $logs, $defending);
if ($over) { echo finish($battle, $char, $logs); exit; }

echo json_encode(['logs'=>$logs,'char_hp'=>$battle['char_hp'],'monster_hp'=>$monster['hp'],'over'=>false]);

// ── 敵人回合函式 ──────────────────────────────────────────────
function enemy_turn(array &$battle, array $char, array $logs, bool $playerDefending): array {
    $monster = &$battle['monster'];
    if (mt_rand(1,1000) <= dodge_chance($char['agi']) * 1000) {
        $logs[] = log_msg("你閃避了 {$monster['name']} 的攻擊！", 'player');
    } else {
        ['dmg'=>$dmg,'crit'=>$crit] = calc_damage($monster['atk'], intdiv($char['str'], 3));
        if ($playerDefending) $dmg = max(1, intdiv($dmg, 2));
        $battle['char_hp'] = max(0, $battle['char_hp'] - $dmg);
        $logs[] = $crit
            ? log_msg("{$monster['name']} 暴擊！你受到 {$dmg} 點傷害！", 'crit')
            : log_msg("{$monster['name']} 攻擊你，受到 {$dmg} 點傷害。", 'enemy');
    }
    $over = ($battle['char_hp'] <= 0);
    return [$logs, $over];
}

function finish(array &$battle, array $char, array $logs): string {
    global $user;
    $recovered = max(1, intdiv($char['max_hp'] * 3, 10));
    db()->prepare('UPDATE users SET hp=? WHERE id=?')->execute([$recovered, $user['id']]);
    db()->prepare('INSERT INTO battle_records (user_id,floor,result,exp_gain,gold_gain) VALUES (?,?,?,?,?)')
         ->execute([$user['id'], $char['max_floor'], 'lose', 0, 0]);
    unset($_SESSION['battle']);
    return json_encode(['logs'=>$logs,'char_hp'=>0,'monster_hp'=>$battle['monster']['hp'],'over'=>true,'result'=>'lose','result_title'=>'敗北...','result_desc'=>"HP 回復至 {$recovered}，停留在第 {$char['max_floor']} 層。"]);
}
