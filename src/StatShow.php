<?php
namespace statshow;
define("STATPATH", dirname(__FILE__));
class StatShow {
    private $host;
    private $port;
    private $socket = null;
    public $config = null;
    public static $webpath = '/bangbangbong1';
    private $connections = 0; // 初始化为 0

    public function __construct($host = '0.0.0.0', $port = 8081) {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     *  注入配置
     */
    public function setConfig(Config $config){
        $this->config = $config;
    }

    public function start() {

        if($this->config){
            $this->host = $this->config->getHost();
            $this->port = $this->config->getPort();
            self::$webpath = $this->config->getWebPath();
        }

        cli_set_process_title("statshow");
        global $argv;
        if(in_array("-d", $argv)){
            $this->daemonize();
        }
        $this->socket = stream_socket_server("tcp://$this->host:$this->port", $errno, $errstr);
        if($errno != 0){
            die("socket创建失败");
        }
        $socket = socket_import_stream($this->socket);
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
        socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        stream_set_blocking($this->socket, 0);
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        Reactor::getInstance()->add($this->socket, \Event::READ | \Event::PERSIST, function($socket) {
            $connection = stream_socket_accept($socket);
            if ($connection) {
                $this->handleConnection($connection);
            }
        });
        Reactor::getInstance()->loop();
    }

    private function handleConnection($connection) {
        $conn = new Connection($connection);
        unset($conn);
        // $this->connections[] = $conn;
        $this->connections++;
        echo "现在的连接数：".$this->connections.PHP_EOL;
        echo "可读事件总数：".count(Reactor::getInstance()->events_read).PHP_EOL;
        echo "可写事件总数：".count(Reactor::getInstance()->events_write).PHP_EOL;
        // var_dump(Reactor::getInstance()->events);
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
