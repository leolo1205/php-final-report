<?php
require_once __DIR__ . '/includes/auth.php';
session_start_once();

if (!empty($_SESSION['user_id'])) {
    header('Location: game.php'); exit;
}

$tab        = 'login';
$err_login  = '';
$err_reg    = '';
$ok_reg     = '';

// ── 登入 ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $err_login = '請輸入帳號與密碼';
    } else {
        $stmt = db()->prepare("SELECT id, password, is_banned FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $err_login = '找不到此帳號';
        } elseif ($user['is_banned']) {
            $err_login = '此帳號已被封鎖，請聯絡管理員';
        } elseif (!password_verify($password, $user['password'])) {
            $err_login = '密碼錯誤，請重試';
        } else {
            $_SESSION['user_id'] = $user['id'];
            header('Location: game.php'); exit;
        }
    }
}

// ── 註冊 ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'register') {
    $tab      = 'register';
    $username = trim($_POST['reg_username'] ?? '');
    $password = $_POST['reg_password'] ?? '';
    $confirm  = $_POST['reg_confirm']  ?? '';

    if ($username === '' || $password === '') {
        $err_reg = '請填寫所有欄位';
    } elseif (mb_strlen($username) < 2 || mb_strlen($username) > 16) {
        $err_reg = '帳號長度需在 2–16 字元之間';
    } elseif (mb_strlen($password) < 6) {
        $err_reg = '密碼至少需要 6 個字元';
    } elseif ($password !== $confirm) {
        $err_reg = '兩次密碼輸入不一致';
    } else {
        $pdo  = db();
        $dup  = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $dup->execute([$username]);
        if ($dup->fetch()) {
            $err_reg = '此帳號名稱已被使用';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO users
                  (username, password, role, level, exp, str, agi, con, intel, per, cha, gold, max_floor, hp, max_hp)
                VALUES
                  (?, ?, 'player', 1, 0, 10, 10, 10, 10, 10, 10, 0, 0, 100, 100)
            ")->execute([$username, $hash]);

            $newId = (int)$pdo->lastInsertId();
            $_SESSION['user_id'] = $newId;
            header('Location: game.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>異界塔 — 登入</title>
<link rel="stylesheet" href="assets/style.css">
<style>
  body {
    display: flex; align-items: center; justify-content: center;
    min-height: 100vh; background: #0e1117;
  }

  .login-wrap {
    width: 400px;
    background: #1a1d27;
    border: 1px solid #2a2d3e;
    border-radius: 16px;
    padding: 40px 36px 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
  }

  .logo {
    text-align: center;
    font-size: 1.8em;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 6px;
    letter-spacing: 2px;
  }
  .logo-sub {
    text-align: center;
    color: #4b5563;
    font-size: .82em;
    letter-spacing: 1px;
    margin-bottom: 28px;
  }

  /* ── 頁籤 ── */
  .tabs {
    display: flex;
    border-bottom: 1px solid #2a2d3e;
    margin-bottom: 24px;
  }
  .tab-btn {
    flex: 1; background: none; border: none; cursor: pointer;
    padding: 10px 0; font-size: .95em; font-family: var(--font);
    color: #4b5563; border-bottom: 2px solid transparent;
    margin-bottom: -1px; transition: color .15s, border-color .15s;
  }
  .tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    font-weight: 600;
  }

  /* ── 表單 ── */
  .field { margin-bottom: 16px; }
  .field label {
    display: block; font-size: .8em; color: #6b7280;
    margin-bottom: 6px; letter-spacing: 1px; text-transform: uppercase;
  }
  .field input {
    width: 100%; padding: 11px 14px;
    background: #0d1117; border: 1px solid #2a2d3e; border-radius: 8px;
    color: #e5e7eb; font-size: .95em; font-family: var(--font);
    transition: border-color .15s;
  }
  .field input:focus { outline: none; border-color: var(--primary); }

  .btn-submit {
    width: 100%; padding: 13px; border: none; border-radius: 8px;
    background: var(--primary); color: #fff;
    font-size: 1em; font-family: var(--font); font-weight: 700;
    cursor: pointer; letter-spacing: 1px;
    transition: filter .15s, transform .1s; margin-top: 6px;
  }
  .btn-submit:hover  { filter: brightness(1.12); transform: translateY(-1px); }
  .btn-submit:active { transform: translateY(0); }

  /* ── 訊息 ── */
  .msg-err {
    background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3);
    color: #fca5a5; border-radius: 8px;
    padding: 10px 13px; font-size: .88em; margin-bottom: 16px;
  }
  .msg-ok {
    background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.3);
    color: #6ee7b7; border-radius: 8px;
    padding: 10px 13px; font-size: .88em; margin-bottom: 16px;
  }

  .admin-link {
    display: block; text-align: center;
    margin-top: 22px; font-size: .8em; color: #374151;
    text-decoration: none;
  }
  .admin-link:hover { color: #6b7280; }

  .tab-panel { display: none; }
  .tab-panel.active { display: block; }
</style>
</head>
<body>

<div class="login-wrap">
  <div class="logo">⚔ 異界塔</div>
  <div class="logo-sub">TOWER OF THE OTHER REALM</div>

  <div class="tabs">
    <button class="tab-btn <?= $tab === 'login'    ? 'active' : '' ?>" onclick="switchTab('login')">登入</button>
    <button class="tab-btn <?= $tab === 'register' ? 'active' : '' ?>" onclick="switchTab('register')">註冊</button>
  </div>

  <!-- ── 登入 ── -->
  <div id="panel-login" class="tab-panel <?= $tab === 'login' ? 'active' : '' ?>">
    <?php if ($err_login): ?>
    <div class="msg-err">⚠ <?= htmlspecialchars($err_login) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="form" value="login">
      <div class="field">
        <label>帳號</label>
        <input type="text" name="username"
               placeholder="輸入角色名稱"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               autofocus required>
      </div>
      <div class="field">
        <label>密碼</label>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-submit">進入遊戲</button>
    </form>
  </div>

  <!-- ── 註冊 ── -->
  <div id="panel-register" class="tab-panel <?= $tab === 'register' ? 'active' : '' ?>">
    <?php if ($err_reg): ?>
    <div class="msg-err">⚠ <?= htmlspecialchars($err_reg) ?></div>
    <?php endif; ?>
    <?php if ($ok_reg): ?>
    <div class="msg-ok">✓ <?= htmlspecialchars($ok_reg) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="form" value="register">
      <div class="field">
        <label>角色名稱（2–16字）</label>
        <input type="text" name="reg_username"
               placeholder="你的冒險者名稱"
               value="<?= htmlspecialchars($_POST['reg_username'] ?? '') ?>"
               maxlength="16" required>
      </div>
      <div class="field">
        <label>密碼（至少6位）</label>
        <input type="password" name="reg_password" placeholder="••••••••" required>
      </div>
      <div class="field">
        <label>確認密碼</label>
        <input type="password" name="reg_confirm" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-submit">建立角色</button>
    </form>
  </div>

  <a href="admin/login.php" class="admin-link">管理員入口 →</a>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b =>
    b.classList.toggle('active', b.textContent.trim() === (tab === 'login' ? '登入' : '註冊'))
  );
  document.getElementById('panel-login').classList.toggle('active', tab === 'login');
  document.getElementById('panel-register').classList.toggle('active', tab === 'register');
}
// 伺服器端決定初始 tab（POST 錯誤後保持在對應 tab）
document.addEventListener('DOMContentLoaded', () => {
  const init = '<?= $tab ?>';
  if (init === 'register') switchTab('register');
});
</script>
</body>
</html>
