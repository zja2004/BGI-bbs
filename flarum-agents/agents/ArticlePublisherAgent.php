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
    public function getDescription(): string { return '每2小时自动发布一篇生物信息学/AI领域的专业文章'; }

    public function execute(): array
    {
        $this->log('info', '开始执行文章发布任务');

        $field = $this->selectField();
        $topic = $this->selectTopic($field);
        
        $this->log('info', "选中领域: {$field['name']}, 主题: $topic");

        $searchResults = $this->searchLatestInfo($field['name'], $topic);
        $this->log('info', '完成联网搜索');

        $article = $this->generateArticle($field, $topic, $searchResults);
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

    protected function selectTopic(array $field): string
    {
        return $field['topics'][array_rand($field['topics'])];
    }

    protected function searchLatestInfo(string $field, string $topic): array
    {
        return $this->searchWeb("$topic 最新进展 2024 2025");
    }

    protected function generateArticle(array $field, string $topic, array $research): array
    {
        // 专业的生信领域系统提示词
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

避免：
- 泛泛而谈的内容
- 与生物信息学无关的通用技术讨论
- 过于基础的科普内容（假设读者有一定专业背景）
PROMPT;

        $prompt = <<<PROMPT
请撰写一篇关于"{$topic}"的专业文章。

要求：
1. 标题要吸引人且准确反映内容，体现生物信息学专业特色
2. 文章结构：引言 → 技术背景 → 核心方法/工具 → 实际应用案例 → 总结与展望
3. 包含具体的技术细节，如算法名称、软件版本、参数设置等
4. 如果涉及代码，请提供Python/R代码示例
5. 引用相关的生物数据库（如PDB、UniProt、NCBI等）
6. 提及该领域的最新进展（2024-2025年）
7. 文章要实用，读者能从中获得可操作的见解
PROMPT;

        $result = $this->callAI($prompt, $systemPrompt);
        $content = $result['choices'][0]['message']['content'] ?? '';

        // 提取标题
        $title = '';
        if (preg_match('/^#\s*(.+)$/m', $content, $matches)) {
            $title = trim($matches[1]);
            $content = preg_replace('/^#\s*.+$/m', '', $content, 1);
        }
        if (empty($title)) {
            $lines = explode("\n", $content);
            $title = trim($lines[0]);
        }

        return ['title' => $title, 'content' => trim($content)];
    }

    protected function getFields(): array
    {
        return $this->getConfigValue('fields', []);
    }
}
