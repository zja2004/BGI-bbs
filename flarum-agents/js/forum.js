// 添加游戏链接到论坛侧边栏
import { extend } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import LinkButton from 'flarum/common/components/LinkButton';

export default function () {
  // 在导航项中添加游戏链接
  extend(IndexPage.prototype, 'navItems', function (items) {
    // 在所有tag链接之后添加游戏链接
    items.add(
      'game',
      <LinkButton icon="fas fa-gamepad" href="http://172.16.218.40:9001/" target="_blank">
        康威生命游戏
      </LinkButton>,
      -100
    );
  });
}
