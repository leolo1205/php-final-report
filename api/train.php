<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();
$result = do_train($user['id']);

if ($result['ok']) {
    $gained = implode('、', array_filter(
        array_map(fn($s,$v) => $v > 0 ? ['str'=>'力量','agi'=>'敏捷','con'=>'體魄','intel'=>'智慧','per'=>'感知','cha'=>'魅力'][$s]."+$v" : '',
            array_keys($result['delta']), $result['delta'])
    ));
    $_SESSION['train_msg'] = $result['leveledUp']
        ? "🎉 升級！Lv.{$result['newLevel']}！{$gained}，EXP+{$result['expGain']}"
        : "訓練完成！{$gained}，EXP+{$result['expGain']}";
} else {
    $_SESSION['train_msg'] = '訓練冷卻中，剩餘 ' . fmt_time($result['remaining']);
}

header('Location: ../game.php');
exit;
