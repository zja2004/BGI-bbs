<?php

namespace FlarumAgents\Agents;

use FlarumAgents\Core\BaseAgent;
use FlarumAgents\Core\FlarumClient;

class ArticlePublisherAgent extends BaseAgent
{
    private FlarumClient $flarum;

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
    }

    public function getName(): string { return 'article_publisher'; }
    public function getDescription(): string { return '每2小时自动发布生物信息学前沿文章'; }

    public function execute(): array
    {
        $this->log('info', '开始执行文章发布任务');
        
        $field = $this->selectField();
        $this->log('info', '选择领域', ['field' => $field['name']]);
        
        $topic = $this->selectTopic($field);
        $this->log('info', '选择主题', ['topic' => $topic]);
        
        $article = $this->generateArticle($field, $topic);
        $this->log('info', '文章生成完成', ['title' => $article['title'], 'length' => strlen($article['content'])]);

        $tags = $field['tag_ids'] ?? [];
        $userId = $this->getConfigValue('publisher_user_id');
        
        $this->log('info', '准备发布', ['user_id' => $userId, 'tags' => $tags]);
        
        $result = $this->flarum->createDiscussion($article['title'], $article['content'], $tags, $userId);
        
        $discussionId = $result['data']['id'] ?? null;
        
        if ($discussionId) {
            $this->log('info', '发布成功', ['discussion_id' => $discussionId]);
            return ['success' => true, 'title' => $article['title'], 'field' => $field['name'], 'discussion_id' => $discussionId];
        } else {
            $this->log('error', '发布失败', ['result' => json_encode($result)]);
            throw new \Exception('发布失败');
        }
    }

    protected function selectField(): array
    {
        $fields = $this->getFields();
        $stateFile = __DIR__ . '/../config/article_field_index.txt';
        $currentIndex = file_exists($stateFile) ? (int)file_get_contents($stateFile) : 0;
        $field = $fields[$currentIndex % count($fields)];
        file_put_contents($stateFile, ($currentIndex + 1) % count($fields));
        return $field;
    }

    protected function getFields(): array
    {
        return $this->getConfigValue('fields', []);
    }

    protected function selectTopic(array $field): string
    {
        $topics = $field['topics'] ?? [];
        return $topics[array_rand($topics)];
    }

    protected function generateArticle(array $field, string $topic): array
    {
        $systemPrompt = <<<PROMPT
你是一位资深的{$field['name']}领域专家，专注于生物信息学与人工智能的交叉研究。

写作要求：
1. 内容必须专业、准确，符合生物信息学/计算生物学领域的学术标准
2. 结合具体的工具、算法、数据库（如AlphaFold、BLAST、Biopython、PyTorch等）
3. 包含实际的代码示例、命令行或分析流程（使用Markdown代码块）
4. 提及真实的文献、数据集或案例研究
5. 适合生物信息学从业者、研究人员和学生阅读
6. 文章长度1500-2500字
7. 使用Markdown格式，包含适当的标题层级、列表和强调
8. 结尾提出开放性问题，引导读者讨论

重要：不要添加任何关于AI生成、作者信息或发布时间的声明。直接输出文章内容即可。

避免：
- 泛泛而谈的内容
- "本文由AI生成"等声明
- 作者署名
- 发布日期
PROMPT;

        $prompt = <<<PROMPT
请撰写一篇关于"{$topic}"的技术文章。

文章结构建议：
1. 引言 - 介绍主题背景和重要性
2. 核心内容 - 技术细节、方法论、工具使用
3. 实践案例 - 具体的代码示例或分析流程
4. 总结与展望 - 要点总结和未来发展方向
5. 讨论问题 - 提出2-3个开放性问题供读者讨论

请确保内容深入、实用，能够帮助读者掌握相关技能。
PROMPT;

        $result = $this->callAI($prompt, $systemPrompt);
        $content = $result['choices'][0]['message']['content'] ?? '';
        
        // 清理AI标识
        $content = preg_replace('/\n+\s*[-—]*\s*本文由.+生成.*$/isu', '', $content);
        $content = preg_replace('/\n+\s*[-—]*\s*AI生成.*$/isu', '', $content);
        $content = preg_replace('/\n+\s*[-—]*\s*由\s*Qwen.*驱动.*$/isu', '', $content);
        
        // 提取标题
        $lines = explode("\n", $content);
        $title = $topic;
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '# ') === 0) {
                $title = trim(substr($line, 2));
                break;
            }
        }
        
        return [
            'title' => $title,
            'content' => $content
        ];
    }
}
