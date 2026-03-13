#!/bin/bash
# Agent启动脚本 - 自动重试直到成功

cd /home/ztron/flarum/flarum-agents
echo "========================================"
echo "Flarum AI Agents - 启动脚本"
echo "启动时间: $(date)"
echo "========================================"
echo ""

# 重置状态
php agent.php --reset=all

# 函数：运行Agent直到成功
run_agent() {
    local agent=$1
    local max_attempts=10
    local attempt=1
    
    echo "🤖 启动 $agent..."
    
    while [ $attempt -le $max_attempts ]; do
        echo "  尝试 $attempt/$max_attempts..."
        
        result=$(php agent.php --agent=$agent --force 2>&1)
        
        if echo "$result" | grep -q "✅ 成功"; then
            echo "  ✅ $agent 运行成功！"
            echo "$result" | grep -A5 "✅ 成功"
            return 0
        elif echo "$result" | grep -q "429"; then
            echo "  ⏳ API过载，等待60秒后重试..."
            sleep 60
        else
            echo "  ⚠️ 其他错误，等待30秒后重试..."
            sleep 30
        fi
        
        attempt=$((attempt + 1))
    done
    
    echo "  ❌ $agent 达到最大重试次数"
    return 1
}

# 依次运行三个Agent
run_agent "article_publisher"
echo ""
run_agent "question_answerer"
echo ""
run_agent "column_writer"
echo ""

# 启动守护进程
echo "🚀 启动守护进程模式..."
nohup php agent.php --daemon > logs/daemon.log 2>&1 &
sleep 2

echo ""
echo "========================================"
echo "Agent已启动！"
echo "守护进程PID: $(pgrep -f 'agent.php --daemon')"
echo "查看日志: tail -f logs/daemon.log"
echo "========================================"
