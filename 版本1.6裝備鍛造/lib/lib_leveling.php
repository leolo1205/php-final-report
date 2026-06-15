<?php
/**
 * 升級系統
 * 包含：升級所需 EXP、升級屬性成長、連續升級處理
 */

/**
 * 取得目前等級升到下一級需要的 EXP
 * 需求序列：100、200、350、550、800...
 */
function level_exp_required($level) {
    $level = max(1, (int)$level);

    return 25 * $level * ($level + 1) + 50;
}

/**
 * 取得目前等級升級時獲得的屬性
 * Lv.1~3：HP +10 / DMG +3 / DEF +1
 * Lv.4~6：HP +20 / DMG +6 / DEF +2
 * Lv.7~9：HP +30 / DMG +9 / DEF +3
 */
function levelup_stat_bonus($level) {
    $level = max(1, (int)$level);
    $tier = intdiv($level - 1, 3) + 1;

    return [
        'hp' => 10 * $tier,
        'dmg' => 3 * $tier,
        'def' => 1 * $tier,
        'tier' => $tier,
    ];
}

/**
 * 處理升級，可連續升多級
 */
function process_levelup($conn, $user_id) {
    $user_id = (int)$user_id;
    $user = $conn->query("SELECT level, exp, hp, max_hp FROM users WHERE id=$user_id")->fetch_assoc();

    if (!$user) {
        return [
            'leveled_up' => false,
            'levels_gained' => 0,
            'new_level' => 1,
            'new_exp' => 0,
            'hp_gained' => 0,
            'dmg_gained' => 0,
            'def_gained' => 0,
        ];
    }

    $lvl = (int)$user['level'];
    $exp = (int)$user['exp'];
    $gained = 0;
    $hp_add = 0;
    $dmg_add = 0;
    $def_add = 0;

    while ($exp >= level_exp_required($lvl)) {
        $need = level_exp_required($lvl);
        $bonus = levelup_stat_bonus($lvl);

        $exp -= $need;
        $lvl++;
        $gained++;

        $hp_add += $bonus['hp'];
        $dmg_add += $bonus['dmg'];
        $def_add += $bonus['def'];
    }

    if ($gained > 0) {
        $new_max_hp = (int)$user['max_hp'] + $hp_add;

        $conn->query("UPDATE users SET
            level=$lvl,
            exp=$exp,
            max_hp=$new_max_hp,
            hp=$new_max_hp,
            dmg=dmg+$dmg_add,
            def=def+$def_add
            WHERE id=$user_id");
    }

    return [
        'leveled_up' => ($gained > 0),
        'levels_gained' => $gained,
        'new_level' => $lvl,
        'new_exp' => $exp,
        'hp_gained' => $hp_add,
        'dmg_gained' => $dmg_add,
        'def_gained' => $def_add,
    ];
}

/**
 * 計算升到指定等級所需的累計 EXP
 * 例：Lv.1 = 0、Lv.2 = 100、Lv.3 = 300、Lv.4 = 650
 */
function exp_needed_for_level($level) {
    $level = max(1, (int)$level);
    $total = 0;

    for ($lv = 1; $lv < $level; $lv++) {
        $total += level_exp_required($lv);
    }

    return $total;
}