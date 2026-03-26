# 每日生物信息学论文自动下载与解读系统

## 系统概述

本系统实现了全自动化的生物信息学论文获取和解读流程：

- **每天凌晨3:00** - 自动从arXiv下载15篇最新生物信息学论文
- **每2小时** - 自动深度解读并发布一篇论文到论坛

## 工作流程

每天凌晨3点 → 下载15篇论文 → 保存到队列 → 每2小时解读一篇 → 发布到论坛

## 定时任务

```
# 每天凌晨3点下载15篇论文
0 3 * * * ./scripts/daily_paper_downloader.sh

# 每2小时解读一篇论文  
0 */2 * * * php agent.php daily_paper_interpreter
```

## 手动操作

```bash
# 立即下载论文（测试）
cd /home/ztron/flarum/flarum-agents
./scripts/daily_paper_downloader.sh

# 立即解读一篇论文（测试）
php agent.php daily_paper_interpreter

# 查看Agent状态
php agent.php --status

# 查看Agent列表
php agent.php --list
```

## 文件位置

- 论文PDF: `preprints/daily_papers/YYYYMMDD/`
- 论文队列: `preprints/paper_queue.json`
- 下载日志: `logs/daily_downloader.log`
- 解读日志: `logs/daily_interpreter_cron.log`

## 监控日志

```bash
tail -f logs/daily_downloader.log
tail -f logs/daily_interpreter_cron.log
```
