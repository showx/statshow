<?php
namespace statshow;
class Reactor {
    public $base;
    public $events = [];
    public $events_read = [];
    public $events_write = [];
    const READ = \Event::READ | \Event::PERSIST;
    const WRITE = \Event::WRITE | \Event::PERSIST;
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

            if($flags == self::READ){
                $this->events_read[$socketid] = $event;
            }elseif($flags == self::WRITE){
                $this->events_write[$socketid] = $event;
            }else{
                // 其它的暂时不考虑
            }
            // $this->events[$socketid] = $event;

            // 超时检测
            // $this->addTimeoutCheck($socket, $event);
        }
        // echo 'add_event:'.$socketid.PHP_EOL;
    }

    public function remove(mixed $socket, $flag){
        $socketid = (int)$socket;
        if($flag == self::READ){
            $this->events_read[$socketid]->del();
            // $this->events_read[$socketid]->free();
            unset($this->events_read[$socketid]);
        }elseif($flag == self::WRITE){
            $this->events_write[$socketid]->del();
            // $this->events_write[$socketid]->free();
            unset($this->events_write[$socketid]);
        }
        
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