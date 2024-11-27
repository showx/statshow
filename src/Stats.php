<?php
namespace statshow;

class Stats {
    private $storage;
    private $config;
    
    public function __construct(Config $config) {
        if (!$config) {
            throw new \InvalidArgumentException('Config object is required');
        }
        $this->config = $config;
        $this->initStorage();
    }
    
    private function initStorage() {
        try {
            // 直接获取完整的存储配置
            $storageConfig = $this->config->get('storage');
            
            // 调试输出
            echo "Storage config: " . print_r($storageConfig, true) . "\n";
            
            if (!is_array($storageConfig)) {
                throw new \RuntimeException('Storage configuration must be an array');
            }
            
            if (!isset($storageConfig['type'])) {
                throw new \RuntimeException('Storage type is not specified');
            }

            if ($storageConfig['type'] !== 'redis') {
                throw new \RuntimeException('Only Redis storage is supported');
            }
            
            if (!isset($storageConfig['redis']) || !is_array($storageConfig['redis'])) {
                throw new \RuntimeException('Redis configuration is missing');
            }
            
            $redisConfig = $storageConfig['redis'];
            
            if (!isset($redisConfig['host']) || !isset($redisConfig['port'])) {
                throw new \RuntimeException('Redis host or port is missing');
            }
            
            $this->storage = new \Redis();
            if (!@$this->storage->connect($redisConfig['host'], $redisConfig['port'])) {
                throw new \RuntimeException('Failed to connect to Redis server');
            }
            
            if (isset($redisConfig['db'])) {
                if (!$this->storage->select($redisConfig['db'])) {
                    throw new \RuntimeException('Failed to select Redis database');
                }
            }
            
            // 测试 Redis 连接
            if (!$this->storage->ping()) {
                throw new \RuntimeException('Redis server is not responding');
            }
            
        } catch (\Exception $e) {
            throw new \RuntimeException('Storage initialization failed: ' . $e->getMessage());
        }
    }
    
    public function record($method, $uri, $ip, $requestTime, $statusCode) {
        $timestamp = time();
        $minute = floor($timestamp / 60) * 60;  // 按分钟统计
        
        // URI统计
        if ($this->config->get('monitor.enable_uri_stats')) {
            $this->recordUriStats($minute, $method, $uri, $requestTime, $statusCode);
        }
        
        // IP统计
        if ($this->config->get('monitor.enable_ip_stats')) {
            $this->recordIpStats($minute, $ip, $uri);
        }
        
        // 慢请求记录
        if ($requestTime > $this->config->get('monitor.slow_request_time')) {
            $this->recordSlowRequest($timestamp, $method, $uri, $ip, $requestTime);
        }
    }
    
    private function recordUriStats($minute, $method, $uri, $requestTime, $statusCode) {
        $key = "stats:uri:{$minute}:{$method}:{$uri}";
        $this->storage->hincrby($key, 'count', 1);
        $this->storage->hincrby($key, 'total_time', $requestTime);
        $this->storage->hincrby($key, "status:{$statusCode}", 1);
        $this->storage->expire($key, 86400); // 保存24小时
    }
    
    private function recordIpStats($minute, $ip, $uri) {
        $key = "stats:ip:{$minute}:{$ip}";
        $this->storage->hincrby($key, 'count', 1);
        $this->storage->hincrby($key, "uri:{$uri}", 1);
        $this->storage->expire($key, 86400);
    }
    
    private function recordSlowRequest($timestamp, $method, $uri, $ip, $requestTime) {
        $key = "stats:slow:{$timestamp}";
        $data = json_encode([
            'method' => $method,
            'uri' => $uri,
            'ip' => $ip,
            'time' => $requestTime
        ]);
        $this->storage->set($key, $data);
        $this->storage->expire($key, 86400);
    }
    
    public function getStats($type = 'uri', $timeRange = 3600) {
        $endTime = time();
        $startTime = $endTime - $timeRange;
        $stats = [];
        
        switch ($type) {
            case 'uri':
                $stats = $this->getUriStats($startTime, $endTime);
                break;
            case 'ip':
                $stats = $this->getIpStats($startTime, $endTime);
                break;
            case 'slow':
                $stats = $this->getSlowRequests($startTime, $endTime);
                break;
        }
        
        return $stats;
    }
    
    private function getUriStats($startTime, $endTime) {
        $stats = [];
        $keys = $this->storage->keys("stats:uri:*");
        
        foreach ($keys as $key) {
            $parts = explode(':', $key);
            $minute = $parts[2];
            
            if ($minute >= $startTime && $minute <= $endTime) {
                $data = $this->storage->hGetAll($key);
                $method = $parts[3];
                $uri = $parts[4];
                
                if (!isset($stats[$uri])) {
                    $stats[$uri] = [
                        'count' => 0,
                        'total_time' => 0
                    ];
                }
                
                $stats[$uri]['count'] += $data['count'] ?? 0;
                $stats[$uri]['total_time'] += $data['total_time'] ?? 0;
            }
        }
        
        // 计算平均响应时间并格式化数据
        $result = [
            'uris' => [],
            'counts' => [],
            'avgTimes' => []
        ];
        
        foreach ($stats as $uri => $data) {
            $result['uris'][] = $uri;
            $result['counts'][] = $data['count'];
            $result['avgTimes'][] = $data['count'] > 0 ? round($data['total_time'] / $data['count'], 2) : 0;
        }
        
        return $result;
    }
    
    private function getIpStats($startTime, $endTime) {
        $stats = [];
        $keys = $this->storage->keys("stats:ip:*");
        
        foreach ($keys as $key) {
            $parts = explode(':', $key);
            $minute = $parts[2];
            
            if ($minute >= $startTime && $minute <= $endTime) {
                $data = $this->storage->hGetAll($key);
                $ip = $parts[3];
                
                if (!isset($stats[$ip])) {
                    $stats[$ip] = 0;
                }
                
                $stats[$ip] += $data['count'] ?? 0;
            }
        }
        
        // 格式化数据
        $result = [];
        foreach ($stats as $ip => $count) {
            $result[] = [
                'ip' => $ip,
                'count' => $count
            ];
        }
        
        // 按请求数降序排序
        usort($result, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return array_slice($result, 0, 10); // 只返回前10个IP
    }
    
    private function getSlowRequests($startTime, $endTime) {
        $result = [];
        $keys = $this->storage->keys("stats:slow:*");
        
        foreach ($keys as $key) {
            $parts = explode(':', $key);
            $timestamp = $parts[2];
            
            if ($timestamp >= $startTime && $timestamp <= $endTime) {
                $data = json_decode($this->storage->get($key), true);
                $result[] = $data;
            }
        }
        
        // 按响应时间降序排序
        usort($result, function($a, $b) {
            return $b['time'] - $a['time'];
        });
        
        return array_slice($result, 0, 20); // 只返回前20个慢请求
    }
} 