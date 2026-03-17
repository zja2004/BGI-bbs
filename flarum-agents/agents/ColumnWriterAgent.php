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
1. 深度专业：提供深入的技术细节和洞见
2. 前沿视角：结合最新研究进展
3. 实用性：提供可操作的指导
4. 数据支撑：引用真实数据、文献
5. 代码友好：包含完整代码示例
6. 批判性思维：分析优缺点和适用场景

【重要】总结部分要求：
- 严格控制字数：100-200字
- 格式：3-5条 bullet points
- 每条：1句话，不超过30字
- 只列核心要点，不展开

内容要求：
- 字数：{$column['word_count']}字左右（不含总结）
- 格式：Markdown
- 引用：具体文献、软件版本
- 受众：生物信息学研究人员

避免内容空洞，确保每一段落都有实质价值。
PROMPT;

        $prompt = <<<PROMPT
请为"{$column['name']}"专栏撰写一篇深度文章。

专栏定位: {$column['description']}
写作风格: {$column['style']}
目标字数: {$column['word_count']}字

文章结构：
1. 引言（200-300字）- 背景介绍和问题陈述
2. 技术原理（600-800字）- 核心概念和算法详解
3. 实践指南（400-600字）- 工具安装、参数设置、代码
4. 案例分析（300-500字）- 真实数据集分析
5. 【精炼总结】（100-200字，严格控制）
   - 格式：bullet points
   - 数量：3-5条
   - 每条：1句话，≤30字
   - 只列要点，零废话
6. 讨论问题 - 2-3个开放性问题

示例总结：
```
## 总结
- 核心技术：XX算法在处理YY问题时具有ZZ优势
- 关键参数：学习率设为0.001，batch size 32最佳
- 适用场景：适用于1000+样本的大规模数据集
- 局限性：对内存要求较高，小数据集易过拟合
- 未来方向：与Transformer架构结合可能提升性能
```

请确保总结部分精炼有力，符合顶级专栏标准。
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
