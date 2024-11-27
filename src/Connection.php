<?php
namespace statshow;

class Connection {
    private $connection;
    public $read_buffer = '';
    private $maxBufferSize = 10240; // 10KB
    private $readChunkSize = 4096;  // 4KB
    private $stats;
    private $startTime;

    public function __construct($connection, Stats $stats) {
        $this->connection = $connection;
        $this->stats = $stats;
        $this->startTime = microtime(true);
        
        if (!is_resource($connection)) {
            throw new \RuntimeException("Invalid connection resource");
        }
        
        stream_set_blocking($connection, 0);
        stream_set_read_buffer($connection, 0);

        Reactor::getInstance()->add($connection, Reactor::READ, [$this, 'handleRead']);
    }

    public function handleRead() {
        try {
            // 读取数据
            $data = fread($this->connection, $this->readChunkSize);
            if ($data === false || $data === '') {
                if (feof($this->connection)) {
                    $this->close();
                }
                return;
            }
            
            $this->read_buffer .= $data;
            
            // 检查是否收到完整的 HTTP 请求
            if (strpos($this->read_buffer, "\r\n\r\n") !== false) {
                $this->processRequest();
            }
            
        } catch (\Exception $e) {
            error_log("Error in handleRead: " . $e->getMessage());
            $this->close();
        }
    }

    private function processRequest() {
        try {
            $parser = new HttpRequest();
            $parser->parse($this->read_buffer);
            
            $method = $parser->getMethod();
            $path = $parser->getPath();
            $ip = $this->getClientIp();
            
            error_log("Processing request: $method $path");
            
            // 创建响应
            $response = new HttpResponse();
            $response->addHeader('Connection', 'close');
            
            if ($path === StatShow::$webpath) {
                // 处理统计页面请求
                $response->setStatusCode(200);
                $response->addHeader('Content-Type', 'text/html; charset=utf-8');
                $template = new Tpl('templates/stats.tpl');
                $response->setBody($template->render());
            } else {
                // 404 响应
                $response->setStatusCode(404);
                $response->setBody('Not Found');
            }
            
            // 发送响应
            $responseStr = $response->build();
            $this->sendResponse($responseStr);
            
            // 记录统计
            $requestTime = (microtime(true) - $this->startTime) * 1000;
            $this->stats->record($method, $path, $ip, $requestTime, $response->getStatusCode());
            
        } catch (\Exception $e) {
            error_log("Error processing request: " . $e->getMessage());
        } finally {
            // 确保连接关闭
            $this->close();
        }
    }

    private function sendResponse($responseStr) {
        if (!is_resource($this->connection)) {
            return;
        }

        try {
            // 设置写超时
            stream_set_timeout($this->connection, 5);
            
            // 写入响应
            $written = fwrite($this->connection, $responseStr);
            if ($written === false) {
                error_log("Failed to write response");
                return;
            }
            
            error_log("Response sent: $written bytes");
            fflush($this->connection);
            
        } catch (\Exception $e) {
            error_log("Error sending response: " . $e->getMessage());
        }
    }

    private function close() {
        if (!is_resource($this->connection)) {
            return;
        }

        try {
            error_log("Closing connection");
            fflush($this->connection);
            stream_socket_shutdown($this->connection, STREAM_SHUT_RDWR);
            fclose($this->connection);
            
            // 移除事件监听
            Reactor::getInstance()->remove($this->connection, Reactor::READ);
            
        } catch (\Exception $e) {
            error_log("Error closing connection: " . $e->getMessage());
        }
    }

    private function getClientIp() {
        $info = stream_socket_get_name($this->connection, true);
        return trim(explode(':', $info)[0]);
    }
}
