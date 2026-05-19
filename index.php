<?php
require_once __DIR__ . '/includes/auth.php';
session_start_once();
header('Location: ' . (!empty($_SESSION['user_id']) ? 'game.php' : 'login.php'));
exit;
