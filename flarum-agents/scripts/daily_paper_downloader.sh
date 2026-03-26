#!/bin/bash
#===============================================================================
# 每日生物信息学论文自动下载脚本
# 每天凌晨3点执行，下载15篇最新arXiv生物信息学论文
#===============================================================================

set -e

# 配置
BASE_DIR="/home/ztron/flarum/flarum-agents"
PAPERS_DIR="$BASE_DIR/preprints/daily_papers"
QUEUE_FILE="$BASE_DIR/preprints/paper_queue.json"
LOG_FILE="$BASE_DIR/logs/daily_downloader.log"
DATE=$(date +%Y%m%d)
DAILY_DIR="$PAPERS_DIR/$DATE"

# 确保目录存在
mkdir -p "$DAILY_DIR" "$BASE_DIR/logs"

# 日志函数
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log "=========================================="
log "开始下载每日生物信息学论文 - $DATE"
log "=========================================="

# 搜索关键词（生物信息学相关）
KEYWORDS=(
    "bioinformatics"
    "computational biology"
    "single cell RNA-seq"
    "spatial transcriptomics"
    "protein structure prediction"
    "genomics"
    "AlphaFold"
    "deep learning biology"
    "scRNA-seq"
    "multiomics"
)

# 随机选择一个关键词进行搜索
SELECTED_KEYWORD=${KEYWORDS[$RANDOM % ${#KEYWORDS[@]}]}
log "今日搜索关键词: $SELECTED_KEYWORD"

# 使用arXiv API搜索论文（获取最新的15篇）
ARXIV_API_URL="https://export.arxiv.org/api/query?search_query=cat:q-bio.BM+OR+cat:q-bio.GN+OR+cat:q-bio.MN+OR+cat:q-bio.CB+OR+cat:cs.CE&start=0&max_results=15&sortBy=submittedDate&sortOrder=descending"

log "正在从arXiv获取论文列表..."

# 下载XML并解析
TEMP_XML="/tmp/arxiv_$$.xml"
curl -s "$ARXIV_API_URL" > "$TEMP_XML"

# 检查是否获取到内容
if [ ! -s "$TEMP_XML" ]; then
    log "错误: 无法从arXiv获取数据"
    exit 1
fi

# 使用Python解析XML并下载论文
python3 /home/ztron/flarum/flarum-agents/scripts/download_papers.py "$TEMP_XML" "$DAILY_DIR" "$QUEUE_FILE" "$DATE" "$SELECTED_KEYWORD"

# 清理临时文件
rm -f "$TEMP_XML"

log "=========================================="
log "每日论文下载完成 - $(date '+%Y-%m-%d %H:%M:%S')"
log "=========================================="

exit 0
