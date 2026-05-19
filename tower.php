<?php
date_default_timezone_set('Asia/Taipei');
require_once __DIR__ . '/includes/auth.php';

$user    = require_login();
$user_id = $user['id'];
$pdo     = db();
$char    = get_user($user_id);

// ── 劇情文字 ─────────────────────────────────────────────────
function get_story(int $floor, int $node): string {
    $stories = [
        1 => [
             5 => "眼前是一片寧靜祥和的森林，空氣真好...",
            10 => "一隻敏捷的兔子從你眼前跑過，牠就這樣消失了。",
            15 => "爬上樹頂，能清楚地看見森林中央有一塊空地。",
            20 => "隨著繼續向前，空地越來越清晰。",
            25 => "空地中央有一座巨大祭壇，散發著古老而神秘的氣息。",
            29 => "一聲嚎叫劃破寧靜，一股強大的威壓向你壓來...",
        ],
        2 => [
             5 => "森林深處光線漸暗，空氣中瀰漫著淡淡的血腥味...",
            10 => "樹枝上掛著奇怪的繭，似乎有什麼東西在裡面蠕動。",
            15 => "地上散落著生鏽的兵器，這裡曾經發生過激烈的戰鬥。",
            20 => "周圍溫度急遽下降，連呼吸都會吐出白霧。",
            25 => "前方有一個巨大的洞穴入口，那裡就是巢穴了。",
            29 => "洞穴深處傳來令人毛骨悚然的振翅聲...",
        ],
    ];
    $text = $stories[$floor][$node] ?? "此處一片死寂，只有你的腳步聲迴盪著。";
    return "<p style='color:#ce93d8;font-style:italic;font-size:15px;padding:10px;border-left:3px solid #ce93d8;background:#2a2233;'>📜 $text</p>";
}

// ── 怪物資料 ─────────────────────────────────────────────────
function get_floor_data(int $floor): array {
    $floors = [
        1 => ['mob'=>'野狼','hp'=>30,'str'=>5,'agi'=>5,'per'=>5,
              'boss'=>'暴走的尖牙野豬','boss_hp'=>150,'boss_str'=>15,'boss_agi'=>10,'boss_per'=>10],
        2 => ['mob'=>'暗影蝙蝠','hp'=>50,'str'=>10,'agi'=>10,'per'=>5,
              'boss'=>'吸血蝙蝠領主','boss_hp'=>300,'boss_str'=>25,'boss_agi'=>20,'boss_per'=>20],
        3 => ['mob'=>'石像哥布林','hp'=>80,'str'=>14,'agi'=>8,'per'=>7,
              'boss'=>'哥布林酋長','boss_hp'=>500,'boss_str'=>35,'boss_agi'=>15,'boss_per'=>15],
        4 => ['mob'=>'熔岩蟹','hp'=>120,'str'=>18,'agi'=>6,'per'=>10,
              'boss'=>'熔岩蟹王','boss_hp'=>700,'boss_str'=>45,'boss_agi'=>12,'boss_per'=>20],
        5 => ['mob'=>'幽靈騎士','hp'=>160,'str'=>22,'agi'=>14,'per'=>14,
              'boss'=>'冥界將軍','boss_hp'=>1000,'boss_str'=>60,'boss_agi'=>25,'boss_per'=>25],
    ];
    if (isset($floors[$floor])) return $floors[$floor];
    return [
        'mob' => "第{$floor}層守衛", 'hp' => 50*$floor, 'str' => 10*$floor, 'agi' => 8*$floor, 'per' => 8*$floor,
        'boss' => "第{$floor}層領主", 'boss_hp' => 200*$floor, 'boss_str' => 20*$floor, 'boss_agi' => 15*$floor, 'boss_per' => 15*$floor,
    ];
}

// ── 進入塔層（GET）───────────────────────────────────────────
if (isset($_GET['floor'])) {
    $f = (int)$_GET['floor'];
    if ($f < 1 || $f > 10 || $f > $char['max_floor'] + 1) {
        die("<h2 style='color:#fff;text-align:center;padding:60px'>❌ 未解鎖的樓層！<br><a href='game.php' style='color:#4caf50'>⬅ 返回城鎮</a></h2>");
    }
    $_SESSION['run'] = [
        'floor'  => $f,
        'node'   => 1,
        'hp'     => $char['max_hp'],
        'gold'   => 0,
        'exp'    => 0,
        'buffs'  => ['str'=>0,'agi'=>0,'con'=>0,'intel'=>0,'per'=>0,'cha'=>0,'max_hp'=>0],
        'log'    => '',
        'state'  => 'auto',
    ];
    header('Location: tower.php'); exit;
}

