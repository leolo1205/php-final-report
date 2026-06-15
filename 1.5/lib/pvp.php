<?php
/**
 * PVP 函式：排名維護、戰鬥模擬、積分計算、週結算
 */

require_once __DIR__ . '/equipment.php';
require_once __DIR__ . '/skill_tree.php';
require_once __DIR__ . '/combat.php';

function ensure_pvp_ranking($conn, $user_id) {
    $q = $conn->query("SELECT user_id FROM pvp_rankings WHERE user_id=$user_id");
    if ($q !== false && !$q->fetch_assoc()) {
        $conn->query("INSERT INTO pvp_rankings (user_id) VALUES ($user_id)");
    }
}

function get_pvp_fighter($conn, $user_id) {
    $u  = $conn->query("SELECT id,username,dmg,def,hp,max_hp,level FROM users WHERE id=$user_id")->fetch_assoc();
    $eq = get_equipment_bonus($conn, $user_id);
    $dodge_lvl = 0; $crit_lvl = 0;
    $sr = $conn->query("SELECT skill_id,level FROM user_skills WHERE user_id=$user_id");
    if ($sr) while ($row = $sr->fetch_assoc()) {
        if ($row['skill_id']==='dodge') $dodge_lvl = (int)$row['level'];
        if ($row['skill_id']==='crit')  $crit_lvl  = (int)$row['level'];
    }
    $build = get_skill_build($conn, $user_id);
    $sb    = get_skill_stat_bonus($build);
    return [
        'id'         => (int)$u['id'],
        'username'   => $u['username'],
        'level'      => (int)$u['level'],
        'hp'         => (int)$u['max_hp'] + $eq['hp'] + $sb['hp'],
        'max_hp'     => (int)$u['max_hp'] + $eq['hp'] + $sb['hp'],
        'atk'        => (int)$u['dmg']    + $eq['atk'] + $sb['atk'],
        'def'        => (int)$u['def']    + $eq['def'] + $sb['def'],
        'crit_rate'  => 10 + $crit_lvl + $sb['crit'],
        'dodge_rate' => 10 + $dodge_lvl,
        'build'      => $build,
    ];
}

