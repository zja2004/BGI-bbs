<?php
/**
 * 论文PDF下载代理
 * 从本地存储提供PDF下载，支持按arXiv ID查找
 */

// 配置
$preprintsDir = __DIR__ . '/../../flarum-agents/preprints/daily_papers/';
$cacheDir = __DIR__ . '/../../storage/papers_cache/';

// 获取arXiv ID
$arxivId = $_GET['id'] ?? '';

if (empty($arxivId)) {
    http_response_code(400);
    die('错误：请提供论文ID (id 参数)');
}

// 清理ID（只允许数字和点）
$arxivId = preg_replace('/[^0-9.]/', '', $arxivId);

if (empty($arxivId)) {
    http_response_code(400);
    die('错误：无效的论文ID');
}

// 在daily_papers目录下查找PDF文件
$pdfFile = null;
$dateDirs = glob($preprintsDir . '*/', GLOB_ONLYDIR);

foreach ($dateDirs as $dateDir) {
    // 查找匹配的文件名（格式可能是: 01_2603.21503.pdf 或 2603.21503.pdf）
    $patterns = [
        $dateDir . '*_' . $arxivId . '.pdf',
        $dateDir . $arxivId . '.pdf'
    ];
    
    foreach ($patterns as $pattern) {
        $matches = glob($pattern);
        if (!empty($matches)) {
            $pdfFile = $matches[0];
            break 2;
        }
    }
}

// 如果本地没有找到，检查缓存目录
if (!$pdfFile && is_dir($cacheDir)) {
    $cacheFile = $cacheDir . $arxivId . '.pdf';
    if (file_exists($cacheFile)) {
        $pdfFile = $cacheFile;
    }
}

// 如果还是找不到，从arXiv下载并缓存
if (!$pdfFile) {
    // 确保缓存目录存在
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . $arxivId . '.pdf';
    $arxivUrl = "https://arxiv.org/pdf/{$arxivId}.pdf";
    
    // 尝试下载
    $ch = curl_init($arxivUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; BGI-Forum/1.0)');
    $pdfData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && strlen($pdfData) > 1000) {
        file_put_contents($cacheFile, $pdfData);
        $pdfFile = $cacheFile;
    } else {
        http_response_code(404);
        die('错误：无法获取论文PDF，可能该论文不存在或arXiv暂时不可用');
    }
}

// 验证文件存在且可读
if (!file_exists($pdfFile) || !is_readable($pdfFile)) {
    http_response_code(500);
    die('错误：PDF文件无法访问');
}

// 获取文件信息
$fileSize = filesize($pdfFile);
$fileName = basename($pdfFile);

// 设置下载头
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $arxivId . '.pdf"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=86400'); // 缓存24小时
header('X-Content-Type-Options: nosniff');

// 输出文件
readfile($pdfFile);
exit;
