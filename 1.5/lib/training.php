<?php
/**
 * 訓練函式：方案定義、冷卻查詢、開始訓練、升級處理
 */

function get_train_plans() {
    return [
        'short'  => ['label' => '10 分鐘', 'duration_sec' =>   600, 'exp' =>   50, 'stat' =>  1],
        'medium' => ['label' => '1 小時',  'duration_sec' =>  3600, 'exp' =>  300, 'stat' =>  3],
        'long'   => ['label' => '8 小時',  'duration_sec' => 28800, 'exp' => 1500, 'stat' => 10],
    ];
}

function check_training_cooldown($conn, $user_id) {
    $q   = $conn->query("SELECT last_train_time, train_duration FROM users WHERE id=$user_id");
    $row = ($q !== false) ? $q->fetch_assoc() : null;
    if (!$row || !$row['last_train_time']) {
        return ['is_training' => false, 'seconds_remaining' => 0, 'duration_sec' => 0, 'plan_key' => ''];
    }
    $duration  = (int)$row['train_duration'];
    $elapsed   = time() - strtotime($row['last_train_time']);
    $remaining = max(0, $duration - $elapsed);

    if ($elapsed >= $duration) {
        $conn->query("UPDATE users SET last_train_time=NULL, train_duration=0 WHERE id=$user_id");
        return ['is_training' => false, 'seconds_remaining' => 0, 'duration_sec' => 0, 'plan_key' => ''];
    }

    $plan_key = '';
    foreach (get_train_plans() as $key => $plan) {
        if ($plan['duration_sec'] === $duration) { $plan_key = $key; break; }
    }

    return [
        'is_training'       => true,
        'seconds_remaining' => $remaining,
        'duration_sec'      => $duration,
        'plan_key'          => $plan_key,
    ];
}

function start_training($conn, $user_id, $plan_key = 'short') {
    $plans = get_train_plans();
    if (!isset($plans[$plan_key])) $plan_key = 'short';
    $plan = $plans[$plan_key];

    $status = check_training_cooldown($conn, $user_id);
    if ($status['is_training']) {
        return ['success' => false, 'message' => "訓練冷卻中，剩餘 {$status['seconds_remaining']} 秒"];
    }

    $exp  = $plan['exp'];
    $stat = $plan['stat'];
    $dur  = $plan['duration_sec'];

    $conn->query("UPDATE users SET last_train_time=NOW(), train_duration=$dur, exp=exp+$exp, stat_points=stat_points+$stat WHERE id=$user_id");
    $conn->query("INSERT INTO training_logs (user_id,exp_gained,stat_points_gained) VALUES ($user_id,$exp,$stat)");

    return [
        'success'      => true,
        'exp_gained'   => $exp,
        'stat_gained'  => $stat,
        'duration_sec' => $dur,
        'label'        => $plan['label'],
        'message'      => "訓練開始！獲得 {$exp} EXP 與 {$stat} 屬性點",
    ];
}

function claim_training_reward($conn, $user_id) {
    return ['success' => false, 'message' => '獎勵已在訓練開始時發放'];
}

function process_levelup($conn, $user_id) {
    $user = $conn->query("SELECT level,exp,hp,max_hp FROM users WHERE id=$user_id")->fetch_assoc();
    $lvl = (int)$user['level'];
    $exp = (int)$user['exp'];
    $gained = 0;
    $hp_add = 0; $dmg_add = 0; $def_add = 0;

    while ($exp >= $lvl * 100) {
        $exp    -= $lvl * 100;
        $lvl++;
        $gained++;
        $hp_add  += 10;
        $dmg_add += 3;
        $def_add += 1;
    }

    if ($gained > 0) {
        $new_max_hp = $user['max_hp'] + $hp_add;
        $conn->query("UPDATE users SET
            level=$lvl, exp=$exp,
            max_hp=$new_max_hp, hp=$new_max_hp,
            dmg=dmg+$dmg_add, def=def+$def_add
            WHERE id=$user_id");
    }

    return [
        'leveled_up'    => ($gained > 0),
        'levels_gained' => $gained,
        'new_level'     => $lvl,
        'new_exp'       => $exp,
    ];
}

function exp_needed_for_level($level) {
    $total = 0;
    for ($i = 1; $i < $level; $i++) $total += $i * 100;
    return $total;
}
