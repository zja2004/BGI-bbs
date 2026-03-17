<?php

namespace FlarumAgents\Agents;

use FlarumAgents\Core\BaseAgent;

/**
 * 预印本论文检索Agent
 * 每日从bioRxiv检索最新生物信息学相关论文并下载PDF
 */
class PreprintRetrieverAgent extends BaseAgent
{
    private string $pdfDir;
    private string $metadataDir;
    private string $apiBaseUrl = 'https://api.biorxiv.org';
    
    public function __construct()
    {
        parent::__construct();
        $this->pdfDir = __DIR__ . '/../preprints/pdf/';
        $this->metadataDir = __DIR__ . '/../preprints/metadata/';
        
        // 确保目录存在
        if (!is_dir($this->pdfDir)) {
            mkdir($this->pdfDir, 0755, true);
        }
        if (!is_dir($this->metadataDir)) {
            mkdir($this->metadataDir, 0755, true);
        }
    }

    public function getName(): string { return 'preprint_retriever'; }
    public function getDescription(): string { return '每日检索bioRxiv最新预印本并下载PDF'; }

    public function execute(): array
    {
        $this->log('info', '开始检索预印本论文');
        
        // 生物信息学相关关键词
        $keywords = $this->getConfigValue('keywords', [
            'bioinformatics',
            'computational biology',
            'genomics',
            'transcriptomics',
            'proteomics',
            'single cell',
            'machine learning',
            'deep learning',
            'AlphaFold',
            'CRISPR'
        ]);
        
        $retrievedCount = 0;
        $downloadedCount = 0;
        
        foreach ($keywords as $keyword) {
            $this->log('info', "检索关键词: $keyword");
            
            try {
                $papers = $this->searchPapers($keyword);
                $retrievedCount += count($papers);
                
                foreach ($papers as $paper) {
                    if ($this->shouldDownload($paper)) {
                        if ($this->downloadPaper($paper)) {
                            $downloadedCount++;
                        }
                    }
                }
                
                // 避免API限流，每个关键词间隔2秒
                sleep(2);
                
            } catch (\Exception $e) {
                $this->log('error', "检索失败: $keyword", ['error' => $e->getMessage()]);
            }
        }
        
        $this->log('info', '检索完成', [
            'retrieved' => $retrievedCount,
            'downloaded' => $downloadedCount
        ]);
        
        return [
            'success' => true,
            'retrieved' => $retrievedCount,
            'downloaded' => $downloadedCount
        ];
    }

    /**
     * 从bioRxiv API搜索论文
     */
    protected function searchPapers(string $keyword): array
    {
        // 获取最近7天的论文
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));
        
        // bioRxiv API endpoint
        $url = sprintf(
            '%s/details/biorxiv/%s/%s/%d',
            $this->apiBaseUrl,
            $startDate,
            $endDate,
            100 // limit
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($response)) {
            throw new \Exception("API请求失败: HTTP $httpCode");
        }
        
        $data = json_decode($response, true);
        $papers = $data['collection'] ?? [];
        
        // 过滤与关键词相关的论文
        $filteredPapers = [];
        $keywordLower = strtolower($keyword);
        
        foreach ($papers as $paper) {
            $title = strtolower($paper['title'] ?? '');
            $abstract = strtolower($paper['abstract'] ?? '');
            $category = strtolower($paper['category'] ?? '');
            
            // 检查是否匹配关键词
            if (strpos($title, $keywordLower) !== false ||
                strpos($abstract, $keywordLower) !== false ||
                strpos($category, str_replace(' ', '-', $keywordLower)) !== false) {
                $filteredPapers[] = $paper;
            }
        }
        
        return $filteredPapers;
    }

    /**
     * 判断是否应该下载这篇论文
     */
    protected function shouldDownload(array $paper): bool
    {
        $doi = $paper['doi'] ?? '';
        if (empty($doi)) {
            return false;
        }
        
        // 检查是否已下载
        $filename = $this->sanitizeFilename($doi) . '.pdf';
        $filepath = $this->pdfDir . $filename;
        
        if (file_exists($filepath)) {
            return false;
        }
        
        // 检查是否已解读
        $interpretedFile = __DIR__ . '/../preprints/interpreted/' . $this->sanitizeFilename($doi) . '.json';
        if (file_exists($interpretedFile)) {
            return false;
        }
        
        return true;
    }

    /**
     * 下载论文PDF和元数据
     */
    protected function downloadPaper(array $paper): bool
    {
        $doi = $paper['doi'] ?? '';
        if (empty($doi)) {
            return false;
        }
        
        $filename = $this->sanitizeFilename($doi);
        
        try {
            // 保存元数据
            $metadataPath = $this->metadataDir . $filename . '.json';
            file_put_contents($metadataPath, json_encode($paper, JSON_PRETTY_PRINT));
            
            // 构建PDF下载URL
            // bioRxiv PDF URL格式: https://www.biorxiv.org/content/10.1101/XXXXX.full.pdf
            $pdfUrl = sprintf(
                'https://www.biorxiv.org/content/%s.full.pdf',
                $doi
            );
            
            // 下载PDF
            $pdfPath = $this->pdfDir . $filename . '.pdf';
            
            $ch = curl_init($pdfUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; FlarumBot/1.0)');
            
            $pdfContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && !empty($pdfContent)) {
                file_put_contents($pdfPath, $pdfContent);
                
                $this->log('info', "下载成功: $doi", [
                    'title' => $paper['title'] ?? '',
                    'size' => strlen($pdfContent)
                ]);
                
                return true;
            } else {
                $this->log('warning', "PDF下载失败: $doi", ['http_code' => $httpCode]);
                return false;
            }
            
        } catch (\Exception $e) {
            $this->log('error', "下载失败: $doi", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 清理文件名
     */
    protected function sanitizeFilename(string $doi): string
    {
        // 替换特殊字符
        $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $doi);
        return substr($filename, 0, 200); // 限制长度
    }

    /**
     * 获取待解读的论文列表
     */
    public function getPendingPapers(): array
    {
        $pending = [];
        
        $pdfFiles = glob($this->pdfDir . '*.pdf');
        foreach ($pdfFiles as $pdfFile) {
            $basename = basename($pdfFile, '.pdf');
            $interpretedFile = __DIR__ . '/../preprints/interpreted/' . $basename . '.json';
            
            if (!file_exists($interpretedFile)) {
                $metadataFile = $this->metadataDir . $basename . '.json';
                $metadata = [];
                if (file_exists($metadataFile)) {
                    $metadata = json_decode(file_get_contents($metadataFile), true) ?: [];
                }
                
                $pending[] = [
                    'pdf_path' => $pdfFile,
                    'metadata' => $metadata,
                    'doi' => $basename
                ];
            }
        }
        
        // 按发布时间排序（如果有的话）
        usort($pending, function($a, $b) {
            $dateA = $a['metadata']['date'] ?? '1900-01-01';
            $dateB = $b['metadata']['date'] ?? '1900-01-01';
            return strtotime($dateB) - strtotime($dateA);
        });
        
        return $pending;
    }
}
