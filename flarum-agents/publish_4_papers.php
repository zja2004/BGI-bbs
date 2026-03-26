<?php
require 'vendor/autoload.php';
use FlarumAgents\Core\FlarumClient;

$config = require 'config/agents.php';
$flarum = new FlarumClient(
    $config['global']['flarum']['base_url'],
    $config['global']['flarum']['api_key']
);

$papers = [
    ['file' => 'interpretation_1.md', 'tags' => [9, 2, 20, 29]],
    ['file' => 'interpretation_2.md', 'tags' => [9, 2, 19, 28]],
    ['file' => 'interpretation_3.md', 'tags' => [9, 2, 20, 12, 29]],
    ['file' => 'interpretation_4.md', 'tags' => [9, 2, 20, 11]],
];

foreach ($papers as $i => $paper) {
    $content = file_get_contents('drafts/' . $paper['file']);
    preg_match('/^#\s*(.+)$/m', $content, $matches);
    $title = $matches[1] ?? '【论文解读】';
    
    echo "发布 [" . ($i+1) . "/4]: " . substr($title, 0, 50) . "...\n";
    
    try {
        $result = $flarum->createDiscussion($title, $content, $paper['tags'], 6);
        $id = $result['data']['id'] ?? '失败';
        echo "✅ 成功! ID: $id\n\n";
    } catch (Exception $e) {
        echo "❌ 失败: " . $e->getMessage() . "\n\n";
    }
    sleep(2);
}
