<?php
namespace statshow;

class Connection {
    private $connection;

    public function __construct($connection) {
        $this->connection = $connection;
        stream_set_blocking($this->connection, 0);

        Reactor::getInstance()->add($this->connection, \Event::READ | \Event::PERSIST, function ($connection) {
            $this->handleRead();
        });
    }

    private function handleRead() {
        $read_buffer = '';
        if (is_resource($this->connection)) {
            // 一般4096就够
            while ($content = fread($this->connection, 65535)) {
                $read_buffer .= $content;
            }
        }
        if (!empty($read_buffer)) {
            // 这里
            // echo "read:".$read_buffer . PHP_EOL;
            // 解析 JSON
            $jsondata = json_decode($read_buffer, true);
            $response_send = 'ok';
            if ($jsondata !== null) {
                // tcp表示已经接收成功
                $response_send = "ok";
            } else {
                $parser = new HttpRequest();
                $parser->parse($read_buffer);
                
                $method = $parser->getMethod();
                $path = $parser->getPath();
                // 看看会不会阻塞, 会阻塞掉
                // sleep(10);
                if($method == 'GET'){
                    if($path == StatShow::$webpath){
                        // 使用示例
                        $response = new HttpResponse();
                        $response->setStatusCode(200);
                        $response->addHeader('Content-Type', 'text/html');
                        $response->setBody('<h1>StatShow</h1><h2>time:'.time().'</h2>');
                        $http_response =  $response->build();
                        $response_send = $http_response;
    
                    }
                }
            }
            Reactor::getInstance()->add($this->connection, \Event::WRITE | \Event::PERSIST, function ($connection) use ($response_send) {
                echo "write".PHP_EOL;
                fwrite($connection, $response_send);
                $this->closeConnection($connection);
            });
            echo 'read'.PHP_EOL;
        } else {
            echo 'E_close'.PHP_EOL;
            // 处理连接关闭
            $this->closeConnection($this->connection);
        }
    }
    private function closeConnection($connection) {
        echo 'close'.PHP_EOL;
        fclose($connection);
        Reactor::getInstance()->remove($connection); // 移除事件监听
    }
}
