#!/usr/bin/env php
<?php
/**
 * Flarum AI Agent 主入口脚本
 * 
 * 使用方法:
 *   php agent.php                    # 运行所有到期的Agent
 *   php agent.php [agent_name]       # 运行指定Agent
 *   php agent.php --list             # 列出所有Agent
 *   php agent.php --status           # 查看Agent状态
 *   php agent.php --force [agent]    # 强制运行（忽略时间间隔）
 *   php agent.php --daemon           # 守护模式
 */

require 'vendor/autoload.php';

use FlarumAgents\Core\AgentManager;
use FlarumAgents\Agents\PreprintRetrieverAgent;
use FlarumAgents\Agents\PaperInterpreterAgent;
use FlarumAgents\Agents\DailyPaperInterpreterAgent;
use FlarumAgents\Agents\QuestionAnswererAgent;
use FlarumAgents\Agents\ColumnWriterAgent;

// 创建管理器
$manager = new AgentManager();

// 注册所有Agent
$manager->registerAgent(new PreprintRetrieverAgent());
$manager->registerAgent(new PaperInterpreterAgent());
$manager->registerAgent(new DailyPaperInterpreterAgent());  // 每日arXiv论文解读
$manager->registerAgent(new QuestionAnswererAgent());
$manager->registerAgent(new ColumnWriterAgent());

// 解析命令行参数
$options = getopt('l,s,f,d', ['list', 'status', 'force:', 'daemon']);
$args = array_slice($argv, 1);

// 处理选项
if (isset($options['l']) || isset($options['list']) || in_array('--list', $args)) {
    echo "=== 可用Agent列表 ===\n\n";
    foreach ($manager->getAllAgents() as $name => $agent) {
        $status = $agent->isEnabled() ? '✅ 启用' : '❌ 禁用';
        $interval = $agent->getInterval();
        if ($interval === 0) {
            $intervalStr = '实时';
        } elseif ($interval >= 1440) {
            $intervalStr = floor($interval / 1440) . '天';
        } elseif ($interval >= 60) {
            $intervalStr = floor($interval / 60) . '小时';
        } else {
            $intervalStr = $interval . '分钟';
        }
        echo sprintf("  %-25s [%s] 间隔: %-8s %s\n", $name, $status, $intervalStr, $agent->getDescription());
    }
    echo "\n";
    exit(0);
}

if (isset($options['s']) || isset($options['status']) || in_array('--status', $args)) {
    echo "=== Agent状态 ===\n\n";
    $stateFile = __DIR__ . '/config/agent_state.json';
    $state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : [];
    
    foreach ($manager->getAllAgents() as $name => $agent) {
        $lastRun = $state[$name]['last_run'] ?? 0;
        $nextRun = $lastRun + ($agent->getInterval() * 60);
        $interval = $agent->getInterval();
        
        if ($interval === 0) {
            echo sprintf("  %-25s 实时运行中\n", $name);
        } else {
            $status = time() >= $nextRun ? '⏰ 可运行' : '⏳ 等待中';
            $lastRunStr = $lastRun ? date('Y-m-d H:i:s', $lastRun) : '从未';
            $nextRunStr = date('Y-m-d H:i:s', $nextRun);
            echo sprintf("  %-25s [%s] 上次: %s 下次: %s\n", $name, $status, $lastRunStr, $nextRunStr);
        }
    }
    echo "\n";
    exit(0);
}

// 强制运行模式
$forceMode = isset($options['f']) || isset($options['force']) || in_array('--force', $args);
if ($forceMode) {
    // 获取要强制运行的Agent名称
    $forceAgent = $options['force'] ?? null;
    foreach ($args as $arg) {
        if (!str_starts_with($arg, '-')) {
            $forceAgent = $arg;
            break;
        }
    }
    
    if ($forceAgent) {
        echo "=== 强制运行Agent: $forceAgent ===\n\n";
        $manager->resetState($forceAgent);
        $result = $manager->executeAgent($forceAgent);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "=== 强制运行所有Agent ===\n\n";
        $manager->resetState();
        $results = $manager->executeAllDue();
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    exit(0);
}

// 守护模式
if (isset($options['d']) || isset($options['daemon']) || in_array('--daemon', $args)) {
    echo "=== 启动Agent守护模式 ===\n\n";
    
    // 创建PID文件
    $pidFile = __DIR__ . '/runtime/agent_daemon.pid';
    file_put_contents($pidFile, getpid());
    
    // 设置信号处理
    pcntl_signal(SIGTERM, function() use ($pidFile) {
        echo "\n收到终止信号，正在退出...\n";
        unlink($pidFile);
        exit(0);
    });
    
    echo "Agent守护模式已启动 (PID: " . getpid() . ")\n";
    echo "按 Ctrl+C 停止\n\n";
    
    while (true) {
        pcntl_signal_dispatch();
        
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] 检查Agent...\n";
        
        $results = $manager->executeAllDue();
        
        foreach ($results as $name => $result) {
            if ($result['success']) {
                echo "  ✅ $name 执行成功\n";
            } elseif (isset($result['message'])) {
                echo "  ⏳ $name: {$result['message']}\n";
            } else {
                echo "  ❌ $name: {$result['error']}\n";
            }
        }
        
        echo "\n";
        sleep(60); // 每分钟检查一次
    }
}

// 运行指定Agent或所有到期Agent
$targetAgent = null;
foreach ($args as $arg) {
    if (!str_starts_with($arg, '-')) {
        $targetAgent = $arg;
        break;
    }
}

if ($targetAgent) {
    echo "=== 运行Agent: $targetAgent ===\n\n";
    $result = $manager->executeAgent($targetAgent);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "=== 运行所有到期Agent ===\n\n";
    $results = $manager->executeAllDue();
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
