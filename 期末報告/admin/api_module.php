<?php
require_once 'auth.php';
require_once '../db.php';
require_once '../lib/session.php';
function q_val($conn, $sql) {
    $r = $conn->query($sql);
    return $r ? ($r->fetch_row()[0] ?? 0) : 0;
}

// ── 訓練 API 統計 ──
$train_today   = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='train' AND DATE(created_at)=CURDATE()");
$train_ok      = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='train' AND status='success' AND DATE(created_at)=CURDATE()");
$train_fail    = $train_today - $train_ok;
$train_avg_ms  = q_val($conn, "SELECT ROUND(AVG(response_ms),1) FROM api_logs WHERE api_name='train' AND DATE(created_at)=CURDATE()");
$train_actions = [];
foreach (['cooldown_check','start_train','add_stat'] as $a) {
    $train_actions[$a] = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='train' AND action='$a' AND DATE(created_at)=CURDATE()");
}

// ── 鍛造 API 統計 ──
$forge_today   = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='forge' AND DATE(created_at)=CURDATE()");
$forge_ok      = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='forge' AND status='success' AND DATE(created_at)=CURDATE()");
$forge_fail    = $forge_today - $forge_ok;
$forge_avg_ms  = q_val($conn, "SELECT ROUND(AVG(response_ms),1) FROM api_logs WHERE api_name='forge' AND DATE(created_at)=CURDATE()");
$forge_actions = [];
foreach (['get_status','upgrade'] as $a) {
    $forge_actions[$a] = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='forge' AND action='$a' AND DATE(created_at)=CURDATE()");
}
$forge_upgrade_ok   = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='forge' AND action='upgrade' AND status='success' AND DATE(created_at)=CURDATE()");
$forge_upgrade_fail = $forge_actions['upgrade'] - $forge_upgrade_ok;

// ── PVP API 統計 ──
$pvp_today  = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='pvp' AND DATE(created_at)=CURDATE()");
$pvp_ok     = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='pvp' AND status='success' AND DATE(created_at)=CURDATE()");
$pvp_challenge_total = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='pvp' AND action='challenge' AND DATE(created_at)=CURDATE()");

// ── 塔探索統計 ──
$tower_today   = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='tower' AND DATE(created_at)=CURDATE()");
$tower_win     = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='tower' AND action='win'    AND DATE(created_at)=CURDATE()");
$tower_escape  = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='tower' AND action='escape' AND DATE(created_at)=CURDATE()");
$tower_lose    = q_val($conn, "SELECT COUNT(*) FROM api_logs WHERE api_name='tower' AND action='lose'   AND DATE(created_at)=CURDATE()");
$tower_avg_ms  = q_val($conn, "SELECT ROUND(AVG(response_ms),0) FROM api_logs WHERE api_name='tower' AND DATE(created_at)=CURDATE()");
$tower_win_rate = $tower_today > 0 ? round($tower_win / $tower_today * 100, 1) : 0;

