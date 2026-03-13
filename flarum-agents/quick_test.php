<?php
/**
 * 快速测试AI功能
 */

require_once __DIR__ . '/vendor/autoload.php';

use FlarumAgents\Agents\ArticlePublisherAgent;

$config = require __DIR__ . '/config/agents.php';

echo "=== AI功能快速测试 ===\n\n";

$apiKey = $config['global']['ai']['api_key'];
$model = $config['global']['ai']['model'];
$baseUrl = $config['global']['ai']['base_url'];

echo "配置信息:\n";
echo "- Model: $model\n";
echo "- Base URL: $baseUrl\n";
echo "- API Key: " . substr($apiKey, 0, 15) . "...\n\n";

echo "测试1: 简单对话...\n";

$ch = curl_init($baseUrl . '/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => '你是一个乐于助人的助手。'],
        ['role' => 'user', 'content' => '你好！请用一句话介绍自己。']
    ],
    'temperature' => 0.3,
    'max_tokens' => 100
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '无响应';
    echo "✅ 成功!\n";
    echo "回复: $content\n\n";
} else {
    echo "❌ 失败 (HTTP $httpCode)\n";
    echo "响应: $response\n";
    exit(1);
}

echo "测试2: 联网搜索...\n";

$ch = curl_init($baseUrl . '/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => '你是一个信息助手，请提供最新、准确的行业信息，并标注数据来源。'],
        ['role' => 'user', 'content' => '请搜索并提供2024-2025年人工智能领域的最新发展趋势，包括大模型、多模态AI等重要进展。']
    ],
    'temperature' => 0.3,
    'max_tokens' => 1500
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '无响应';
    
    echo "✅ 成功!\n";
    echo "回复长度: " . strlen($content) . " 字符\n";
    echo "回复预览:\n" . substr($content, 0, 500) . "...\n\n";
} else {
    echo "❌ 失败 (HTTP $httpCode)\n";
    echo "响应: $response\n";
}

echo "=== 测试完成 ===\n";
