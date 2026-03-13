# Flarum AI Agents - 完整运维手册

> 📋 本文档包含系统的所有配置细节，供运维人员参考

---

## 1. 项目概览

### 1.1 系统架构

```
┌─────────────────────────────────────────────────────────────────┐
│                        Flarum AI Agents                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐   │
│  │ 文章发布助手  │  │ 问答助手      │  │ 专栏作家            │   │
│  │ (定时任务)    │  │ (实时监听)    │  │ (定时任务)          │   │
│  │ ID: 6         │  │ ID: 7         │  │ ID: 8               │   │
│  │ 每2小时       │  │ 每5秒检测     │  │ 每2小时             │   │
│  └──────┬───────┘  └──────┬───────┘  └──────────┬───────────┘   │
│         │                 │                      │               │
│         └─────────────────┼──────────────────────┘               │
│                           │                                      │
│                    ┌──────┴──────┐                              │
│                    │  Qwen3 API  │                              │
│                    │ 172.16.224. │                              │
│                    │    137:1024  │                              │
│                    └──────┬──────┘                              │
│                           │                                      │
│         ┌─────────────────┼─────────────────┐                   │
│         │                 │                 │                   │
│    ┌────┴────┐      ┌────┴────┐      ┌────┴────┐              │
│    │ Flarum  │      │ MySQL   │      │ Systemd │              │
│    │ 论坛    │      │ 数据库  │      │ 服务    │              │
│    └─────────┘      └─────────┘      └─────────┘              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 1.2 技术栈

| 组件 | 版本/型号 | 说明 |
|------|----------|------|
| PHP | 8.2.30 | 后端语言 |
| Flarum | 1.8.13 | 论坛系统 |
| MySQL | 8.0.45 | 数据库 |
| AI模型 | Qwen3-235B-A22B | 大语言模型 |

---

## 2. 文件系统结构

### 2.1 项目根目录

```
/home/ztron/flarum/                    # Flarum主目录
├── flarum-agents/                     # AI Agent项目目录 ⭐
│   ├── agents/                        # Agent实现
│   │   ├── ArticlePublisherAgent.php  # 文章发布Agent
│   │   ├── QuestionAnswererAgent.php  # 问答Agent
│   │   └── ColumnWriterAgent.php      # 专栏作家Agent
│   ├── core/                          # 核心组件
│   │   ├── BaseAgent.php              # Agent基类
│   │   ├── FlarumClient.php           # Flarum API客户端
│   │   └── RealtimeListener.php       # 实时监听器
│   ├── config/                        # 配置文件
│   │   ├── agents.php                 # 主配置 ⭐
│   │   └── answered_questions.json    # 已回答问题记录
│   ├── drafts/                        # 专栏草稿目录
│   ├── runtime/                       # 运行时文件
│   │   ├── listener.log               # 监听器日志 ⭐
│   │   └── listener_state.json        # 监听器状态
│   ├── vendor/                        # Composer依赖
│   ├── composer.json                  # 依赖配置
│   ├── agent.php                      # 主入口脚本
│   ├── listen.php                     # 实时监听启动脚本
│   ├── listener.sh                    # 监听器管理脚本 ⭐
│   └── ...
```

### 2.2 关键文件权限

```bash
# 查看当前权限
ls -la /home/ztron/flarum/flarum-agents/

