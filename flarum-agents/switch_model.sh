#!/bin/bash
# 模型切换脚本

cd /home/ztron/flarum/flarum-agents

echo "=== Flarum AI Agents - 模型切换 ==="
echo ""
echo "请选择要使用的AI模型:"
echo "1) Kimi (Moonshot) - 官方API，联网搜索能力强"
echo "2) Qwen3-235B-A22B - 本地部署，速度快"
echo ""
read -p "请输入选项 (1或2): " choice

case $choice in
    1)
        echo "切换到 Kimi..."
        if [ -f "config/agents.php.bak" ]; then
            cp config/agents.php config/agents.php.qwen3
            cp config/agents.php.bak config/agents.php
            echo "✅ 已切换到 Kimi"
        else
            echo "⚠️  备份文件不存在，当前配置保持不变"
        fi
        ;;
    2)
        echo "切换到 Qwen3-235B-A22B..."
        # 备份当前配置
        if [ ! -f "config/agents.php.bak" ]; then
            cp config/agents.php config/agents.php.bak
        fi
        
        # 创建Qwen3配置
        cat > config/agents.php << 'EOFPHP'
<?php
/**
 * Flarum AI Agents 配置文件
 * 使用模型: Qwen3-235B-A22B
 */

return [
    'global' => [
        'flarum' => [
            'base_url' => 'https://172.16.218.40',
            'api_key' => '29cdb349-eaa5-2a55-be53-6f875d154114',
            'auth_token' => null,
        ],
        'ai' => [
            // Qwen3-235B-A22B 配置
            'api_key' => 'dummy-key',
            'model' => 'Qwen3-235B-A22B',
            'base_url' => 'http://172.16.224.137:1024/v1',
            'temperature' => 0.3,
            'max_tokens' => 4000,
        ],
    ],
    'article_publisher' => [
        'enabled' => true,
        'interval' => 30,
        'publisher_user_id' => 6,
        'fields' => [
            ['name' => '人工智能', 'tags' => ['AI', '技术'], 'topics' => ['最新AI突破与应用', '大模型技术演进', 'AI安全与伦理', '机器学习实践案例']],
            ['name' => '区块链与Web3', 'tags' => ['区块链', 'Web3'], 'topics' => ['DeFi发展趋势分析', 'NFT市场洞察', '区块链技术应用', '数字资产管理']],
            ['name' => '商业与管理', 'tags' => ['商业', '管理'], 'topics' => ['创业实战经验分享', '企业管理最佳实践', '市场趋势深度分析', '领导力与团队建设']],
            ['name' => '编程与开发', 'tags' => ['编程', '开发'], 'topics' => ['新框架与技术趋势', '代码质量与最佳实践', '性能优化技巧', '开源项目推荐']],
            ['name' => '产品与设计', 'tags' => ['产品', '设计'], 'topics' => ['用户体验设计方法', '产品思维与实践', '设计趋势分析', '产品案例研究']],
            ['name' => '科学与探索', 'tags' => ['科学', '探索'], 'topics' => ['前沿科学发现', '太空探索进展', '医学与健康研究', '环境与可持续发展']],
        ],
    ],
    'question_answerer' => [
        'enabled' => true,
        'interval' => 30,
        'answerer_user_id' => 7,
        'max_answers_per_run' => 1,
        'min_answer_length' => 100,
        'avoid_types' => ['个人决策', '主观选择', '隐私相关', '法律咨询', '医疗诊断', '投资建议'],
    ],
    'column_writer' => [
        'enabled' => true,
        'interval' => 60,
        'writer_user_id' => 8,
        'mode' => 'draft_for_review',
        'columns' => [
            ['id' => 'tech-deep-dive', 'name' => '技术深度解析', 'description' => '深入探讨前沿技术原理与应用', 'tags' => ['技术', '深度'], 'style' => 'technical', 'word_count' => 3000],
            ['id' => 'industry-analysis', 'name' => '行业观察', 'description' => '分析行业趋势与商业洞察', 'tags' => ['商业', '分析'], 'style' => 'business', 'word_count' => 2500],
            ['id' => 'research-spotlight', 'name' => '研究前沿', 'description' => '解读最新学术论文与研究成果', 'tags' => ['学术', '研究'], 'style' => 'academic', 'word_count' => 3500],
            ['id' => 'practical-guide', 'name' => '实战指南', 'description' => '实用的方法论与操作指南', 'tags' => ['实战', '教程'], 'style' => 'practical', 'word_count' => 2800],
            ['id' => 'thought-leadership', 'name' => '观点洞察', 'description' => '对行业热点的深度思考', 'tags' => ['观点', '思考'], 'style' => 'opinion', 'word_count' => 2200],
        ],
    ],
];
EOFPHP
        echo "✅ 已切换到 Qwen3-235B-A22B"
        ;;
    *)
        echo "❌ 无效选项"
        exit 1
        ;;
esac

echo ""
echo "当前配置:"
grep "'model'" config/agents.php | head -1
echo ""
echo "提示: 请重启Agent进程以应用新配置"
