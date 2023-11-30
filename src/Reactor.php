<?php
namespace statshow;
class Reactor {
    protected $base;
    public $events = [];
    protected static $instance  = null;

    public static function getInstance(){
        if(is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->base = new \EventBase();
    }

    public function add(mixed $socket, int $flags, callable $callback) {
        $event = new \Event($this->base, $socket, $flags, $callback);
        $status = $event->add();
        if($status){
            $socketid = (int)$socket;
            $this->events[$socketid] = $event;

            // 超时检测
            // $this->addTimeoutCheck($socket, $event);
        }
        // echo 'add_event:'.$socketid.PHP_EOL;
    }

    public function remove(mixed $socket){
        $socketid = (int)$socket;
        $this->events[$socketid]->del();
        $this->events[$socketid]->free();
        unset($this->events[$socketid]);
    }

    private function addTimeoutCheck($socket, $event) {
        // 默认2秒超时
        $timeout = 2;
    
        // 添加定时器检查连接超时
        $timer = \Event::timer($this->base, function () use ($socket) {
            // 关闭超时连接
            $this->remove($socket);
            fclose($socket);
            echo "Connection timed out." . PHP_EOL;
        });
    
        // 设置定时器超时时间
        $timer->add($timeout);
        
        // 关联定时器与事件，以确保事件活动时不会超时关闭连接
        $event->addTimer($timer);
    }

    public function loop() {
        $this->base->loop();
        // $status = $this->base->loop();
        // echo 'loop状态:'.$status.PHP_EOL;
        // 一般来说，loop() 方法执行后返回 1 代表着事件循环正常结束，没有事件需要处理了。
    }
}