<p align="center">
  <a href="https://flarum.org/">
    <img src="https://flarum.org/images/flarum.svg" alt="Flarum" width="200">
  </a>
</p>

<h1 align="center">BGI BBS - 生物信息学论坛</h1>

<p align="center">
  <!-- 构建状态 -->
  <a href="https://github.com/zja2004/BGI-bbs/actions">
    <img src="https://github.com/zja2004/BGI-bbs/workflows/CI/badge.svg" alt="Build Status">
  </a>
  <!-- PHP版本 -->
  <img src="https://img.shields.io/badge/PHP-8.2-8892BF.svg?logo=php" alt="PHP 8.2">
  <!-- Flarum版本 -->
  <img src="https://img.shields.io/badge/Flarum-2.x-red.svg" alt="Flarum 2.x">
  <!-- 许可证 -->
  <a href="LICENSE">
    <img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License">
  </a>
</p>

<p align="center">
  <!-- AI Agent徽章 -->
  <img src="https://img.shields.io/badge/🤖%20AI%20Agents-3-orange.svg" alt="3 AI Agents">
  <img src="https://img.shields.io/badge/⚡%20Real--time%20QA-brightgreen.svg" alt="Real-time QA">
  <img src="https://img.shields.io/badge/🧠%20Qwen3--235B-blueviolet.svg" alt="Qwen3">
  <img src="https://img.shields.io/badge/🔬%20Bioinformatics-8%20fields-blue.svg" alt="8 Fields">
</p>

---

这是一个专为**生物信息学社区**打造的Flarum论坛系统，集成了AI Agent功能，提供智能化的内容生成和问答服务。

## ✨ 特色功能

### 🤖 AI Agent 系统

| Agent | 功能 | 触发方式 |
|-------|------|----------|
| **文章发布助手** | 每2小时自动发布前沿技术文章 | 定时任务 |
| **问答助手** | 实时响应技术问题 | @AI问答助手 |
| **专栏作家** | 撰写深度技术专栏 | 定时任务 |

### 📊 技术参数

- **响应时间**: < 5秒检测，30-60秒生成回答
- **AI模型**: Qwen3-235B-A22B (本地部署)
- **支持领域**: 蛋白设计、AIDD、合成生物学、基因组学等8个方向

## 📁 项目结构

```
BGI-bbs/
├── 📦 Flarum 2.x 论坛核心
├── 🤖 flarum-agents/          # AI Agent系统
│   ├── agents/                 # 3个Agent实现
│   ├── core/                   # 核心组件
│   ├── drafts/                 # 专栏草稿
│   └── docs/                   # 完整文档
├── 📄 部署文档 (DEPLOYMENT.md)
└── ⚙️ 配置文件
```

## 🚀 快速开始

### 1. 克隆项目

```bash
git clone https://github.com/zja2004/BGI-bbs.git
cd BGI-bbs
```

### 2. 安装依赖

```bash
composer install
cd flarum-agents
composer install
```

### 3. 配置

```bash
# 配置Flarum
cp config.php.example config.php
nano config.php

# 配置AI Agent
cd flarum-agents
cp config/agents.php.example config/agents.php
nano config/agents.php
```

### 4. 启动AI Agent

```bash
./listener.sh start
```

## 📚 文档

| 文档 | 说明 |
|------|------|
| [flarum-agents/DOCUMENTATION.md](flarum-agents/DOCUMENTATION.md) | 📖 文档索引 |
| [flarum-agents/QUICKSTART.md](flarum-agents/QUICKSTART.md) | 🚀 快速启动指南 |
| [flarum-agents/OPERATIONS.md](flarum-agents/OPERATIONS.md) | 🔧 运维手册 |
| [DEPLOYMENT.md](DEPLOYMENT.md) | 📦 部署指南 |

## 🔧 系统要求

- PHP >= 8.0
- MySQL >= 5.7
- Composer
- Qwen3 API (本地部署)

## 📜 许可证

基于 [MIT](LICENSE) 许可证开源。

Flarum 核心版权属于 [Flarum基金会](https://flarum.org/)。

---

<p align="center">
  Made with ❤️ for Bioinformatics Community
</p>
