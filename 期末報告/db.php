<?php
// 正式環境：程式層保底，確保錯誤不顯示給使用者（.htaccess 為主要設定）
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Session Cookie 安全屬性（必須在 session_start() 之前設定）
if (session_status() === PHP_SESSION_NONE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

$host   = 'localhost';
$user   = 'root';
$pass   = '';
$dbname = 'targame';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    error_log('DB Connect Error: ' . $conn->connect_error);
    die("伺服器暫時無法使用，請稍後再試。");
}
// 使用 utf8mb4 以正確支援 emoji 與完整 Unicode
$conn->set_charset("utf8mb4");

// 自動偵測部署路徑，供 session 重導向使用
if (!defined('BASE_URL')) {
    $_doc = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $_dir = str_replace('\\', '/', __DIR__);
    define('BASE_URL', $_doc ? rtrim(str_replace($_doc, '', $_dir), '/') : '');
    unset($_doc, $_dir);
}
?>