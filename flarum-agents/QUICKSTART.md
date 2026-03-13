# 🚀 快速启动指南

## 首次部署

### 1. 环境检查

```bash
# PHP版本 >= 8.0
php -v

# 扩展检查
php -m | grep -E "curl|json"

# Composer
composer --version
```

### 2. 安装依赖

```bash
cd /home/ztron/flarum/flarum-agents
composer install --no-dev
```

### 3. 配置文件

```bash
# 复制示例配置
cp config/agents.php.example config/agents.php

# 编辑配置
nano config/agents.php
```

**必须修改的配置项**:
- `flarum.base_url` - 论坛URL
- `flarum.api_key` - Flarum Master API Key
- `ai.base_url` - Qwen3 API地址

### 4. 启动服务

```bash
# 启动实时监听器 (问答助手)
sudo systemctl start flarum-ai-listener

# 设置开机自启
sudo systemctl enable flarum-ai-listener

# 添加定时任务 (文章/专栏)
crontab -e
```

添加以下行:
```
0 */2 * * * cd /home/ztron/flarum/flarum-agents && /usr/bin/php agent.php article_publisher >> /var/log/flarum-agents.log 2>&1
30 */2 * * * cd /home/ztron/flarum/flarum-agents && /usr/bin/php agent.php column_writer >> /var/log/flarum-agents.log 2>&1
```

### 5. 验证

```bash
# 检查服务状态
./listener.sh status

# 查看日志
./listener.sh logs

# 测试发布
php agent.php article_publisher --force
```

---

## 日常运维

### 查看状态

```bash
# 服务状态
./listener.sh status

# 实时日志
./listener.sh follow

# 最后50行日志
./listener.sh logs
```

### 重启服务

```bash
# 重启监听器
./listener.sh restart

# 或
sudo systemctl restart flarum-ai-listener
```

### 手动触发

```bash
# 手动发布文章
php agent.php article_publisher --force

# 手动撰写专栏
php agent.php column_writer --force
```

---

## 故障处理

### 服务起不来

```bash
# 查看详细错误
./listener.sh test

# 检查权限
ls -la runtime/

# 修复权限
sudo chown -R ztron:ztron runtime/
```

### 问答不响应

```bash
# 1. 重启服务
./listener.sh restart

# 2. 清空状态重新检测
./listener.sh clear

# 3. 检查API
curl http://172.16.224.137:1024/v1/models
```

---

## 目录速查

| 文件/目录 | 用途 |
|-----------|------|
| `config/agents.php` | 主配置 |
| `runtime/listener.log` | 监听器日志 |
| `runtime/listener_state.json` | 监听器状态 |
| `drafts/` | 专栏草稿 |
| `core/FlarumClient.php` | API客户端 |
| `core/RealtimeListener.php` | 实时监听器 |
