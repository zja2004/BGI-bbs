<?php

declare(strict_types=1);

namespace FlarumAgents\Core;

use FlarumAgents\Agents\QuestionAnswererAgent;

class RealtimeListener
{
    private FlarumClient $flarum;
    private QuestionAnswererAgent $agent;
    private string $stateFile;
    private int $checkInterval;
    private array $processedPosts = [];
    private array $processedDiscussions = [];
    
    public function __construct(
        FlarumClient $flarum,
        QuestionAnswererAgent $agent,
        string $stateFile = __DIR__ . '/../runtime/listener_state.json',
        int $checkInterval = 5
    ) {
        $this->flarum = $flarum;
        $this->agent = $agent;
        $this->stateFile = $stateFile;
        $this->checkInterval = $checkInterval;
        $this->loadState();
    }
    
    private function loadState(): void
    {
        if (file_exists($this->stateFile)) {
            $state = json_decode(file_get_contents($this->stateFile), true);
            $this->processedPosts = $state['processed_posts'] ?? [];
            $this->processedDiscussions = $state['processed_discussions'] ?? [];
            $this->log("加载状态: " . count($this->processedPosts) . " 个已处理帖子");
        }
    }
    
    private function saveState(): void
    {
        $state = [
            'processed_posts' => array_slice($this->processedPosts, -1000),
            'processed_discussions' => array_slice($this->processedDiscussions, -500),
            'last_update' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT));
    }
    
    public function start(): void
    {
        $this->log("========================================");
        $this->log("🚀 实时监听器启动");
        $this->log("检查间隔: {$this->checkInterval}秒");
        $this->log("按 Ctrl+C 停止");
        $this->log("========================================");
        
        while (true) {
            try {
                $this->checkNewPosts();
            } catch (\Exception $e) {
                $this->log("❌ 错误: " . $e->getMessage());
                $this->log($e->getTraceAsString());
            }
            
            sleep($this->checkInterval);
        }
    }
    
    private function checkNewPosts(): void
    {
        $posts = $this->flarum->getRecentPosts(60);
        
        $newPosts = 0;
        $triggeredPosts = 0;
        
        foreach ($posts as $post) {
            $postId = $post['id'];
            
            if (in_array($postId, $this->processedPosts)) {
                continue;
            }
            
            $this->processedPosts[] = $postId;
            $newPosts++;
            
            $authorId = $post['relationships']['user']['data']['id'] ?? null;
            if ($authorId == 7) {
                continue;
            }
            
            $content = $this->getPostContent($post);
            if (empty($content)) {
                continue;
            }
            
            if ($this->containsTrigger($content)) {
                $this->log("🔔 检测到@AI问答小助手 (帖子ID: {$postId})");
                $triggeredPosts++;
                
                $this->handleTrigger($post, $content);
            }
        }
        
        if ($newPosts > 0 || $triggeredPosts > 0) {
            $this->saveState();
            $this->log("📊 本轮检查: {$newPosts}个新帖子, {$triggeredPosts}个触发回答");
        }
    }
    
    private function getPostContent(array $post): string
    {
        $content = $post['attributes']['content'] ?? '';
        if (!empty($content)) {
            return $content;
        }
        
        $contentHtml = $post['attributes']['contentHtml'] ?? '';
        if (!empty($contentHtml)) {
            return strip_tags($contentHtml);
        }
        
        return '';
    }
    
    private function containsTrigger(string $content): bool
    {
        $triggerKeyword = '@AI问答助手';
        return strpos($content, $triggerKeyword) !== false;
    }
    
    private function handleTrigger(array $post, string $content): void
    {
        $startTime = microtime(true);
        
        try {
            // 修正：将字符串ID转为整数
            $discussionId = (int)($post['relationships']['discussion']['data']['id'] ?? 0);
            if ($discussionId === 0) {
                $this->log("⚠️ 无法获取讨论ID");
                return;
            }
            
            $cacheKey = $discussionId . '_' . date('YmdH');
            if (in_array($cacheKey, $this->processedDiscussions)) {
                $this->log("⏭️ 该讨论最近已回答过，跳过");
                return;
            }
            
            $discussion = $this->flarum->getDiscussion($discussionId);
            $title = $discussion['attributes']['title'] ?? '无标题';
            
            $this->log("💬 正在生成回答...");
            $this->log("   标题: {$title}");
            
            $answer = $this->agent->generateAnswer($title, $content);
            
            if (empty($answer)) {
                $this->log("⚠️ 生成的回答为空");
                return;
            }
            
            $this->flarum->replyToDiscussion($discussionId, $answer, 7);
            
            $this->processedDiscussions[] = $cacheKey;
            
            $elapsed = round(microtime(true) - $startTime, 2);
            $this->log("✅ 回答完成！耗时: {$elapsed}秒");
            
        } catch (\Exception $e) {
            $this->log("❌ 处理失败: " . $e->getMessage());
        }
    }
    
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] {$message}\n";
        echo $line;
        
        $logFile = __DIR__ . '/../runtime/listener.log';
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}
