<?php
session_start();
date_default_timezone_set('Asia/Taipei');
require 'db.php';

$user_id = 1;
$result = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $result->fetch_assoc();

$monster_db = [];
$mob_res = $conn->query("SELECT * FROM monster_stats");
if ($mob_res) {
    while ($row = $mob_res->fetch_assoc()) {
        $monster_db[$row['level']] = $row;
    }
}

function get_story($floor, $node) {
    $stories = [
        1 => [ 
            5  => "眼前是一片寧靜祥和的森林，空氣真好...",
            10 => "一隻敏捷的兔子從你眼前跑過，但你沒有攻擊牠，牠就這樣消失了。",
            15 => "爬上樹頂，能清楚的看見森林中央遠處有一塊空地，似乎是個休息的好地方。",
            20 => "隨著繼續向前，空地越來越清晰。",
            25 => "空地中央有一座巨大祭壇，散發著古老而神秘的氣息。",
            29 => "一聲嚎叫劃破了寧靜，一股強大的威壓向你壓來..."
        ],
        2 => [ 
            5 => "森林的深處光線漸暗，空氣中瀰漫著淡淡的血腥味...",
            10 => "樹枝上掛著奇怪的繭，似乎有什麼東西在裡面蠕動。",
            15 => "地上散落著生鏽的兵器，這裡曾經發生過激烈的戰鬥。",
            20 => "周圍的溫度急遽下降，連呼吸都會吐出白霧。",
            25 => "你看到前方有一個巨大的洞穴入口，那裡就是巢穴了。",
            29 => "洞穴深處傳來令人毛骨悚然的振翅聲..."
        ]
    ];
    if (isset($stories[$floor][$node])) {
        return "<p style='color:#ce93d8; font-style:italic; font-size: 16px; padding: 10px; border-left: 3px solid #ce93d8; background: #2a2233;'>📜 " . $stories[$floor][$node] . "</p>";
    }
    return "<p style='color:#888;'>此處一片死寂，只有你的腳步聲迴盪著。</p>";
}

function get_floor_data($floor) {
    $floors = [
        1 => ['mob_name' => '野狼', 'mob_level' => 1, 'boss_name' => '暴走的尖牙野豬', 'boss_level' => 3],
        2 => ['mob_name' => '暗影蝙蝠', 'mob_level' => 2, 'boss_name' => '吸血蝙蝠領主', 'boss_level' => 5],
        3 => ['mob_name' => '骷髏戰士', 'mob_level' => 4, 'boss_name' => '亡靈騎士', 'boss_level' => 7]
    ];
    if (isset($floors[$floor])) return $floors[$floor];
    return ['mob_name' => "第 {$floor} 層守衛", 'mob_level' => $floor + 1, 'boss_name' => "第 {$floor} 層領主", 'boss_level' => $floor + 3];
}

// 塔的樓層上限變更為 20
if (isset($_GET['floor'])) {
    $target_floor = (int)$_GET['floor'];
    if ($target_floor < 1 || $target_floor > 20 || $target_floor > $user['max_floor'] + 1) {
        die("<h2 style='color:white; text-align:center;'>領域展開失敗：未解鎖的樓層！<br><a href='index.php'>⬅ 返回城鎮</a></h2>");
    }
    $_SESSION['run'] = ['floor' => $target_floor, 'node' => 1, 'hp' => $user['max_hp'], 'gold' => 0, 'exp' => 0, 'buffs' => ['dmg'=>0, 'def'=>0, 'max_hp'=>0], 'skill_gains' => [], 'log' => '', 'state' => 'auto'];
    header("Location: tower.php"); 
    exit;
}

if (!isset($_SESSION['run'])) { header("Location: index.php"); exit; }

$run = &$_SESSION['run'];
$target_floor = $run['floor'];
$floor_data = get_floor_data($target_floor);
$story_nodes = [5, 10, 15, 20, 25, 29];

// 同時讀取爆擊與閃避技能
$crit_lvl = 0;
$dodge_lvl = 0;
$skill_res = $conn->query("SELECT skill_id, level FROM user_skills WHERE user_id = $user_id");
if ($skill_res && $skill_res->num_rows > 0) {
    while($row = $skill_res->fetch_assoc()) {
        if ($row['skill_id'] === 'crit') $crit_lvl = $row['level'];
        if ($row['skill_id'] === 'dodge') $dodge_lvl = $row['level'];
    }
}
$p_crit_rate = 10 + $crit_lvl;
$p_dodge_rate = 10 + $dodge_lvl; // 玩家防守時的閃避機率

