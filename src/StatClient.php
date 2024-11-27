<?php
namespace statshow;

class StatClient {
    private $host;
    private $port;
    private $timeout;
    
    public function __construct($host = '127.0.0.1', $port = 8081, $timeout = 1) {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
    }
    
    public function record($method, $uri, $time, $status = 200, $ip = null) {
        try {
            $data = [
                'method' => $method,
                'uri' => $uri,
                'time' => $time,
                'status' => $status,
                'ip' => $ip ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ];
            
            $socket = @stream_socket_client(
                "tcp://{$this->host}:{$this->port}",
                $errno,
                $errstr,
                $this->timeout
            );
            
            if (!$socket) {
                throw new \RuntimeException("Failed to connect: $errstr ($errno)");
            }
            
            fwrite($socket, json_encode($data) . "\n");
            fclose($socket);
            
            return true;
        } catch (\Exception $e) {
            error_log("StatClient error: " . $e->getMessage());
            return false;
        }
    }
} 