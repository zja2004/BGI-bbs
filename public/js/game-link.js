// 康威生命游戏链接扩展
(function() {
    'use strict';

    function addGameLink() {
        // 找到侧边栏导航
        var nav = document.querySelector('.IndexPage-nav ul');
        if (!nav) {
            // 如果IndexPage-nav不存在，尝试其他选择器
            nav = document.querySelector('.App-nav ul');
        }
        if (!nav) {
            // 再试一个常见选择器
            nav = document.querySelector('nav.sideNav ul, .sideNav ul');
        }
        
        if (!nav) {
            console.log('Game Link: Navigation not found, retrying...');
            setTimeout(addGameLink, 500);
            return;
        }
        
        // 检查是否已经添加
        if (document.getElementById('game-link-item')) {
            return;
        }
        
        // 创建游戏链接li元素
        var li = document.createElement('li');
        li.id = 'game-link-item';
        li.innerHTML = '<a class="Button Button--link" href="http://172.16.218.40:9001/" target="_blank" style="display:flex;align-items:center;gap:8px;">' +
                       '<i class="fas fa-gamepad"></i> ' +
                       '<span>康威生命游戏</span>' +
                       '</a>';
        
        // 添加到导航末尾
        nav.appendChild(li);
        console.log('Game Link: Successfully added to sidebar');
    }

    // 页面加载完成后添加
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addGameLink);
    } else {
        addGameLink();
    }
    
    //  also try after a delay for SPA navigation
    setTimeout(addGameLink, 1000);
    setTimeout(addGameLink, 2000);
})();