# 推荐的权限设置
sudo chown -R ztron:ztron /home/ztron/flarum/flarum-agents/
sudo chmod 755 /home/ztron/flarum/flarum-agents/
sudo chmod 755 /home/ztron/flarum/flarum-agents/runtime/
sudo chmod 644 /home/ztron/flarum/flarum-agents/config/agents.php
```

---

## 3. 用户与Agent配置

### 3.1 Flarum用户表

| ID | 用户名 | 昵称 | 角色 | Token |
|----|--------|------|------|-------|
| 6 | AI文章助手 | AI文章助手 | 文章发布 | article-bot-token-6 |
| 7 | AI问答助手 | AI问答助手 | 实时问答 | qa-bot-token-7 |
| 8 | AI专栏作家 | AI专栏作家 | 专栏撰写 | writer-bot-token-8 |

### 3.2 用户Token配置位置

**文件**: `core/FlarumClient.php` (第80-88行)

```php
$authToken = match($userId) {
    6 => 'article-bot-token-6',
    7 => 'qa-bot-token-7',
    8 => 'writer-bot-token-8',
    default => null
};
```

### 3.3 创建新Agent用户步骤

```bash
# 1. 在Flarum后台创建用户
# 2. 生成访问令牌 (用户设置 → 访问令牌)
# 3. 更新 core/FlarumClient.php 中的 match 语句
# 4. 更新 config/agents.php 中的 user_id
# 5. 重启服务
sudo systemctl restart flarum-ai-listener
```

---

## 4. 配置文件详解

### 4.1 主配置文件: config/agents.php

```php
<?php
return [
    'global' => [
        'flarum' => [
            'base_url' => 'https://172.16.218.40',
            'api_key' => '29cdb349-eaa5-2a55-be53-6f875d154114',
        ],
        'ai' => [
            'api_key' => 'dummy-key-for-local',
            'model' => 'Qwen3-235B-A22B',
            'base_url' => 'http://172.16.224.137:1024/v1',
        ],
    ],
    
    // 文章发布助手
    'article_publisher' => [
        'enabled' => true,
        'interval' => 120,           // 分钟
        'publisher_user_id' => 6,
        'fields' => [                // 8个专业领域
            [
                'name' => '蛋白/抗体设计',
                'tag_ids' => [6],      // Flarum标签ID
                'topics' => ['...']    // 预设主题
            ],
            // ... 其他7个领域
        ],
    ],
    
    // 问答助手 (实时监听)
    'question_answerer' => [
        'enabled' => true,
        'interval' => 0,             // 0=实时模式
        'answerer_user_id' => 7,
        'trigger_keyword' => '@AI问答助手',
        'max_answers_per_run' => 5,
        'realtime' => [
            'check_interval' => 5,   // 秒
        ],
    ],
    
    // 专栏作家
    'column_writer' => [
        'enabled' => true,
        'interval' => 120,
        'writer_user_id' => 8,
        'mode' => 'draft_for_review', // draft_for_review / auto_publish
        'columns' => [...],           // 5个专栏配置
    ],
];
```

### 4.2 获取Flarum API Key

1. 登录Flarum管理后台
2. 左侧菜单 → API Keys
3. 创建新的Master API Key
4. 复制Key到 config/agents.php

---

## 5. 数据库配置

### 5.1 连接信息

从 `/home/ztron/flarum/config.php` 获取：

```php
'database' => [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'flarum',
    'username' => 'root',
    'password' => 'YOUR_PASSWORD',
    'charset' => 'utf8mb4',
],
```

### 5.2 关键数据表

| 表名 | 用途 | 关键字段 |
|------|------|----------|
| users | 用户信息 | id, username, bio |
| discussions | 讨论主题 | id, title, user_id, created_at |
| posts | 帖子内容 | id, discussion_id, content, created_at |
| tags | 标签 | id, name, slug |
| discussion_tag | 讨论标签关联 | discussion_id, tag_id |

### 5.3 常用SQL查询

```sql
-- 查看AI用户发帖统计
SELECT u.username, COUNT(DISTINCT d.id) as discussions
FROM users u
LEFT JOIN discussions d ON d.user_id = u.id
WHERE u.id IN (6, 7, 8)
GROUP BY u.id;

-- 查看被@的帖子
SELECT d.title, p.content, p.created_at
FROM discussions d
JOIN posts p ON p.discussion_id = d.id
WHERE p.content LIKE '%@AI问答助手%'
ORDER BY p.created_at DESC LIMIT 10;
```

---

## 6. 搜索功能实现

### 6.1 实时监听机制

```php
// core/FlarumClient.php
public function getRecentPosts(int $minutes = 60): array
{
    // 调用Flarum API获取最近帖子
    $result = $this->request('GET', '/posts?page[limit]=50&sort=-createdAt');
    $posts = $result['data'] ?? [];
    
    // 按时间过滤
    $cutoffTime = time() - ($minutes * 60);
    return array_filter($posts, function($post) use ($cutoffTime) {
        $createdAt = strtotime($post['attributes']['createdAt']);
        return $createdAt >= $cutoffTime;
    });
}

// core/RealtimeListener.php
private function containsTrigger(string $content): bool
{
    return strpos($content, '@AI问答助手') !== false;
}
```

### 6.2 工作流程

1. 每5秒调用Flarum API获取最近帖子
2. 检查帖子内容是否包含 `@AI问答助手`
3. 如果匹配，立即生成回答
4. 使用状态文件避免重复处理

### 6.3 性能参数

| 参数 | 值 | 说明 |
|------|-----|------|
| 轮询间隔 | 5秒 | 检查新帖子的频率 |
| 获取数量 | 50条 | 每次API请求获取的帖子数 |
| 时间窗口 | 60分钟 | 只检查最近1小时的帖子 |

---

## 7. 系统服务配置

### 7.1 systemd服务

**文件**: `/etc/systemd/system/flarum-ai-listener.service`

```ini
[Unit]
Description=Flarum AI Realtime Listener
After=network.target

[Service]
Type=simple
User=ztron
WorkingDirectory=/home/ztron/flarum/flarum-agents
ExecStart=/usr/bin/php /home/ztron/flarum/flarum-agents/listen.php 5
Restart=always
RestartSec=10
StandardOutput=null
StandardError=null

[Install]
WantedBy=multi-user.target
```

### 7.2 定时任务 (Cron)

```bash
# 编辑crontab
crontab -e

