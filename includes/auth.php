<?php
require_once __DIR__ . '/db.php';

function session_start_once(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function current_user(): ?array {
    session_start_once();
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        // 從 admin/ 子目錄呼叫時，login.php 與目前檔案同層
        header('Location: login.php'); exit;
    }
    if ($user['is_banned']) {
        session_destroy();
        header('Location: login.php?err=banned'); exit;
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if ($user['role'] !== 'admin') {
        header('Location: login.php?err=forbidden'); exit;
    }
    return $user;
}
