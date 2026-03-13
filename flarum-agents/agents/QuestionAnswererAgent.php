<?php

namespace FlarumAgents\Agents;

use FlarumAgents\Core\BaseAgent;
use FlarumAgents\Core\FlarumClient;

class QuestionAnswererAgent extends BaseAgent
{
    private FlarumClient $flarum;
    private string $triggerKeyword;

    public function __construct()
    {
        parent::__construct();
        
        $configFile = __DIR__ . '/../config/agents.php';
        $allConfig = require $configFile;
        $flarumConfig = $allConfig['global']['flarum'] ?? [];
        
        $this->flarum = new FlarumClient(
            $flarumConfig['base_url'] ?? 'http://localhost',
            $flarumConfig['api_key'] ?? ''
        );
        
        $this->triggerKeyword = $this->getConfigValue('trigger_keyword', '@AI问答助手');
    }

    public function getName(): string { return 'question_answerer'; }
    public function getDescription(): string { return '每30分钟检测被@的帖子并回答'; }

    public function execute(): array
    {
        $this->log('info', '开始执行问题回答任务', ['trigger' => $this->triggerKeyword]);

        $discussions = $this->flarum->getRecentDiscussions(30);
        $this->log('info', '获取到 ' . count($discussions) . ' 个讨论');

        $answered = 0;
        $skipped = 0;
        $notTagged = 0;
        $maxAnswers = $this->getConfigValue('max_answers_per_run', 5);

        foreach ($discussions as $discussion) {
            if ($answered >= $maxAnswers) {
                $this->log('info', '已达到最大回答数限制', ['max' => $maxAnswers]);
                break;
            }

            $discussionId = $discussion['id'];
            $title = $discussion['attributes']['title'] ?? '';
            
            if ($this->hasBeenAnswered($discussionId)) {
                continue;
            }

            // 获取帖子内容（从posts中获取第一个帖子）
            try {
                $posts = $this->flarum->getDiscussionPosts($discussionId);
                
                // 找到第一个帖子（number=1）
                $firstPost = null;
                foreach ($posts as $post) {
                    if (($post['attributes']['number'] ?? 0) === 1) {
                        $firstPost = $post;
                        break;
                    }
                }
                
                if (!$firstPost) {
                    $this->log('warning', '找不到首帖', ['id' => $discussionId]);
                    continue;
                }
                
                $content = $firstPost['attributes']['content'] ?? '';
                
                // 检查是否包含触发关键词
                if (!$this->isTaggedForAnswer($title, $content)) {
                    $notTagged++;
                    continue;
                }
                
                $this->log('info', "检测到@请求: $title", ['id' => $discussionId]);
            } catch (\Exception $e) {
                $this->log('warning', '获取帖子内容失败', ['id' => $discussionId, 'error' => $e->getMessage()]);
                continue;
            }

            // 检查是否已有其他用户回复
            $otherReplies = 0;
            foreach ($posts as $post) {
                if (($post['attributes']['number'] ?? 0) > 1) {
                    $otherReplies++;
                }
            }
            if ($otherReplies > 0) {
                $this->log('info', '帖子已有回复，跳过', ['id' => $discussionId]);
                $this->markAsAnswered($discussionId);
                continue;
            }

            // 生成并提交回答
            try {
                $answer = $this->generateAnswer($title, $content);
                
                $userId = $this->getConfigValue('answerer_user_id');
                $this->flarum->replyToDiscussion($discussionId, $answer, $userId);
                
                $this->log('info', '回答已提交', ['discussion_id' => $discussionId]);
                $this->markAsAnswered($discussionId);
                $answered++;
            } catch (\Exception $e) {
                $this->log('error', '回答问题失败', ['error' => $e->getMessage()]);
                $skipped++;
            }
        }

        $this->log('info', '任务完成', [
            'answered' => $answered, 
            'skipped' => $skipped,
            'not_tagged' => $notTagged,
            'total_checked' => count($discussions)
        ]);
        
        return [
            'success' => true, 
            'answered' => $answered, 
            'skipped' => $skipped,
            'not_tagged' => $notTagged
        ];
    }

    protected function isTaggedForAnswer(string $title, string $content): bool
    {
        $keyword = $this->triggerKeyword;
        
        if (strpos($title, $keyword) !== false) {
            return true;
        }
        
        if (strpos($content, $keyword) !== false) {
            return true;
        }
        
        return false;
    }

    protected function hasBeenAnswered(int $discussionId): bool
    {
        $file = __DIR__ . '/../config/answered_questions.json';
        if (!file_exists($file)) return false;
        $data = json_decode(file_get_contents($file), true) ?: [];
        return isset($data[$discussionId]);
    }

    protected function markAsAnswered(int $discussionId): void
    {
        $file = __DIR__ . '/../config/answered_questions.json';
        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: [];
        }
        $data[$discussionId] = ['time' => time(), 'answered' => true];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function generateAnswer(string $title, string $content): string
    {
        $systemPrompt = <<<PROMPT
你是一位资深的生物信息学专家，被用户在论坛中@请求回答问题。

回答要求：
1. 以友善、专业的方式回应用户的@请求
2. 直接针对问题提供实用、可操作的解决方案
3. 如果涉及软件/工具，提供具体的版本、参数和命令
4. 如果涉及代码，提供完整的代码示例（Python/R/Bash）
5. 引用相关的数据库、文献或最佳实践
6. 解释"为什么"，帮助提问者理解背后的原理
7. 保持友善、专业的语气
8. 使用Markdown格式，包含代码块、列表等
9. 结尾可以邀请用户继续提问或深入讨论

回复格式建议：
- 开头："感谢@！关于您的问题..."
- 中间：详细解答
- 结尾：鼓励进一步交流
PROMPT;

        $prompt = <<<PROMPT
用户在论坛中@你询问：

【帖子标题】
$title

【帖子内容】
$content

请提供专业、详细的回答。要求：
1. 开头回应@，表示感谢
2. 直接回答核心问题
3. 提供具体的技术细节（软件名称、版本、关键参数）
4. 如有必要，提供代码示例
5. 解释相关概念，帮助理解
6. 使用Markdown格式
PROMPT;

        $result = $this->callAI($prompt, $systemPrompt);
        return $result['choices'][0]['message']['content'] ?? '感谢您的@！这是一个很有趣的问题，我会尽快为您提供详细的解答。';
    }
}
