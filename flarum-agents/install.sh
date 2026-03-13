#!/bin/bash

# Flarum AI Agents 安装脚本

set -e

echo "========================================"
echo "Flarum AI Agents - 安装脚本"
echo "========================================"
echo ""

# 检查PHP
echo "检查PHP..."
if ! command -v php &> /dev/null; then
    echo "❌ PHP未安装"
    exit 1
fi

PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
echo "✅ PHP $PHP_VERSION"

# 检查Composer
echo "检查Composer..."
if ! command -v composer &> /dev/null; then
    echo "❌ Composer未安装，请先安装Composer"
    echo "   安装指南: https://getcomposer.org/download/"
    exit 1
fi
echo "✅ Composer已安装"

# 安装依赖
echo ""
echo "安装依赖..."
composer install --no-dev --optimize-autoloader

# 创建目录
echo ""
echo "创建必要目录..."
mkdir -p logs drafts config
touch logs/.gitkeep drafts/.gitkeep

# 设置权限
echo ""
echo "设置目录权限..."
chmod 755 logs drafts config
chmod 644 config/agents.php

# 运行测试
echo ""
echo "运行测试..."
php test.php

echo ""
echo "========================================"
echo "安装完成！"
echo "========================================"
echo ""
echo "请编辑 config/agents.php 配置你的API Key"
echo ""
echo "常用命令:"
echo "  php agent.php --list      查看所有Agent"
echo "  php agent.php --status    查看状态"
echo "  php agent.php --force     测试运行所有Agent"
echo "  php agent.php --daemon    守护进程模式"
echo ""
echo "配置定时任务:"
echo "  crontab -e"
echo "  */5 * * * * cd $(pwd) && php agent.php >> logs/cron.log 2>&1"
