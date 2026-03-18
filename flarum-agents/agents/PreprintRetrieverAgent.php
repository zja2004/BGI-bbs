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
            'CRISPR',
            'synthetic biology'
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
                
                sleep(3); // 避免API限流
                
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
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-7 days'));
        
        $url = sprintf(
            '%s/details/biorxiv/%s/%s/%d',
            $this->apiBaseUrl,
            $startDate,
            $endDate,
            100
        );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($response)) {
            throw new \Exception("API请求失败: HTTP $httpCode");
        }
        
        $data = json_decode($response, true);
        $papers = $data['collection'] ?? [];
        
        $filteredPapers = [];
        $keywordLower = strtolower($keyword);
        
        foreach ($papers as $paper) {
            $title = strtolower($paper['title'] ?? '');
            $abstract = strtolower($paper['abstract'] ?? '');
            $category = strtolower($paper['category'] ?? '');
            
            if (strpos($title, $keywordLower) !== false ||
                strpos($abstract, $keywordLower) !== false ||
                strpos($category, str_replace(' ', '-', $keywordLower)) !== false) {
                $filteredPapers[] = $paper;
            }
        }
        
        return $filteredPapers;
    }

    protected function shouldDownload(array $paper): bool
    {
        $doi = $paper['doi'] ?? '';
        if (empty($doi)) {
            return false;
        }
        
        $filename = $this->sanitizeFilename($doi) . '.pdf';
        $filepath = $this->pdfDir . $filename;
        
        if (file_exists($filepath)) {
            return false;
        }
        
        $interpretedFile = __DIR__ . '/../preprints/interpreted/' . $this->sanitizeFilename($doi) . '.json';
        if (file_exists($interpretedFile)) {
            return false;
        }
        
        return true;
    }

    /**
     * 下载论文PDF - 使用多种URL格式尝试
     */
    protected function downloadPaper(array $paper): bool
    {
        $doi = $paper['doi'] ?? '';
        if (empty($doi)) {
            return false;
        }
        
        $filename = $this->sanitizeFilename($doi);
        $metadataPath = $this->metadataDir . $filename . '.json';
        file_put_contents($metadataPath, json_encode($paper, JSON_PRETTY_PRINT));
        
        // 尝试多种PDF URL格式
        $pdfUrls = [
            // 格式1: 直接使用DOI
            "https://www.biorxiv.org/content/{$doi}.full.pdf",
            // 格式2: 使用short DOI (去掉10.1101/)
            str_replace('10.1101/', '', "https://www.biorxiv.org/content/{$doi}.full.pdf"),
            // 格式3: 文章页面URL（可能重定向到PDF）
            "https://www.biorxiv.org/content/{$doi}",
        ];
        
        $pdfPath = $this->pdfDir . $filename . '.pdf';
        
        foreach ($pdfUrls as $pdfUrl) {
            try {
                $this->log('info', "尝试下载: $pdfUrl");
                
                $ch = curl_init($pdfUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/pdf,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Referer: https://www.biorxiv.org/',
                ]);
                curl_setopt($ch, CURLOPT_COOKIE, 'cookieConsent=true');
                
                $pdfContent = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                
                // 检查是否是PDF内容
                if ($httpCode === 200 && !empty($pdfContent)) {
                    // 检查内容是否以PDF头部开头
                    if (strpos($pdfContent, '%PDF') === 0) {
                        file_put_contents($pdfPath, $pdfContent);
                        
                        $this->log('info', "PDF下载成功: $doi", [
                            'title' => $paper['title'] ?? '',
                            'size' => strlen($pdfContent),
                            'url' => $pdfUrl
                        ]);
                        
                        return true;
                    } else {
                        $this->log('warning', "返回的不是PDF: $doi", ['content_type' => $contentType, 'size' => strlen($pdfContent)]);
                    }
                } else {
                    $this->log('warning', "下载失败: $doi", ['http_code' => $httpCode, 'url' => $pdfUrl]);
                }
                
            } catch (\Exception $e) {
                $this->log('warning', "下载异常: $pdfUrl", ['error' => $e->getMessage()]);
            }
            
            // 失败后等待一下再试下一个URL
            sleep(1);
        }
        
        $this->log('error', "所有下载方式都失败: $doi");
        return false;
    }

    protected function sanitizeFilename(string $doi): string
    {
        $filename = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $doi);
        return substr($filename, 0, 200);
    }

    public function getPendingPapers(): array
    {
        $pending = [];
        
        $pdfFiles = glob($this->pdfDir . '*.pdf');
        foreach ($pdfFiles as $pdfFile) {
            $basename = basename($pdfFile, '.pdf');
            $interpretedFile = __DIR__ . '/../preprints/interpreted/' . $basename . '.json';
            
            if (in_array($basename, $this->getInterpretedDois())) {
                continue;
            }
            
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
        
        usort($pending, function($a, $b) {
            $dateA = $a['metadata']['date'] ?? '1900-01-01';
            $dateB = $b['metadata']['date'] ?? '1900-01-01';
            return strtotime($dateB) - strtotime($dateA);
        });
        
        return $pending;
    }

    protected function getInterpretedDois(): array
    {
        $interpretedDir = __DIR__ . '/../preprints/interpreted/';
        if (!is_dir($interpretedDir)) {
            return [];
        }
        
        $files = glob($interpretedDir . '*.json');
        $dois = [];
        foreach ($files as $file) {
            $dois[] = basename($file, '.json');
        }
        return $dois;
    }
}
