#!/bin/bash
# AI问答助手实时监听器管理脚本

SERVICE_NAME="flarum-ai-listener"
LOG_FILE="/home/ztron/flarum/flarum-agents/runtime/listener.log"
STATE_FILE="/home/ztron/flarum/flarum-agents/runtime/listener_state.json"

case "$1" in
    start)
        echo "🚀 启动实时监听器..."
        sudo systemctl start $SERVICE_NAME
        echo "✅ 已启动"
        ;;
    stop)
        echo "🛑 停止实时监听器..."
        sudo systemctl stop $SERVICE_NAME
        echo "✅ 已停止"
        ;;
    restart)
        echo "🔄 重启实时监听器..."
        sudo systemctl restart $SERVICE_NAME
        echo "✅ 已重启"
        ;;
    status)
        sudo systemctl status $SERVICE_NAME --no-pager
        ;;
    logs)
        echo "=== 最近50行日志 ==="
        tail -50 "$LOG_FILE" 2>/dev/null || echo "暂无日志"
        ;;
    follow)
        echo "=== 实时日志 (按 Ctrl+C 退出) ==="
        tail -f "$LOG_FILE" 2>/dev/null
        ;;
    clear)
        echo "🗑️  清空日志和状态..."
        > "$LOG_FILE" 2>/dev/null
        rm -f "$STATE_FILE" 2>/dev/null
        echo "✅ 已清空"
        ;;
    test)
        echo "🧪 运行一次测试..."
        cd /home/ztron/flarum/flarum-agents
        timeout 10 php listen.php 5 2>&1 | head -20
        ;;
    *)
        echo "用法: $0 {start|stop|restart|status|logs|follow|clear|test}"
        echo ""
        echo "命令说明:"
        echo "  start   - 启动监听器服务"
        echo "  stop    - 停止监听器服务"
        echo "  restart - 重启监听器服务"
        echo "  status  - 查看服务状态"
        echo "  logs    - 查看最新日志"
        echo "  follow  - 实时跟踪日志"
        echo "  clear   - 清空日志和状态"
        echo "  test    - 运行一次测试"
        ;;
esac
