#!/bin/bash
# 运行所有Agent并监控

cd /home/ztron/flarum/flarum-agents
LOG_FILE="logs/run_all_$(date +%Y%m%d_%H%M%S).log"

echo "========================================" | tee -a "$LOG_FILE"
echo "启动所有Agent - $(date)" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# 停止现有的Agent进程
pkill -f "agent.php --daemon" 2>/dev/null
sleep 1

# 运行文章发布Agent
echo "🤖 [1/3] 启动文章发布Agent..." | tee -a "$LOG_FILE"
try_count=0
while [ $try_count -lt 5 ]; do
    result=$(php agent.php --agent=article_publisher --force 2>&1)
    if echo "$result" | grep -q "✅ 成功"; then
        echo "✅ 文章发布Agent运行成功！" | tee -a "$LOG_FILE"
        echo "$result" | grep -A3 "✅ 成功" | tee -a "$LOG_FILE"
        break
    fi
    try_count=$((try_count + 1))
    echo "  尝试 $try_count/5..." | tee -a "$LOG_FILE"
    sleep 30
done
echo "" | tee -a "$LOG_FILE"

# 运行问题回答Agent
echo "🤖 [2/3] 启动问题回答Agent..." | tee -a "$LOG_FILE"
php agent.php --agent=question_answerer --force 2>&1 | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# 运行专栏作者Agent
echo "🤖 [3/3] 启动专栏作者Agent..." | tee -a "$LOG_FILE"
try_count=0
while [ $try_count -lt 5 ]; do
    result=$(php agent.php --agent=column_writer --force 2>&1)
    if echo "$result" | grep -q "✅ 成功"; then
        echo "✅ 专栏作者Agent运行成功！" | tee -a "$LOG_FILE"
        echo "$result" | grep -A3 "✅ 成功" | tee -a "$LOG_FILE"
        break
    fi
    echo "  尝试 $try_count/5，API可能过载，等待..." | tee -a "$LOG_FILE"
    try_count=$((try_count + 1))
    sleep 30
done
echo "" | tee -a "$LOG_FILE"

# 启动守护进程
echo "🚀 启动守护进程模式..." | tee -a "$LOG_FILE"
nohup php agent.php --daemon > logs/daemon.log 2>&1 &
sleep 2
PID=$(pgrep -f "agent.php --daemon" | head -1)
echo "守护进程已启动，PID: $PID" | tee -a "$LOG_FILE"

echo "" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"
echo "所有Agent已启动！" | tee -a "$LOG_FILE"
echo "日志文件: $LOG_FILE" | tee -a "$LOG_FILE"
echo "查看实时日志: tail -f logs/daemon.log" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"
