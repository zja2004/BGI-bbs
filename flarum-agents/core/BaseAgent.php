<?php

namespace FlarumAgents\Core;

abstract class BaseAgent implements AgentInterface
{
    protected array $config = [];
    protected array $logs = [];
    protected string $logFile = '';

    public function __construct()
    {
        $this->loadConfig();
        $this->logFile = __DIR__ . '/../logs/' . $this->getName() . '.log';
    }

    protected function loadConfig(): void
    {
        $configFile = __DIR__ . '/../config/agents.php';
        if (file_exists($configFile)) {
            $allConfig = require $configFile;
            $this->config = $allConfig[$this->getName()] ?? [];
        }
    }

    protected function getConfigValue(string $key, $default = null)
    {
        // 首先检查Agent专属配置
        if (isset($this->config[$key]) && $this->config[$key] !== '' && $this->config[$key] !== null) {
            return $this->config[$key];
        }
        
        // 然后检查全局AI配置
        $configFile = __DIR__ . '/../config/agents.php';
        if (file_exists($configFile)) {
            $allConfig = require $configFile;
            if (isset($allConfig['global']['ai'][$key]) && $allConfig['global']['ai'][$key] !== '') {
                return $allConfig['global']['ai'][$key];
            }
            // 检查Flarum配置
            if (isset($allConfig['global']['flarum'][$key]) && $allConfig['global']['flarum'][$key] !== '') {
                return $allConfig['global']['flarum'][$key];
            }
        }
        
        return $default;
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp] [$level] [{$this->getName()}] $message";
        if (!empty($context)) {
            $logLine .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $logLine .= PHP_EOL;
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    public function getInterval(): int { return $this->getConfigValue('interval', 30); }
    public function isEnabled(): bool { return $this->getConfigValue('enabled', true); }
    public function getConfig(): array { return $this->config; }
    public function setConfig(array $config): void { $this->config = array_merge($this->config, $config); }

    protected function callAI(string $prompt, string $systemPrompt = '', array $tools = []): array
    {
        $apiKey = $this->getConfigValue('api_key');
        $model = $this->getConfigValue('model', 'Qwen3-235B-A22B');
        $baseUrl = $this->getConfigValue('base_url', 'http://172.16.224.137:1024/v1');

        if (empty($apiKey)) {
            throw new \Exception('API Key 未配置');
        }

        $messages = [];
        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $this->getConfigValue('temperature', 0.3),
            'max_tokens' => $this->getConfigValue('max_tokens', 4000),
        ];

        $ch = curl_init($baseUrl . '/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new \Exception('CURL Error: ' . curl_error($ch));
        }
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("API 请求失败: HTTP $httpCode, Response: $response");
        }

        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            throw new \Exception('API Error: ' . $data['error']['message']);
        }

        // 处理Qwen3模型的<think>标签
        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
            $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
            $content = trim($content);
            $data['choices'][0]['message']['content'] = $content;
        }

        return $data;
    }

    protected function searchWeb(string $query): array
    {
        $searchPrompt = "请搜索并提供关于以下主题的最新信息:\n\n$query\n\n请注意:\n1. 只提供有可靠来源的信息\n2. 标注数据来源\n3. 如果不确定，明确说明";
        return $this->callAI($searchPrompt, '');
    }
}
