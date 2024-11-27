<?php
namespace statshow;
define("STATPATH", dirname(__FILE__));
class StatShow {
    private $host = '0.0.0.0';
    private $port = 8081;
    private $socket = null;
    public $config = null;
    public static $webpath = '/stats';
    private $connections = 0;
    private $activeConnections = [];
    public $maxConnections = 1000;

    public function __construct($host = null, $port = null) {
        if ($host !== null) {
            $this->host = $host;
        }
        if ($port !== null) {
            $this->port = (int)$port;
        }
    }

    /**
     *  注入配置
     */
    public function setConfig(Config $config) {
        $this->config = $config;
        
        // 明确获取基本配置项
        $host = $config->get('host');
        $port = $config->get('port');
        $webpath = $config->get('webpath');
        
        // 类型检查和转换
        if (!is_string($host)) {
            throw new \RuntimeException('Host must be a string');
        }
        if (!is_numeric($port)) {
            throw new \RuntimeException('Port must be a number');
        }
        if (!is_string($webpath)) {
            throw new \RuntimeException('Webpath must be a string');
        }
        
        $this->host = $host;
        $this->port = (int)$port;
        self::$webpath = $webpath;
        
        // 其他配置项
        $maxConnections = $config->get('max_connections');
        if (is_numeric($maxConnections)) {
            $this->maxConnections = (int)$maxConnections;
        }
    }

    public function start() {
        // 验证配置
        if (empty($this->host) || !is_string($this->host)) {
            throw new \RuntimeException('Invalid host configuration');
        }
        if (empty($this->port) || !is_int($this->port)) {
            throw new \RuntimeException('Invalid port configuration');
        }

        cli_set_process_title("statshow");
        global $argv;
        if (in_array("-d", $argv)) {
            $this->daemonize();
        }

        // 创建服务器socket
        $context = stream_context_create([
            'socket' => [
                'so_reuseaddr' => true,
                'backlog' => 128
            ]
        ]);

        $address = sprintf("tcp://%s:%d", $this->host, $this->port);
        $this->socket = @stream_socket_server(
            $address,
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );
        
        if (!$this->socket) {
            throw new \RuntimeException(
                sprintf("Failed to create server socket: %s (%d)", $errstr, $errno)
            );
        }

        // 设置非阻塞模式
        stream_set_blocking($this->socket, 0);

        // 如果安装了sockets扩展，设置额外的socket选项
        if (extension_loaded('sockets')) {
            $socket = socket_import_stream($this->socket);
            if ($socket) {
                socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
                socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
                socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
            }
        }

        echo sprintf("Server started at %s:%d\n", $this->host, $this->port);

        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        
        Reactor::getInstance()->add($this->socket, Reactor::READ, function($socket) {
            $connection = @stream_socket_accept($socket);
            if ($connection) {
                $this->handleConnection($connection);
            }
        });
        
        Reactor::getInstance()->loop();
    }

    private function handleConnection($connection) {
        try {
            if (!is_resource($connection)) {
                error_log("Invalid connection resource");
                return;
            }

            if (count(Reactor::getInstance()->events_read) >= $this->maxConnections) {
                error_log("连接数已达到最大限制: {$this->maxConnections}");
                fclose($connection);
                return;
            }
            
            $connectionId = (int)$connection;
            $this->activeConnections[$connectionId] = $connection;
            $this->connections++;
            
            try {
                // 确保配置对象存在
                if (!$this->config) {
                    throw new \RuntimeException("Server configuration is not set");
                }
                
                // 创建连接
                $conn = new Connection($connection, new Stats($this->config));
                
                Reactor::getInstance()->addConnectionCloseCallback($connectionId, function() use ($connectionId) {
                    unset($this->activeConnections[$connectionId]);
                    $this->connections--;
                });
            } catch (\Exception $e) {
                error_log("Failed to create connection: " . $e->getMessage());
                fclose($connection);
                unset($this->activeConnections[$connectionId]);
                $this->connections--;
            }
        } catch (\Exception $e) {
            error_log("Error handling connection: " . $e->getMessage());
        }
    }

    public function handleSignal($signo) {
        switch ($signo) {
            case SIGTERM:
                // 处理 SIGTERM 信号的逻辑
                echo "Received SIGTERM signal. Shutting down gracefully...\n";
                // 停止服务器或执行其他关闭操作
                $this->stop();
                break;
            // 可以根据需求处理其他信号
            // case 其他信号:
            //     // 处理其他信号的逻辑
            //     break;
            default:
                // 其他未捕获的信号
                break;
        }
    }

    public function stop() {
        // 停止服务器或执行其他关闭操作的逻辑
        // 例如关闭连接、清理资源等
        exit();
    }

    public function daemonize() {
        $pid = pcntl_fork();
        if ($pid === -1) {
            die("server error");
        } elseif ($pid) {
            exit(); // 结束父进程
        }
        // 创建新会话并设置为领头
        posix_setsid();

        // 忽略 SIGHUP 信号
        // pcntl_signal(SIGHUP, SIG_IGN);

        // 关闭标准 I/O
        fclose(STDIN);
        // fclose(STDOUT);
        // fclose(STDERR);
    }

}