$new_log = ""; 
$old_log = isset($run['log']) ? $run['log'] : ""; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $node = $run['node'];
    
    $post_new = "<div class='node-box reveal-item hidden-item' data-delay='100'>";
    $post_old = "<div class='node-box'>";
    
    $add_post_line = function($text, $delay=800) use (&$post_new, &$post_old) {
        $post_new .= "<div class='reveal-item hidden-item' data-delay='$delay'>$text</div>";
        $post_old .= "<div>$text</div>";
    };

    $add_post_line("<h4 class='node-title'>節點 $node / 30 - 抉擇結果</h4>", 500);

    if ($run['state'] === 'wait_merchant' && strpos($action, 'merch_') === 0) {
        $btn_name = ($action === 'merch_A') ? "紅色按鈕" : "藍色按鈕";
        $add_post_line("<p>你深吸一口氣，按下了 $btn_name ...</p>", 1500);
        if (rand(1, 100) <= 50) {
            $run['buffs']['dmg'] += 10; 
            $add_post_line("<p style='color:#4caf50;'>🎉 商人拍手大笑：「運氣真好！」你獲得了一股狂暴之力。(本層傷害 +10)</p>", 1000);
        } else {
            $dmg = floor($user['max_hp'] * 0.3); 
            $run['hp'] -= $dmg;
            $add_post_line("<p style='color:#f44336;'>💀 轟！箱子爆炸了！商人壞笑著溜走。受到 $dmg 點傷害。</p>", 1000);
        }
        $run['state'] = ($run['hp'] > 0) ? 'auto' : 'dead';
    } elseif ($run['state'] === 'wait_exp' && strpos($action, 'exp_') === 0) {
        $cost = 5 * $target_floor; $gain = 10 * $target_floor;
        if ($action === 'exp_yes') {
            if (($user['gold'] + $run['gold']) >= $cost) {
                $run['gold'] -= $cost; $run['exp'] += $gain;
                $add_post_line("<p style='color:#64b5f6;'>✨ 交易成立！消耗了 $cost 金幣，獲得 $gain EXP！</p>", 1000);
            } else {
                $add_post_line("<p style='color:#888;'>金幣不足... 神秘學者鄙視地看了你一眼，轉身離去。</p>", 1000);
            }
        } else {
            $add_post_line("<p>你守住了錢包，拒絕了交易繼續前進。</p>", 1000);
        }
        $run['state'] = 'auto';
    }

    $post_new .= "</div>"; $post_old .= "</div>";
    $new_log .= $post_new; $run['log'] .= $post_old; $run['node']++; 
}

