# 阿里 CDN (闲鱼) 图床 (alicdn-imgbed)

这是一个基于“闲鱼创作者平台”接口的轻量级 PHP 图床程序。通过调用闲鱼上传 API，利用阿里 CDN 实现高速稳定的图片存储与分发。

上游参考：`goofish_img`

## 🌟 特性

- **高速稳定**：依托阿里 CDN 体系（闲鱼创作者平台接口）作为底层图床。
- **丰富的管理功能**：
  - 支持多图上传及快速复制链接。
  - 内置画廊 (`gallery.html`) 用于查看已上传的历史记录。
  - 支持后台图片管理与分类检索 (`manage.php`, `categories.php`)。
- **高可用与安全**：
  - 支持设置请求超时、失败重连重试。
  - 内置速率限制 (Rate Limit) 与 IP 白名单选项。
  - 完善的日志记录 (`logs/upload.log`) 与缓存机制 (`cache/`)。
- **多格式支持**：支持 JPG、PNG、GIF、WebP 等常见 Web 图片格式，单张最大支持 50MB。

## 🚀 部署与使用

### 环境要求

- PHP >= 7.2
- 安装并开启 cURL 扩展

### 1. 获取 Cookie

1. 使用电脑版浏览器访问 [闲鱼创作者平台](https://author.goofish.com/#/) 并登录您的账号。
2. 按 `F12` 打开开发者工具，切换到 **Network (网络)** 或 **Application (应用)** 面板。
3. 在 Cookie 中寻找名为 `cookie2` 的字段，复制它的值。

### 2. 配置程序

1. 将本仓库所有文件上传至你的 PHP 网站目录下。
2. 在前端页面的设置功能中设置

### 3. 可选配置 (config.php)

在 `config.php` 中还可以调整更多核心参数：
- `MAX_FILE_SIZE`: 限制最大上传大小
- `ENABLE_RATE_LIMIT`: 是否开启请求频率限制（防刷）
- `ENABLE_LOGGING`: 是否开启上传日志记录
- HTTP TCP 参数与重试参数等

## 📚 目录结构

```text
├── index.html        # 前台上传主页面
├── gallery.html      # 画廊展示页面
├── manage.php        # 图片管理页面
├── upload.php        # 核心上传接口处理
├── config.php        # 全局核心配置文件
├── set_cookie.php    # Cookie2 设置接口
├── categories.php    # 分类管理接口
├── categories.json   # 分类存储数据
├── gallery.json      # 已上传图片记录清单
└── logs/             # 系统日志目录
```

## 📜 协议声明

本项目遵循 [MIT License](LICENSE) 协议，你可以自由修改、分发及商用。
请勿将本程序用于违法违规图像的存储与分发，用户上传产生的一切法律责任由使用者自行承担，与本项目及阿里/闲鱼官方无关。

