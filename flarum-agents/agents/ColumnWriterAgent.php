<?php

namespace FlarumAgents\Agents;

use FlarumAgents\Core\BaseAgent;
use FlarumAgents\Core\FlarumClient;

class ColumnWriterAgent extends BaseAgent
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

    public function getName(): string { return 'column_writer'; }
    public function getDescription(): string { return '每2小时撰写生信领域深度专栏文章'; }

    public function execute(): array
    {
        $this->log('info', '开始执行专栏写作任务');

        $column = $this->selectColumn();
        $this->log('info', "选中专栏: {$column['name']}");

        $article = $this->writeArticle($column);
        $this->log('info', '文章撰写完成', ['title' => $article['title'], 'length' => strlen($article['content'])]);

        $mode = $this->getConfigValue('mode', 'draft_for_review');

        if ($mode === 'draft_for_review') {
            $this->saveDraft($article, $column);
            return ['success' => true, 'mode' => 'draft', 'title' => $article['title']];
        } else {
            $tags = $column['tag_ids'] ?? [];
            $userId = $this->getConfigValue('writer_user_id');
            
            $result = $this->flarum->createDiscussion($article['title'], $article['content'], $tags, $userId);
            $discussionId = $result['data']['id'] ?? null;
            
            if ($discussionId) {
                $this->log('info', '专栏发布成功', ['discussion_id' => $discussionId]);
                return ['success' => true, 'mode' => 'published', 'discussion_id' => $discussionId];
            } else {
                throw new \Exception('专栏发布失败');
            }
        }
    }

    protected function selectColumn(): array
    {
        $columns = $this->getConfigValue('columns', []);
        $stateFile = __DIR__ . '/../config/column_schedule.json';
        
        $schedule = [];
        if (file_exists($stateFile)) {
            $schedule = json_decode(file_get_contents($stateFile), true) ?: [];
        }

        $oldestColumn = null;
        $oldestTime = PHP_INT_MAX;
        
        foreach ($columns as $column) {
            $lastTime = $schedule[$column['id']]['last_publish'] ?? 0;
            if ($lastTime < $oldestTime) {
                $oldestTime = $lastTime;
                $oldestColumn = $column;
            }
        }

        $schedule[$oldestColumn['id']]['last_publish'] = time();
        file_put_contents($stateFile, json_encode($schedule, JSON_PRETTY_PRINT));

        return $oldestColumn;
    }

    protected function writeArticle(array $column): array
    {
        $systemPrompt = <<<PROMPT
你是一位资深的{$column['name']}领域专家专栏作家，专注于生物信息学与计算生物学研究。

写作风格: {$column['style']}

专栏要求：
1. 深度专业：提供比常规文章更深入的技术细节和洞见
2. 前沿视角：结合2024-2025年最新研究进展和技术趋势
3. 实用性：提供可操作的指导，读者能实际应用
4. 数据支撑：引用真实数据、文献或案例
5. 代码友好：包含完整的代码示例和工作流程
6. 结构清晰：使用多级标题、列表、表格等组织内容
7. 批判性思维：不仅介绍技术，还要分析优缺点和适用场景

内容要求：
- 字数：{$column['word_count']}字左右
- 格式：Markdown，包含代码块、表格、列表
- 引用：提及具体文献（作者+年份）、软件版本、数据库
- 受众：生物信息学研究人员、生信工程师、计算生物学家

避免内容空洞，确保每一段落都有实质信息价值。
PROMPT;

        $prompt = <<<PROMPT
请为"{$column['name']}"专栏撰写一篇深度文章。

专栏定位: {$column['description']}
写作风格: {$column['style']}
目标字数: {$column['word_count']}字

文章要求：
1. 选择一个具体的、有深度的技术主题（避免过于宽泛）
2. 包含以下结构：
   - 引言：背景介绍和问题陈述
   - 技术原理：核心概念和算法详解
   - 实践指南：工具安装、参数设置、代码示例
   - 案例分析：真实数据集的分析流程
   - 讨论：优缺点、适用场景、与其他方法的比较
   - 展望：未来发展方向
3. 提供完整的、可运行的代码示例（Python或R）
4. 引用2-3篇相关文献（Nature/Science/Cell/Bioinformatics等）
5. 包含具体的性能数据（准确率、运行时间、内存使用等）
6. 结尾提出2-3个思考问题，引导读者深入探讨

请确保文章内容专业、深入、实用，符合顶级生信专栏的标准。
PROMPT;

        $result = $this->callAI($prompt, $systemPrompt);
        $content = $result['choices'][0]['message']['content'] ?? '';

        $title = '';
        if (preg_match('/^#\s*(.+)$/m', $content, $matches)) {
            $title = trim($matches[1]);
            $content = preg_replace('/^#\s*.+$/m', '', $content, 1);
        }
        if (empty($title)) {
            $lines = explode("\n", $content);
            $title = trim($lines[0]);
        }

        return [
            'title' => $title,
            'content' => trim($content),
            'column' => $column['name']
        ];
    }

    protected function saveDraft(array $article, array $column): void
    {
        $draftDir = __DIR__ . '/../drafts';
        if (!is_dir($draftDir)) {
            mkdir($draftDir, 0755, true);
        }

        $filename = date('Y-m-d-H-i') . '-' . $column['id'] . '.md';
        $filepath = $draftDir . '/' . $filename;

        $content = "---\n";
        $content .= "column: {$column['name']}\n";
        $content .= "created_at: " . date('Y-m-d H:i:s') . "\n";
        $content .= "---\n\n";
        $content .= "# {$article['title']}\n\n";
        $content .= $article['content'];

        file_put_contents($filepath, $content);
        $this->log('info', "草稿已保存: $filepath");
    }
}
