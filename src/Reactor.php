<?php
namespace statshow;

class Reactor {
    public $base;
    public $events_read = [];
    public $events_write = [];
    const READ = 2;
    const WRITE = 4;
    const LOOP_ONCE = 1;
    protected static $instance = null;
    private $connectionCloseCallbacks = [];
    private $errorHandler;
    private $resources = [];

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        if (!extension_loaded('event')) {
            throw new \RuntimeException('Event extension is required');
        }
        
        $this->base = new \EventBase();
        
        $this->errorHandler = function($errno, $errstr) {
            error_log("Event error: ($errno) $errstr");
        };
    }

    public function setErrorHandler(callable $handler) {
        $this->errorHandler = $handler;
    }

    public function add(mixed $socket, int $flags, callable $callback) {
        try {
            $wrappedCallback = function($fd, $what, $arg) use ($callback) {
                try {
                    call_user_func($callback, $fd, $what, $arg);
                } catch (\Throwable $e) {
                    call_user_func($this->errorHandler, $e->getCode(), $e->getMessage());
                    if (is_resource($fd)) {
                        @fclose($fd);
                    }
                    $this->remove($fd, $what);
                }
            };

            $event = new \Event($this->base, $socket, $flags, $wrappedCallback);
            if (!$event || !$event->add()) {
                throw new \RuntimeException("Failed to create or add event");
            }
            
            $socketId = (int)$socket;
            if ($flags == self::READ) {
                $this->events_read[$socketId] = $event;
                $this->resources[$socketId] = $socket;
            } elseif ($flags == self::WRITE) {
                $this->events_write[$socketId] = $event;
                $this->resources[$socketId] = $socket;
            }
            
        } catch (\Exception $e) {
            call_user_func($this->errorHandler, $e->getCode(), $e->getMessage());
            if (is_resource($socket)) {
                @fclose($socket);
            }
        }
    }

    public function remove(mixed $socket, $flag) {
        $socketId = (int)$socket;
        
        try {
            if ($flag == self::READ && isset($this->events_read[$socketId])) {
                $this->events_read[$socketId]->del();
                unset($this->events_read[$socketId]);
                unset($this->resources[$socketId]);
            } elseif ($flag == self::WRITE && isset($this->events_write[$socketId])) {
                $this->events_write[$socketId]->del();
                unset($this->events_write[$socketId]);
                unset($this->resources[$socketId]);
            }
            
            if (isset($this->connectionCloseCallbacks[$socketId])) {
                call_user_func($this->connectionCloseCallbacks[$socketId]);
                unset($this->connectionCloseCallbacks[$socketId]);
            }
        } catch (\Exception $e) {
            call_user_func($this->errorHandler, $e->getCode(), $e->getMessage());
        }
    }

    public function loop() {
        try {
            while (true) {
                $result = $this->base->loop(self::LOOP_ONCE);
                if ($result === false) {
                    break;
                }
                
                $this->processTimeouts();
                usleep(1000); // 1ms 避免CPU占用过高
            }
        } catch (\Exception $e) {
            call_user_func($this->errorHandler, $e->getCode(), $e->getMessage());
        }
    }

    private function processTimeouts() {
        foreach ($this->events_read as $socketId => $event) {
            if (isset($this->resources[$socketId]) && !is_resource($this->resources[$socketId])) {
                $this->remove($this->resources[$socketId], self::READ);
            }
        }
    }

    public function addConnectionCloseCallback($connectionId, callable $callback) {
        $this->connectionCloseCallbacks[$connectionId] = $callback;
    }
}