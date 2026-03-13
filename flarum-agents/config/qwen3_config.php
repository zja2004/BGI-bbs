<?php
/**
 * Qwen3-235B-A22B 模型配置
 * 测试端点: http://172.16.224.137:1024/v1
 */

return [
    'global' => [
        'flarum' => [
            'base_url' => 'https://172.16.218.40',
            'api_key' => '29cdb349-eaa5-2a55-be53-6f875d154114',
            'auth_token' => null,
        ],
        'ai' => [
            // Qwen3-235B-A22B 配置
            'api_key' => 'dummy-key',  // 本地模型不需要真实key，但header中需要
            'model' => 'Qwen3-235B-A22B',
            'base_url' => 'http://172.16.224.137:1024/v1',
            'temperature' => 0.3,
            'max_tokens' => 4000,
        ],
    ],
    'article_publisher' => [
        'enabled' => true,
        'interval' => 30,
        'publisher_user_id' => 6,
        // 保留原有fields配置...
    ],
    'question_answerer' => [
        'enabled' => true,
        'interval' => 30,
        'answerer_user_id' => 7,
        'max_answers_per_run' => 1,
        'min_answer_length' => 100,
    ],
    'column_writer' => [
        'enabled' => true,
        'interval' => 60,
        'writer_user_id' => 8,
        'mode' => 'draft_for_review',
    ],
];
