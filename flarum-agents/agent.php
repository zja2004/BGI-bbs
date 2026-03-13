#!/usr/bin/env php
<?php

/**
 * Flarum AI Agents - 主入口脚本
 * 
 * 用法：
 *   php agent.php                      # 运行所有到期的Agent
 *   php agent.php --agent=publisher    # 运行指定Agent
 *   php agent.php --list               # 列出所有Agent
 *   php agent.php --status             # 查看Agent状态
 *   php agent.php --reset=publisher    # 重置Agent状态
 *   php agent.php --force              # 强制运行（忽略时间间隔）
 *   php agent.php --daemon             # 守护进程模式（持续运行）
 */

require_once __DIR__ . '/vendor/autoload.php';

use FlarumAgents\Core\AgentManager;
use FlarumAgents\Agents\ArticlePublisherAgent;
use FlarumAgents\Agents\QuestionAnswererAgent;
use FlarumAgents\Agents\ColumnWriterAgent;

// 解析命令行参数
$options = getopt('', [
    'agent:',
    'list',
    'status',
    'reset:',
    'force',
    'daemon',
    'help'
]);

// 显示帮助
if (isset($options['help']) || $argc === 1 && empty($options)) {
    showHelp();
    exit(0);
}

// 创建管理器
$manager = new AgentManager();

// 注册所有Agent
$manager->registerAgent(new ArticlePublisherAgent());
$manager->registerAgent(new QuestionAnswererAgent());
$manager->registerAgent(new ColumnWriterAgent());

// 处理命令

// --list: 列出所有Agent
if (isset($options['list'])) {
    echo "=== Flarum AI Agents ===\n\n";
    foreach ($manager->getAllAgents() as $name => $agent) {
        $status = $agent->isEnabled() ? '✅ 启用' : '❌ 禁用';
        $interval = $agent->getInterval();
        echo "[$status] $name\n";
        echo "  描述: {$agent->getDescription()}\n";
        echo "  间隔: {$interval}分钟\n\n";
    }
    exit(0);
}

// --status: 查看状态
if (isset($options['status'])) {
    showStatus($manager);
    exit(0);
}

// --reset: 重置Agent状态
if (isset($options['reset'])) {
    $agentName = $options['reset'];
    $manager->resetState($agentName === 'all' ? null : $agentName);
    echo "✅ Agent '$agentName' 状态已重置\n";
    exit(0);
}

// 守护进程模式
if (isset($options['daemon'])) {
    runDaemon($manager);
    exit(0);
}

// 运行Agent
$force = isset($options['force']);

if (isset($options['agent'])) {
    // 运行指定Agent
    $agentName = $options['agent'];
    runAgent($manager, $agentName, $force);
} else {
    // 运行所有到期的Agent
    runAllDue($manager, $force);
}

// ========================================
// 函数定义
// ========================================

function showHelp(): void {
    echo <<<HELP
Flarum AI Agents - 智能论坛助手

用法: php agent.php [选项]

选项:
  --agent=<name>     运行指定Agent (article_publisher|question_answerer|column_writer)
  --list             列出所有Agent
  --status           查看Agent运行状态
  --reset=<name>     重置指定Agent状态 (或 'all' 重置所有)
  --force            强制运行，忽略时间间隔
  --daemon           守护进程模式（持续运行）
  --help             显示此帮助

示例:
  php agent.php --list                           # 列出所有Agent
  php agent.php --agent=article_publisher        # 运行文章发布Agent
  php agent.php --force                          # 强制运行所有Agent
  php agent.php --daemon                         # 后台持续运行

定时任务配置（crontab -e）:
  # 每5分钟检查一次是否有Agent需要运行
  */5 * * * * cd /path/to/flarum-agents && php agent.php >> logs/cron.log 2>&1

HELP;
}

