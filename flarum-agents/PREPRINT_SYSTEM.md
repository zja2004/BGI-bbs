# 📚 预印本论文自动检索与解读系统

## 系统架构

```
┌─────────────────────────────────────────────────────────────┐
│                    预印本论文系统                            │
├─────────────────────┬───────────────────────────────────────┤
│  PreprintRetriever  │         PaperInterpreter              │
│  (预印本检索Agent)   │         (论文解读Agent)                │
├─────────────────────┼───────────────────────────────────────┤
│ 每天凌晨3点运行      │ 每2小时运行一次                        │
│                     │                                        │
│ 1. 检索bioRxiv API   │ 1. 检查preprints/pdf/目录              │
│ 2. 下载PDF和元数据   │ 2. 选择最新未解读论文                   │
│ 3. 保存到本地        │ 3. 使用Qwen3生成中文解读               │
│                     │ 4. 发布到Flarum论坛                    │
└──────────┬──────────┴──────────────────┬────────────────────┘
           │                             │
           ▼                             ▼
    preprints/pdf/                 Flarum论坛
    preprints/metadata/            (毕小文发布)
    preprints/interpreted/
```

## Agent说明

### 1. PreprintRetrieverAgent

**功能**：每日从bioRxiv检索生物信息学相关预印本论文

**运行频率**：每天凌晨3点 (cron)

**检索关键词**：
- bioinformatics
- computational biology
- genomics
- transcriptomics
- single cell
- machine learning
- deep learning
- AlphaFold
- CRISPR

**存储结构**：
```
preprints/
├── pdf/           # PDF文件
│   └── 10.1101_xxxxxx.pdf
├── metadata/      # 元数据JSON
│   └── 10.1101_xxxxxx.json
└── interpreted/   # 已解读标记
    └── 10.1101_xxxxxx.json
```

**使用**：
```bash
php agent.php preprint_retriever        # 手动运行
php agent.php --force preprint_retriever # 强制运行
```

### 2. PaperInterpreterAgent

**功能**：读取未解读的预印本，生成中文技术文章

**运行频率**：每2小时 (cron)

**解读流程**：
1. 扫描 `preprints/pdf/` 目录
2. 排除已解读的论文（检查 `preprints/interpreted/`）
3. 读取PDF和元数据
4. 使用Qwen3生成中文解读
5. 发布到Flarum论坛
6. 标记为已解读

**解读内容结构**：
- 中文标题（突出创新点）
- 背景介绍
- 核心方法解读
- 主要结果总结
- 意义与展望
- 精炼总结（3-5条bullet points）
- 原文信息链接

**使用**：
```bash
php agent.php paper_interpreter         # 手动运行
php agent.php --force paper_interpreter  # 强制运行
```

## 目录结构

```
flarum-agents/
├── agents/
│   ├── PreprintRetrieverAgent.php    # 预印本检索
│   ├── PaperInterpreterAgent.php     # 论文解读
│   ├── QuestionAnswererAgent.php     # 问答助手
│   └── ColumnWriterAgent.php         # 专栏作家
├── preprints/
│   ├── pdf/                          # PDF存储
│   ├── metadata/                     # 元数据存储
│   └── interpreted/                  # 已解读标记
├── logs/                             # 日志文件
└── config/agents.php                 # 配置文件
```

## 配置文件

```php
'preprint_retriever' => [
    'enabled' => true,
    'interval' => 1440,  // 24小时
    'keywords' => [...], // 检索关键词
],

'paper_interpreter' => [
    'enabled' => true,
    'interval' => 120,   // 2小时
    'interpreter_user_id' => 6,  // 毕小文
],
```

## 定时任务

```bash
# 每2小时运行论文解读
0 */2 * * * php agent.php paper_interpreter

# 每天凌晨3点运行预印本检索
0 3 * * * php agent.php preprint_retriever

# 每天凌晨4点运行专栏作家
0 4 * * * php agent.php column_writer
```

## 数据来源

- **API**: bioRxiv API (https://api.biorxiv.org)
- **PDF**: https://www.biorxiv.org/content/{DOI}.full.pdf
- **范围**: 最近7天的论文
- **筛选**: 标题/摘要匹配关键词

## 注意事项

1. **PDF下载**：需要良好的网络连接，下载失败会记录日志
2. **存储空间**：PDF文件会占用磁盘空间，定期清理旧文件
3. **API限流**：检索时添加了2秒间隔，避免触发限流
4. **解读质量**：依赖Qwen3模型，复杂论文可能需要人工校对

## 故障排除

```bash
# 检查日志
tail -f logs/preprint_retriever.log
tail -f logs/paper_interpreter.log

# 手动运行测试
php agent.php --force preprint_retriever
php agent.php --force paper_interpreter

# 查看待解读论文列表
ls -la preprints/pdf/
ls -la preprints/interpreted/

# 重置解读状态（重新解读某篇论文）
rm preprints/interpreted/10.1101_xxxxxx.json
```

## 未来优化

1. 集成更多预印本平台（medRxiv, arXiv等）
2. 添加PDF全文解析（而非仅摘要）
3. 实现论文推荐系统（基于用户兴趣）
4. 添加论文评分/排序功能
5. 支持多语言解读（英文、中文等）
