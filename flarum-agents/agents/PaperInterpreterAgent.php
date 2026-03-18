<?php

namespace FlarumAgents\Agents;

use FlarumAgents\Core\BaseAgent;
use FlarumAgents\Core\FlarumClient;

/**
 * 论文解读Agent
 * 每2小时读取一篇未解读的预印本论文（基于元数据），生成中文解读文章
 */
class PaperInterpreterAgent extends BaseAgent
{
    private FlarumClient $flarum;
    private string $metadataDir;
    private string $interpretedDir;
    
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
        
        $this->metadataDir = __DIR__ . '/../preprints/metadata/';
        $this->interpretedDir = __DIR__ . '/../preprints/interpreted/';
    }

    public function getName(): string { return 'paper_interpreter'; }
    public function getDescription(): string { return '每2小时解读一篇预印本论文并发布'; }

    public function execute(): array
    {
        $this->log('info', '开始执行论文解读任务');
        
        // 获取待解读的论文（基于元数据）
        $pendingPapers = $this->getPendingPapers();
        
        if (empty($pendingPapers)) {
            $this->log('info', '没有待解读的论文');
            return ['success' => true, 'message' => '没有待解读的论文，请先运行preprint_retriever获取论文元数据'];
        }
        
        // 选择最新的一篇
        $paper = $pendingPapers[0];
        $this->log('info', '选择论文', ['doi' => $paper['doi'], 'title' => $paper['metadata']['title'] ?? '']);
        
        try {
            // 生成解读（基于元数据，无需PDF）
            $interpretation = $this->interpretPaper($paper);
            
            // 发布到论坛
            $tags = $this->selectTags($paper['metadata']);
            $userId = $this->getConfigValue('interpreter_user_id', 6); // 默认使用毕小文
            
            $result = $this->flarum->createDiscussion(
                $interpretation['title'],
                $interpretation['content'],
                $tags,
                $userId
            );
            
            $discussionId = $result['data']['id'] ?? null;
            
            if ($discussionId) {
                // 标记为已解读
                $this->markAsInterpreted($paper['doi'], $discussionId);
                
                $this->log('info', '论文解读发布成功', [
                    'discussion_id' => $discussionId,
                    'doi' => $paper['doi']
                ]);
                
                return [
                    'success' => true,
                    'discussion_id' => $discussionId,
                    'doi' => $paper['doi'],
                    'title' => $interpretation['title']
                ];
            } else {
                throw new \Exception('发布失败');
            }
            
        } catch (\Exception $e) {
            $this->log('error', '论文解读失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 获取待解读的论文列表（基于元数据）
     */
    protected function getPendingPapers(): array
    {
        $pending = [];
        
        if (!is_dir($this->metadataDir)) {
            return $pending;
        }
        
        $metadataFiles = glob($this->metadataDir . '*.json');
        
        foreach ($metadataFiles as $metadataFile) {
            $basename = basename($metadataFile, '.json');
            $interpretedFile = $this->interpretedDir . $basename . '.json';
            
            // 检查是否已解读
            if (file_exists($interpretedFile)) {
                continue;
            }
            
            // 读取元数据
            $metadata = json_decode(file_get_contents($metadataFile), true) ?: [];
            
            $pending[] = [
                'metadata' => $metadata,
                'doi' => $basename,
                'metadata_file' => $metadataFile
            ];
        }
        
        // 按发布时间排序（最新的优先）
        usort($pending, function($a, $b) {
            $dateA = $a['metadata']['date'] ?? $a['metadata']['posted'] ?? '1900-01-01';
            $dateB = $b['metadata']['date'] ?? $b['metadata']['posted'] ?? '1900-01-01';
            return strtotime($dateB) - strtotime($dateA);
        });
        
        return $pending;
    }

    /**
     * 解读论文（基于元数据）
     */
    protected function interpretPaper(array $paper): array
    {
        $metadata = $paper['metadata'];
        $title = $metadata['title'] ?? '';
        $abstract = $metadata['abstract'] ?? '';
        $authors = $metadata['authors'] ?? '';
        $date = $metadata['date'] ?? $metadata['posted'] ?? date('Y-m-d');
        $doi = $metadata['doi'] ?? $paper['doi'];
        $category = $metadata['category'] ?? '';
        
        $systemPrompt = <<<PROMPT
你是一位资深的生物信息学论文解读专家。你的任务是将英文学术论文（基于标题和摘要）转化为通俗易懂的中文技术文章。

解读要求：
1. **标题**：创建一个吸引人的中文标题，突出论文核心创新点
2. **背景**：解释研究背景，为什么要做这项研究（200-300字）
3. **核心方法**：根据标题和摘要推断技术方法，用通俗语言解释（300-400字）
4. **主要发现**：总结摘要中提到的关键发现，用通俗语言解释意义（200-300字）
5. **意义与展望**：这项研究对领域的影响，可能的临床应用或技术影响（150-200字）
6. **精炼总结**：3-5条bullet points，每条一句话，核心要点

写作风格：
- 面向生物信息学研究人员和研究生
- 专业但通俗易懂
- 适当使用类比帮助理解复杂概念
- 突出论文的创新点和实用价值
- 基于摘要合理推断，但明确标注哪些是推断

格式要求：
- 使用Markdown格式
- 总结部分必须精炼（3-5条bullet points，每条≤30字）

重要提示：
- 你只看到了标题和摘要，没有看到全文
- 在解读时合理推断，但不要过度解读
- 明确标注"根据摘要推断"等说明
PROMPT;

        $prompt = <<<PROMPT
请解读以下预印本论文（基于标题和摘要）：

【论文标题】
$title

【作者】
$authors

【发表日期】
$date

【DOI】
$doi

【分类】
$category

【摘要】
$abstract

请生成一篇中文解读文章。要求：
1. 标题突出创新点
2. 解释研究背景和动机
3. 用通俗语言解释核心方法（基于摘要推断）
4. 总结主要发现和意义
5. 精炼总结（3-5条bullet points）
6. 明确标注哪些是摘要明确提到的，哪些是合理推断

文章长度1200-1500字。
PROMPT;

        $result = $this->callAI($prompt, $systemPrompt);
        $content = $result['choices'][0]['message']['content'] ?? '';
        
        // 添加原文链接
        $content .= "\n\n---\n\n**原文信息**\n";
        $content .= "- **标题**: $title\n";
        $content .= "- **作者**: $authors\n";
        $content .= "- **预印本平台**: bioRxiv\n";
        $content .= "- **发表日期**: $date\n";
        $content .= "- **DOI**: [$doi](https://doi.org/$doi)\n";
        $content .= "- **分类**: $category\n";
        $content .= "\n> 💡 本文解读基于论文标题和摘要生成。如需完整内容，请访问原文链接。\n";
        
        // 提取中文标题
        $chineseTitle = '';
        if (preg_match('/^#\s*(.+)$/m', $content, $matches)) {
            $chineseTitle = trim($matches[1]);
        }
        
        if (empty($chineseTitle)) {
            $chineseTitle = "【论文解读】" . mb_substr($title, 0, 50);
        }
        
        return [
            'title' => $chineseTitle,
            'content' => $content,
            'original_title' => $title,
            'doi' => $doi
        ];
    }

    /**
     * 选择标签
     */
    protected function selectTags(array $metadata): array
    {
        $category = strtolower($metadata['category'] ?? '');
        
        // 根据分类选择标签
        $tagMapping = [
            'bioinformatics' => [6],
            'genomics' => [7],
            'transcriptomics' => [8],
            'synthetic biology' => [2],
            'pharmacology' => [1],
            'biophysics' => [6],
        ];
        
        foreach ($tagMapping as $key => $tags) {
            if (strpos($category, $key) !== false) {
                return $tags;
            }
        }
        
        // 默认标签
        return [20]; // 科研经验分享
    }

    /**
     * 标记为已解读
     */
    protected function markAsInterpreted(string $doi, int $discussionId): void
    {
        if (!is_dir($this->interpretedDir)) {
            mkdir($this->interpretedDir, 0755, true);
        }
        
        $filename = $this->sanitizeFilename($doi) . '.json';
        $filepath = $this->interpretedDir . $filename;
        
        $data = [
            'doi' => $doi,
            'discussion_id' => $discussionId,
            'interpreted_at' => date('Y-m-d H:i:s'),
            'interpreter' => 'PaperInterpreterAgent'
        ];
        
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * 清理文件名
     */
    protected function sanitizeFilename(string $doi): string
    {
        $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $doi);
        return substr($filename, 0, 200);
    }
}
