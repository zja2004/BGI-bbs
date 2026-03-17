# Flarum AI Agent 系统

<!-- 徽章区域 -->
<p align="center">
  <!-- CI构建状态 -->
  <a href="https://github.com/zja2004/BGI-bbs/actions">
    <img src="https://github.com/zja2004/BGI-bbs/workflows/CI/badge.svg" alt="Build Status">
  </a>
  <!-- PHP版本 -->
  <img src="https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF.svg?style=flat-square&logo=php" alt="PHP Version">
  <!-- 许可证 -->
  <a href="LICENSE">
    <img src="https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square" alt="License">
  </a>
  <!-- 最后提交 -->
  <img src="https://img.shields.io/github/last-commit/zja2004/BGI-bbs?style=flat-square" alt="Last Commit">
  <!-- 仓库大小 -->
  <img src="https://img.shields.io/github/repo-size/zja2004/BGI-bbs?style=flat-square" alt="Repo Size">
  <!-- 代码行数 -->
  <img src="https://img.shields.io/tokei/lines/github/zja2004/BGI-bbs?style=flat-square" alt="Lines of Code">
</p>

<p align="center">
  <!-- AI模型 -->
  <img src="https://img.shields.io/badge/AI-Qwen3--235B-blueviolet?style=flat-square&logo=openai" alt="AI Model">
  <!-- 响应时间 -->
  <img src="https://img.shields.io/badge/Response-5s-brightgreen?style=flat-square" alt="Response Time">
  <!-- 功能模块 -->
  <img src="https://img.shields.io/badge/Agents-3-orange?style=flat-square" alt="Agent Count">
</p>

---

🤖 自动为Flarum论坛发布文章、回答问题、撰写专栏的AI Agent系统

## 📑 目录

- [系统架构](#系统架构)
- [AI模型](#ai模型)
- [功能模块](#功能模块)
- [快速开始](#快速开始)
- [文档](#文档)

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

| 属性 | 详情 |
|------|------|
| **模型名称** | Qwen3-235B-A22B |
| **参数量** | 2350亿 (MoE架构) |
| **激活参数** | 220亿 |
| **上下文** | 128K tokens |
| **部署** | 本地部署 |
| **API地址** | http://172.16.224.137:1024/v1 |

**优势**: 代码能力强、超长上下文、稳定无限制

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

### 安装

```bash
git clone https://github.com/zja2004/BGI-bbs.git
cd BGI-bbs/flarum-agents
composer install
```

### 配置

```bash
cp config/agents.php.example config/agents.php
# 编辑 config/agents.php 填入配置
```

### 启动

```bash
# 启动实时监听器
./listener.sh start

# 查看状态
./listener.sh status
```

## 文档

| 文档 | 说明 |
|------|------|
| [DOCUMENTATION.md](DOCUMENTATION.md) | 📚 文档索引 |
| [QUICKSTART.md](QUICKSTART.md) | 🚀 快速启动指南 |
| [OPERATIONS.md](OPERATIONS.md) | 🔧 完整运维手册 |
| [MODEL_INFO.md](MODEL_INFO.md) | 🤖 AI模型信息 |
| [REALTIME_LISTENER.md](REALTIME_LISTENER.md) | ⚡ 实时监听说明 |

## 性能指标

| 指标 | 数值 |
|------|------|
| 检测延迟 | < 5秒 |
| AI生成时间 | 10-60秒 |
| 总响应时间 | 15-65秒 |
| 轮询间隔 | 5秒 |

## 许可证

[MIT](LICENSE)

---

<p align="center">
  Made with ❤️ for Bioinformatics Community
</p>
