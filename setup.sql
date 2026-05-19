-- ============================================================
-- 異界塔 targame 資料庫完整建置腳本
-- 請在 phpMyAdmin 執行此檔案（先刪除舊的 targame DB 再匯入）
-- ============================================================

CREATE DATABASE IF NOT EXISTS targame
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE targame;

-- ── 玩家資料表（含後台驗證欄位）──────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id              INT          PRIMARY KEY AUTO_INCREMENT,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL DEFAULT '',   -- bcrypt，後台登入用
    role            ENUM('player','admin') DEFAULT 'player',
    is_banned       TINYINT(1)   DEFAULT 0,
    level           INT          DEFAULT 1,
    exp             INT          DEFAULT 0,
    str             INT          DEFAULT 10,
    agi             INT          DEFAULT 10,
    con             INT          DEFAULT 10,
    intel           INT          DEFAULT 10,
    per             INT          DEFAULT 10,
    cha             INT          DEFAULT 10,
    gold            INT          DEFAULT 0,
    max_floor       INT          DEFAULT 0,
    hp              INT          DEFAULT 100,
    max_hp          INT          DEFAULT 100,
    last_train_time DATETIME     DEFAULT NULL,
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 戰鬥紀錄（後台查詢用）────────────────────────────────────
CREATE TABLE IF NOT EXISTS battle_records (
    id         INT     PRIMARY KEY AUTO_INCREMENT,
    user_id    INT     NOT NULL,
    floor      INT     NOT NULL,
    result     ENUM('win','lose') NOT NULL,
    exp_gain   INT     DEFAULT 0,
    gold_gain  INT     DEFAULT 0,
    fought_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 訓練紀錄（後台查詢用）────────────────────────────────────
CREATE TABLE IF NOT EXISTS training_logs (
    id          INT          PRIMARY KEY AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    stat_gained VARCHAR(200) NOT NULL,
    exp_gained  INT          DEFAULT 0,
    trained_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 預設資料 ──────────────────────────────────────────────────
-- 測試玩家（密碼：player123）
INSERT IGNORE INTO users (id, username, password, role, level, exp, str, agi, con, intel, per, cha, gold, max_floor, hp, max_hp)
VALUES (1, '玄墨', '$2y$10$0rx0quUiC4KeReJWhn1VMe7OceK9v49NfvxV8THhexDKR0Pl.1xDu', 'player', 3, 105, 12, 11, 11, 11, 11, 11, 715, 1, 140, 140);

-- 後台管理員帳號（密碼：admin123）
INSERT IGNORE INTO users (username, password, role)
VALUES ('admin', '$2y$10$TKh8H1.PfbuNIVsivgDnEOX0YEJP3FkBbDTLH6NpKIz3JwKHpFa2i', 'admin');

-- 若測試玩家舊密碼為空（先前版本），自動補上 player123 的 hash
UPDATE users SET password = '$2y$10$0rx0quUiC4KeReJWhn1VMe7OceK9v49NfvxV8THhexDKR0Pl.1xDu'
WHERE username = '玄墨' AND (password = '' OR password IS NULL);
