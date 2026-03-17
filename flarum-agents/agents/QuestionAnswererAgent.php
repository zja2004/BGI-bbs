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
        
        $this->triggerKeyword = $this->getConfigValue('trigger_keyword', '@AI助手');
    }

    public function getName(): string { return 'question_answerer'; }
    public function getDescription(): string { return '实时检测被@的帖子并回答'; }

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

            try {
                $posts = $this->flarum->getDiscussionPosts($discussionId);
                
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
                
                if (!$this->isTaggedForAnswer($title, $content)) {
                    $notTagged++;
                    continue;
                }
                
                $this->log('info', "检测到@请求: $title", ['id' => $discussionId]);
            } catch (\Exception $e) {
                $this->log('warning', '获取帖子内容失败', ['id' => $discussionId, 'error' => $e->getMessage()]);
                continue;
            }

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

            try {
                // 判断是否是总结请求
                if ($this->isSummaryRequest($title, $content)) {
                    $this->log('info', '识别为总结请求', ['id' => $discussionId]);
                    $answer = $this->generateSummary($title, $content);
                } else {
                    $answer = $this->generateAnswer($title, $content);
                }
                
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

    /**
     * 判断是否是总结请求
     */
    protected function isSummaryRequest(string $title, string $content): bool
    {
        $summaryKeywords = [
            '总结一下',
            '总结',
            '概括',
            '归纳',
            '提炼',
            '精简',
            '简要说明',
            '一句话',
            '核心要点',
            '要点',
            'tl;dr',
            'TL;DR',
            'tldr',
        ];
        
        $combinedText = $title . ' ' . $content;
        
        foreach ($summaryKeywords as $keyword) {
            if (strpos($combinedText, $keyword) !== false) {
                return true;
            }
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

    /**
     * 生成精炼总结（专门针对总结请求）
     */
    public function generateSummary(string $title, string $content): string
    {
        $systemPrompt = <<<PROMPT
你是一位内容提炼专家。用户要求你对提供的内容进行精炼总结。

【极其重要】总结要求：
1. 总字数控制在150字以内（严格限制）
2. 格式：3-5条 bullet points
3. 每条：1句话，不超过25字
4. 只提取核心要点，删除所有解释、例子、背景
5. 不要重复标题
6. 不要添加"综上所述"等套话
7. 直接输出总结，不要问候语

错误示例（太长）：
❌ "这篇文章主要介绍了AlphaFold 3的原理，它是如何工作的，以及在实际应用中有哪些优势和局限性..."

正确示例（精炼）：
✅ "- 核心技术：扩散模型预测原子坐标
- 优势：比AlphaFold 2精度提高30%
- 局限：计算资源需求高
- 应用：蛋白质-配体相互作用预测"

你的回复应该只有bullet points，没有其他内容。
PROMPT;

        $prompt = <<<PROMPT
请对以下内容进行精炼总结：

【标题】
$title

【内容】
$content

要求：
1. 提取核心要点，150字以内
2. 3-5条bullet points
3. 每条1句话，≤25字
4. 零废话、零解释
5. 直接输出，不要问候

格式示例：
- 要点1...
- 要点2...
- 要点3...
PROMPT;

        $result = $this->callAI($prompt, $systemPrompt);
        $summary = $result['choices'][0]['message']['content'] ?? '';
        
        // 清理可能的多余内容
        $summary = preg_replace('/^(总结|归纳|提炼)[:：]?\s*/i', '', $summary);
        $summary = trim($summary);
        
        // 如果超过150字，截断
        if (mb_strlen($summary) > 150) {
            $lines = explode("\n", $summary);
            $shortSummary = [];
            $currentLength = 0;
            
            foreach ($lines as $line) {
                $lineLength = mb_strlen($line);
                if ($currentLength + $lineLength > 140) {
                    break;
                }
                $shortSummary[] = $line;
                $currentLength += $lineLength;
            }
            
            $summary = implode("\n", $shortSummary);
        }
        
        return $summary;
    }

    /**
     * 生成普通回答
     */
    public function generateAnswer(string $title, string $content): string
    {
        $systemPrompt = <<<PROMPT
你是一位资深的生物信息学专家，被用户在论坛中@请求回答问题。

回答结构（必须严格遵循）：
1. 简要回应（1-2句话）
2. 核心解答（分点说明，每点简洁）
3. 【一句话总结】（控制在50字以内，直接给出结论）

回答要求：
1. 直接针对问题，不绕弯子
2. 如果涉及软件/工具，提供具体版本、参数
3. 如果涉及代码，提供最小可运行示例
4. 解释"为什么"控制在2-3句话
5. 使用Markdown格式
6. 【总结必须精炼】用一句话概括核心要点

【重要】总结部分要求：
- 只能是1句话
- 不超过50字
- 直接给出结论或建议
- 不要重复正文

回复格式示例：
```
关于您的问题...

## 解答
1. 要点1...
2. 要点2...

## 总结
一句话核心结论。
```
PROMPT;

        $prompt = <<<PROMPT
用户在论坛中@你询问：

【帖子标题】
$title

【帖子内容】
$content

请提供简洁专业的回答。要求：
1. 开头简要回应（1-2句）
2. 直接回答核心问题（分点，每点简洁）
3. 【一句话总结】（不超过50字，直接结论）
4. 提供必要的代码/命令示例（精简版）
5. 使用Markdown格式

特别注意：
- 总结只能是1句话，50字以内
- 不要长篇大论的总结段落
- 直接给出核心结论即可
PROMPT;

        $result = $this->callAI($prompt, $systemPrompt);
        return $result['choices'][0]['message']['content'] ?? '感谢您的@！这是一个很有趣的问题，我会尽快为您提供详细的解答。';
    }
}
