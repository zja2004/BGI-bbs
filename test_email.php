<?php
// 使用正确的Flarum引导方式
$site = require '/home/ztron/flarum/site.php';
$app = $site->bootApp();

// 获取容器
$container = $app->getContainer();

// 获取EmailConfirmationMailer
$emailConfirmationMailer = $container->make('Flarum\User\EmailConfirmationMailer');

// 获取用户
try {
    $user = $container->make('Flarum\User\UserRepository')->findOrFail(2); // koliuhiks@gmail.com
} catch (Exception $e) {
    echo "Error finding user: " . $e->getMessage() . "\n";
    exit;
}

echo "Sending confirmation email to: " . $user->email . "\n";

// 尝试发送确认邮件
try {
    // 创建EmailChangeRequested事件
    $event = new \Flarum\User\Event\EmailChangeRequested($user, $user->email);

    // 发送确认邮件
    $emailConfirmationMailer->handle($event);

    echo "Email sent successfully\n";
} catch (Exception $e) {
    echo "Error sending email: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>