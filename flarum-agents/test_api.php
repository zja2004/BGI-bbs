<?php
/**
 * 测试Flarum API连接
 */

require_once __DIR__ . '/vendor/autoload.php';

use FlarumAgents\Core\FlarumClient;

$config = require __DIR__ . '/config/agents.php';

$flarumConfig = $config['global']['flarum'];

echo "=== Flarum API 测试 ===\n\n";
echo "论坛地址: {$flarumConfig['base_url']}\n";
echo "API Key: " . substr($flarumConfig['api_key'], 0, 15) . "...\n\n";

$client = new FlarumClient(
    $flarumConfig['base_url'],
    $flarumConfig['api_key']
);

try {
    // 测试获取标签
    echo "测试1: 获取标签列表...\n";
    $tags = $client->getTags();
    echo "✅ 成功! 获取到 " . count($tags) . " 个标签\n";
    foreach (array_slice($tags, 0, 5) as $tag) {
        echo "   - {$tag['attributes']['name']}\n";
    }
    echo "\n";
    
    // 测试获取用户
    echo "测试2: 获取用户列表...\n";
    $users = $client->getUsers(5);
    echo "✅ 成功! 获取到 " . count($users) . " 个用户\n";
    foreach ($users as $user) {
        echo "   - {$user['attributes']['username']} (ID: {$user['id']})\n";
    }
    echo "\n";
    
    // 测试获取讨论
    echo "测试3: 获取讨论列表...\n";
    $discussions = $client->getRecentDiscussions(3);
    echo "✅ 成功! 获取到 " . count($discussions) . " 个讨论\n";
    foreach ($discussions as $d) {
        echo "   - {$d['attributes']['title']}\n";
    }
    
    echo "\n=== 所有测试通过！API配置正确 ===\n";
    
} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    echo "\n可能原因:\n";
    echo "1. API Key不正确\n";
    echo "2. 论坛地址不正确\n";
    echo "3. 论坛无法访问\n";
    exit(1);
}
