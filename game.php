<?php
date_default_timezone_set('Asia/Taipei');
require_once __DIR__ . '/includes/auth.php';

$user    = require_login();
$user_id = $user['id'];
$pdo     = db();
$msg     = '';

// ── 重置帳號 ─────────────────────────────────────────────────
if (isset($_POST['reset_account'])) {
    $pdo->prepare("UPDATE users SET
        level=1, exp=0, str=10, agi=10, con=10, intel=10, per=10, cha=10,
        gold=0, max_floor=0, hp=100, max_hp=100, last_train_time=NULL
        WHERE id=?")->execute([$user_id]);
    $msg = "<span style='color:#ef4444;'><b>🚨 帳號已成功重置。</b></span>";
}

// ── 讀取玩家 ─────────────────────────────────────────────────
$user = get_user($user_id);

// ── 升級檢查 ─────────────────────────────────────────────────
while ($user['exp'] >= $user['level'] * 100) {
    $new_exp    = $user['exp'] - $user['level'] * 100;
    $new_con    = $user['con'] + 1;
    $new_max_hp = $user['max_hp'] + ($new_con * 2);
    $pdo->prepare("UPDATE users SET
        level=level+1, exp=?,
        str=str+1, agi=agi+1, con=con+1, intel=intel+1, per=per+1, cha=cha+1,
        max_hp=?, hp=?
        WHERE id=?")->execute([$new_exp, $new_max_hp, $new_max_hp, $user_id]);
    $user = get_user($user_id);
    $msg .= "<span style='color:#fbbf24;'><b>🎉 升級至 Lv.{$user['level']}！全屬性 +1！</b></span><br>";
}

// ── 訓練 ─────────────────────────────────────────────────────
if (isset($_POST['train'])) {
    $now   = new DateTime();
    $last  = $user['last_train_time'] ? new DateTime($user['last_train_time']) : null;
    $diff  = $last ? $now->getTimestamp() - $last->getTimestamp() : PHP_INT_MAX;

    if ($diff >= TRAIN_CD_SECONDS) {
        $stats    = ['str','agi','con','intel','per','cha'];
        shuffle($stats);
        $up       = array_slice($stats, 0, rand(2,3));
        $sets     = implode(', ', array_map(fn($s) => "$s=$s+1", $up));
        $up_label = implode(' ', array_map('strtoupper', $up));
        $pdo->prepare("UPDATE users SET last_train_time=NOW(), exp=exp+50, $sets WHERE id=?")
            ->execute([$user_id]);
        $msg .= "訓練完成！獲得 <b>50 EXP</b>。提升：<b>$up_label</b>";
        header('Refresh:0'); exit;
    }
}

$user      = get_user($user_id);
$now       = new DateTime();
$last      = $user['last_train_time'] ? new DateTime($user['last_train_time']) : null;
$diff      = $last ? $now->getTimestamp() - $last->getTimestamp() : PHP_INT_MAX;
$remaining = max(0, TRAIN_CD_SECONDS - $diff);
$exp_need  = $user['level'] * 100;
$exp_pct   = min(100, ($user['exp'] / $exp_need) * 100);
$hp_pct    = ($user['max_hp'] > 0) ? ($user['hp'] / $user['max_hp']) * 100 : 100;

// ── 排行榜（目前只有一位玩家，之後可擴充）────────────────────
$rank = $pdo->query("SELECT username, level, max_floor, gold FROM users ORDER BY max_floor DESC, level DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>異界塔 — 城鎮</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI','Microsoft JhengHei',sans-serif;background:#1e1e24;color:#e0e0e0;padding:24px 16px;}
  .container{display:flex;gap:20px;flex-wrap:wrap;max-width:960px;margin:0 auto;}
  .panel{background:#2b2b36;padding:25px;border-radius:12px;box-shadow:0 8px 16px rgba(0,0,0,.3);flex:1;min-width:280px;border:1px solid #3f3f4e;display:flex;flex-direction:column;gap:12px;}
  h2,h3{margin:0;color:#fff;border-bottom:2px solid #4caf50;padding-bottom:10px;}
  .msg{background:#1a4325;color:#a5d6a7;padding:10px 14px;border-radius:8px;border:1px solid #2e7d32;line-height:1.7;font-size:.93em;}
  label{font-size:.83em;color:#aaa;}
  .bar-wrap{position:relative;height:22px;background:#424242;border-radius:8px;overflow:hidden;}
  .bar{height:100%;transition:width .3s;}
  .bar-hp{background:#e53935;} .bar-exp{background:#4caf50;}
  .bar-txt{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;color:#fff;text-shadow:1px 1px 2px #000;}
  .stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
  .stat-item{background:#353542;padding:10px;border-radius:8px;text-align:center;font-size:13px;}
  .stat-val{font-size:22px;font-weight:bold;color:#64b5f6;display:block;margin-top:4px;}
  .btn{width:100%;padding:13px;border:none;border-radius:8px;font-size:15px;font-weight:bold;cursor:pointer;transition:opacity .2s;}
  .btn:hover{opacity:.85;} .btn:disabled{opacity:.5;cursor:not-allowed;}
  .btn-train{background:#4caf50;color:#fff;}
  .btn-reset{background:transparent;color:#f44336;border:1px solid #f44336;font-size:13px;padding:9px;}
  .btn-reset:hover{background:#f44336;color:#fff;}
  .floor-item{display:block;padding:14px;margin-bottom:8px;border-radius:8px;text-align:center;font-weight:bold;text-decoration:none;color:#fff;transition:transform .1s;}
  .floor-item:active{transform:scale(.98);}
  .floor-cleared{background:#2e7d32;border:1px solid #1b5e20;}
  .floor-current{background:#f57f17;border:1px solid #bc5100;}
  .floor-locked{background:#424242;border:1px solid #212121;color:#666;pointer-events:none;}
  .tower-list{overflow-y:auto;max-height:420px;padding-right:4px;}
  .rank-table{width:100%;border-collapse:collapse;font-size:.9em;}
  .rank-table th{color:#4caf50;border-bottom:1px solid #3f3f4e;padding:7px 10px;text-align:left;}
  .rank-table td{padding:8px 10px;border-bottom:1px solid #3f3f4e;color:#ccc;}
  .gold{color:gold;font-weight:bold;}
  .topbar{display:flex;justify-content:space-between;align-items:center;max-width:960px;margin:0 auto 20px;}
  .topbar h1{color:#4caf50;font-size:1.4em;letter-spacing:2px;}
  .topbar span{color:#888;font-size:.85em;}
</style>
</head>
<body>

<div class="topbar">
  <h1>⚔ 異界塔</h1>
  <span>城鎮廣場</span>
</div>

<div class="container">

  <!-- 角色面板 -->
  <div class="panel">
    <h2>🧑‍🚀 <?= htmlspecialchars($user['username']) ?>
      <span style="font-size:15px;color:#aaa;"> Lv. <?= $user['level'] ?></span>
    </h2>

    <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>

    <div>
      <label>❤️ HP</label>
      <div class="bar-wrap">
        <div class="bar bar-hp" style="width:<?= $hp_pct ?>%"></div>
        <div class="bar-txt"><?= $user['hp'] ?> / <?= $user['max_hp'] ?></div>
      </div>
    </div>

    <div>
      <label>✨ EXP</label>
      <div class="bar-wrap">
        <div class="bar bar-exp" style="width:<?= $exp_pct ?>%"></div>
        <div class="bar-txt"><?= $user['exp'] ?> / <?= $exp_need ?> (<?= round($exp_pct,1) ?>%)</div>
      </div>
    </div>

    <p>💰 金幣：<span class="gold"><?= $user['gold'] ?></span></p>

    <div class="stats-grid">
      <div class="stat-item">力量 STR<span class="stat-val"><?= $user['str'] ?></span></div>
      <div class="stat-item">敏捷 AGI<span class="stat-val"><?= $user['agi'] ?></span></div>
      <div class="stat-item">體魄 CON<span class="stat-val"><?= $user['con'] ?></span></div>
      <div class="stat-item">智慧 INT<span class="stat-val"><?= $user['intel'] ?></span></div>
      <div class="stat-item">感知 PER<span class="stat-val"><?= $user['per'] ?></span></div>
      <div class="stat-item">魅力 CHA<span class="stat-val"><?= $user['cha'] ?></span></div>
    </div>

    <form method="post">
      <button type="submit" name="train" id="trainBtn" class="btn btn-train">💪 進行訓練</button>
    </form>

    <form method="post" onsubmit="return confirm('⚠️ 確定要重置？等級、屬性、金幣、塔層數全部歸零。');">
      <button type="submit" name="reset_account" class="btn btn-reset">🚨 重置帳號</button>
    </form>
  </div>

  <!-- 右側 -->
  <div style="display:flex;flex-direction:column;gap:20px;flex:1;min-width:280px;">

    <div class="panel">
      <h3>🏰 爬塔挑戰（上限 10 層）</h3>
      <p style="color:#aaa;font-size:.9em;">最高通關：第 <b style="color:#4caf50;font-size:22px;"><?= $user['max_floor'] ?></b> 層</p>
      <div class="tower-list">
        <?php for ($i = 1; $i <= 10; $i++):
          if ($i <= $user['max_floor']): ?>
            <a href="tower.php?floor=<?= $i ?>" class="floor-item floor-cleared">✅ 第 <?= $i ?> 層（反覆探索）</a>
          <?php elseif ($i == $user['max_floor'] + 1): ?>
            <a href="tower.php?floor=<?= $i ?>" id="cur" class="floor-item floor-current">⚔️ 挑戰第 <?= $i ?> 層</a>
          <?php else: ?>
            <div class="floor-item floor-locked">🔒 第 <?= $i ?> 層</div>
          <?php endif;
        endfor; ?>
      </div>
    </div>

    <div class="panel">
      <h3>🏆 排行榜</h3>
      <table class="rank-table">
        <thead><tr><th>#</th><th>玩家</th><th>等級</th><th>最高層</th><th>金幣</th></tr></thead>
        <tbody>
          <?php foreach ($rank as $i => $r): ?>
          <tr>
            <td><?= ['🥇','🥈','🥉'][$i] ?? $i+1 ?></td>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td>Lv.<?= $r['level'] ?></td>
            <td><?= $r['max_floor'] ?> 層</td>
            <td class="gold"><?= $r['gold'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script>
let cd = <?= $remaining ?>;
const btn = document.getElementById('trainBtn');
if (cd > 0) {
  btn.disabled = true;
  const t = setInterval(() => {
    btn.textContent = `💪 訓練中（剩餘 ${cd} 秒）`;
    cd--;
    if (cd < 0) { clearInterval(t); btn.disabled=false; btn.textContent='💪 進行訓練'; }
  }, 1000);
  btn.textContent = `💪 訓練中（剩餘 ${cd} 秒）`;
}
document.getElementById('cur')?.scrollIntoView({block:'center'});
</script>
</body>
</html>
