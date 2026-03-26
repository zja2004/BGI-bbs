<?php

namespace FlarumAgents\Agents;

use FlarumAgents\Core\BaseAgent;
use FlarumAgents\Core\FlarumClient;

/**
 * 论文解读Agent
 * 每2小时读取一篇未解读的预印本论文（基于元数据），生成中文解读文章
 * 
 * 改进功能：
 * 1. 严格的Tag匹配 - 只发布与生物信息学高度相关的论文
 * 2. 规范的文献信息格式
 * 3. 质量过滤 - 跳过不相关的论文
 */
class PaperInterpreterAgent extends BaseAgent
{
    private FlarumClient $flarum;
    private string $metadataDir;
    private string $interpretedDir;
    
    // 生物信息学核心关键词（必须至少匹配一个才发布）
    private array $coreBioinformaticsKeywords = [
        'bioinformatic', 'computational biology', 'genomic', 'transcriptom', 'proteom',
        'metabolom', 'single-cell', 'scrna', 'rna-seq', 'dna-seq', 'chip-seq',
        'atac-seq', 'methylation', 'epigenetic', 'metagenom', 'microbiome',
        'phylogen', 'sequence alignment', 'gene expression', 'protein structure',
        'alphafold', 'crispr', 'synthetic biology', 'systems biology',
        'single cell', 'spatial transcript', 'scRNA-seq', 'bulk RNA-seq',
        'whole genome', 'whole exome', 'variant calling', 'snp calling',
        'differential expression', 'pathway analysis', 'network analysis',
        'machine learning', 'deep learning', 'neural network', 'language model'
    ];
    
    // 明确不相关的关键词（遇到这些直接跳过）
    private array $excludeKeywords = [
        'ethereum', 'bitcoin', 'cryptocurrency', 'blockchain', 'financial trading',
        'stock market', 'aeroelastic', 'flight dynamic', 'gust load',
        'concrete', 'battery electrode', 'electromagnetic', 'microwave',
        'control system', 'feedback linearisation', 'UAV', 'aircraft',
        'cantilevered plate', 'damage identification', 'frequency response'
    ];
    
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
    public function getDescription(): string { return '每2小时解读一篇预印本论文并发布（严格匹配生物信息学相关）'; }

    public function execute(): array
    {
        $this->log('info', '开始执行论文解读任务');
        
        // 获取待解读的论文（基于元数据）
        $pendingPapers = $this->getPendingPapers();
        
        if (empty($pendingPapers)) {
            $this->log('info', '没有待解读的论文');
            return ['success' => true, 'message' => '没有待解读的论文，请先运行preprint_retriever获取论文元数据'];
        }
        
        // 筛选生物信息学相关的论文
        $relevantPapers = [];
        foreach ($pendingPapers as $paper) {
            if ($this->isBioinformaticsRelevant($paper['metadata'])) {
                $relevantPapers[] = $paper;
            } else {
                // 标记为已跳过（不解读）
                $this->markAsSkipped($paper['doi'], 'not_relevant');
                $this->log('info', '跳过不相关论文', ['doi' => $paper['doi'], 'title' => $paper['metadata']['title'] ?? '']);
            }
        }
        
        if (empty($relevantPapers)) {
            $this->log('info', '没有找到生物信息学相关的论文，跳过本次执行');
            return ['success' => true, 'message' => '没有生物信息学相关的论文待解读'];
        }
        
        // 选择最新的一篇相关论文
        $paper = $relevantPapers[0];
        $this->log('info', '选择论文进行解读', ['doi' => $paper['doi'], 'title' => $paper['metadata']['title'] ?? '']);
        
        try {
            // 生成解读（基于元数据，无需PDF）
            $interpretation = $this->interpretPaper($paper);
            
            // 发布到论坛
            $tags = $this->selectTags($paper['metadata']);
            
            // 如果没有匹配到任何相关tag，跳过发布
            if (count($tags) <= 2) { // 只有基础标签[9,2]
                $this->log('warning', '论文没有匹配到相关tag，跳过发布', ['doi' => $paper['doi']]);
                $this->markAsSkipped($paper['doi'], 'no_matching_tags');
                return ['success' => true, 'message' => '论文没有匹配到相关tag，跳过发布'];
            }
            
            $userId = $this->getConfigValue('interpreter_user_id', 6); // 默认使用华小文
            
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
                    'doi' => $paper['doi'],
                    'tags' => $tags
                ]);
                