function showStatus(AgentManager $manager): void {
    echo "=== Agent 运行状态 ===\n\n";
    
    $stateFile = __DIR__ . '/config/agent_state.json';
    $states = [];
    if (file_exists($stateFile)) {
        $states = json_decode(file_get_contents($stateFile), true) ?: [];
    }
    
    foreach ($manager->getAllAgents() as $name => $agent) {
        $lastRun = $states[$name]['last_run'] ?? 0;
        $interval = $agent->getInterval();
        $nextRun = $lastRun + ($interval * 60);
        $isDue = time() >= $nextRun;
        
        echo "Agent: $name\n";
        echo "  状态: " . ($agent->isEnabled() ? '✅ 启用' : '❌ 禁用') . "\n";
        echo "  间隔: {$interval}分钟\n";
        
        if ($lastRun > 0) {
            echo "  上次运行: " . date('Y-m-d H:i:s', $lastRun) . "\n";
            echo "  下次运行: " . date('Y-m-d H:i:s', $nextRun);
            echo $isDue ? " (⏰ 已到期)\n" : "\n";
        } else {
            echo "  上次运行: 从未\n";
            echo "  下次运行: 随时\n";
        }
        echo "\n";
    }
}

function runAgent(AgentManager $manager, string $name, bool $force): void {
    $agent = $manager->getAgent($name);
    if (!$agent) {
        echo "❌ 错误: Agent '$name' 不存在\n";
        echo "可用的Agent: article_publisher, question_answerer, column_writer\n";
        exit(1);
    }
    
    echo "🤖 运行 Agent: $name\n";
    echo str_repeat('-', 50) . "\n";
    
    if ($force) {
        // 强制重置状态后执行
        $manager->resetState($name);
    }
    
    try {
        $result = $manager->executeAgent($name);
        displayResult($result);
    } catch (\Exception $e) {
        echo "❌ 执行失败: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function runAllDue(AgentManager $manager, bool $force): void {
    echo "🤖 检查并运行到期的Agent...\n";
    echo str_repeat('-', 50) . "\n";
    
    if ($force) {
        echo "⚠️  强制模式: 忽略时间间隔\n\n";
        $manager->resetState();
    }
    
    $results = $manager->executeAllDue();
    
    $hasRun = false;
    foreach ($results as $name => $result) {
        if (isset($result['success'])) {
            $hasRun = true;
            echo "\n📌 Agent: $name\n";
            displayResult($result);
        }
    }
    
    if (!$hasRun) {
        echo "ℹ️  没有到期的Agent需要运行\n";
    }
}

function displayResult(array $result): void {
    if (!$result['success']) {
        echo "❌ 失败: " . ($result['error'] ?? $result['message'] ?? '未知错误') . "\n";
        return;
    }
    
    echo "✅ 成功\n";
    
    if (isset($result['result'])) {
        foreach ($result['result'] as $key => $value) {
            if (is_array($value)) {
                echo "  $key: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "  $key: $value\n";
            }
        }
    }
    
    foreach ($result as $key => $value) {
        if ($key !== 'success' && $key !== 'result') {
            if (is_array($value)) {
                echo "  $key: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "  $key: $value\n";
            }
        }
    }
}

function runDaemon(AgentManager $manager): void {
    echo "🚀 启动守护进程模式\n";
    echo "按 Ctrl+C 停止\n";
    echo str_repeat('=', 50) . "\n\n";
    
    $checkInterval = 60; // 每分钟检查一次
    
    while (true) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] 检查Agent...\n";
        
        try {
            $results = $manager->executeAllDue();
            
            foreach ($results as $name => $result) {
                if ($result['success'] && !isset($result['message'])) {
                    echo "  ✅ $name 执行成功\n";
                } elseif (isset($result['message'])) {
                    echo "  ℹ️  $name: {$result['message']}\n";
                }
            }
        } catch (\Exception $e) {
            echo "  ❌ 错误: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
        sleep($checkInterval);
    }
}
