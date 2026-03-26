// 康威生命游戏链接扩展
(function() {
    'use strict';

    // 等待DOM和Flarum准备就绪
    function init() {
        if (typeof flarum === 'undefined' || !flarum.core || !flarum.core.compat) {
            setTimeout(init, 100);
            return;
        }

        const extend = flarum.core.compat['common/extend'];
        const IndexPage = flarum.core.compat['forum/components/IndexPage'];
        const LinkButton = flarum.core.compat['common/components/LinkButton'];

        if (!extend || !IndexPage || !LinkButton) {
            setTimeout(init, 100);
            return;
        }

        // 扩展侧边栏导航项
        extend.extend(IndexPage.prototype, 'navItems', function(items) {
            // 创建游戏链接
            const gameLink = LinkButton.component({
                icon: 'fas fa-gamepad',
                href: 'http://172.16.218.40:9001/',
                external: true,
                target: '_blank'
            }, '康威生命游戏');
            
            // 添加到导航项末尾（在所有tags之后）
            items.add('game', gameLink, -100);
        });
    }

    // 页面加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
