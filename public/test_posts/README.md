# 论坛测试文档说明

这些文档专为测试论坛的 Markdown 表格和代码高亮功能而设计。

## 文档列表

| 文件名 | 对应板块 | 功能测试 |
|-------|---------|---------|
| 01_ai_driven_dev.md | 核心技术与基础设施 → AI辅助生信开发 | Python代码块、对比表格 |
| 02_protein_design.md | 生成式生物学 → 蛋白/抗体设计 | Python + Bash代码、数据表格 |
| 03_clinical_variant.md | 临床与精准医疗 → 变异解读 | Python + R代码、临床表格 |
| 04_self_driving_lab.md | 湿实验与干实验闭环 → 自动化实验室 | Python + Bash + LIMS集成代码 |
| 05_career_transformation.md | 职业发展 → 技能转型 | Python学习代码、技能矩阵表 |
| 06_ai_agent_showcase.md | AI Agent专区 | 完整项目代码、性能对比表 |

## 使用方法

### 方式1：直接复制内容

1. 访问 https://172.16.218.40/test_posts/01_ai_driven_dev.md
2. 复制文件内容
3. 在论坛创建新讨论，粘贴内容
4. 选择对应的标签（如"AI辅助生信开发"）

### 方式2：命令行复制

```bash
# 在服务器上执行
cat /home/ztron/flarum/public/test_posts/01_ai_driven_dev.md
# 复制输出内容到论坛
```

## 测试重点

### Markdown表格
每个文档都包含多个表格：
- 工具对比表
- 性能基准表
- 实验结果表
- 资源对比表

### 代码高亮
每个文档都包含多种语言的代码块：
- Python (生信分析)
- R (统计分析)
- Bash (流程脚本)
- SQL (数据库查询)

### 内容特点
- 贴合2026年生信AI主题
- 前沿技术（AlphaFold 3、LLM、Agent）
- 真实代码示例
- 专业且详细

## 创建测试帖的建议

1. **AI辅助生信开发** - 复制 01_ai_driven_dev.md
   - 标签：核心技术与基础设施 + AI辅助生信开发

2. **蛋白设计** - 复制 02_protein_design.md
   - 标签：生成式生物学 + 蛋白/抗体设计

3. **临床变异解读** - 复制 03_clinical_variant.md
   - 标签：临床与精准医疗 + 变异解读

4. **自动化实验室** - 复制 04_self_driving_lab.md
   - 标签：湿实验与干实验闭环 + 自动化实验室

5. **职业转型** - 复制 05_career_transformation.md
   - 标签：职业发展 + 生信职业转型

6. **AI Agent展示** - 复制 06_ai_agent_showcase.md
   - 标签：AI Agent专区 + RNA-seq自动化Agent

## 验证功能

发布帖子后，检查：
1. ✅ 表格是否正确显示边框和列对齐
2. ✅ Python代码是否有语法高亮
3. ✅ Bash代码是否有语法高亮
4. ✅ 行内代码是否显示为等宽字体
5. ✅ 标题层级（H1/H2/H3）是否正确

---
*文档由AI助手生成，贴合2026年生信论坛主题*
