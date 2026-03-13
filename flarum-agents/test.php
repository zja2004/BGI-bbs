#!/usr/bin/env php
<?php

/**
 * 测试脚本 - 验证配置和基础功能
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "========================================\n";
echo "Flarum AI Agents - 测试脚本\n";
echo "========================================\n\n";

// 1. 检查PHP版本
echo "1. 检查PHP版本... ";
if (PHP_VERSION_ID >= 80000) {
    echo "✅ PHP " . PHP_VERSION . "\n";
} else {
    echo "❌ 需要PHP 8.0+\n";
    exit(1);
}

// 2. 检查扩展
echo "2. 检查必需扩展...\n";
$required = ['curl', 'json'];
foreach ($required as $ext) {
    echo "   - $ext: ";
    if (extension_loaded($ext)) {
        echo "✅\n";
    } else {
        echo "❌ 缺失\n";
        exit(1);
    }
}

// 3. 检查配置文件
echo "3. 检查配置文件... ";
$configFile = __DIR__ . '/config/agents.php';
if (!file_exists($configFile)) {
    echo "❌ 配置文件不存在\n";
    exit(1);
}
$config = require $configFile;
echo "✅\n";

// 4. 检查API Key配置
echo "4. 检查API Key配置...\n";
$apiKey = $config['global']['ai']['api_key'] ?? '';
if (empty($apiKey) || $apiKey === 'sk-xxxxxxxx' || strpos($apiKey, 'YOUR_') !== false) {
    echo "   ⚠️  警告: API Key 未配置或使用了占位符\n";
    echo "   请在 config/agents.php 中配置正确的 API Key\n";
} else {
    echo "   ✅ API Key 已配置 (" . substr($apiKey, 0, 10) . "...)\n";
}

// 5. 测试AI连接
echo "5. 测试AI API连接... ";
try {
    $baseUrl = $config['global']['ai']['base_url'] ?? 'https://api.moonshot.cn/v1';
    $model = $config['global']['ai']['model'] ?? 'kimi-latest';
    
    $ch = curl_init($baseUrl . '/models');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ 连接成功\n";
    } else {
        echo "❌ 连接失败 (HTTP $httpCode)\n";
        echo "   响应: " . substr($response, 0, 200) . "\n";
    }
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
}

// 6. 检查Flarum配置
echo "6. 检查Flarum配置...\n";
$flarumConfig = $config['global']['flarum'] ?? [];
if (empty($flarumConfig['base_url']) || $flarumConfig['base_url'] === 'http://localhost') {
    echo "   ⚠️  警告: Flarum base_url 使用默认配置\n";
} else {
    echo "   ✅ Base URL: " . $flarumConfig['base_url'] . "\n";
}

if (empty($flarumConfig['api_key']) || strpos($flarumConfig['api_key'], 'YOUR_') !== false) {
    echo "   ⚠️  警告: Flarum API Key 未配置\n";
} else {
    echo "   ✅ Flarum API Key 已配置\n";
}

// 7. 检查目录权限
echo "7. 检查目录权限...\n";
$dirs = ['logs', 'config', 'drafts'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    echo "   - $dir: ";
    if (!is_dir($path)) {
        if (@mkdir($path, 0755, true)) {
            echo "✅ 已创建\n";
        } else {
            echo "❌ 无法创建，请手动创建并设置权限\n";
        }
    } elseif (is_writable($path)) {
        echo "✅ 可写\n";
    } else {
        echo "⚠️  可能无写权限\n";
    }
}

// 8. 加载Agent类
echo "8. 加载Agent类...\n";
use FlarumAgents\Core\AgentManager;
use FlarumAgents\Agents\ArticlePublisherAgent;
use FlarumAgents\Agents\QuestionAnswererAgent;
use FlarumAgents\Agents\ColumnWriterAgent;

try {
    $manager = new AgentManager();
    $manager->registerAgent(new ArticlePublisherAgent());
    $manager->registerAgent(new QuestionAnswererAgent());
    $manager->registerAgent(new ColumnWriterAgent());
    
    foreach ($manager->getAllAgents() as $name => $agent) {
        echo "   ✅ $name: {$agent->getDescription()}\n";
    }
} catch (Exception $e) {
    echo "   ❌ 错误: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n========================================\n";
echo "测试完成！\n";
echo "========================================\n\n";

// 提供下一步建议
echo "下一步:\n";
echo "1. 确保 API Key 配置正确\n";
echo "2. 配置 Flarum API Key 和 base_url\n";
echo "3. 运行 php agent.php --list 查看Agent\n";
echo "4. 运行 php agent.php --force 测试运行\n";
echo "5. 配置定时任务或守护进程\n";
