<?php
namespace statshow;

class Config {
    private $config = [
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
    ];
    
    public function __construct(array $config = []) {
        $this->config = array_replace_recursive($this->config, $config);
    }
    
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        if (count($keys) === 1 && in_array($key, ['host', 'port', 'webpath'])) {
            return is_scalar($value) ? $value : $default;
        }
        
        return $value;
    }
    
    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
}
