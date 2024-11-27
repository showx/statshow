# StatShow

StatShow 是一个轻量级的 HTTP 请求监控统计工具,可以帮助您实时监控和分析 API 的调用情况。

## 功能特点

- 实时监控 HTTP 请求
- URI 访问统计(请求次数和响应时间)
- IP 访问统计
- 慢请求记录
- 可视化数据展示
- 支持多种时间范围查看
- 自动数据更新

## 系统要求

- PHP >= 7.1
- Redis 服务器
- PHP 扩展:
  - redis
  - event
  - pcntl
  - posix
  - json
  - sockets (可选，用于设置额外的socket选项)

## 安装

### 1. 安装PHP扩展

```bash
# Ubuntu/Debian
sudo apt-get install php-redis php-event php-pcntl php-sockets

# CentOS/RHEL
sudo yum install php-redis php-event php-process php-sockets

# 或通过PECL安装
sudo pecl install redis event
```

### 2. 通过 Composer 安装

```bash
composer require showx/statshow
```

### 手动安装

1. 克隆代码:
```bash
git clone https://github.com/showx/statshow.git
```

2. 安装依赖:
```bash
cd statshow
composer install
```

## 配置

创建配置文件 `config.php`:

```php
<?php
use statshow\Config;

$config = new Config([
    'host' => '0.0.0.0',      // 监听地址
    'port' => 8081,           // 监听端口
    'webpath' => '/stats',    // Web界面路径
    'monitor' => [
        'enable_uri_stats' => true,     // 启用URI统计
        'enable_ip_stats' => true,      // 启用IP统计
        'slow_request_time' => 1000,    // 慢请求阈值(毫秒)
        'stats_interval' => 60,         // 统计间隔(秒)
    ],
    'storage' => [
        'type' => 'redis',
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'db' => 0
        ]
    ]
]);
```

## 使用方法

### 1. 启动服务器

```php
<?php
require 'vendor/autoload.php';

use statshow\StatShow;
use statshow\Config;

// 加载配置
$config = require 'config.php';

// 创建服务器实例
$server = new StatShow();
$server->setConfig($config);

// 启动服务器
$server->start();
```

### 2. 后台运行

```bash
php start.php -d
```

### 3. 访问监控界面

打开浏览器访问: `http://your-server:8081/stats`

## 监控数据说明

### URI 统计
- 显示各 URI 的访问次数
- 平均响应时间
- 支持按时间范围筛选

### IP 统计
- 显示访问量最大的IP
- 每个IP的请求分布
- 支持查看详细访问记录

### 慢请求统计
- 记录响应时间超过阈值的请求
- 包含完整的请求信息
- 按响应时间排序

## API 接口

### 获取 URI 统计
```
GET /stats/uri?timeRange=3600
```

### 获取 IP 统计
```
GET /stats/ip?timeRange=3600
```

### 获取慢请求记录
```
GET /stats/slow?timeRange=3600
```

参数说明:
- timeRange: 时间范围(秒),默认3600

## 注意事项

1. 确保 Redis 服务器已启动
2. 建议在生产环境使用 supervisor 等工具管理进程
3. 统计数据默认保存24小时
4. 建议定期清理 Redis 数据,避免占用过多内存

## License

MIT License

## 作者

showx <9448923@qq.com>