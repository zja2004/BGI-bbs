# 🤖 AI问答助手实时监听器

## 概述

AI问答助手现在使用**实时监听模式**，当用户在论坛中@AI问答小助手时，系统会在**5秒内**检测到并立即开始生成回答。

## 系统架构

```
用户发帖 @AI问答小助手
       ↓
实时监听器 (每5秒检查)
       ↓
检测到触发词
       ↓
立即调用Qwen3生成回答
       ↓
自动发布回复
```

## 管理命令

使用 `listener.sh` 脚本管理服务：

```bash
# 查看服务状态
./listener.sh status

# 启动服务
./listener.sh start

# 停止服务
./listener.sh stop

# 重启服务
./listener.sh restart

# 查看最近日志
./listener.sh logs

# 实时跟踪日志 (按 Ctrl+C 退出)
./listener.sh follow

# 清空日志和状态
./listener.sh clear

# 运行一次测试
./listener.sh test
```

## 服务信息

- **服务名称**: `flarum-ai-listener`
- **检查间隔**: 5秒
- **日志文件**: `/home/ztron/flarum/flarum-agents/runtime/listener.log`
- **状态文件**: `/home/ztron/flarum/flarum-agents/runtime/listener_state.json`
- **systemd服务**: `/etc/systemd/system/flarum-ai-listener.service`

## 直接systemctl管理

```bash
# 查看状态
sudo systemctl status flarum-ai-listener

# 启动/停止/重启
sudo systemctl start flarum-ai-listener
sudo systemctl stop flarum-ai-listener
sudo systemctl restart flarum-ai-listener

# 设置开机自启
sudo systemctl enable flarum-ai-listener

# 查看systemd日志
sudo journalctl -u flarum-ai-listener -f
```

## 工作原理

1. **持续监听**: 服务每5秒检查一次论坛的最新帖子
2. **触发检测**: 检测帖子内容是否包含 `@AI问答小助手`
3. **防重复**: 
   - 同一帖子不会重复处理
   - 同一讨论每小时最多回答一次
   - AI助手自己发的帖子会被跳过
4. **即时响应**: 检测到触发后，立即调用Qwen3生成回答
5. **状态保存**: 处理过的帖子ID保存在状态中，重启后不会重复处理

## 日志示例

```
[2026-03-11 10:18:12] ========================================
[2026-03-11 10:18:12] 🚀 实时监听器启动
[2026-03-11 10:18:12] 检查间隔: 5秒
[2026-03-11 10:18:12] 按 Ctrl+C 停止
[2026-03-11 10:18:12] ========================================
[2026-03-11 10:18:12] 🔔 检测到@AI问答小助手 (帖子ID: 85)
[2026-03-11 10:18:12] 💬 正在生成回答...
[2026-03-11 10:18:12]    标题: 📖 AI助手使用指南
[2026-03-11 10:19:05] ✅ 回答完成！耗时: 52.62秒
```

## 性能指标

- **检测延迟**: < 5秒 (从发帖到检测)
- **回答生成**: 10-60秒 (取决于问题复杂度)
- **内存占用**: ~10MB
- **CPU占用**: 几乎为零 (空闲时)

## 故障排除

### 服务无法启动
```bash
# 检查PHP路径
which php

# 检查文件权限
ls -la listen.php
ls -la core/RealtimeListener.php

# 手动运行查看错误
php listen.php 5
```

### 无法检测到@
```bash
# 清空状态重新检测
./listener.sh clear
./listener.sh restart

# 检查论坛API是否正常
php -r "require 'vendor/autoload.php'; \$c=require 'config/agents.php'; \$f=new FlarumAgents\Core\FlarumClient(\$c['global']['flarum']['base_url'], \$c['global']['flarum']['api_key']); print_r(\$f->getRecentPosts(10));"
```

### 回答生成失败
```bash
# 检查Qwen3 API是否正常
curl http://172.16.224.137:1024/v1/models

# 查看详细错误日志
./listener.sh follow
```

## 与传统定时模式的对比

| 特性 | 实时监听 | 定时任务 (旧) |
|------|----------|--------------|
| 响应时间 | 5秒内检测 | 最长30分钟 |
| 资源占用 | 持续运行 | 按需启动 |
| 用户体验 | 即时反馈 | 延迟反馈 |
| 适用场景 | 问答助手 | 文章发布、专栏 |

## 安全注意事项

1. **访问令牌**: 用户token存储在代码中，确保文件权限安全
2. **API限流**: 监控Qwen3 API调用频率，避免过载
3. **内容过滤**: AI生成的回答会经过内容检查
4. **日志清理**: 定期清理日志文件，避免磁盘占用过大

## 更新历史

- **2026-03-11**: 实现实时监听器，响应时间从30分钟缩短到5秒
