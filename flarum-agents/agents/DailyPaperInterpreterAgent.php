<?php

namespace FlarumAgents\Agents;

use FlarumAgents\Core\BaseAgent;
use FlarumAgents\Core\FlarumClient;

/**
 * 每日论文解读Agent
 * 每2小时从队列中读取一篇已下载的PDF论文，生成深度解读文章
 * 
 * 工作流程：
 * 1. 从paper_queue.json队列中读取状态为pending的论文
 * 2. 使用AI读取PDF内容并生成深度解读
 * 3. 发布到论坛并标记为已解读
 */
class DailyPaperInterpreterAgent extends BaseAgent
{
    private FlarumClient $flarum;
    private string $queueFile;
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
        'cantilevered plate', 'damage identification', 'frequency response',
        'flight dynamics', 'gust alleviation', 'nonlinear aeroelastic'
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
        
        $this->queueFile = __DIR__ . '/../preprints/paper_queue.json';
        $this->interpretedDir = __DIR__ . '/../preprints/interpreted/';
        
        if (!is_dir($this->interpretedDir)) {
            mkdir($this->interpretedDir, 0755, true);
        }
    }

    public function getName(): string { return 'daily_paper_interpreter'; }
    public function getDescription(): string { return '每2小时解读一篇已下载的arXiv论文并发布（严格匹配生物信息学相关）'; }

    public function execute(): array
    {
        $this->log('info', '开始执行每日论文解读任务');
        
        // 获取待解读的论文（循环查找相关论文）
        $pendingPaper = $this->getNextRelevantPaper();
        
        if ($pendingPaper === null) {
            $this->log('info', '没有生物信息学相关的论文，跳过本次执行');
            return ['success' => true, 'message' => '没有生物信息学相关的论文待解读'];
        }
        
        $this->log('info', '选择论文进行解读', [
            'id' => $pendingPaper['id'],
            'title' => $pendingPaper['title'],
            'pdf_file' => $pendingPaper['pdf_file']
        ]);
        
        try {
            // 检查PDF文件是否存在
            if (!file_exists($pendingPaper['pdf_file'])) {
                throw new \Exception("PDF文件不存在: " . $pendingPaper['pdf_file']);
            }
            
            // 生成深度解读（基于PDF内容）
            $interpretation = $this->interpretPaperWithPDF($pendingPaper);
            
            // 发布到论坛
            $tags = $this->selectTags($pendingPaper);
            
            // 如果没有匹配到任何相关tag，跳过发布
            if (count($tags) <= 2) { // 只有基础标签[9,2]
                $this->log('warning', '论文没有匹配到相关tag，跳过发布', ['arxiv_id' => $pendingPaper['id']]);
                $this->updateQueueStatus($pendingPaper['id'], 'skipped_no_tags');
                return ['success' => true, 'message' => '论文没有匹配到相关tag，跳过发布'];
            }
            
            $userId = $this->getConfigValue('daily_interpreter_user_id', 6); // 默认使用华小文
            
            $result = $this->flarum->createDiscussion(
                $interpretation['title'],
                $interpretation['content'],
                $tags,
                $userId
            );
            
            $discussionId = $result['data']['id'] ?? null;
            
            if ($discussionId) {
                // 标记为已解读
                $this->markAsInterpreted($pendingPaper['id'], $discussionId);
                $this->updateQueueStatus($pendingPaper['id'], 'published', $discussionId);
                
                $this->log('info', '论文解读发布成功', [
                    'discussion_id' => $discussionId,
                    'arxiv_id' => $pendingPaper['id'],
                    'title' => $interpretation['title'],
                    'tags' => $tags
                ]);
                
                return [
                    'success' => true,
                    'discussion_id' => $discussionId,
                    'arxiv_id' => $pendingPaper['id'],
                    'title' => $interpretation['title'],
                    'tags' => $tags
                ];
            } else {
                throw new \Exception('发布到论坛失败');
            }
            
        } catch (\Exception $e) {
            $this->log('error', '论文解读失败', ['error' => $e->getMessage()]);
            $this->updateQueueStatus($pendingPaper['id'], 'failed');
            throw $e;
        }
    }

    /**
     * 判断论文是否与生物信息学相关
     */
    protected function isBioinformaticsRelevant(array $paper): bool
    {
        $title = strtolower($paper['title'] ?? '');
        $abstract = strtolower($paper['abstract'] ?? '');
        $combinedText = $title . ' ' . $abstract;
        
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
        
        // 至少匹配2个关键词才算相关
        return $matchCount >= 2;
    }

    /**
     * 获取队列中下一篇相关的待解读论文
     */
    protected function getNextRelevantPaper(): ?array
    {
        while (true) {
            $paper = $this->getNextPendingPaper();
            
            if ($paper === null) {
                return null; // 队列为空
            }
            
            if ($this->isBioinformaticsRelevant($paper)) {
                return $paper;
            }
            
            // 不相关的论文，标记为跳过并继续查找
            $this->log('info', '跳过不相关论文', ['arxiv_id' => $paper['id'], 'title' => $paper['title']]);
            $this->updateQueueStatus($paper['id'], 'skipped_not_relevant');
        }
    }

    /**
     * 获取队列中下一篇待解读的论文
     */
    protected function getNextPendingPaper(): ?array
    {
        if (!file_exists($this->queueFile)) {
            return null;
        }
        
        $queue = json_decode(file_get_contents($this->queueFile), true);
        if (!is_array($queue)) {
            return null;
        }
        
        // 找到第一个状态为pending的论文
        foreach ($queue as $paper) {
            if (($paper['status'] ?? 'pending') === 'pending') {
                return $paper;
            }
        }
        
        return null;
    }

    /**
     * 基于PDF内容深度解读论文
     */
    protected function interpretPaperWithPDF(array $paper): array
    {
        $title = $paper['title'] ?? '';
        $authors = $paper['authors'] ?? '';
        $abstract = $paper['abstract'] ?? '';
        $published = $paper['published'] ?? '';
        $arxivId = $paper['id'] ?? '';
        $pdfFile = $paper['pdf_file'] ?? '';
        
        $this->log('info', "开始深度解读论文: $arxivId");
        
        // 首先尝试从PDF提取文本（使用pdftotext）
        $pdfText = $this->extractTextFromPDF($pdfFile);
        $hasFullText = !empty($pdfText) && strlen($pdfText) > 1000;
        
        if ($hasFullText) {
            $this->log('info', "成功提取PDF文本，长度: " . strlen($pdfText));
            // 限制文本长度，避免超出AI上下文限制
            $pdfText = substr($pdfText, 0, 15000); // 约前15KB文本
        } else {
            $this->log('info', "无法提取完整PDF文本，将基于摘要解读");
        }
        
        $systemPrompt = <<<PROMPT
你是一位资深的生物信息学论文解读专家。你的任务是将学术论文转化为专业、深入、易懂的中文技术文章。

解读要求：
1. **标题**：创建一个吸引人的中文标题，突出论文核心创新点，格式如：【论文解读】xxx
2. **研究背景**（300-400字）：解释该研究领域的背景、现有问题和研究动机
3. **核心方法**（400-500字）：详细解释论文提出的方法、算法或技术路线
4. **主要发现与结果**（300-400字）：总结实验结果、性能评估和关键发现
5. **讨论与意义**（200-300字）：分析研究的创新点、局限性和对领域的影响
6. **实践启示**（150-200字）：该研究对实际工作的指导意义
7. **精炼总结**：3-5条bullet points，每条一句话，概括核心要点

写作风格：
- 面向生物信息学研究人员、研究生和高级工程师
- 专业但通俗易懂，适当使用类比
- 深入技术细节，不只是表面描述
- 批判性思维：指出研究的优点和潜在局限
- 引用具体数据、指标和实验结果

格式要求：
- 使用Markdown格式，包含适当的标题层级
- 代码块、公式使用正确的Markdown语法
- 总结部分必须精炼（3-5条bullet points，每条≤30字）

重要提示：
- 基于提供的{"$hasFullText ? 'PDF全文内容' : '摘要信息'"}进行解读
- 合理推断技术细节，但不要过度解读
- 明确标注哪些是论文明确提到的，哪些是合理推断
PROMPT;

        // 构建提示词
        if ($hasFullText) {
            $prompt = <<<PROMPT
请深度解读以下生物信息学论文（基于PDF全文）：

【论文元数据】
标题: $title
作者: $authors
发表日期: $published
arXiv ID: $arxivId
原文链接: https://arxiv.org/abs/$arxivId

【论文摘要】
$abstract

【PDF全文内容（前15000字符）】
$pdfText

请基于以上信息生成一篇深度解读文章，要求：
1. 深入分析论文的核心方法和技术创新
2. 解释实验设计和关键结果
3. 评估研究的贡献和局限性
4. 讨论对实际应用的指导意义
5. 提供精炼的总结（3-5条bullet points）

文章长度2000-2500字，要求专业、深入、有洞察力。
PROMPT;
        } else {
            $prompt = <<<PROMPT
请解读以下生物信息学论文（基于摘要）：

【论文标题】
$title

【作者】
$authors

【发表日期】
$published

【arXiv ID】
$arxivId
原文链接: https://arxiv.org/abs/$arxivId

【摘要】
$abstract

请基于摘要信息生成一篇解读文章，要求：
1. 解释研究背景和动机
2. 根据标题和摘要推断核心方法
3. 总结主要发现和意义
4. 评估潜在的创新点和应用价值
5. 提供精炼总结（3-5条bullet points）

注意：由于只提供了摘要，请明确标注哪些是摘要明确提到的，哪些是合理推断。
文章长度1500-1800字。
PROMPT;
        }

        $result = $this->callAI($prompt, $systemPrompt);
        $content = $result['choices'][0]['message']['content'] ?? '';
        
        // 构建本地下载链接
        $flarumConfig = $this->getConfigValue('global.flarum', []);
        $baseUrl = $flarumConfig['base_url'] ?? 'https://172.16.218.40';
        $localDownloadUrl = "$baseUrl/papers/download.php?id=$arxivId";
        
        // 添加规范的文献信息格式
        $content .= "\n\n---\n\n**文献信息**\n\n";
        $content .= "- **标题**: $title\n";
        $content .= "- **作者**: $authors\n";
        $content .= "- **预印本平台**: arXiv\n";
        $content .= "- **发表日期**: $published\n";
        $content .= "- **arXiv ID**: [$arxivId](https://arxiv.org/abs/$arxivId)\n";
        $content .= "- **PDF下载**: [本地下载]($localDownloadUrl) (也可访问 [arXiv原文](https://arxiv.org/pdf/$arxivId.pdf))\n";
        $interpretationType = $hasFullText ? 'PDF全文' : '论文摘要';
        $content .= "\n> 💡 本文由AI基于{$interpretationType}深度解读生成。\n";
        
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
            'arxiv_id' => $arxivId,
            'interpretation_type' => $hasFullText ? 'full_pdf' : 'abstract_only'
        ];
    }

    /**
     * 从PDF提取文本
     */
    protected function extractTextFromPDF(string $pdfFile): string
    {
        // 尝试使用pdftotext
        $tempTxt = tempnam(sys_get_temp_dir(), 'pdf_') . '.txt';
        
        $output = [];
        $returnVar = 0;
        exec("pdftotext -layout -nopgbrk " . escapeshellarg($pdfFile) . " " . escapeshellarg($tempTxt) . " 2>&1", $output, $returnVar);
        
        $text = '';
        if ($returnVar === 0 && file_exists($tempTxt)) {
            $text = file_get_contents($tempTxt);
            unlink($tempTxt);
        }
        
        // 清理文本
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
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
    protected function selectTags(array $paper): array
    {
        $title = strtolower($paper['title'] ?? '');
        $abstract = strtolower($paper['abstract'] ?? '');
        $combinedText = $title . ' ' . $abstract;
        
        // 基础标签：AI论文解读(9) + 生物信息学(2)
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
    protected function markAsInterpreted(string $arxivId, int $discussionId): void
    {
        $filename = $this->sanitizeFilename($arxivId) . '.json';
        $filepath = $this->interpretedDir . $filename;
        
        $data = [
            'arxiv_id' => $arxivId,
            'discussion_id' => $discussionId,
            'interpreted_at' => date('Y-m-d H:i:s'),
            'interpreter' => 'DailyPaperInterpreterAgent'
        ];
        
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * 更新队列中的论文状态
     */
    protected function updateQueueStatus(string $arxivId, string $status, ?int $discussionId = null): void
    {
        if (!file_exists($this->queueFile)) {
            return;
        }
        
        $queue = json_decode(file_get_contents($this->queueFile), true);
        if (!is_array($queue)) {
            return;
        }
        
        foreach ($queue as &$paper) {
            if ($paper['id'] === $arxivId) {
                $paper['status'] = $status;
                $paper['updated_at'] = date('Y-m-d H:i:s');
                if ($discussionId !== null) {
                    $paper['discussion_id'] = $discussionId;
                }
                break;
            }
        }
        
        file_put_contents($this->queueFile, json_encode($queue, JSON_PRETTY_PRINT));
    }

    /**
     * 清理文件名
     */
    protected function sanitizeFilename(string $name): string
    {
        $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $name);
        return substr($filename, 0, 200);
    }
}
