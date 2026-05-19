<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
$char = get_character($user['id']);

session_start_once();
// 初始化戰鬥 session
if (empty($_SESSION['battle']) || $_SESSION['battle']['floor'] !== $char['max_floor']) {
    $monster = get_monster($char['max_floor']);
    $_SESSION['battle'] = [
        'floor'      => $char['max_floor'],
        'monster'    => $monster,
        'char_hp'    => $char['hp'],
        'rounds'     => 0,
        'over'       => false,
        'enemy_first'=> $monster['spd'] > $char['agi'],
    ];
}
$battle  = &$_SESSION['battle'];
$monster = $battle['monster'];

$monsterIcons = ['👹','🕷','🦀','💀','👺','🤺','🐙','🐜','🦇','❄'];
$mIcon = $monster['is_boss'] ? '👑' : $monsterIcons[($char['max_floor']-1) % count($monsterIcons)];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>異界塔 — 戰鬥</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="battle-wrap">

  <div class="battle-header">
    <a href="game.php" class="btn btn-ghost" style="padding:6px 14px;font-size:.85em;text-decoration:none">← 返回</a>
    <h2>第 <?= $char['max_floor'] ?> 層<?= $monster['is_boss'] ? ' ★ BOSS' : '' ?></h2>
  </div>

  <div class="battle-body">

    <div class="combatants">
      <div class="combatant player">
        <span class="c-icon">🧙</span>
        <div class="c-name"><?= htmlspecialchars($char['username']) ?> Lv.<?= $char['level'] ?></div>
        <div class="c-hp-label"><span>HP</span><span id="p-hp-text"><?= $battle['char_hp'] ?> / <?= $char['max_hp'] ?></span></div>
        <div class="bar c-hp-bar">
          <div class="bar-fill" id="p-hp-bar" style="width:<?= round($battle['char_hp']/$char['max_hp']*100) ?>%"></div>
        </div>
      </div>

      <div class="vs-badge">VS</div>

      <div class="combatant enemy">
        <span class="c-icon" <?= $monster['is_boss'] ? 'style="filter:drop-shadow(0 0 14px rgba(245,166,35,.7))"' : '' ?>><?= $mIcon ?></span>
        <div class="c-name"><?= htmlspecialchars($monster['name']) ?></div>
        <div class="c-hp-label"><span>HP</span><span id="e-hp-text"><?= $battle['monster']['hp'] ?> / <?= $monster['max_hp'] ?></span></div>
        <div class="bar c-hp-bar">
          <div class="bar-fill" id="e-hp-bar" style="width:<?= round($battle['monster']['hp']/$monster['max_hp']*100) ?>%"></div>
        </div>
      </div>
    </div>

    <div class="battle-log" id="battle-log">
      <?php if ($battle['enemy_first'] && $battle['rounds'] === 0): ?>
        <div class="log-line system">⚔ 戰鬥開始！<?= htmlspecialchars($monster['name']) ?> 速度較快，先手攻擊！</div>
      <?php else: ?>
        <div class="log-line system">⚔ 戰鬥開始！</div>
      <?php endif; ?>
    </div>

    <div class="action-panel" id="action-panel">
      <div class="row">
        <button class="btn btn-red"     onclick="act('attack')">⚔ 普通攻擊</button>
        <button class="btn btn-primary" onclick="act('defend')">🛡 防禦姿態</button>
      </div>
      <div class="row">
        <button class="btn btn-ghost"   onclick="act('flee')">🏃 嘗試逃跑</button>
      </div>
    </div>

  </div>
</div>

<!-- 結果 -->
<div class="result-overlay" id="result-overlay">
  <div class="result-box">
    <h2 id="result-title"></h2>
    <p  id="result-desc"></p>
    <a href="game.php" class="btn btn-primary">返回主畫面</a>
  </div>
</div>

<script>
const MAX_HP_P = <?= $char['max_hp'] ?>;
const MAX_HP_E = <?= $monster['max_hp'] ?>;
let busy = false;

<?php if ($battle['enemy_first'] && $battle['rounds'] === 0): ?>
// 敵人先手
window.addEventListener('load', () => {
  setActions(false);
  setTimeout(() => doEnemyFirst(), 800);
});

function doEnemyFirst() {
  fetch('api/battle_action.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'enemy_first'})})
    .then(r=>r.json()).then(handleResponse);
}
<?php endif; ?>

function act(action) {
  if (busy) return;
  busy = true;
  setActions(false);
  fetch('api/battle_action.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action})
  }).then(r => r.json()).then(handleResponse);
}

function handleResponse(data) {
  data.logs.forEach(l => addLog(l.msg, l.type));

  document.getElementById('p-hp-text').textContent = data.char_hp + ' / ' + MAX_HP_P;
  document.getElementById('p-hp-bar').style.width  = Math.max(0, data.char_hp / MAX_HP_P * 100) + '%';
  document.getElementById('e-hp-text').textContent = data.monster_hp + ' / ' + MAX_HP_E;
  document.getElementById('e-hp-bar').style.width  = Math.max(0, data.monster_hp / MAX_HP_E * 100) + '%';

  if (data.over) {
    showResult(data.result, data.result_title, data.result_desc);
  } else {
    busy = false;
    setActions(true);
  }
}

function addLog(msg, type='') {
  const log = document.getElementById('battle-log');
  const d = document.createElement('div');
  d.className = 'log-line ' + type;
  d.textContent = msg;
  log.appendChild(d);
  log.scrollTop = log.scrollHeight;
}

function setActions(on) {
  document.querySelectorAll('#action-panel button').forEach(b => b.disabled = !on);
}

function showResult(type, title, desc) {
  document.getElementById('result-title').textContent = title;
  document.getElementById('result-title').className   = type;
  document.getElementById('result-desc').textContent  = desc;
  document.getElementById('result-overlay').classList.add('show');
}
</script>
</body>
</html>
