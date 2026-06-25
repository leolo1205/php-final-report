<?php
// BOSS 與怪物數據
function get_special_boss($floor) {
    if ($floor % 5 !== 0) return null;
    
    $boss_pool = [
        1 => [
            'id' => 'boar_king',
            'name' => '🐗 爆走的尖牙野豬王',
            'base_crit' => 20, 'base_dodge' => 10,
            'hp_mult' => 2.5, 'dmg_mult' => 1.3, 'def_mult' => 1.2
        ],
        2 => [
            'id' => 'stone_golem',
            'name' => '🗿 熔岩石人',
            'base_crit' => 5, 'base_dodge' => 0,
            'hp_mult' => 3.5, 'dmg_mult' => 1.1, 'def_mult' => 2.2
        ],
        3 => [
            'id' => 'shadow_wolf',
            'name' => '🐺 暗影狼王',
            'base_crit' => 35, 'base_dodge' => 30,
            'hp_mult' => 1.8, 'dmg_mult' => 1.7, 'def_mult' => 0.7
        ],
        4 => [
            'id' => 'fire_drake',
            'name' => '🔥 炎龍',
            'base_crit' => 25, 'base_dodge' => 15,
            'hp_mult' => 2.8, 'dmg_mult' => 1.9, 'def_mult' => 1.0
        ],
        5 => [
            'id' => 'lich_lord',
            'name' => '💀 巫妖領主',
            'base_crit' => 15, 'base_dodge' => 25,
            'hp_mult' => 2.0, 'dmg_mult' => 2.2, 'def_mult' => 0.5
        ],
    ];
    
    $index = (($floor / 5 - 1) % count($boss_pool)) + 1;
    return $boss_pool[$index];
}

function get_floor_data($floor) {
    $special = get_special_boss($floor);
    if ($special) {
        return [
            'mob_name' => '守衛精英',
            'mob_level' => $floor + 1,
            'boss_name' => $special['name'],
            'boss_level' => $floor + 3,
            'is_special' => true,
            'special_data' => $special
        ];
    }
    return [
        'mob_name' => "第 {$floor} 層守衛", 
        'mob_level' => $floor + 1, 
        'boss_name' => "第 {$floor} 層領主", 
        'boss_level' => $floor + 3,
        'is_special' => false
    ];
}
?>