<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config.php';

// ── 角色資料（相容舊 battle 系統）──────────────────────────────
function get_character(int $user_id): array {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $c = $stmt->fetch();
    if (!$c) return [];
    $c['name']        = $c['username'];          // 相容舊欄位
    $c['tower_floor'] = $c['max_floor'];          // 相容舊欄位
    $c['exp_max']     = $c['level'] * 100;        // 相容舊欄位
    return $c;
}

// ── 怪物生成 ──────────────────────────────────────────────────
const MONSTER_NAMES = [
    '石像哥布林','暗影蜘蛛','熔岩蟹','毒霧骷髏','狂怒獸人',
    '幽靈騎士','深海觸手','鐵甲巨蟻','瘟疫蝙蝠','冰晶元素',
];
const BOSS_NAMES = [
    '熔焰龍將','深淵守門者','冰霜女王','混沌裂縫者','虛空吞噬者',
    '鋼鐵傀儡王','死亡預言者','暗影教主','神域守護者','世界終焉者',
];

function get_monster(int $floor): array {
    $isBoss = ($floor % 10 === 0);
    $base = [
        'hp'  => 50 + $floor * 15,
        'atk' => 5  + $floor * 2,
        'def' => 2  + $floor,
        'spd' => 4  + intdiv($floor, 5),
    ];
    if ($isBoss) {
        $idx = intdiv($floor, 10) - 1;
        $name = BOSS_NAMES[min($idx, count(BOSS_NAMES) - 1)];
        return [
            'name'       => $name,
            'hp'         => $base['hp'] * 3,
            'max_hp'     => $base['hp'] * 3,
            'atk'        => $base['atk'] * 2,
            'def'        => $base['def'] * 2,
            'spd'        => $base['spd'] + 3,
            'is_boss'    => true,
            'exp_reward' => (30 + $floor * 5) * 5,
        ];
    }
    $idx = ($floor - 1) % count(MONSTER_NAMES);
    return [
        'name'       => MONSTER_NAMES[$idx],
        'hp'         => $base['hp'],
        'max_hp'     => $base['hp'],
        'atk'        => $base['atk'],
        'def'        => $base['def'],
        'spd'        => $base['spd'],
        'is_boss'    => false,
        'exp_reward' => 30 + $floor * 5,
    ];
}

// ── 戰鬥計算 ──────────────────────────────────────────────────
function calc_damage(int $atk, int $def, int $per = 0): array {
    $crit = (mt_rand(1, 1000) <= (50 + $per * 8)); // 5% + per*0.8%
    $dmg  = max(1, $atk - $def + mt_rand(-2, 2));
    if ($crit) $dmg = (int)($dmg * 1.5);
    return ['dmg' => $dmg, 'crit' => $crit];
}

function dodge_chance(int $agi): float {
    return 0.03 + $agi * 0.005;
}

// ── 訓練 ──────────────────────────────────────────────────────
function do_train(int $user_id): array {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $c = $stmt->fetch();

    $now     = time();
    $last    = $c['last_train_time'] ? strtotime($c['last_train_time']) : 0;
    $elapsed = $now - $last;

    if ($elapsed < TRAIN_CD_SECONDS) {
        return ['ok' => false, 'remaining' => TRAIN_CD_SECONDS - $elapsed];
    }

    $stats  = ['str', 'agi', 'con', 'intel', 'per', 'cha'];
    shuffle($stats);
    $delta  = array_fill_keys($stats, 0);
    $delta[$stats[0]] = mt_rand(2, 4);
    $delta[$stats[1]] = mt_rand(2, 4);
    $delta[$stats[2]] = mt_rand(1, 2);

    $expGain   = mt_rand(20, 40);
    $newExp    = $c['exp'] + $expGain;
    $newLevel  = $c['level'];
    $newExpMax = $c['level'] * 100;
    $leveledUp = false;

    while ($newExp >= $newExpMax) {
        $newExp -= $newExpMax;
        $newLevel++;
        $newExpMax = $newLevel * 100;
        foreach ($stats as $s) $delta[$s]++;
        $leveledUp = true;
    }

    $newCon   = $c['con'] + $delta['con'];
    $newMaxHp = 80 + $newCon * 5;
    $newHp    = min($c['hp'] + 20, $newMaxHp);

    $sets = implode(', ', array_map(fn($s) => "$s = $s + {$delta[$s]}", $stats));
    $pdo->prepare("
        UPDATE users
        SET $sets,
            level = ?, exp = ?,
            hp = ?, max_hp = ?,
            last_train_time = NOW()
        WHERE id = ?
    ")->execute([$newLevel, $newExp, $newHp, $newMaxHp, $user_id]);

    $statDesc = implode(', ', array_filter(
        array_map(fn($s) => $delta[$s] > 0 ? "$s+{$delta[$s]}" : '', $stats)
    ));
    $pdo->prepare('INSERT INTO training_logs (user_id, stat_gained, exp_gained) VALUES (?,?,?)')
        ->execute([$user_id, $statDesc, $expGain]);

    return ['ok' => true, 'delta' => $delta, 'expGain' => $expGain, 'leveledUp' => $leveledUp, 'newLevel' => $newLevel];
}

// ── 升級處理 ──────────────────────────────────────────────────
function apply_exp(int $user_id, int $expGain): bool {
    $pdo  = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $c = $stmt->fetch();

    $exp     = $c['exp'] + $expGain;
    $level   = $c['level'];
    $expMax  = $c['level'] * 100;
    $bonus   = [];
    $leveled = false;

    while ($exp >= $expMax) {
        $exp -= $expMax;
        $level++;
        $expMax = $level * 100;
        foreach (['str', 'agi', 'con', 'intel', 'per', 'cha'] as $s) $bonus[$s] = ($bonus[$s] ?? 0) + 1;
        $leveled = true;
    }

    $maxHp = 80 + ($c['con'] + ($bonus['con'] ?? 0)) * 5;
    $hp    = $leveled ? $maxHp : $c['hp'];

    if ($leveled) {
        $sets = implode(', ', array_map(fn($s) => "$s = $s + {$bonus[$s]}", array_keys($bonus)));
        $pdo->prepare("UPDATE users SET $sets, level=?, exp=?, hp=?, max_hp=? WHERE id=?")
            ->execute([$level, $exp, $hp, $maxHp, $user_id]);
    } else {
        $pdo->prepare('UPDATE users SET exp=? WHERE id=?')->execute([$exp, $user_id]);
    }
    return $leveled;
}

// ── 格式化時間 ────────────────────────────────────────────────
function fmt_time(int $secs): string {
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    $s = $secs % 60;
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m {$s}s";
}