// ── 最近 50 筆 API 記錄 ──
$logs_res = $conn->query("SELECT * FROM api_logs ORDER BY created_at DESC LIMIT 50");
$api_logs = [];
if ($logs_res) {
    while ($r = $logs_res->fetch_assoc()) $api_logs[] = $r;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API 模組 — 後台管理</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<?php include '_sidebar.php'; ?>

  <div class="admin-topbar">
    <div class="page-title">🔌 API 模組</div>
    <div class="breadcrumb">後台管理 / <span>API 模組</span></div>
  </div>

  <div class="content">

    <!-- ── 訓練 API 監控 ── -->
    <div class="section" style="margin-bottom:24px;">
      <div class="section-header">
        <h3>🏋️ 訓練 API 監控</h3>
        <span class="badge">api/train.php · 今日統計</span>
      </div>
      <div style="padding:20px;">
        <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
          <div class="stat-card blue">
            <div class="label">今日呼叫</div>
            <div class="value"><?= number_format($train_today) ?></div>
            <div class="sub">所有 action 合計</div>
          </div>
          <div class="stat-card green">
            <div class="label">成功次數</div>
            <div class="value" style="color:#66bb6a;"><?= $train_ok ?></div>
            <div class="sub">成功率 <?= $train_today > 0 ? round($train_ok/$train_today*100,1) : 0 ?>%</div>
          </div>
          <div class="stat-card" style="border-color:#ef5350;">
            <div class="label">失敗次數</div>
            <div class="value" style="color:#ef5350;"><?= $train_fail ?></div>
            <div class="sub">失敗率 <?= $train_today > 0 ? round($train_fail/$train_today*100,1) : 0 ?>%</div>
          </div>
          <div class="stat-card yellow">
            <div class="label">平均回應</div>
            <div class="value" style="font-size:26px;"><?= $train_avg_ms ?></div>
            <div class="sub">毫秒 (ms)</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
          <?php
          $action_labels = [
            'cooldown_check' => ['🕐','冷卻查詢','#4fc3f7'],
            'start_train'    => ['▶️','開始訓練','#66bb6a'],
            'add_stat'       => ['📊','屬性配點','#e040fb'],
          ];
          foreach ($train_actions as $a => $cnt):
            [$icon, $label, $color] = $action_labels[$a];
          ?>
          <div style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:8px;padding:14px;text-align:center;">
            <div style="font-size:22px;margin-bottom:6px;"><?= $icon ?></div>
            <div style="font-size:20px;font-weight:700;color:<?= $color ?>;"><?= $cnt ?></div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;"><?= $label ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── 鍛造 API 監控 ── -->
    <div class="section" style="margin-bottom:24px;">
      <div class="section-header">
        <h3>⚒️ 鍛造 API 監控</h3>
        <span class="badge">api/forge.php · 今日統計</span>
      </div>
      <div style="padding:20px;">
        <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
          <div class="stat-card blue">
            <div class="label">今日呼叫</div>
            <div class="value"><?= number_format($forge_today) ?></div>
            <div class="sub">所有 action 合計</div>
          </div>
          <div class="stat-card green">
            <div class="label">成功次數</div>
            <div class="value" style="color:#66bb6a;"><?= $forge_ok ?></div>
            <div class="sub">成功率 <?= $forge_today > 0 ? round($forge_ok/$forge_today*100,1) : 0 ?>%</div>
          </div>
          <div class="stat-card" style="border-color:#ef5350;">
            <div class="label">升級失敗</div>
            <div class="value" style="color:#ef5350;"><?= $forge_upgrade_fail ?></div>
            <div class="sub">鍛造失敗次數</div>
          </div>
          <div class="stat-card yellow">
            <div class="label">平均回應</div>
            <div class="value" style="font-size:26px;"><?= $forge_avg_ms ?></div>
            <div class="sub">毫秒 (ms)</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
          <div style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:8px;padding:14px;text-align:center;">
            <div style="font-size:22px;margin-bottom:6px;">🔍</div>
            <div style="font-size:20px;font-weight:700;color:#4fc3f7;"><?= $forge_actions['get_status'] ?></div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">查詢裝備狀態</div>
          </div>
          <div style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:8px;padding:14px;text-align:center;">
            <div style="font-size:22px;margin-bottom:6px;">⚒️</div>
            <div style="font-size:20px;font-weight:700;color:#ffca28;"><?= $forge_actions['upgrade'] ?></div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">鍛造升級（成功 <?= $forge_upgrade_ok ?> / 失敗 <?= $forge_upgrade_fail ?>）</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── PVP API 監控 ── -->
    <div class="section" style="margin-bottom:24px;">
      <div class="section-header">
        <h3>⚔️ PVP API 監控</h3>
        <span class="badge">api/pvp.php · 今日統計</span>
      </div>
      <div style="padding:20px;">
        <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
          <div class="stat-card blue">
            <div class="label">今日呼叫</div>
            <div class="value"><?= number_format($pvp_today) ?></div>
            <div class="sub">所有 action 合計</div>
          </div>
          <div class="stat-card green">
            <div class="label">成功次數</div>
            <div class="value" style="color:#66bb6a;"><?= $pvp_ok ?></div>
            <div class="sub">成功率 <?= $pvp_today > 0 ? round($pvp_ok/$pvp_today*100,1) : 0 ?>%</div>
          </div>
          <div class="stat-card yellow">
            <div class="label">今日對戰</div>
            <div class="value" style="color:#ffca28;"><?= $pvp_challenge_total ?></div>
            <div class="sub">challenge 次數</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── 塔探索監控 ── -->
    <div class="section" style="margin-bottom:24px;">
      <div class="section-header">
        <h3>🗼 塔探索監控</h3>
        <span class="badge">tower.php · 今日結算</span>
      </div>
      <div style="padding:20px;">
        <div class="stat-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:16px;">
          <div class="stat-card blue">
            <div class="label">今日結算</div>
            <div class="value"><?= $tower_today ?></div>
            <div class="sub">爬塔次數</div>
          </div>
          <div class="stat-card green">
            <div class="label">通關</div>
            <div class="value" style="color:#66bb6a;"><?= $tower_win ?></div>
            <div class="sub">勝率 <?= $tower_win_rate ?>%</div>
          </div>
          <div class="stat-card yellow">
            <div class="label">撤退</div>
            <div class="value" style="color:#ffca28;"><?= $tower_escape ?></div>
            <div class="sub"><?= $tower_today > 0 ? round($tower_escape/$tower_today*100,1) : 0 ?>%</div>
          </div>
          <div class="stat-card" style="border-color:#ef5350;">
            <div class="label">陣亡</div>
            <div class="value" style="color:#ef5350;"><?= $tower_lose ?></div>
            <div class="sub"><?= $tower_today > 0 ? round($tower_lose/$tower_today*100,1) : 0 ?>%</div>
          </div>
          <div class="stat-card" style="border-color:#7e57c2;">
            <div class="label">平均耗時</div>
            <div class="value" style="font-size:22px;color:#b39ddb;"><?= number_format($tower_avg_ms) ?></div>
            <div class="sub">毫秒 (ms)</div>
          </div>
        </div>
        <?php if ($tower_today > 0): ?>
        <div style="background:#0d0d1a;border:1px solid #2a2a4a;border-radius:8px;padding:14px;">
          <div style="font-size:11px;color:#94a3b8;margin-bottom:8px;">結果分布</div>
          <?php
          $bar_w = fn($n) => $tower_today > 0 ? round($n / $tower_today * 100) : 0;
          foreach ([['通關','win','#4caf50',$tower_win],['撤退','escape','#ff9800',$tower_escape],['陣亡','lose','#ef5350',$tower_lose]] as [$lbl,,$col,$cnt]):
          ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <div style="width:36px;font-size:11px;color:#94a3b8;"><?= $lbl ?></div>
            <div style="flex:1;background:#1a1a2e;border-radius:4px;height:14px;overflow:hidden;">
              <div style="width:<?= $bar_w($cnt) ?>%;height:100%;background:<?= $col ?>;border-radius:4px;transition:width .4s;"></div>
            </div>
            <div style="width:30px;font-size:11px;color:#e0e0e0;text-align:right;"><?= $cnt ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── API 即時記錄 ── -->
    <div style="margin-bottom:24px;">
      <div class="section">
        <div class="section-header">
          <h3>📋 API 呼叫即時記錄</h3>
          <span class="badge">最近 50 筆</span>
        </div>
        <div style="overflow-x:auto;">
          <table class="tbl" style="font-size:12px;">
            <thead><tr>
              <th style="width:40px;">#</th>
              <th>API</th>
              <th>Action</th>
              <th>玩家</th>
              <th>狀態</th>
              <th>回應(ms)</th>
              <th>時間</th>
            </tr></thead>
            <tbody>
            <?php if (empty($api_logs)): ?>
            <tr><td colspan="7" style="text-align:center;color:#444;padding:30px;">尚無 API 記錄</td></tr>
            <?php else: foreach ($api_logs as $i => $lg): ?>
            <tr>
              <td style="color:#555;"><?= $i+1 ?></td>
              <td>
                <span style="display:inline-block;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:600;
                  background:<?= $lg['api_name']==='train' ? '#1a3a2a' : '#2a1a1a' ?>;
                  color:<?= $lg['api_name']==='train' ? '#66bb6a' : '#ef5350' ?>;">
                  <?= $lg['api_name'] ?>
                </span>
              </td>
              <td style="color:#94a3b8;font-size:11px;"><?= htmlspecialchars($lg['action']) ?></td>
              <td style="color:#b0bec5;"><?= $lg['user_id'] ?? '<span style="color:#555;">—</span>' ?></td>
              <td>
                <span class="tag <?= $lg['status']==='success' ? 'tag-active' : 'tag-lose' ?>">
                  <?= $lg['status'] ?>
                </span>
              </td>
              <td style="color:<?= $lg['response_ms'] > 200 ? '#ffca28' : '#66bb6a' ?>;">
                <?= $lg['response_ms'] ?>
              </td>
              <td style="color:#555;font-size:11px;"><?= substr($lg['created_at'],5,14) ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

</body>
</html>
