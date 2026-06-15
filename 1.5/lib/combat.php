<?php
/**
 * 戰鬥函式：怪物生成、傷害計算、逃跑判定
 */

function generate_monster($conn, $floor, $type = 'mob') {
    $mob_level  = $floor;
    $boss_level = $floor + 2;

    $mob_names  = ['哥布林','骷髏兵','蝙蝠怪','石像鬼','暗影狼','毒蜥蜴','冰雪精靈','熔岩魔','死靈法師','惡魔騎士'];
    $boss_names = ['哥布林王','死靈巫師','冰霜龍','熔岩魔王','黑暗領主','野豬王','深淵使者','混沌巨人','時空裂縫','終焉之神'];
    $mob_name   = $mob_names[($floor - 1) % count($mob_names)];
    $boss_name  = $boss_names[($floor - 1) % count($boss_names)];

    $is_special = ($floor % 5 === 0);
    $special_data = null;
    if ($is_special) {
        $special_data = [
            'id'       => 'boar_king',
            'hp_mult'  => 2.5,
            'dmg_mult' => 1.3,
            'def_mult' => 1.2,
            'base_crit'  => 15,
            'base_dodge' => 5,
        ];
    }

    $target = ($type === 'boss') ? $boss_level : $mob_level;
    $row = $conn->query("SELECT * FROM monster_stats WHERE level=$target")->fetch_assoc();
    if (!$row) {
        $row = [
            'level' => $target,
            'hp'    => $target * 100,
            'dmg'   => $target * 10,
            'def'   => (int)($target * 1.5),
            'exp'   => $target * 40,
            'gold'  => $target * 30,
        ];
    }

    return [
        'floor'        => $floor,
        'type'         => $type,
        'mob_level'    => $mob_level,
        'boss_level'   => $boss_level,
        'mob_name'     => $mob_name,
        'boss_name'    => $boss_name,
        'is_special'   => $is_special,
        'special_data' => $special_data,
        'stats'        => $row,
    ];
}

function get_monster_stats($conn, $level) {
    $row = $conn->query("SELECT * FROM monster_stats WHERE level=$level")->fetch_assoc();
    return $row ?: [
        'level' => $level,
        'hp'    => $level * 100,
        'dmg'   => $level * 10,
        'def'   => (int)($level * 1.5),
        'exp'   => $level * 40,
        'gold'  => $level * 30,
    ];
}

function calculate_damage($atk, $def, $crit_rate = 10, $dodge_rate = 10) {
    if (rand(1, 100) <= $dodge_rate) {
        return ['hit' => false, 'dodged' => true, 'crit' => false, 'raw_atk' => 0, 'damage' => 0];
    }
    $crit    = (rand(1, 100) <= $crit_rate);
    $raw_atk = $crit ? (int)floor($atk * 1.5) : $atk;
    $damage  = max(1, $raw_atk - $def);
    return ['hit' => true, 'dodged' => false, 'crit' => $crit, 'raw_atk' => $raw_atk, 'damage' => $damage];
}

function calculate_defense_stance($atk, $def, $crit_rate = 10, $dodge_rate = 10) {
    $result = calculate_damage($atk, $def * 2, $crit_rate, $dodge_rate);
    $result['damage'] = max(1, (int)floor($result['damage'] * 0.5));
    $result['stance'] = 'defense';
    return $result;
}

function calculate_escape($dodge_level = 0) {
    $rate    = min(80, 40 + $dodge_level * 10);
    $success = (rand(1, 100) <= $rate);
    return ['success' => $success, 'rate' => $rate];
}
