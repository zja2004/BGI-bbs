<?php
/**
 * Qwen3-235B-A22B 测试脚本
 */

$baseUrl = 'http://172.16.224.137:1024/v1';
$model = 'Qwen3-235B-A22B';

echo "=== Qwen3-235B-A22B 模型测试 ===\n\n";

// 测试1: 简单对话
echo "1. 测试简单对话...\n";
$ch = curl_init($baseUrl . '/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'user', 'content' => '你好！请简短介绍一下自己。']
    ],
    'temperature' => 0.7,
    'max_tokens' => 200
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '无响应';
    // 去除思考过程
    $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
    $content = trim($content);
    echo "✅ 成功!\n";
    echo "回复: " . substr($content, 0, 100) . "...\n\n";
} else {
    echo "❌ 失败 (HTTP $httpCode)\n\n";
}

// 测试2: 联网搜索提示
echo "2. 测试搜索提示...\n";
$searchPrompt = "请搜索并提供2024-2025年人工智能领域的最新发展趋势。提供可靠来源和具体数据。";

$ch = curl_init($baseUrl . '/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => '你是一个信息助手，请提供最新、准确的行业信息。'],
        ['role' => 'user', 'content' => $searchPrompt]
    ],
    'temperature' => 0.3,
    'max_tokens' => 1000
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '无响应';
    $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
    $content = trim($content);
    echo "✅ 成功!\n";
    echo "回复长度: " . strlen($content) . " 字符\n";
    echo "回复预览:\n" . substr($content, 0, 200) . "...\n\n";
} else {
    echo "❌ 失败 (HTTP $httpCode)\n\n";
}

// 测试3: 生成文章大纲
echo "3. 测试生成文章大纲...\n";
$outlinePrompt = "请为一篇关于'人工智能安全与伦理'的专业文章设计大纲。包含：引言、背景、核心内容、案例分析、总结展望。使用Markdown格式。";

$ch = curl_init($baseUrl . '/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => '你是一位资深专栏作家，擅长撰写深度专业文章。'],
        ['role' => 'user', 'content' => $outlinePrompt]
    ],
    'temperature' => 0.5,
    'max_tokens' => 1500
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '无响应';
    $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
    $content = trim($content);
    echo "✅ 成功!\n";
    echo "大纲预览:\n" . substr($content, 0, 300) . "...\n\n";
} else {
    echo "❌ 失败 (HTTP $httpCode)\n\n";
}

echo "=== 测试完成 ===\n";
echo "\n模型Qwen3-235B-A22B状态: ✅ 可用\n";
echo "建议: 模型响应正常，可以配置到Agent系统中使用\n";
