<?php
/**
 * 技能樹函式：節點定義、數值加成、戰鬥技能效果
 */

function get_node_costs() {
    return [1 => 1500, 2 => 2500, 3 => 3500, 4 => 4500,
            5 => 5500, 6 => 6500, 7 => 7500, 8 => 8500, 9 => 10000];
}

function get_archetype_nodes() {
    return [
        'assault' => [
            1 => ['type'=>'stat',  'label'=>'ATK +3',      'atk'=>3],
            2 => ['type'=>'stat',  'label'=>'ATK +3',      'atk'=>3],
            3 => ['type'=>'skill', 'label'=>'🩸 血肉渴望', 'skill'=>'blood_thirst',     'desc'=>'每次攻擊追加敵方最大HP×1.4%真實傷害'],
            4 => ['type'=>'stat',  'label'=>'ATK +5',      'atk'=>5],
            5 => ['type'=>'stat',  'label'=>'爆擊率 +5%',  'crit'=>5],
            6 => ['type'=>'skill', 'label'=>'💀 穿心一擊', 'skill'=>'pierce_heart',     'desc'=>'每4回合造成敵方最大HP×8%真實傷害'],
            7 => ['type'=>'stat',  'label'=>'ATK +5',      'atk'=>5],
            8 => ['type'=>'stat',  'label'=>'爆擊率 +5%',  'crit'=>5],
            9 => ['type'=>'skill', 'label'=>'🔥 獵殺本能', 'skill'=>'hunting_instinct', 'desc'=>'敵方HP<60%傷害+15%，HP<25%再+15%'],
        ],
        'guardian' => [
            1 => ['type'=>'stat',  'label'=>'DEF +2',      'def'=>2],
            2 => ['type'=>'stat',  'label'=>'DEF +2',      'def'=>2],
            3 => ['type'=>'skill', 'label'=>'🌵 荊棘之壁', 'skill'=>'thorn_wall',       'desc'=>'受傷時積累荊棘值，每4回合反彈積累×62%'],
            4 => ['type'=>'stat',  'label'=>'DEF +3',      'def'=>3],
            5 => ['type'=>'stat',  'label'=>'HP +20',      'hp'=>20],
            6 => ['type'=>'skill', 'label'=>'⚙️ 鋼鐵意志', 'skill'=>'iron_will',        'desc'=>'HP低於40%時防禦力×1.3'],
            7 => ['type'=>'stat',  'label'=>'DEF +3',      'def'=>3],
            8 => ['type'=>'stat',  'label'=>'HP +20',      'hp'=>20],
            9 => ['type'=>'skill', 'label'=>'⚡ 報復之刃', 'skill'=>'vengeance_blade',  'desc'=>'被暴擊後下次攻擊必定暴擊'],
        ],
        'vitality' => [
            1 => ['type'=>'stat',  'label'=>'HP +20',      'hp'=>20],
            2 => ['type'=>'stat',  'label'=>'HP +20',      'hp'=>20],
            3 => ['type'=>'skill', 'label'=>'🌿 生命脈動', 'skill'=>'life_pulse',       'desc'=>'每回合恢復最大HP×4%'],
            4 => ['type'=>'stat',  'label'=>'HP +30',      'hp'=>30],
            5 => ['type'=>'stat',  'label'=>'DEF +1',      'def'=>1],
            6 => ['type'=>'skill', 'label'=>'🧪 侵蝕詛咒', 'skill'=>'corrosion_curse',  'desc'=>'每次攻擊削減敵方DEF-3(上限-30)，追加層數×1.15真傷'],
            7 => ['type'=>'stat',  'label'=>'HP +30',      'hp'=>30],
            8 => ['type'=>'stat',  'label'=>'DEF +2',      'def'=>2],
            9 => ['type'=>'skill', 'label'=>'🔮 不滅之軀', 'skill'=>'undying_body',     'desc'=>'HP首次歸零時復活至25%，下次受傷減半'],
        ],
    ];
}

function get_skill_build($conn, $user_id) {
    $q   = $conn->query("SELECT archetype, nodes_unlocked FROM user_skill_build WHERE user_id=$user_id");
    $row = ($q !== false) ? $q->fetch_assoc() : null;
    return $row ?: ['archetype' => null, 'nodes_unlocked' => 0];
}

function get_skill_stat_bonus($build) {
    $bonus = ['atk' => 0, 'def' => 0, 'hp' => 0, 'crit' => 0];
    if (!$build['archetype'] || $build['nodes_unlocked'] <= 0) return $bonus;
    $nodes = get_archetype_nodes()[$build['archetype']] ?? [];
    for ($i = 1; $i <= (int)$build['nodes_unlocked']; $i++) {
        $n = $nodes[$i] ?? [];
        if (($n['type'] ?? '') === 'stat') {
            foreach (['atk','def','hp','crit'] as $k) {
                if (isset($n[$k])) $bonus[$k] += $n[$k];
            }
        }
    }
    return $bonus;
}

