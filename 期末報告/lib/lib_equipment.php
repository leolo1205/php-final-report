<?php
/**
 * 裝備鍛造與真實屬性計算
 * 包含：強化費用、成功率、裝備倍率、裝備初始化、強化處理、玩家有效屬性
 */

define('FORGE_MAX_LEVEL', 50);

/**
 * 強化費用
 * +1 = 100
 * +2 = 300
 * +3 = 600
 * +4 = 1000
 */
function forge_upgrade_cost($current_level) {
    $current_level = max(0, (int)$current_level);
    $next_level = $current_level + 1;

    return (int)(100 * $next_level * ($next_level + 1) / 2);
}

/**
 * 強化成功率
 *
 * +1~+10：100%、98%、96%...
 * +11~+20：90%、88%、86%...
 * +21~+30：80%、78%、76%...
 */
function forge_upgrade_chance($current_level) {
    $current_level = max(0, (int)$current_level);
    $next_level = $current_level + 1;

    if ($next_level > FORGE_MAX_LEVEL) {
        return 0;
    }

    $block = intdiv($next_level - 1, 10);
    $step = ($next_level - 1) % 10;
    $chance = 100 - ($block * 10) - ($step * 2);

    return max(1, min(100, $chance));
}


/**
 * 裝備倍率
 *
 * +0 = 1.00x
 * +1 = 1.01x
 * +2 = 1.02x
 * +50 = 1.50x
 */
function equipment_multiplier($level) {
    $level = max(0, min(FORGE_MAX_LEVEL, (int)$level));

    return 1 + ($level * 0.01);
}

/**
 * 取得玩家所有裝備狀態，若不存在則自動初始化
 */