                return [
                    'success' => true,
                    'discussion_id' => $discussionId,
                    'doi' => $paper['doi'],
                    'title' => $interpretation['title'],
                    'tags' => $tags
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
     * 判断论文是否与生物信息学相关
     */
    protected function isBioinformaticsRelevant(array $metadata): bool
    {
        $category = strtolower($metadata['category'] ?? '');
        $abstract = strtolower($metadata['abstract'] ?? '');
        $title = strtolower($metadata['title'] ?? '');
        $combinedText = $title . ' ' . $abstract . ' ' . $category;
        
        // 首先检查是否包含排除关键词
        foreach ($this->excludeKeywords as $exclude) {
            if (strpos($combinedText, $exclude) !== false) {
                return false;
            }
        }
        
        // 检查是否包含核心生物信息学关键词
        $matchCount = 0;
        foreach ($this->coreBioinformaticsKeywords as $keyword) {
            if (strpos($combinedText, $keyword) !== false) {
                $matchCount++;
            }
        }
        
        // 至少匹配2个关键词才算相关（提高门槛）
        return $matchCount >= 2;
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
            $skippedFile = $this->interpretedDir . $basename . '_skipped.json';
            
            // 检查是否已解读或已跳过
            if (file_exists($interpretedFile) || file_exists($skippedFile)) {
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
        
        // 提取年份
        $year = substr($date, 0, 4);
        
        $systemPrompt = <<<PROMPT
你是一位资深的生物信息学论文解读专家。你的任务是将英文学术论文（基于标题和摘要）转化为通俗易懂的中文技术文章。

解读要求：
1. **标题**：创建一个吸引人的中文标题，突出论文核心创新点，格式：【论文解读】+ 核心内容
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
        
        // 添加规范的文献信息格式
        $content .= "\n\n---\n\n**文献信息**\n\n";
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
     * 选择标签 - 严格匹配，只添加明确相关的标签
     * 标签ID对照：
     *   一级: 1=AI与智能体, 2=生物信息学
     *   二级: 9=AI论文解读, 17=RNA-seq, 18=单细胞测序, 19=基因组学, 
     *         20=蛋白质组学, 21=代谢组学, 22=多组学整合, 23=表观遗传,
     *         24=宏基因组, 28=机器学习, 29=深度学习, 11=AlphaFold,
     *         12=大语言模型
     */
    protected function selectTags(array $metadata): array
    {
        $category = strtolower($metadata['category'] ?? '');
        $abstract = strtolower($metadata['abstract'] ?? '');
        $title = strtolower($metadata['title'] ?? '');
        $combinedText = $title . ' ' . $abstract . ' ' . $category;
        
        // 基础标签：AI论文解读 (必须) + 生物信息学(一级分类)
        $tags = [9, 2];
        $matchedKeywords = [];
        
        // 严格匹配：每个类别只匹配最明确的关键词
        
        // 单细胞测序 - 需要明确的单细胞相关关键词
        if (preg_match('/\bsingle.cell\b|\bscrna\b|\bscRNA-seq\b|\bspatial transcriptom/', $combinedText)) {
            $tags[] = 18;
            $matchedKeywords[] = '单细胞测序';
        }
        
        // RNA-seq - 需要明确的RNA-seq关键词
        if (preg_match('/\brna-seq\b|\btranscriptom\b|\bdifferential expression\b/', $combinedText)) {
            $tags[] = 17;
            $matchedKeywords[] = 'RNA-seq';
        }
        
        // 基因组学 - 需要明确的基因组相关关键词
        if (preg_match('/\bgenom\b|\bwhole genome\b|\bwhole exome\b|\bwgs\b|\bwes\b/', $combinedText)) {
            $tags[] = 19;
            $matchedKeywords[] = '基因组学';
        }
        
        // 蛋白质组学 - 需要明确的蛋白质相关关键词
        if (preg_match('/\bproteom\b|\bprotein structure\b|\bprotein sequence\b|\bamino acid\b/', $combinedText)) {
            $tags[] = 20;
            $matchedKeywords[] = '蛋白质组学';
        }
        
        // 代谢组学 - 需要明确的代谢相关关键词
        if (preg_match('/\bmetabolom\b|\bmetabolite\b/', $combinedText)) {
            $tags[] = 21;
            $matchedKeywords[] = '代谢组学';
        }
        
        // 多组学整合
        if (preg_match('/\bmulti.omics?\b|\bintegrative\b|\btranscriptomic.*proteomic|\bproteomic.*transcriptomic/', $combinedText)) {
            $tags[] = 22;
            $matchedKeywords[] = '多组学整合';
        }
        
        // 表观遗传 - 需要明确的表观遗传相关关键词
        if (preg_match('/\bepigenetic\b|\bchip-seq\b|\batac-seq\b|\bmethylation\b|\bhistone\b/', $combinedText)) {
            $tags[] = 23;
            $matchedKeywords[] = '表观遗传';
        }
        
        // 宏基因组
        if (preg_match('/\bmetagenom\b|\bmicrobiome\b|\b16s\b|\bshotgun sequencing/', $combinedText)) {
            $tags[] = 24;
            $matchedKeywords[] = '宏基因组';
        }
        
        // 深度学习 - 需要明确的深度学习相关关键词
        if (preg_match('/\bdeep learning\b|\bneural network\b|\bcnn\b|\brnn\b|\blstm\b|\btransformer\b/', $combinedText)) {
            $tags[] = 29;
            $tags[] = 1;  // AI与智能体(一级)
            $matchedKeywords[] = '深度学习';
        }
        // 机器学习（如果不是深度学习）
        elseif (preg_match('/\bmachine learning\b|\brandom forest\b|\bsvm\b|\bgradient boosting\b/', $combinedText)) {
            $tags[] = 28;
            $tags[] = 1;
            $matchedKeywords[] = '机器学习';
        }
        
        // AlphaFold/蛋白质结构预测
        if (preg_match('/\balphafold\b|\bprotein folding\b/', $combinedText)) {
            $tags[] = 11;
            $tags[] = 1;
            $matchedKeywords[] = 'AlphaFold';
        }
        
        // 大语言模型
        if (preg_match('/\blarge language model\b|\bllm\b|\bgpt\b/', $combinedText)) {
            $tags[] = 12;
            $tags[] = 1;
            $matchedKeywords[] = '大语言模型';
        }
        
        // 记录匹配的标签用于调试
        if (!empty($matchedKeywords)) {
            $this->log('info', '论文标签匹配', ['matched' => $matchedKeywords]);
        }
        
        return array_unique($tags);
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
     * 标记为已跳过
     */
    protected function markAsSkipped(string $doi, string $reason): void
    {
        if (!is_dir($this->interpretedDir)) {
            mkdir($this->interpretedDir, 0755, true);
        }
        
        $filename = $this->sanitizeFilename($doi) . '_skipped.json';
        $filepath = $this->interpretedDir . $filename;
        
        $data = [
            'doi' => $doi,
            'skipped_at' => date('Y-m-d H:i:s'),
            'reason' => $reason,
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
