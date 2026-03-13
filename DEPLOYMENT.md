# 生物信息学AI论坛 - 部署记录

## 项目概述
- **Flarum版本**: 1.8.13
- **PHP版本**: 8.2.30
- **数据库**: MySQL 8.0.45
- **服务器**: Nginx
- **访问地址**: https://172.16.218.40

## 已安装扩展列表

| 扩展名称 | 版本 | 功能描述 |
|---------|------|---------|
| flarum/markdown | v1.8.1 | Markdown支持（已添加PipeTables） |
| askvortsov/markdown-tables | v1.2.1 | Markdown表格支持 |
| flarum-lang/chinese-simplified | v1.6.0 | 简体中文语言包 |
| club-1/flarum-ext-server-side-highlight | v1.4.0 | 代码高亮 |
| tohsakarat/table-of-content | v1.1.1 | 文章目录（左侧显示） |
| nearata/flarum-ext-copy-code-to-clipboard | v2.2.1 | 复制代码按钮 |
| fof/upload | v1.8.11 | 文件上传（30MB，所有类型） |

## 关键配置修改

### 1. Markdown表格修复
修改 `vendor/flarum/markdown/extend.php`，添加 `$config->PipeTables;` 启用GitHub风格表格。

### 2. 目录样式调整（左侧显示，无镜像）
```css
@media (min-width: 992px) {
    .catalog-top {
        left: 20px !important;
        right: auto !important;
        position: fixed !important;
        max-width: 180px !important;
        top: 120px !important;
        transform: none !important; /* 移除镜像 */
    }
    .catalog-top p, .catalog-top a {
        transform: none !important;
        text-align: left !important;
    }
    .App-content {
        margin-left: 220px !important;
    }
}
```

### 3. 文件上传配置
- **大小限制**: 30MB (31457280 bytes)
- **文件类型**: 允许所有类型
- **PHP配置**: upload_max_filesize = 30M, post_max_size = 30M
- **Nginx配置**: client_max_body_size 30M

### 4. 自定义CSS
存储在数据库 `settings.custom_less` 中：
- 强制黑色文字、白色背景
- 表格样式美化
- 复制代码按钮样式
- 目录左侧定位样式

## 标签分类

已创建22个标签，分为6大类：
1. **核心技术与基础设施**: 算法开发、数据库与资源
2. **AI辅助生信开发**: Vibe Coding、脚本自动生成
3. **干/湿实验闭环**: 实验设计、生信验证
4. **生成生物学**: 蛋白质设计、代谢通路
5. **临床生物信息学**: 肿瘤基因组、罕见病
6. **职业发展/学术伦理**: 职业发展、伦理规范

## 常见问题修复记录

### 表格不显示
- **原因**: TextFormatter未配置PipeTables
- **修复**: 修改extend.php添加$config->PipeTables

### 目录镜像文字
- **原因**: 扩展默认使用transform: scale(-1,1)
- **修复**: 添加transform: none !important

### 上传插件安装后报错
- **原因**: 数据库表未创建
- **修复**: 运行php flarum migrate

## 文件备份
备份位置: `/home/ztron/flarum-backup/`
包含数据库SQL和文件tar.gz，附带restore.sh恢复脚本。

## 备注
- 调试模式: debug => true (生产环境建议关闭)
- 存储权限: www-data所有 (storage/cache等目录)