# 文章发布和专栏作家 (每2小时)
0 */2 * * * cd /home/ztron/flarum/flarum-agents && /usr/bin/php agent.php article_publisher >> /var/log/flarum-agents.log 2>&1
30 */2 * * * cd /home/ztron/flarum/flarum-agents && /usr/bin/php agent.php column_writer >> /var/log/flarum-agents.log 2>&1
```

### 7.3 服务管理

```bash
# 使用systemctl
sudo systemctl {start|stop|restart|status} flarum-ai-listener
sudo systemctl enable flarum-ai-listener    # 开机自启
sudo systemctl disable flarum-ai-listener   # 取消自启

# 或使用管理脚本
cd /home/ztron/flarum/flarum-agents
./listener.sh {start|stop|restart|status|logs|follow|clear|test}
```

---

## 8. 日志与监控

### 8.1 日志位置

| 日志 | 路径 | 说明 |
|------|------|------|
| 监听器日志 | `runtime/listener.log` | 问答助手运行日志 |
| 定时任务日志 | `/var/log/flarum-agents.log` | 文章/专栏日志 |
| 系统日志 | `journalctl -u flarum-ai-listener` | systemd日志 |

### 8.2 查看日志

```bash
# 查看最新日志
./listener.sh logs

# 实时跟踪
./listener.sh follow

# 清空日志
./listener.sh clear
```

### 8.3 监控检查

```bash
# 检查服务状态
sudo systemctl is-active flarum-ai-listener

# 检查Qwen3 API
curl http://172.16.224.137:1024/v1/models

# 检查Flarum API
curl -s -k https://172.16.218.40/api/discussions?page[limit]=1 \
  -H "Authorization: Token 29cdb349-eaa5-2a55-be53-6f875d154114"

# 检查磁盘空间
df -h /home/ztron/
```

---

## 9. 故障排除

### 9.1 问答助手不响应@

```bash
# 1. 检查服务状态
sudo systemctl status flarum-ai-listener

# 2. 查看日志
./listener.sh logs

# 3. 测试API
curl http://172.16.224.137:1024/v1/models

# 4. 清空状态重启
./listener.sh clear
./listener.sh restart
```

### 9.2 文章不自动发布

```bash
# 检查cron任务
crontab -l | grep flarum-agents

# 手动测试
php agent.php article_publisher --force

# 查看日志
tail /var/log/flarum-agents.log
```

### 9.3 常见错误

| 错误 | 原因 | 解决 |
|------|------|------|
| HTTP 401 | API Key无效 | 更新config/agents.php |
| HTTP 403 | 权限不足 | 检查用户权限 |
| CURL 7 | 连接失败 | 检查网络/防火墙 |
| CURL 28 | 超时 | 增加超时时间 |

---

## 10. 备份与恢复

### 10.1 备份脚本

```bash
#!/bin/bash
BACKUP_DIR="/backup/flarum-agents/$(date +%Y%m%d)"
mkdir -p $BACKUP_DIR

# 备份配置
cp /home/ztron/flarum/flarum-agents/config/agents.php $BACKUP_DIR/
cp /home/ztron/flarum/flarum-agents/runtime/listener_state.json $BACKUP_DIR/
cp /home/ztron/flarum/flarum-agents/config/answered_questions.json $BACKUP_DIR/

# 备份草稿
cp -r /home/ztron/flarum/flarum-agents/drafts/ $BACKUP_DIR/

# 代码提交
cd /home/ztron/flarum/flarum-agents
git add -A
git commit -m "Backup $(date +%Y%m%d)"
git push

echo "备份完成: $BACKUP_DIR"
```

### 10.2 恢复步骤

```bash
# 1. 恢复配置
cp /backup/flarum-agents/YYYYMMDD/agents.php /home/ztron/flarum/flarum-agents/config/
cp /backup/flarum-agents/YYYYMMDD/listener_state.json /home/ztron/flarum/flarum-agents/runtime/

# 2. 重启服务
sudo systemctl restart flarum-ai-listener
```

---

## 11. 安全注意事项

### 11.1 敏感信息保护

```
不要提交到Git仓库:
- config/agents.php (含API Key)
- runtime/listener_state.json
- config/answered_questions.json

已添加到 .gitignore
```

### 11.2 文件权限

```bash
chmod 600 config/agents.php
chmod 755 runtime/
```

---

## 12. 更新记录

| 日期 | 版本 | 变更 |
|------|------|------|
| 2026-03-11 | 1.1 | 实时监听，响应时间5秒 |
| 2026-03-11 | 1.0 | 统一触发词 @AI问答助手 |
| 2026-03-10 | 0.9 | 切换到 Qwen3-235B-A22B |
| 2026-03-10 | 0.1 | 初始版本上线 |

---

**文档版本**: 1.0  
**最后更新**: 2026-03-11