if (!isset($_SESSION['run'])) { header('Location: game.php'); exit; }

$run         = &$_SESSION['run'];
$target_floor = $run['floor'];
$fd          = get_floor_data($target_floor);
$story_nodes = [5, 10, 15, 20, 25, 29];
$new_log     = '';
$old_log     = $run['log'];

// ── 玩家互動（POST）──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $node   = $run['node'];

    $pn  = "<div class='node-box reveal-item hidden-item' data-delay='100'>";
    $po  = "<div class='node-box'>";
    $add = function(string $text, int $delay=800) use (&$pn, &$po) {
        $pn .= "<div class='reveal-item hidden-item' data-delay='$delay'>$text</div>";
        $po .= "<div>$text</div>";
    };
    $add("<h4 class='node-title'>節點 $node / 30 — 抉擇結果</h4>", 500);

    if ($run['state'] === 'wait_merchant' && str_starts_with($action, 'merch_')) {
        $btn = ($action === 'merch_A') ? '紅色按鈕' : '藍色按鈕';
        $add("<p>你按下了 $btn ...</p>", 1500);
        if (rand(1,100) <= 50) {
            $run['buffs']['str'] += 30;
            $add("<p style='color:#4caf50;'>🎉 商人大笑：「運氣真好！」（本層力量 +30）</p>", 1000);
        } else {
            $dmg = (int)($char['max_hp'] * 0.3);
            $run['hp'] -= $dmg;
            $add("<p style='color:#ef4444;'>💀 箱子爆炸！受到 $dmg 點傷害。</p>", 1000);
        }
        $run['state'] = ($run['hp'] > 0) ? 'auto' : 'dead';
    }
    elseif ($run['state'] === 'wait_exp' && str_starts_with($action, 'exp_')) {
        $cost = 5 * $target_floor;
        $gain = 10 * $target_floor;
        if ($action === 'exp_yes') {
            $total_gold = $char['gold'] + $run['gold'];
            if ($total_gold >= $cost) {
                $run['gold'] -= $cost;
                $run['exp']  += $gain;
                $add("<p style='color:#64b5f6;'>✨ 交易成立！消耗 $cost 金幣，獲得 $gain EXP！</p>", 1000);
            } else {
                $add("<p style='color:#888;'>金幣不足... 學者鄙視地轉身離去。</p>", 1000);
            }
        } else {
            $add("<p>你拒絕了交易，繼續前進。</p>", 1000);
        }
        $run['state'] = 'auto';
    }

    $pn .= '</div>'; $po .= '</div>';
    $new_log    .= $pn;
    $run['log'] .= $po;
    $run['node']++;
}