while ($run['state'] === 'auto' && $run['node'] <= 30) {
    $node = $run['node'];
    
    $node_new = "<div class='node-box reveal-item hidden-item' data-delay='150'>";
    $node_old = "<div class='node-box'>";
    
    $add_line = function($text, $delay = 800) use (&$node_new, &$node_old) {
        $node_new .= "<div class='reveal-item hidden-item' data-delay='$delay'>$text</div>";
        $node_old .= "<div>$text</div>";
    };

    $add_line("<h4 class='node-title'>節點 $node / 30</h4>", 500);

    if (in_array($node, $story_nodes)) { $add_line(get_story($target_floor, $node), 3000); } 
    elseif ($node == 30) { $event = 'boss'; } 
    else {
        $rand = rand(1, 100);
        if ($rand <= 30) { $event = 'monster'; } elseif ($rand <= 50) { $event = 'gold'; } elseif ($rand <= 65) { $event = 'heal'; } elseif ($rand <= 80) { $event = 'buff'; } elseif ($rand <= 90) { $event = 'merchant'; } else { $event = 'buy_exp'; }                   
    }

    if (isset($event)) {
        if ($event === 'merchant') {
            $run['state'] = 'wait_merchant';
            $form_html = "<p>「嘿嘿嘿... 冒險者，要不要來抽個盲盒？一半天堂，一半地獄喔...」</p><form method='post' style='display:flex; gap:10px; margin-top:10px;'><button type='submit' name='action' value='merch_A' class='btn-action' style='background:#f44336;'>🔴 拍下紅色按鈕</button><button type='submit' name='action' value='merch_B' class='btn-action' style='background:#2196f3;'>🔵 拍下藍色按鈕</button></form>";
            $add_line($form_html, 100); $new_log .= $node_new . "</div>"; break; 
        }
        if ($event === 'buy_exp') {
            $run['state'] = 'wait_exp';
            $cost = 5 * $target_floor; $gain = 10 * $target_floor;
            $form_html = "<p>「知識就是力量，給我 $cost 金幣，我傳授你 $gain 經驗值。」</p><form method='post' style='display:flex; gap:10px; margin-top:10px;'><button type='submit' name='action' value='exp_yes' class='btn-action' style='background:#4caf50;'>💰 支付金幣</button><button type='submit' name='action' value='exp_no' class='btn-action' style='background:#757575;'>🚶 轉身離開</button></form>";
            $add_line($form_html, 100); $new_log .= $node_new . "</div>"; break;
        }
        
        if ($event === 'gold') {
            $found_gold = rand(20, 50) * $target_floor; $run['gold'] += $found_gold;
            $add_line("<p>💰 發現寶箱！獲得 <span style='color:gold;'>$found_gold 金幣</span>！</p>", 1000);
        } elseif ($event === 'heal') {
            $heal = floor($user['max_hp'] * 0.2); $run['hp'] = min($user['max_hp'] + $run['buffs']['max_hp'], $run['hp'] + $heal);
            $add_line("<p>🧪 找到神聖甘泉，恢復 20% 生命。<span style='color:#4caf50;'>+$heal HP</span> (目前: {$run['hp']})</p>", 1000);
        } elseif ($event === 'buff') {
            $buff_types = ['dmg' => '傷害', 'def' => '防禦']; $keys = array_keys($buff_types); $b_key = $keys[array_rand($keys)]; $b_val = rand(2, 5) * $target_floor; 
            $run['buffs'][$b_key] += $b_val; $current_val = $user[$b_key] + $run['buffs'][$b_key];
            $add_line("<p>🌟 你觸碰了發光的石碑，獲得臨時強化！<br><span style='color:#64b5f6;'>{$buff_types[$b_key]} +$b_val (當前: $current_val)</span></p>", 1000);
        } elseif ($event === 'monster' || $event === 'boss') {
            $is_boss = ($event === 'boss');
            
            $m_lvl = $is_boss ? $floor_data['boss_level'] : $floor_data['mob_level'];
            $m_name_raw = $is_boss ? $floor_data['boss_name'] : $floor_data['mob_name'];
            $m_name = $is_boss ? "<span style='color:#f44336; font-weight:bold;'>💀 Boss: Lv.$m_lvl $m_name_raw</span>" : "🦇 Lv.$m_lvl $m_name_raw";
            
            if (isset($monster_db[$m_lvl])) {
                $m_stats = $monster_db[$m_lvl];
            } else {
                $m_stats = ['hp' => $m_lvl * 100, 'dmg' => $m_lvl * 10, 'def' => floor($m_lvl * 1.5), 'exp' => $m_lvl * 40, 'gold' => $m_lvl * 30];
            }
            
            $m_hp  = $m_stats['hp']; $m_dmg = $m_stats['dmg']; $m_def = $m_stats['def']; $m_exp = $m_stats['exp']; $m_gold = $m_stats['gold'];
            
            $add_line("<p>遭遇敵人：$m_name (HP: $m_hp)</p>", 1000);
            
            $node_new .= "<div class='combat-log reveal-item hidden-item' data-delay='300'>";
            $node_old .= "<div class='combat-log'>";
            
            $p_dmg = $user['dmg'] + $run['buffs']['dmg']; 
            $p_def = $user['def'] + $run['buffs']['def'];

            while ($run['hp'] > 0 && $m_hp > 0) {
                
                // 為了支援閃避技能，新增 dodge_rate 和 is_defender_player 參數
                $execute_attack = function($attacker, &$def_hp, $atk_dmg, $def_def, $crit_rate, $dodge_rate, $is_attacker_player, $is_defender_player) use (&$node_new, &$node_old, &$run) {
                    $line = "";
                    
                    if (rand(1, 100) <= $dodge_rate) {
                        $line .= "<span style='color:#888;'>$attacker 的攻擊被閃避了！</span>";
                        
                        // 🌟 如果防禦方(閃避方)是玩家，增加閃避熟練度
                        if ($is_defender_player) {
                            if(!isset($run['skill_gains']['dodge'])) $run['skill_gains']['dodge'] = 0;
                            $run['skill_gains']['dodge']++;
                        }
                    } else {
                        $is_crit = (rand(1, 100) <= $crit_rate);
                        
                        // 如果攻擊方是玩家且爆擊，增加爆擊熟練度
                        if ($is_crit && $is_attacker_player) {
                            if(!isset($run['skill_gains']['crit'])) $run['skill_gains']['crit'] = 0;
                            $run['skill_gains']['crit']++;
                        }
                        
                        $final_atk = $is_crit ? floor($atk_dmg * 1.5) : $atk_dmg; 
                        $actual_dmg = max(1, $final_atk - $def_def);
                        $def_hp -= $actual_dmg;
                        
                        if ($is_crit) {
                            $line .= "<span>💥 <b>爆擊！</b></span> ";
                        }
                        $line .= "$attacker 造成了 <span style='color:#ff9800;'>$actual_dmg</span> 點傷害。";
                        if ($def_def > 0) {
                            $line .= " <span style='color:#666; font-size:12px;'>(被抵抗 $def_def 點)</span>";
                        }
                    }
                    $node_new .= "<div class='reveal-item hidden-item' data-delay='600'>$line</div>";
                    $node_old .= "<div>$line</div>";
                };

                // 玩家先攻：玩家攻擊，套用玩家爆擊、怪物固定 10% 閃避
                $execute_attack($user['username'], $m_hp, $p_dmg, $m_def, $p_crit_rate, 10, true, false);
                
                // 怪物存活才反擊：怪物攻擊，套用怪物 10% 爆擊、玩家動態閃避率
                if ($m_hp > 0) {
                    $execute_attack(strip_tags($m_name), $run['hp'], $m_dmg, $p_def, 10, $p_dodge_rate, false, true);
                }
            }
            $node_new .= "</div>"; $node_old .= "</div>"; 
            
            if ($run['hp'] <= 0) {
                $add_line("<p style='color:#f44336; font-weight:bold;'>你被擊敗了... 探索中斷。</p>", 1000);
                $run['state'] = 'dead';
            } else {
                $run['exp'] += $m_exp; $run['gold'] += $m_gold;
                $add_line("<p style='color:#4caf50;'>戰鬥勝利！剩餘 HP: {$run['hp']}，獲得 <span style='color:#64b5f6;'>$m_exp EXP</span> 與 <span style='color:gold;'>$m_gold 金幣</span>。</p>", 1000);
            }
        }
    }

    $new_log .= $node_new . "</div>";
    $run['log'] .= $node_old . "</div>";
    
    $run['node']++;
}

