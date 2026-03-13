# Flarum AI Agent 系统

🤖 自动为Flarum论坛发布文章、回答问题、撰写专栏的AI Agent系统

## 系统架构

```
┌─────────────────────────────────────────────────────────┐
│                    Flarum AI Agents                      │
├─────────────┬─────────────┬─────────────────────────────┤
│  文章发布助手 │  问答助手   │      专栏作家               │
│  (定时任务)  │ (实时监听)  │     (定时任务)              │
├─────────────┼─────────────┼─────────────────────────────┤
│ 每2小时     │  每5秒检测  │      每2小时                │
│ 发布前沿    │  @触发回答  │      撰写深度专栏            │
│ 技术文章    │  即时响应   │      (草稿待审)              │
└─────────────┴─────────────┴─────────────────────────────┘
                          │
                    ┌─────┴─────┐
                    ▼           ▼
            ┌──────────┐  ┌──────────┐
            │  Flarum  │  │ Qwen3    │
            │   论坛   │  │235B-A22B │
            └──────────┘  └──────────┘
```

## AI模型

### 当前模型: Qwen3-235B-A22B

- **参数量**: 2350亿 (MoE架构，激活22B)
- **上下文**: 128K tokens
- **部署**: 本地部署 (http://172.16.224.137:1024/v1)
- **优势**: 代码能力强、超长上下文、稳定无限制

详见 [MODEL_INFO.md](MODEL_INFO.md)

## 功能模块

### 1️⃣ 文章发布助手 (ID: 6)
- 每2小时发布一篇生物信息学前沿文章
- 覆盖8个专业领域：蛋白设计、AIDD、合成生物学等
- 自动匹配论坛标签

### 2️⃣ 问答助手 (ID: 7) ⭐实时
- **5秒内**检测到@触发
- 支持技术问答、代码调试、原理解释
- 使用方法: 在帖子中 `@AI问答助手`

### 3️⃣ 专栏作家 (ID: 8)
- 每2小时撰写深度技术专栏
- 保存为草稿，供管理员审核后发布
- 5个专业专栏主题

## 快速开始

### 安装依赖
```bash
cd flarum-agents
composer install
```

### 配置
```bash
cp config/agents.php.example config/agents.php
# 编辑 config/agents.php 配置API密钥和用户信息
```

### 运行
```bash
# 单次运行
php agent.php

# 守护模式（推荐）
php agent.php --daemon

# 查看状态
php agent.php --status
```

### 问答助手实时监听
```bash
# 查看状态
./listener.sh status

# 查看日志
./listener.sh logs

# 实时跟踪
./listener.sh follow
```

## 配置说明

```php
// config/agents.php
return [
    'global' => [
        'flarum' => [
            'base_url' => 'https://your-forum.com',
            'api_key' => 'your-api-key',
        ],
        'ai' => [
            'api_key' => 'dummy-key-for-local',
            'model' => 'Qwen3-235B-A22B',
            'base_url' => 'http://172.16.224.137:1024/v1',
        ],
    ],
    'article_publisher' => [
        'enabled' => true,
        'interval' => 120,  // 分钟
        'publisher_user_id' => 6,
        'fields' => [...],
    ],
    'question_answerer' => [
        'enabled' => true,
        'answerer_user_id' => 7,
        'trigger_keyword' => '@AI问答助手',
        'realtime' => [
            'check_interval' => 5,  // 秒
        ],
    ],
    'column_writer' => [
        'enabled' => true,
        'interval' => 120,
        'writer_user_id' => 8,
        'mode' => 'draft_for_review',
    ],
];
```

## 系统服务

```bash
# 启用开机自启
sudo systemctl enable flarum-ai-listener

# 管理服务
sudo systemctl {start|stop|restart} flarum-ai-listener

# 或使用管理脚本
./listener.sh {start|stop|restart|status|logs}
```

## 目录结构

```
flarum-agents/
├── agents/                 # Agent实现
│   ├── ArticlePublisherAgent.php
│   ├── QuestionAnswererAgent.php
│   └── ColumnWriterAgent.php
├── core/                   # 核心组件
│   ├── BaseAgent.php      # 基类
│   ├── FlarumClient.php   # Flarum API客户端
│   └── RealtimeListener.php # 实时监听器
├── config/                 # 配置文件
│   └── agents.php
├── drafts/                 # 专栏草稿
├── runtime/                # 运行时文件
├── vendor/                 # 依赖
├── listen.php             # 监听启动脚本
├── listener.sh            # 管理脚本
├── agent.php              # 主入口
├── MODEL_INFO.md          # 模型信息
└── README.md              # 本文档
```

## 依赖

- PHP >= 8.0
- ext-curl
- ext-json
- Composer

## 更新日志

- **2026-03-11**: 实现实时监听器，响应时间从30分钟缩短到5秒
- **2026-03-11**: 统一触发词为 @AI问答助手
- **2026-03-10**: 切换到 Qwen3-235B-A22B 本地部署
- **2026-03-10**: 初始版本，支持三Agent系统

## 许可证

MIT