// ── 自動產生節點 ─────────────────────────────────────────────
while ($run['state'] === 'auto' && $run['node'] <= 30) {
    $node = $run['node'];
    $nn   = "<div class='node-box reveal-item hidden-item' data-delay='150'>";
    $no   = "<div class='node-box'>";
    $add  = function(string $text, int $delay=800) use (&$nn, &$no) {
        $nn .= "<div class='reveal-item hidden-item' data-delay='$delay'>$text</div>";
        $no .= "<div>$text</div>";
    };

    $add("<h4 class='node-title'>節點 $node / 30</h4>", 500);

    if (in_array($node, $story_nodes)) {
        $add(get_story($target_floor, $node), 3000);
        $event = null;
    } elseif ($node == 30) {
        $event = 'boss';
    } else {
        $r = rand(1,100);
        $event = match(true) {
            $r <= 30 => 'monster',
            $r <= 50 => 'gold',
            $r <= 65 => 'heal',
            $r <= 80 => 'buff',
            $r <= 90 => 'merchant',
            default  => 'buy_exp',
        };
    }

    if ($event === 'merchant') {
        $run['state'] = 'wait_merchant';
        $form = "<p>「嘿嘿嘿... 冒險者，要不要抽個盲盒？一半天堂，一半地獄...」</p>
                 <form method='post' style='display:flex;gap:10px;margin-top:10px;'>
                   <button type='submit' name='action' value='merch_A' class='btn-action' style='background:#ef4444;'>🔴 紅色按鈕</button>
                   <button type='submit' name='action' value='merch_B' class='btn-action' style='background:#2196f3;'>🔵 藍色按鈕</button>
                 </form>";
        $add($form, 100);
        $new_log .= $nn . '</div>'; break;
    }
    if ($event === 'buy_exp') {
        $run['state'] = 'wait_exp';
        $cost = 5 * $target_floor; $gain = 10 * $target_floor;
        $form = "<p>「知識就是力量，給我 $cost 金幣，我傳授你 $gain 經驗值。」</p>
                 <form method='post' style='display:flex;gap:10px;margin-top:10px;'>
                   <button type='submit' name='action' value='exp_yes' class='btn-action' style='background:#4caf50;'>💰 支付金幣</button>
                   <button type='submit' name='action' value='exp_no'  class='btn-action' style='background:#757575;'>🚶 轉身離開</button>
                 </form>";
        $add($form, 100);
        $new_log .= $nn . '</div>'; break;
    }
    if ($event === 'gold') {
        $g = rand(20,50) * $target_floor;
        $run['gold'] += $g;
        $add("<p>💰 發現寶箱！獲得 <span style='color:gold;'>$g 金幣</span>！</p>", 1000);
    }
    elseif ($event === 'heal') {
        $heal = (int)($char['max_hp'] * 0.2);
        $run['hp'] = min($char['max_hp'] + $run['buffs']['max_hp'], $run['hp'] + $heal);
        $add("<p>🧪 找到神聖甘泉，恢復 20% 生命。<span style='color:#4caf50;'>+$heal HP</span>（目前：{$run['hp']}）</p>", 1000);
    }
    elseif ($event === 'buff') {
        $types = ['str'=>'力量','agi'=>'敏捷','con'=>'體魄'];
        $key   = array_rand($types);
        $val   = rand(1,3) * $target_floor;
        $run['buffs'][$key] += $val;
        $add("<p>🌟 你觸碰了發光石碑，獲得臨時強化！<br><span style='color:#64b5f6;'>{$types[$key]} +{$val}（僅限本層）</span></p>", 1000);
    }
    elseif ($event === 'monster' || $event === 'boss') {
        $is_boss = ($event === 'boss');
        $m_name  = $is_boss ? "<span style='color:#ef4444;font-weight:bold;'>💀 Boss: {$fd['boss']}</span>" : "🦇 {$fd['mob']}";
        $m_hp    = $is_boss ? $fd['boss_hp']  : $fd['hp'];
        $m_str   = $is_boss ? $fd['boss_str'] : $fd['str'];
        $m_agi   = $is_boss ? $fd['boss_agi'] : $fd['agi'];
        $m_per   = $is_boss ? $fd['boss_per'] : $fd['per'];

        $add("<p>遭遇敵人：{$m_name}（HP: {$m_hp}）</p>", 1000);
        $nn .= "<div class='combat-log reveal-item hidden-item' data-delay='300'>";
        $no .= "<div class='combat-log'>";

        $p_str = $char['str'] + $run['buffs']['str'];
        $p_agi = $char['agi'] + $run['buffs']['agi'];
        $p_per = $char['per'] + $run['buffs']['per'];

        while ($run['hp'] > 0 && $m_hp > 0) {
            $p_first = ($p_agi >= $m_agi);
            $p_hit   = max(5, min(95, 80 + ($p_per*5) - ($m_agi*5)));
            $p_crit  = min(80, 5 + $p_per);
            $p_dmg   = $p_str * 2;
            $m_hit   = max(5, min(95, 80 + ($m_per*5) - ($p_agi*5)));
            $m_dmg   = (int)($m_str * 1.5);

            $do_attack = function(string $attacker, int &$def_hp, int $hit, int $crit, int $dmg) use (&$nn, &$no) {
                $line = '';
                if (rand(1,100) <= $hit) {
                    if (rand(1,100) <= $crit) { $dmg = (int)($dmg*1.5); $line .= "<span>💥 <b>爆擊！</b></span> "; }
                    $def_hp -= $dmg;
                    $line .= "$attacker 造成了 $dmg 點傷害。";
                } else {
                    $line .= "<span style='color:#888;'>$attacker 的攻擊被閃避了！</span>";
                }
                $nn .= "<div class='reveal-item hidden-item' data-delay='600'>$line</div>";
                $no .= "<div>$line</div>";
            };

            if ($p_first) {
                $do_attack($char['username'], $m_hp, $p_hit, $p_crit, $p_dmg);
                if ($m_hp > 0) $do_attack(strip_tags($m_name), $run['hp'], $m_hit, 0, $m_dmg);
            } else {
                $do_attack(strip_tags($m_name), $run['hp'], $m_hit, 0, $m_dmg);
                if ($run['hp'] > 0) $do_attack($char['username'], $m_hp, $p_hit, $p_crit, $p_dmg);
            }
        }
        $nn .= '</div>'; $no .= '</div>';

        if ($run['hp'] <= 0) {
            $add("<p style='color:#ef4444;font-weight:bold;'>你被擊敗了... 探索中斷。</p>", 1000);
            $run['state'] = 'dead';
        } else {
            $win_exp = $is_boss ? 150 : 30;
            $run['exp'] += $win_exp;
            $add("<p style='color:#4caf50;'>戰鬥勝利！剩餘 HP：{$run['hp']}，獲得 $win_exp EXP。</p>", 1000);
        }
    }

    $new_log    .= $nn . '</div>';
    $run['log'] .= $no . '</div>';
    $run['node']++;
}

