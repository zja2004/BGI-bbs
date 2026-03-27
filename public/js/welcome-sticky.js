// 欢迎消息常驻显示
(function() {
    'use strict';

    // 清除之前存储的关闭状态
    localStorage.removeItem('welcomeHidden');

    // 等待 Flarum 加载完成
    function init() {
        if (typeof flarum === 'undefined' || !flarum.core || !flarum.core.compat) {
            setTimeout(init, 100);
            return;
        }

        const extend = flarum.core.compat['common/extend'];
        const WelcomeHero = flarum.core.compat['forum/components/WelcomeHero'];

        if (!extend || !WelcomeHero) {
            setTimeout(init, 100);
            return;
        }

        // 覆盖 isHidden 方法，始终返回 false（常驻显示）
        WelcomeHero.prototype.isHidden = function() {
            // 只检查是否有设置标题，忽略 localStorage
            if (!flarum.core.compat['forum/app'].default.forum.attribute('welcomeTitle')?.trim()) {
                return true;
            }
            // 忽略 this.hidden 和 localStorage，始终显示
            return false;
        };

        // 覆盖 hide 方法，不存储到 localStorage
        WelcomeHero.prototype.hide = function() {
            // 不执行任何操作，欢迎消息保持显示
            console.log('WelcomeHero: 关闭按钮被点击，但消息将保持显示');
        };

        console.log('WelcomeHero: 已设置为常驻显示模式');
    }

    // 页面加载时清除 localStorage
    localStorage.removeItem('welcomeHidden');

    // 启动
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
