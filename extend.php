<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Extend;
use Flarum\User\Event\Registered;
use Flarum\Locale\LocaleManager;

return [
    (new Extend\Event())
        ->listen(Registered::class, function (Registered $event): void {
            $user = $event->user;

            if (! $user->is_email_confirmed) {
                $user->activate();
                $user->save();
            }
        }),
    
    // 添加中文翻译支持
    (new Extend\Locales(__DIR__ . '/storage/locale')),
    
    // 设置默认语言为中文
    (new Extend\Settings())
        ->default('default_locale', 'zh'),

    // 添加游戏链接到侧边栏
    (new Extend\Frontend('forum'))
        ->content(function (\Flarum\Frontend\Document $document) {
            $document->head[] = '<script src="/js/game-link.js"></script>';
        }),

    // 欢迎消息常驻显示
    (new Extend\Frontend('forum'))
        ->content(function (\Flarum\Frontend\Document $document) {
            $document->head[] = '<script src="/js/welcome-sticky.js"></script>';
        }),
];
