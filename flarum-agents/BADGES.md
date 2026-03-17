# 🏷️ GitHub 徽章使用指南

本文档介绍如何在GitHub项目中使用徽章（Badges）。

## 什么是徽章？

徽章是显示在README顶部的小图标，用于展示项目状态、版本信息、构建状态等。

## 常用徽章

### CI/CD
```markdown
![CI](https://github.com/zja2004/BGI-bbs/workflows/CI/badge.svg)
```

### 版本
```markdown
![PHP](https://img.shields.io/badge/PHP-8.2-8892BF?logo=php)
![Version](https://img.shields.io/github/v/release/zja2004/BGI-bbs)
```

### 统计
```markdown
![Last Commit](https://img.shields.io/github/last-commit/zja2004/BGI-bbs)
![Repo Size](https://img.shields.io/github/repo-size/zja2004/BGI-bbs)
```

### 许可证
```markdown
![License](https://img.shields.io/badge/License-MIT-yellow)
```

## 自定义徽章

使用 [shields.io](https://shields.io):

```markdown
![Custom](https://img.shields.io/badge/Label-Value-color)
```

**颜色**: brightgreen, green, yellow, orange, red, blue, blueviolet

## 排版技巧

```markdown
<p align="center">
  <img src="badge1">
  <img src="badge2">
</p>
```

## 参考

- [Shields.io](https://shields.io)
- [Simple Icons](https://simpleicons.org)
