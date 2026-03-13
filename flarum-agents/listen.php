<?php
/**
 * 实时监听器启动脚本
 * 使用方法: php listen.php [interval_seconds]
 */

require 'vendor/autoload.php';

use FlarumAgents\Core\FlarumClient;
use FlarumAgents\Core\RealtimeListener;
use FlarumAgents\Agents\QuestionAnswererAgent;

$config = require 'config/agents.php';
$flarumConfig = $config['global']['flarum'] ?? [];

// 创建Flarum客户端
$flarum = new FlarumClient(
    $flarumConfig['base_url'] ?? 'http://localhost',
    $flarumConfig['api_key'] ?? ''
);

// 创建问答代理
$agent = new QuestionAnswererAgent();

// 获取检查间隔（默认5秒）
$interval = (int)($argv[1] ?? 5);
if ($interval < 1) $interval = 5;

// 创建并启动监听器
$listener = new RealtimeListener($flarum, $agent, __DIR__ . '/runtime/listener_state.json', $interval);
$listener->start();