function simulate_pvp_battle($conn, $challenger_id, $defender_id) {
    $a = get_pvp_fighter($conn, $challenger_id);
    $b = get_pvp_fighter($conn, $defender_id);

    $ss_a = skill_combat_init();  $ss_b = skill_combat_init();
    $corr_on_b = 0;
    $corr_on_a = 0;

    if ($a['dodge_rate'] > $b['dodge_rate'])      $first = 'a';
    elseif ($b['dodge_rate'] > $a['dodge_rate'])  $first = 'b';
    else $first = (rand(0,1) ? 'a' : 'b');

    $log = [];
    $log[] = ['type'=>'system', 'text'=>
        "⚔️ {$a['username']}（Lv.{$a['level']}）VS {$b['username']}（Lv.{$b['level']}）"];
    $log[] = ['type'=>'system', 'text'=>
        ($first==='a' ? "🎯 {$a['username']}" : "🎯 {$b['username']}") . " 先手出擊！"];

    $round = 0;
    $order = ($first === 'a') ? ['a','b'] : ['b','a'];

    while ($a['hp'] > 0 && $b['hp'] > 0 && $round < 200) {
        $round++;

        foreach (['a','b'] as $side) {
            $f   = &$$side;
            $ss  = $side === 'a' ? $ss_a : $ss_b;
            $rs  = skill_round_start($f['build'], $ss, $f['hp'], $f['max_hp']);
            $opp = $side === 'a' ? 'b' : 'a';
            if ($rs['heal'] > 0) {
                $f['hp'] = min($f['max_hp'], $f['hp'] + $rs['heal']);
                $log[] = ['type'=>'system', 'text' => "🌿 {$f['username']} 生命脈動 +{$rs['heal']} HP"];
            }
            if ($rs['reflect'] > 0) {
                $$opp['hp'] -= $rs['reflect'];
                $log[] = ['type'=>'system', 'text' => "🌵 {$f['username']} 荊棘爆發！{${$opp}['username']} 受到 {$rs['reflect']} 傷害"];
            }
            if ($side === 'a') $ss_a = $ss; else $ss_b = $ss;
            unset($f);
        }
        if ($a['hp'] <= 0 || $b['hp'] <= 0) break;

        foreach ($order as $turn) {
            if ($a['hp'] <= 0 || $b['hp'] <= 0) break;

            [$atk_f, $def_f, $ss_atk, $ss_def, $corr_ref] = $turn === 'a'
                ? [$a, $b, &$ss_a, &$ss_b, &$corr_on_b]
                : [$b, $a, &$ss_b, &$ss_a, &$corr_on_a];

            $crit_r = $atk_f['crit_rate'];
            if ($ss_atk['vengeance_ready']) { $crit_r = 100; $ss_atk['vengeance_ready'] = false; }

            $eff_def = skill_get_effective_def($def_f['build'], $def_f['hp'], $def_f['max_hp'],
                                               max(0, $def_f['def'] - $corr_ref));
            $hunt    = skill_hunting_bonus($atk_f['build'], $def_f['hp'], $def_f['max_hp']);
            $eff_atk = (int)($atk_f['atk'] * (1 + $hunt));

            $hit = calculate_damage($eff_atk, $eff_def, $crit_r, $def_f['dodge_rate']);

            if ($hit['dodged']) {
                $log[] = ['type'=>'dodge', 'text'=>"💨 {$def_f['username']} 閃避了 {$atk_f['username']} 的攻擊！"];
            } else {
                if ($turn==='a') $b['hp'] -= $hit['damage']; else $a['hp'] -= $hit['damage'];
                $prefix    = $hit['crit'] ? "💥 爆擊！" : "";
                $remaining = max(0, ($turn==='a') ? $b['hp'] : $a['hp']);
                $log[] = ['type'=>($hit['crit']?'crit':'attack'), 'text'=>
                    "{$prefix}{$atk_f['username']} 造成 {$hit['damage']} 傷害。{$def_f['username']} 剩餘 HP：{$remaining}"];

                $sa = skill_on_player_attack($atk_f['build'], $ss_atk, $hit,
                                             $def_f['hp'], $def_f['max_hp'], $corr_ref);
                if ($sa['extra'] > 0) {
                    if ($turn==='a') $b['hp'] -= $sa['extra']; else $a['hp'] -= $sa['extra'];
                    $log[] = ['type'=>'system', 'text' => "{$atk_f['username']} {$sa['log']}"];
                }

                $take_r = skill_on_player_take_dmg($def_f['build'], $ss_def, $hit, $hit['damage']);
                if ($take_r['log']) {
                    $log[] = ['type'=>'system', 'text' => "{$def_f['username']} {$take_r['log']}"];
                }
            }

            foreach (['a','b'] as $side) {
                if ($$side['hp'] <= 0) {
                    $ss_check = $side === 'a' ? $ss_a : $ss_b;
                    $ud = skill_check_undying($$side['build'], $ss_check, $$side['max_hp']);
                    if ($ud['revived']) {
                        $$side['hp'] = $ud['new_hp'];
                        $log[] = ['type'=>'system', 'text' => $ud['log']];
                    }
                    if ($side === 'a') $ss_a = $ss_check; else $ss_b = $ss_check;
                }
            }
        }
    }

    $winner = ($a['hp'] > 0) ? $a : $b;
    $loser  = ($a['hp'] > 0) ? $b : $a;
    $log[]  = ['type'=>'result', 'text'=>"🏆 {$winner['username']} 獲勝！（共 {$round} 回合）"];

    return ['winner_id'=>$winner['id'], 'loser_id'=>$loser['id'], 'rounds'=>$round, 'log'=>$log];
}

function calc_rating_change($winner_rating, $loser_rating, $winner_streak) {
    $diff = $winner_rating - $loser_rating;
    if ($diff > 100)      { $w_gain = 10; $l_loss = 30; }
    elseif ($diff < -100) { $w_gain = 30; $l_loss = 10; }
    else                  { $w_gain = 20; $l_loss = 20; }
    if ($winner_streak >= 3) $w_gain += 5;
    return ['winner_gain' => $w_gain, 'loser_loss' => $l_loss];
}

