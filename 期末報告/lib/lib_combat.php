<?php
/**
 * 戰鬥基礎公式
 * 包含：傷害、爆擊、閃避、防禦姿態、逃跑
 */

/**
 * 計算單次攻擊結果
 * @return array ['hit','dodged','crit','raw_atk','damage']
 */
function calculate_damage($atk, $def, $crit_rate = 10, $dodge_rate = 10) {
    $atk = max(1, (int)$atk);
    $def = max(0, (int)$def);
    $crit_rate = max(0, min(100, (int)$crit_rate));
    $dodge_rate = max(0, min(100, (int)$dodge_rate));

    if (rand(1, 100) <= $dodge_rate) {
        return [
            'hit' => false,
            'dodged' => true,
            'crit' => false,
            'raw_atk' => 0,
            'damage' => 0,
        ];
    }

    $crit = (rand(1, 100) <= $crit_rate);
    $raw_atk = $crit ? (int)floor($atk * 1.5) : $atk;
    $damage = max(1, $raw_atk - $def);

    return [
        'hit' => true,
        'dodged' => false,
        'crit' => $crit,
        'raw_atk' => $raw_atk,
        'damage' => $damage,
    ];
}

/**
 * 計算防禦姿態下的減傷結果
 */
function calculate_defense_stance($atk, $def, $crit_rate = 10, $dodge_rate = 10) {
    $result = calculate_damage($atk, $def * 2, $crit_rate, $dodge_rate);
    $result['damage'] = max(1, (int)floor($result['damage'] * 0.5));
    $result['stance'] = 'defense';

    return $result;
}
