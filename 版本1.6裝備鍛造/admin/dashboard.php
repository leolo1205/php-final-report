<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();
$pdo   = db();

$totalPlayers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='player'")->fetchColumn();
$totalBattles = $pdo->query("SELECT COUNT(*) FROM battle_records WHERE DATE(fought_at)=CURDATE()")->fetchColumn();
$totalTrains  = $pdo->query("SELECT COUNT(*) FROM training_logs WHERE DATE(trained_at)=CURDATE()")->fetchColumn();
$topFloor     = $pdo->query("SELECT MAX(max_floor) FROM users WHERE role='player'")->fetchColumn() ?: 0;
$topPlayer    = $pdo->query("SELECT username FROM users WHERE role='player' ORDER BY max_floor DESC, level DESC LIMIT 1")->fetchColumn() ?: '—';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>後台 — 總覽</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  .admin-wrap { display:flex; min-height:100vh; }

  /* ── Sidebar ── */
  .sidebar {
    width: 210px; background: var(--surface); border-right: 1px solid var(--border);
    padding: 20px 0; display: flex; flex-direction: column; gap: 2px; flex-shrink: 0;
  }
  .sidebar .logo {
    padding: 0 20px 16px; font-size: 1.05em; font-weight: 700;
    color: var(--primary); border-bottom: 1px solid var(--border); margin-bottom: 8px;
  }
  .sidebar a {
    padding: 10px 20px; color: var(--muted); text-decoration: none;
    font-size: .9em; display: flex; align-items: center; gap: 8px;
    transition: background .15s, color .15s; border-left: 3px solid transparent;
  }
  .sidebar a:hover  { background: var(--primary-dim); color: var(--primary); }
  .sidebar a.active { background: var(--primary-dim); color: var(--primary); border-left-color: var(--primary); }
  .sidebar .spacer  { flex: 1; }
  .sidebar .user-info {
    padding: 14px 20px; border-top: 1px solid var(--border);
    font-size: .82em; color: var(--muted);
  }

  /* ── Main ── */
  .admin-main { flex: 1; padding: 32px; overflow-y: auto; }
  .page-title { font-size: 1.35em; font-weight: 700; color: var(--text); margin-bottom: 8px; }
  .page-sub   { color: var(--muted); font-size: .9em; margin-bottom: 28px; }

  /* ── Stat cards ── */
  .stat-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px; margin-bottom: 36px;
  }
  .stat-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 12px; padding: 22px 18px; text-align: center;
    transition: box-shadow .15s;
  }
  .stat-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); }
  .stat-card .num  { font-size: 2.4em; font-weight: 900; color: var(--primary); line-height: 1; }
  .stat-card .lbl  { font-size: .82em; color: var(--muted); margin-top: 8px; }
  .stat-card.gold  .num { color: var(--secondary); }
  .stat-card.green .num { color: var(--green); }

  /* ── Recent section ── */
  .section-title { font-size: 1em; font-weight: 700; color: var(--text); margin-bottom: 14px; }
  table { width: 100%; border-collapse: collapse; font-size: .9em; }
  th { background: var(--surface2); color: var(--muted); padding: 10px 14px; text-align: left; border-bottom: 2px solid var(--border); font-weight: 600; font-size: .85em; }
  td { padding: 10px 14px; border-bottom: 1px solid var(--border); }
  tr:last-child td { border-bottom: none; }
  tr:hover td { background: var(--surface2); }
  .card { margin-bottom: 24px; }
</style>
</head>
<body>
<div class="admin-wrap">

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="logo">⚔ 異界塔後台</div>
    <a href="dashboard.php" class="active">📊 總覽</a>
    <a href="users.php">👥 玩家管理</a>
    <a href="logs.php">📋 紀錄查詢</a>
    <a href="../game.php" style="color:var(--secondary);">🏰 前往城鎮</a>
    <div class="spacer"></div>
    <div class="user-info">
      登入：<?= htmlspecialchars($admin['username']) ?><br>
      <a href="logout.php" style="color:var(--red);text-decoration:none;font-size:.95em;">登出</a>
    </div>
  </div>

  <!-- Main -->
  <div class="admin-main">
    <div class="page-title">今日總覽</div>
    <div class="page-sub">即時統計資料</div>

    <div class="stat-cards">
      <div class="stat-card">
        <div class="num"><?= $totalPlayers ?></div>
        <div class="lbl">總玩家數</div>
      </div>
      <div class="stat-card green">
        <div class="num"><?= $topFloor ?></div>
        <div class="lbl">最高通關層數</div>
      </div>
      <div class="stat-card gold">
        <div class="num"><?= $totalBattles ?></div>
        <div class="lbl">今日戰鬥次數</div>
      </div>
      <div class="stat-card">
        <div class="num"><?= $totalTrains ?></div>
        <div class="lbl">今日訓練次數</div>
      </div>
    </div>

    <!-- 玩家排行 -->
    <div class="card">
      <div class="section-title">🏆 玩家排行（前 10 名）</div>
      <table>
        <thead>
          <tr>
            <th>#</th><th>玩家名稱</th><th>等級</th><th>最高層數</th>
            <th>ATK</th><th>DEF</th><th>金幣</th><th>最後訓練</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $rows = $pdo->query("
              SELECT username, level, max_floor,
                     (str+agi+con+intel+per+cha) AS total_stat,
                     (str*2) AS atk_display,
                     con AS def_display,
                     gold, last_train_time
              FROM users WHERE role='player' AND is_banned=0
              ORDER BY max_floor DESC, level DESC
              LIMIT 10
          ")->fetchAll();
          foreach ($rows as $i => $r):
          ?>
          <tr>
            <td style="color:var(--muted)"><?= $i+1 ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($r['username']) ?></td>
            <td>Lv. <?= $r['level'] ?></td>
            <td style="color:var(--primary);font-weight:700"><?= $r['max_floor'] ?> 層</td>
            <td><?= $r['atk_display'] ?></td>
            <td><?= $r['def_display'] ?></td>
            <td style="color:var(--secondary)"><?= number_format($r['gold']) ?></td>
            <td style="color:var(--muted);font-size:.85em">
              <?= $r['last_train_time'] ? substr($r['last_train_time'],0,16) : '從未訓練' ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</body>
</html>