function do_pvp_challenge($conn, $challenger_id, $defender_id) {
    if ($challenger_id === $defender_id) return ['success'=>false,'message'=>'不能挑戰自己'];

    ensure_pvp_ranking($conn, $challenger_id);
    ensure_pvp_ranking($conn, $defender_id);

    $remaining = (int)$conn->query("SELECT GREATEST(0, 60 - TIMESTAMPDIFF(SECOND, last_challenge, NOW())) FROM pvp_rankings WHERE user_id=$challenger_id")->fetch_row()[0];
    if ($remaining > 0) {
        return ['success'=>false, 'message'=>"挑戰冷卻中，剩餘 {$remaining} 秒"];
    }

    $result    = simulate_pvp_battle($conn, $challenger_id, $defender_id);
    $winner_id = $result['winner_id'];
    $loser_id  = $result['loser_id'];

    $wr     = $conn->query("SELECT rating,streak FROM pvp_rankings WHERE user_id=$winner_id")->fetch_assoc();
    $lr     = $conn->query("SELECT rating FROM pvp_rankings WHERE user_id=$loser_id")->fetch_assoc();
    $change = calc_rating_change((int)$wr['rating'], (int)$lr['rating'], (int)$wr['streak']);

    $w_new = max(0, $wr['rating'] + $change['winner_gain']);
    $l_new = max(0, $lr['rating'] - $change['loser_loss']);

    $conn->query("UPDATE pvp_rankings SET
        rating=$w_new, wins=wins+1, streak=streak+1, last_challenge=NOW()
        WHERE user_id=$winner_id");
    $conn->query("UPDATE pvp_rankings SET
        rating=$l_new, losses=losses+1, streak=0
        WHERE user_id=$loser_id");
    if ($loser_id === $challenger_id)
        $conn->query("UPDATE pvp_rankings SET last_challenge=NOW() WHERE user_id=$challenger_id");

    $log_json = $conn->real_escape_string(json_encode($result['log'], JSON_UNESCAPED_UNICODE));
    $w_change = ($winner_id===$challenger_id) ? $change['winner_gain'] : -$change['loser_loss'];
    $d_change = ($defender_id===$winner_id)   ? $change['winner_gain'] : -$change['loser_loss'];
    $conn->query("INSERT INTO pvp_battles
        (challenger_id,defender_id,winner_id,challenger_rating_change,defender_rating_change,battle_log)
        VALUES ($challenger_id,$defender_id,$winner_id,$w_change,$d_change,'$log_json')");

    $challenger_won        = ($winner_id === $challenger_id);
    $rating_gain           = $challenger_won ? $change['winner_gain'] : -$change['loser_loss'];
    $challenger_new_rating = $challenger_won ? $w_new : $l_new;

    return [
        'success'              => true,
        'winner_id'            => $winner_id,
        'loser_id'             => $loser_id,
        'battle_log'           => $result['log'],
        'rounds'               => $result['rounds'],
        'rating_change'        => ($rating_gain >= 0 ? "+{$rating_gain}" : "{$rating_gain}"),
        'rating_gain'          => $rating_gain,
        'new_rating'           => $challenger_new_rating,
        'battle_id'            => $conn->insert_id,
    ];
}

function pvp_weekly_settle($conn) {
    $players = [];
    $res = $conn->query("SELECT r.user_id, r.rating FROM pvp_rankings r JOIN users u ON r.user_id=u.id WHERE u.is_bot=0 ORDER BY r.rating DESC");
    if ($res !== false) while ($r = $res->fetch_assoc()) $players[] = $r;

    $rewarded = 0;
    foreach ($players as $i => $p) {
        $rank = $i + 1;
        if ($rank === 1)     $gold = 10000;
        elseif ($rank === 2) $gold = 5000;
        elseif ($rank === 3) $gold = 2000;
        elseif ($rank <= 10) $gold = 1000;
        else {
            $tier = floor(($rank - 11) / 10);
            $gold = max(0, (int)(500 / pow(2, $tier)));
        }
        if ($gold > 0) {
            $conn->query("UPDATE users SET gold=gold+$gold WHERE id={$p['user_id']}");
            $rewarded++;
        }
    }
    $conn->query("UPDATE pvp_rankings r JOIN users u ON r.user_id=u.id SET r.rating=1000, r.wins=0, r.losses=0, r.streak=0 WHERE u.is_bot=0");
    return ['settled' => count($players), 'rewarded' => $rewarded];
}
