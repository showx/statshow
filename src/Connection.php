<?php
namespace statshow;

class Connection {
    private $connection;
    public $read_buffer = '';

    public function __construct($connection) {
        $this->connection = $connection;
        stream_set_blocking($this->connection, 0);
        stream_set_read_buffer($this->connection, 0);

        Reactor::getInstance()->add($this->connection, Reactor::READ, function ($connection) {
            $this->handleRead();
        });
    }

    private function handleRead() {
        if (is_resource($this->connection)) {
            // 一般4096就够
            // 不允许发送过长，主要不接收完，会不会触发不了write事件
            $this->read_buffer .= fread($this->connection, 65535);

            // while ($content = fread($this->connection, 65535)) {
            //     $this->read_buffer .= $content;
            // }
        }
        // echo "read:".$read_buffer.PHP_EOL."XXXXXXXX".PHP_EOL;
        if (!empty($this->read_buffer)) {
            echo 'read'.PHP_EOL;
            // 这里
            // echo "read:".$read_buffer . PHP_EOL;
            // 解析 JSON
            $jsondata = json_decode($this->read_buffer, true);
            $response_send = 'ok';
            if ($jsondata !== null) {
                // tcp表示已经接收成功
                $response_send = "ok";
            } else {
                $parser = new HttpRequest();
                $parser->parse($this->read_buffer);
                
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
                        $template = new Tpl('welcome.tpl');
                        $variables = [
                            'time' => time()
                        ];
                        
                        $body = $template->render($variables);
                        $response->setBody($body);
                        $http_response =  $response->build();
                        $response_send = $http_response;
    
                    }
                }
            }
            Reactor::getInstance()->add($this->connection, Reactor::WRITE, function ($connection) use ($response_send) {
                echo "write".PHP_EOL;
                fwrite($connection, $response_send);
                fclose($connection);
                $this->closeConnection($connection, Reactor::WRITE);
            });
            $this->closeConnection($this->connection, Reactor::READ);
            
        } else {
            // var_dump($this->connection);
            echo 'E_close'.PHP_EOL;
            // 处理连接关闭
            fclose($this->connection);
            $this->closeConnection($this->connection, Reactor::READ);
            // Reactor::getInstance()->base->exit();

        }
    }
    private function closeConnection($connection, $flag) {
        echo 'close|'.$flag.PHP_EOL;
        // if($flag == Reactor::READ){
        //     fclose($connection);
        // }
        Reactor::getInstance()->remove($connection, $flag); // 移除事件监听
    }
}
