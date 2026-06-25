<?php
// 前台側邊欄 include
// 需要：$user['username'], $user['level'] 已定義
$_sb_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">⚔️</span>
        <h2>塔城傳說</h2>
        <p>TAR GAME TOWN</p>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section">城鎮設施</div>
        <a href="index.php"       class="sidebar-link <?= $_sb_page==='index.php'       ?'active':'' ?>">🏠 <span>主城鎮</span></a>
        <a href="skills_build.php" class="sidebar-link <?= $_sb_page==='skills_build.php'?'active':'' ?>">⚔️ <span>技能樹</span></a>
        <a href="forge.php"       class="sidebar-link <?= $_sb_page==='forge.php'       ?'active':'' ?>">⚒️ <span>裝備鍛造</span></a>
        <a href="arena.php"       class="sidebar-link <?= $_sb_page==='arena.php'       ?'active':'' ?>">🏟️ <span>競技場</span></a>
        <a href="skills.php"      class="sidebar-link <?= $_sb_page==='skills.php'      ?'active':'' ?>">📖 <span>被動技能</span></a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-player">
            <div class="avatar">👤</div>
            <div>
                <div class="player-name"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="player-level">Lv.<?= (int)$user['level'] ?> 冒險者</div>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout">⏻ 登出</a>
    </div>
</aside>
