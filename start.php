<?php
require dirname(__FILE__).'/vendor/autoload.php';

// 1. 检查扩展
if (!extension_loaded('event')) {
    die("Error: Event extension is required\n");
}

if (!extension_loaded('redis')) {
    die("Error: Redis extension is required\n");
}

// 2. 基本配置
$config = new \statshow\Config([
    'host' => '0.0.0.0',
    'port' => 8081,
    'webpath' => '/stats',
    'max_connections' => 1000,
    'storage' => [
        'type' => 'redis',
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'db' => 0
        ]
    ],
    'monitor' => [
        'enable_uri_stats' => true,
        'enable_ip_stats' => true,
        'slow_request_time' => 1000,
        'stats_interval' => 60
    ]
]);

try {
    // 3. 简单的 Redis 连接测试
    echo "Testing Redis connection...\n";
    $redis = new \Redis();
    if (!@$redis->connect('127.0.0.1', 6379)) {
        die("Error: Cannot connect to Redis server. Please check if Redis is running.\n");
    }
    $redis->close();
    echo "Redis connection OK\n";

    // 4. 启动服务器
    echo "Starting server...\n";
    $server = new \statshow\StatShow();
    $server->setConfig($config);
    $server->start();
} catch (\Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}