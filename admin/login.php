<?php
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 已登入直接跳轉
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php'); exit;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $err = '請輸入帳號與密碼';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: dashboard.php'); exit;
        } else {
            $err = '帳號或密碼錯誤，或無管理員權限';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>後台登入 — 異界塔</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
  body { display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .login-box {
    width: 360px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 36px 32px;
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
  }
  .login-box h1 {
    font-size: 1.4em;
    color: var(--primary);
    text-align: center;
    margin-bottom: 6px;
  }
  .login-box .sub {
    text-align: center;
    color: var(--muted);
    font-size: .85em;
    margin-bottom: 28px;
  }
  .field { margin-bottom: 16px; }
  .field label { display:block; font-size:.85em; color:var(--muted); margin-bottom:6px; font-weight:600; }
  .field input {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-size: .95em;
    font-family: var(--font);
    background: var(--surface2);
    color: var(--text);
    transition: border-color .15s;
  }
  .field input:focus { outline:none; border-color:var(--primary); }
  .err {
    background: rgba(239,68,68,.08);
    color: var(--red);
    border: 1px solid rgba(239,68,68,.2);
    border-radius: 8px;
    padding: 10px 14px;
    font-size: .88em;
    margin-bottom: 16px;
  }
  .btn-login {
    width: 100%;
    padding: 12px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    font-family: var(--font);
    font-weight: 600;
    cursor: pointer;
    transition: filter .15s;
    margin-top: 4px;
  }
  .btn-login:hover { filter: brightness(1.1); }
  .back { display:block; text-align:center; margin-top:18px; font-size:.85em; color:var(--muted); text-decoration:none; }
  .back:hover { color:var(--primary); }
</style>
</head>
<body>
<div class="login-box">
  <h1>⚔ 後台管理</h1>
  <p class="sub">異界塔 管理員登入</p>

  <?php if ($err): ?>
  <div class="err"><?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <label>管理員帳號</label>
      <input type="text" name="username" placeholder="admin"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autofocus>
    </div>
    <div class="field">
      <label>密碼</label>
      <input type="password" name="password" placeholder="••••••••">
    </div>
    <button type="submit" class="btn-login">登入後台</button>
  </form>

  <a href="../game.php" class="back">← 返回遊戲</a>
</div>
</body>
</html>
