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
            // $this->read_buffer .= fread($this->connection, 65535);
            while ($data = fread($this->connection, 4096)) {
                // echo $data.PHP_EOL;
                // 将读取到的数据追加到缓冲区中
                $this->read_buffer .= $data;
                if (strlen($this->read_buffer) >= 10240) {
                    break; 
                }
            }
        }

        // $connection_eof = feof($this->connection);
        // echo "conneciton_eof:".(int)$connection_eof.PHP_EOL;

        // array(7) {
        //     ["timed_out"]=>
        //     bool(false)
        //     ["blocked"]=>
        //     bool(false)
        //     ["eof"]=>
        //     bool(false)
        //     ["stream_type"]=>
        //     string(14) "tcp_socket/ssl"
        //     ["mode"]=>
        //     string(2) "r+"
        //     ["unread_bytes"]=>
        //     int(0)
        //     ["seekable"]=>
        //     bool(false)
        //   }
        // $meta = stream_get_meta_data($this->connection);
        // echo "meta:".var_dump($meta);
        // echo "meta:".$meta['unread_bytes'].PHP_EOL;
        // if($meta['unread_bytes'] != 0){
        //     echo 'no';exit();
        // }

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
                $bytes_written = fwrite($connection, $response_send);
                if ($bytes_written !== false) {
                    echo "Data sent successfully. $bytes_written bytes written.";
                } else {
                    Reactor::getInstance()->base->exit();
                    echo "Failed to send data.";exit();
                }
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

        if($flag == Reactor::READ){
            
        }

        Reactor::getInstance()->remove($connection, $flag); // 移除事件监听
    }
}
