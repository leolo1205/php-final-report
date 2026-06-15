<?php
/**
 * 裝備函式：升級表、裝備取得、強化邏輯
 */

function get_upgrade_table() {
    return [
        0 => ['cost' => 200,   'chance' => 90],
        1 => ['cost' => 400,   'chance' => 80],
        2 => ['cost' => 700,   'chance' => 70],
        3 => ['cost' => 1200,  'chance' => 60],
        4 => ['cost' => 2000,  'chance' => 50],
        5 => ['cost' => 3000,  'chance' => 40],
        6 => ['cost' => 4500,  'chance' => 33],
        7 => ['cost' => 6500,  'chance' => 25],
        8 => ['cost' => 9000,  'chance' => 15],
        9 => ['cost' => 13000, 'chance' =>  8],
    ];
}

function equip_bonus_per_level($type) {
    return ['weapon' => 5, 'armor' => 2, 'helmet' => 20][$type] ?? 0;
}

function get_equipment($conn, $user_id) {
    $types  = ['weapon', 'armor', 'helmet'];
    $result = [];
    foreach ($types as $t) {
        $q   = $conn->query("SELECT * FROM user_equipment WHERE user_id=$user_id AND equip_type='$t'");
        $row = ($q !== false) ? $q->fetch_assoc() : null;
        if (!$row) {
            if ($q !== false) {
                $conn->query("INSERT INTO user_equipment (user_id,equip_type,level,attempts,successes) VALUES ($user_id,'$t',0,0,0)");
            }
            $row = ['user_id'=>$user_id,'equip_type'=>$t,'level'=>0,'attempts'=>0,'successes'=>0];
        }
        $result[$t] = $row;
    }
    return $result;
}

function get_equipment_bonus($conn, $user_id) {
    $eq = get_equipment($conn, $user_id);
    return [
        'atk' => (int)$eq['weapon']['level'] * 5,
        'def' => (int)$eq['armor']['level']  * 2,
        'hp'  => (int)$eq['helmet']['level'] * 20,
    ];
}

function upgrade_equipment($conn, $user_id, $type) {
    $types = ['weapon', 'armor', 'helmet'];
    if (!in_array($type, $types)) return ['success' => false, 'message' => '無效的裝備類型'];

    $table  = get_upgrade_table();
    $eq     = get_equipment($conn, $user_id);
    $level  = (int)$eq[$type]['level'];

    if ($level >= 10) return ['success' => false, 'message' => '已達最高等級 +10'];

    $cost   = $table[$level]['cost'];
    $chance = $table[$level]['chance'];

    $user = $conn->query("SELECT gold FROM users WHERE id=$user_id")->fetch_assoc();
    if ((int)$user['gold'] < $cost) {
        return ['success' => false, 'message' => "金幣不足（需要 {$cost} 金）"];
    }

    $conn->query("UPDATE users SET gold=gold-$cost WHERE id=$user_id");

    $rolled   = rand(1, 100);
    $leveled  = ($rolled <= $chance);
    $new_level = $leveled ? $level + 1 : $level;

    $conn->query("UPDATE user_equipment SET
        level=$new_level,
        attempts=attempts+1,
        successes=successes+".($leveled?1:0)."
        WHERE user_id=$user_id AND equip_type='$type'");

    $names = ['weapon'=>'武器','armor'=>'護甲','helmet'=>'頭盔'];
    return [
        'success'    => true,
        'leveled_up' => $leveled,
        'old_level'  => $level,
        'new_level'  => $new_level,
        'cost'       => $cost,
        'chance'     => $chance,
        'rolled'     => $rolled,
        'message'    => $leveled
            ? "✅ 強化成功！{$names[$type]} +{$level} → +{$new_level}"
            : "❌ 強化失敗！{$names[$type]} 維持 +{$level}（扣除 {$cost} 金）",
    ];
}