function has_skill($build, $skill_name) {
    if (!$build['archetype'] || $build['nodes_unlocked'] <= 0) return false;
    $nodes = get_archetype_nodes()[$build['archetype']] ?? [];
    for ($i = 1; $i <= (int)$build['nodes_unlocked']; $i++) {
        if (($nodes[$i]['skill'] ?? '') === $skill_name) return true;
    }
    return false;
}

function skill_combat_init() {
    return [
        'round'           => 0,
        'thorns_acc'      => 0,
        'pierce_cd'       => 0,
        'corr_stacks'     => 0,
        'vengeance_ready' => false,
        'undying_used'    => false,
        'undying_immune'  => false,
    ];
}

function skill_round_start($build, &$ss, $my_hp, $my_max_hp) {
    $heal = $reflect = 0; $log = '';
    $ss['round']++;
    if (!$build['archetype']) return compact('heal','reflect','log');

    if (has_skill($build, 'life_pulse')) {
        $heal = max(1, (int)($my_max_hp * 0.04));
    }
    if (has_skill($build, 'thorn_wall') && $ss['round'] % 4 === 0 && $ss['thorns_acc'] > 0) {
        $reflect = (int)($ss['thorns_acc'] * 0.62);
        $ss['thorns_acc'] = 0;
    }
    return compact('heal','reflect','log');
}

function skill_on_player_attack($build, &$ss, $hit_result, $enemy_hp, $enemy_max_hp, &$enemy_corr) {
    $extra = 0; $log = '';
    if (!$build['archetype'] || !$hit_result['hit']) return compact('extra','log');

    if (has_skill($build, 'blood_thirst')) {
        $td     = max(1, (int)($enemy_max_hp * 0.014));
        $extra += $td;
        $log   .= "🩸 血肉渴望：真傷 {$td}。";
    }
    if (has_skill($build, 'pierce_heart')) {
        $ss['pierce_cd']++;
        if ($ss['pierce_cd'] >= 4) {
            $ss['pierce_cd'] = 0;
            $td     = max(1, (int)($enemy_max_hp * 0.08));
            $extra += $td;
            $log   .= "💀 穿心一擊：真傷 {$td}！";
        }
    }
    if (has_skill($build, 'corrosion_curse')) {
        $enemy_corr = min(30, $enemy_corr + 3);
        $td     = (int)($enemy_corr * 1.15);
        $extra += $td;
        $log   .= "🧪 侵蝕：DEF -{$enemy_corr}，追加真傷 {$td}。";
    }
    return compact('extra','log');
}

function skill_on_player_take_dmg($build, &$ss, $hit_result, $dmg_taken) {
    $log = '';
    if (!$build['archetype'] || !$hit_result['hit']) return ['log'=>$log];

    if (has_skill($build, 'thorn_wall') && $dmg_taken > 0) {
        $ss['thorns_acc'] += $dmg_taken;
    }
    if (has_skill($build, 'vengeance_blade') && $hit_result['crit']) {
        $ss['vengeance_ready'] = true;
        $log .= "⚡ 報復之刃就緒！";
    }
    return ['log' => $log];
}

function skill_get_effective_def($build, $my_hp, $my_max_hp, $base_def) {
    if (!has_skill($build, 'iron_will') || $my_max_hp <= 0) return $base_def;
    return ($my_hp / $my_max_hp) < 0.40 ? (int)($base_def * 1.3) : $base_def;
}

function skill_hunting_bonus($build, $enemy_hp, $enemy_max_hp) {
    if (!has_skill($build, 'hunting_instinct') || $enemy_max_hp <= 0) return 0.0;
    $pct = $enemy_hp / $enemy_max_hp;
    $b   = 0.0;
    if ($pct < 0.60) $b += 0.15;
    if ($pct < 0.25) $b += 0.15;
    return $b;
}

function skill_check_undying($build, &$ss, $my_max_hp) {
    if (!has_skill($build, 'undying_body') || $ss['undying_used']) {
        return ['revived' => false, 'new_hp' => 0, 'log' => ''];
    }
    $new_hp             = max(1, (int)($my_max_hp * 0.25));
    $ss['undying_used'] = true;
    $ss['undying_immune'] = true;
    return ['revived' => true, 'new_hp' => $new_hp,
            'log' => "🔮 不滅之軀：從死亡邊緣復活！回復 {$new_hp} HP，下次受傷減半！"];
}
