# 📚 Flarum AI Agents - 文档索引

> 完整的项目文档导航

---

## 📖 核心文档

### 1. [README.md](README.md) - 项目说明
**适用对象**: 所有用户  
**内容**: 
- 项目简介
- 系统架构
- 快速开始
- 目录结构

### 2. [QUICKSTART.md](QUICKSTART.md) - 快速启动指南 ⭐
**适用对象**: 运维人员  
**内容**:
- 首次部署步骤
- 日常运维命令
- 故障处理速查

### 3. [OPERATIONS.md](OPERATIONS.md) - 完整运维手册 ⭐⭐
**适用对象**: 运维人员、开发者  
**内容**:
- 详细的系统配置
- 数据库结构
- 服务管理
- 故障排除
- 备份恢复

---

## 🔧 技术文档

### 4. [MODEL_INFO.md](MODEL_INFO.md) - AI模型信息
**适用对象**: 技术负责人  
**内容**:
- Qwen3-235B-A22B模型详情
- 性能参数
- 与其他模型对比

### 5. [REALTIME_LISTENER.md](REALTIME_LISTENER.md) - 实时监听说明
**适用对象**: 开发者  
**内容**:
- 实时监听工作原理
- 性能指标
- 故障排除

---

## 📁 配置文件

| 文件 | 说明 | 重要性 |
|------|------|--------|
| `config/agents.php` | 主配置文件 | ⭐⭐⭐ |
| `config/agents.php.example` | 配置示例 | ⭐⭐ |
| `config/answered_questions.json` | 已回答问题记录 | ⭐⭐ |

---

## 🗂️ 关键目录

```
flarum-agents/
├── agents/              # Agent实现
│   ├── ArticlePublisherAgent.php
│   ├── QuestionAnswererAgent.php
│   └── ColumnWriterAgent.php
├── core/                # 核心组件
│   ├── BaseAgent.php
│   ├── FlarumClient.php
│   └── RealtimeListener.php
├── config/              # 配置文件
├── drafts/              # 专栏草稿
├── runtime/             # 运行时文件
│   ├── listener.log     # 日志 ⭐
│   └── listener_state.json
├── vendor/              # 依赖
├── listen.php           # 监听启动脚本
└── listener.sh          # 管理脚本 ⭐
```

---

## 🚀 常用命令速查

### 服务管理
```bash
# 查看状态
./listener.sh status

# 查看日志
./listener.sh logs
./listener.sh follow

# 重启服务
./listener.sh restart

# 清空状态
./listener.sh clear
```

### 手动触发
```bash
# 发布文章
php agent.php article_publisher --force

# 撰写专栏
php agent.php column_writer --force
```

### 系统服务
```bash
# systemctl管理
sudo systemctl {start|stop|restart|status} flarum-ai-listener

# 开机自启
sudo systemctl enable flarum-ai-listener
```

---

## 📊 系统监控

### 关键指标

| 指标 | 正常值 | 检查命令 |
|------|--------|----------|
| 服务状态 | active | `systemctl is-active flarum-ai-listener` |
| 响应延迟 | < 5秒 | `tail runtime/listener.log` |
| 磁盘空间 | < 80% | `df -h` |
| Qwen3 API | 200 OK | `curl http://172.16.224.137:1024/v1/models` |

---

## 🔑 关键配置项

### 必须配置
1. `config/agents.php` 中的 `flarum.base_url`
2. `config/agents.php` 中的 `flarum.api_key`
3. `config/agents.php` 中的 `ai.base_url`
4. `core/FlarumClient.php` 中的用户Token

### 可选配置
- 发布间隔 (`interval`)
- 触发关键词 (`trigger_keyword`)
- 最大回答数 (`max_answers_per_run`)

---

## ⚠️ 重要注意事项

1. **配置文件安全**
   - `config/agents.php` 包含敏感信息，不要提交到Git
   - 已添加到 `.gitignore`

2. **权限设置**
   - `config/agents.php`: 600 (仅所有者可读写)
   - `runtime/`: 755 (可执行以便写入日志)

3. **服务依赖**
   - Qwen3 API必须可访问
   - Flarum API必须可访问
   - MySQL数据库必须正常运行

---

## 🆘 故障速查

| 问题 | 检查 | 解决 |
|------|------|------|
| 问答不响应 | 服务状态 | `./listener.sh restart` |
| API错误 | API Key | 更新 `config/agents.php` |
| 无权限 | 文件权限 | `sudo chown -R ztron:ztron .` |
| 连接失败 | 网络 | 检查防火墙/网络 |

---

## 📞 支持

- **技术问题**: 查看 [OPERATIONS.md](OPERATIONS.md) 故障排除章节
- **配置问题**: 查看 [QUICKSTART.md](QUICKSTART.md) 配置说明
- **模型问题**: 查看 [MODEL_INFO.md](MODEL_INFO.md)

---

**最后更新**: 2026-03-11  
**文档版本**: 1.0