// ==============================================================================
// [結算] 寫入資料庫與技能升級判定
// ==============================================================================
if (($run['state'] === 'auto' && $run['node'] > 30) || $run['state'] === 'dead') {
    $f_gold = $run['gold'];
    $f_exp = $run['exp'];
    
    $crit_gain_display = 0;
    $dodge_gain_display = 0;

    if (isset($run['skill_gains']) && !empty($run['skill_gains'])) {
        foreach ($run['skill_gains'] as $s_id => $gained_exp) {
            if ($s_id === 'crit') $crit_gain_display = $gained_exp;
            if ($s_id === 'dodge') $dodge_gain_display = $gained_exp;
            
            $curr_q = $conn->query("SELECT level, exp FROM user_skills WHERE user_id=$user_id AND skill_id='$s_id'");
            if ($curr_q && $curr_q->num_rows > 0) {
                $row = $curr_q->fetch_assoc(); 
                $s_lvl = $row['level']; 
                $s_exp = $row['exp'] + $gained_exp;
            } else {
                $s_lvl = 0; 
                $s_exp = $gained_exp;
            }

            while (true) {
                $req = ($s_lvl + 1) * 10;
                if ($s_exp >= $req) {
                    $s_exp -= $req; 
                    $s_lvl++;
                } else {
                    break;
                }
            }
            
            $conn->query("INSERT INTO user_skills (user_id, skill_id, level, exp) VALUES ($user_id, '$s_id', $s_lvl, $s_exp) ON DUPLICATE KEY UPDATE level = $s_lvl, exp = $s_exp");
        }
    }
    
    $update_sql = "UPDATE users SET hp = max_hp, gold = gold + $f_gold, exp = exp + $f_exp ";

    if ($run['hp'] > 0) {
        $is_new_record = ($target_floor > $user['max_floor']);
        $title_msg = $is_new_record ? "🎉 恭喜突破第 $target_floor 層！" : "🔁 第 $target_floor 層探索完畢！";
        $end_html = "<h3>$title_msg</h3><p>總計獲得：<span style='color:gold;'>$f_gold 金幣</span>, <span style='color:#64b5f6;'>$f_exp EXP</span>";
        if ($crit_gain_display > 0) $end_html .= "<br><span style='color:#ce93d8;'>💥 爆擊熟練度 +$crit_gain_display</span>";
        if ($dodge_gain_display > 0) $end_html .= "<br><span style='color:#81c784;'>🍃 閃避熟練度 +$dodge_gain_display</span>";
        $end_html .= "</p>";
        
        if ($is_new_record) $update_sql .= ", max_floor = $target_floor ";
    } else {
        $end_html = "<h3 style='color:#f44336;'>💀 挑戰失敗結算</h3><p>雖然失敗，但仍帶回了：<span style='color:gold;'>$f_gold 金幣</span>, <span style='color:#64b5f6;'>$f_exp EXP</span>";
        if ($crit_gain_display > 0) $end_html .= "<br><span style='color:#ce93d8;'>💥 爆擊熟練度 +$crit_gain_display</span>";
        if ($dodge_gain_display > 0) $end_html .= "<br><span style='color:#81c784;'>🍃 閃避熟練度 +$dodge_gain_display</span>";
        $end_html .= "</p>";
    }
    
    $conn->query($update_sql . " WHERE id = $user_id");
    
    $new_log .= "<div class='node-box success-box reveal-item hidden-item' data-delay='1000'>$end_html</div>";
    $new_log .= "<div id='btn-container' class='reveal-item hidden-item' data-delay='100'><a href='index.php' class='back-btn'>⬅ 結算並返回城鎮</a></div>";
    
    unset($_SESSION['run']); 
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>探索第 <?php echo $target_floor; ?> 層</title>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background-color: #1e1e24; color: #e0e0e0; margin: 0; }
        .tower-container { max-width: 700px; width: 100%; margin: 0 auto; padding-bottom: 80px; }
        h2 { border-bottom: 2px solid #ff9800; padding-bottom: 10px; color: #fff;}
        .node-box { background: #2b2b36; border: 1px solid #3f3f4e; padding: 20px; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .node-title { margin-top: 0; color: #9e9e9e; border-bottom: 1px solid #3f3f4e; padding-bottom: 8px;}
        .combat-log { background: #1a1a20; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 14px; color: #bbb; line-height: 1.8; margin: 10px 0; border-left: 3px solid #64b5f6;}
        .success-box { border: 2px solid #ffca28; background: #332d18; text-align: center; }
        .success-box h3 { color: #ffca28; margin-top: 0;}
        .back-btn { display: inline-block; background: #4caf50; color: white; text-decoration: none; font-size: 16px; padding: 12px 20px; border-radius: 6px; font-weight: bold; margin-bottom: 30px;}
        .back-btn:hover { background: #45a049; }
        .btn-action { color: white; border: none; padding: 10px 15px; font-size: 14px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: opacity 0.2s;}
        .btn-action:hover { opacity: 0.8; }
        .hidden-item { display: none; }
    </style>
</head>
<body>

<div class="tower-container">
    <h2>⚔️ 探索第 <?php echo $target_floor; ?> 層</h2>
    
    <?php echo $old_log; ?>
    <?php echo $new_log; ?>
    
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const items = document.querySelectorAll('.hidden-item');
    let currentIndex = 0;
    
    function revealNext() {
        if (currentIndex < items.length) {
            let el = items[currentIndex];
            el.classList.remove('hidden-item');
            
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            
            let delay = parseInt(el.getAttribute('data-delay')) || 1000;
            currentIndex++;
            
            setTimeout(revealNext, delay);
        }
    }
    
    if(items.length > 0) { 
        window.scrollTo({ top: document.body.scrollHeight }); 
        setTimeout(revealNext, 500); 
    }
});
</script>

</body>
</html>