// ── 結算 ─────────────────────────────────────────────────────
if (($run['state'] === 'auto' && $run['node'] > 30) || $run['state'] === 'dead') {
    $f_gold = $run['gold'];
    $f_exp  = $run['exp'];
    $won    = ($run['hp'] > 0);

    $pdo->prepare("UPDATE users SET hp=max_hp, gold=gold+?, exp=exp+? WHERE id=?")
        ->execute([$f_gold, $f_exp, $user_id]);

    if ($won) {
        $is_new = ($target_floor > $char['max_floor']);
        if ($is_new) $pdo->prepare("UPDATE users SET max_floor=? WHERE id=?")->execute([$target_floor, $user_id]);
        $title = $is_new ? "🎉 恭喜突破第 $target_floor 層！" : "🔁 第 $target_floor 層探索完畢！";
    } else {
        $title = "<span style='color:#ef4444;'>💀 挑戰失敗結算</span>";
    }

    $end_html = "<h3>$title</h3><p>總計獲得：<span style='color:gold;'>$f_gold 金幣</span>，<span style='color:#64b5f6;'>$f_exp EXP</span></p>";
    $new_log .= "<div class='node-box success-box reveal-item hidden-item' data-delay='1000'>$end_html</div>";
    $new_log .= "<div id='btn-back' class='reveal-item hidden-item' data-delay='100'><a href='game.php' class='back-btn'>⬅ 結算並返回城鎮</a></div>";
    unset($_SESSION['run']);
}

$target_floor_display = $target_floor;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>探索第 <?= $target_floor ?> 層</title>
<style>
  body { font-family:'Segoe UI','Microsoft JhengHei',sans-serif; padding:20px; background:#1e1e24; color:#e0e0e0; display:flex; justify-content:center; }
  .tower-container { max-width:700px; width:100%; padding-bottom:80px; }
  h2 { border-bottom:2px solid #ff9800; padding-bottom:10px; color:#fff; }
  .node-box { background:#2b2b36; border:1px solid #3f3f4e; padding:20px; margin-bottom:20px; border-radius:10px; box-shadow:0 4px 6px rgba(0,0,0,.2); }
  .node-title { margin-top:0; color:#9e9e9e; border-bottom:1px solid #3f3f4e; padding-bottom:8px; }
  .combat-log { background:#1a1a20; padding:10px; border-radius:6px; font-family:monospace; font-size:14px; color:#bbb; line-height:1.8; margin:10px 0; border-left:3px solid #64b5f6; }
  .success-box { border:2px solid #ffca28; background:#332d18; text-align:center; }
  .success-box h3 { color:#ffca28; margin-top:0; }
  .back-btn { display:inline-block; background:#4caf50; color:#fff; text-decoration:none; font-size:16px; padding:12px 20px; border-radius:6px; font-weight:bold; margin-bottom:30px; }
  .back-btn:hover { background:#45a049; }
  .btn-action { color:#fff; border:none; padding:10px 15px; font-size:14px; border-radius:6px; cursor:pointer; font-weight:bold; transition:opacity .2s; }
  .btn-action:hover { opacity:.8; }
  .hidden-item { display:none; }
  .topbar-simple { display:flex; justify-content:space-between; align-items:center; padding:10px 0 20px; }
  .topbar-simple a { color:#4caf50; text-decoration:none; font-size:.9em; }
</style>
</head>
<body>
<div class="tower-container">
  <div class="topbar-simple">
    <a href="game.php">⬅ 返回城鎮</a>
    <span style="color:#aaa;font-size:.85em;">👤 <?= htmlspecialchars($char['username']) ?></span>
  </div>
  <h2>⚔️ 探索第 <?= $target_floor ?> 層</h2>
  <?= $old_log ?>
  <?= $new_log ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const items = document.querySelectorAll('.hidden-item');
  let i = 0;
  function next() {
    if (i >= items.length) return;
    items[i].classList.remove('hidden-item');
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    const delay = parseInt(items[i].getAttribute('data-delay')) || 1000;
    i++;
    setTimeout(next, delay);
  }
  if (items.length > 0) { window.scrollTo({ top: document.body.scrollHeight }); setTimeout(next, 500); }
});
</script>
</body>
</html>