function get_equipment($conn, $user_id) {
    $user_id = (int)$user_id;
    $types = ['weapon', 'armor', 'helmet'];
    $result = [];

    foreach ($types as $type) {
        $q = $conn->query("SELECT * FROM user_equipment WHERE user_id=$user_id AND equip_type='$type'");
        $row = ($q !== false) ? $q->fetch_assoc() : null;

        if (!$row) {
            if ($q !== false) {
                $conn->query("INSERT INTO user_equipment (user_id, equip_type, level, attempts, successes)
                    VALUES ($user_id, '$type', 0, 0, 0)");
            }

            $row = [
                'user_id' => $user_id,
                'equip_type' => $type,
                'level' => 0,
                'attempts' => 0,
                'successes' => 0,
            ];
        }

        $result[$type] = $row;
    }

    return $result;
}

/**
 * 取得裝備倍率加成
 *
 * weapon：攻擊倍率
 * armor：防禦倍率
 * helmet：生命倍率
 */
function get_equipment_bonus($conn, $user_id) {
    $eq = get_equipment($conn, $user_id);

    return [
        'atk_mult' => equipment_multiplier((int)$eq['weapon']['level']),
        'def_mult' => equipment_multiplier((int)$eq['armor']['level']),
        'hp_mult' => equipment_multiplier((int)$eq['helmet']['level']),
        'weapon_level' => (int)$eq['weapon']['level'],
        'armor_level' => (int)$eq['armor']['level'],
        'helmet_level' => (int)$eq['helmet']['level'],
    ];
}

/**
 * 取得玩家原始屬性、技能樹加成、裝備倍率後的真實屬性
 *
 * 真實值 = floor((原數值 + 固定加成) × 裝備倍率)
 *
 * 回傳格式：
 * [
 *   'atk' => ['value'=>真實值, 'raw'=>原數值, 'flat'=>固定加成, 'mult'=>倍率],
 *   'def' => ...
 *   'hp'  => ...
 * ]
 */
function get_player_effective_stats($conn, $user_id) {
    $user_id = (int)$user_id;

    $res = $conn->query("SELECT dmg, def, max_hp FROM users WHERE id=$user_id");
    $user = ($res !== false) ? $res->fetch_assoc() : null;

    if (!$user) {
        return [
            'atk' => [
                'value' => 1,
                'raw' => 1,
                'flat' => 0,
                'mult' => 1.0,
                'equip_level' => 0,
            ],
            'def' => [
                'value' => 0,
                'raw' => 0,
                'flat' => 0,
                'mult' => 1.0,
                'equip_level' => 0,
            ],
            'hp' => [
                'value' => 1,
                'raw' => 1,
                'flat' => 0,
                'mult' => 1.0,
                'equip_level' => 0,
            ],
        ];
    }

    $eq = get_equipment_bonus($conn, $user_id);

    $build = get_skill_build($conn, $user_id);
    $skill_bonus = get_skill_stat_bonus($build);

    $raw_atk = (int)$user['dmg'];
    $raw_def = (int)$user['def'];
    $raw_hp = (int)$user['max_hp'];

    $flat_atk = (int)($skill_bonus['atk'] ?? 0);
    $flat_def = (int)($skill_bonus['def'] ?? 0);
    $flat_hp = (int)($skill_bonus['hp'] ?? 0);

    $atk_mult = (float)($eq['atk_mult'] ?? 1);
    $def_mult = (float)($eq['def_mult'] ?? 1);
    $hp_mult = (float)($eq['hp_mult'] ?? 1);

    return [
        'atk' => [
            'value' => max(1, (int)floor(($raw_atk + $flat_atk) * $atk_mult)),
            'raw' => $raw_atk,
            'flat' => $flat_atk,
            'mult' => $atk_mult,
            'equip_level' => (int)($eq['weapon_level'] ?? 0),
        ],
        'def' => [
            'value' => max(0, (int)floor(($raw_def + $flat_def) * $def_mult)),
            'raw' => $raw_def,
            'flat' => $flat_def,
            'mult' => $def_mult,
            'equip_level' => (int)($eq['armor_level'] ?? 0),
        ],
        'hp' => [
            'value' => max(1, (int)floor(($raw_hp + $flat_hp) * $hp_mult)),
            'raw' => $raw_hp,
            'flat' => $flat_hp,
            'mult' => $hp_mult,
            'equip_level' => (int)($eq['helmet_level'] ?? 0),
        ],
    ];
}


/**
 * 嘗試強化裝備
 */
function upgrade_equipment($conn, $user_id, $type) {
    $user_id = (int)$user_id;
    $types = ['weapon', 'armor', 'helmet'];

    if (!in_array($type, $types, true)) {
        return [
            'success' => false,
            'leveled_up' => false,
            'message' => '無效的裝備類型',
        ];
    }

    $eq = get_equipment($conn, $user_id);
    $level = (int)$eq[$type]['level'];
    $max_level = FORGE_MAX_LEVEL;

    if ($level >= $max_level) {
        return [
            'success' => false,
            'leveled_up' => false,
            'old_level' => $level,
            'new_level' => $level,
            'cost' => 0,
            'chance' => 0,
            'message' => "已達最高等級 +{$max_level}",
        ];
    }

    $cost = forge_upgrade_cost($level);
    $chance = forge_upgrade_chance($level);

    $user = $conn->query("SELECT gold FROM users WHERE id=$user_id")->fetch_assoc();

    if (!$user || (int)$user['gold'] < $cost) {
        return [
            'success' => false,
            'leveled_up' => false,
            'old_level' => $level,
            'new_level' => $level,
            'cost' => $cost,
            'chance' => $chance,
            'message' => "金幣不足，需要 {$cost} 金",
        ];
    }

    $conn->query("UPDATE users SET gold=gold-$cost WHERE id=$user_id");

    $rolled = rand(1, 100);
    $leveled = ($rolled <= $chance);
    $new_level = $leveled ? $level + 1 : $level;

    $conn->query("UPDATE user_equipment SET
        level=$new_level,
        attempts=attempts+1,
        successes=successes+".($leveled ? 1 : 0)."
        WHERE user_id=$user_id AND equip_type='$type'");

    $names = [
        'weapon' => '武器',
        'armor' => '護甲',
        'helmet' => '頭盔',
    ];

    $old_mult = equipment_multiplier($level);
    $new_mult = equipment_multiplier($new_level);

    return [
        'success' => true,
        'leveled_up' => $leveled,
        'old_level' => $level,
        'new_level' => $new_level,
        'cost' => $cost,
        'chance' => $chance,
        'rolled' => $rolled,
        'multiplier' => $new_mult,
        'message' => $leveled
            ? "✅ 強化成功！{$names[$type]} +{$level} → +{$new_level}，倍率 ".number_format($old_mult, 2)."x → ".number_format($new_mult, 2)."x"
            : "❌ 強化失敗！{$names[$type]} 維持 +{$level}，消耗 {$cost} 金，成功率 {$chance}%，骰出 {$rolled}",
    ];
}