<?php
namespace statshow;

class Config {
    public $host = '0.0.0.0';
    public $port = '8081';
    public $webpath = '/bangbangbong1';

    public function setHost($host){
        $this->host = $host;
    }

    public function setPort($port){
        $this->port = $port;
    }

    public function setWebPath($path){
        $this->webpath = $path;
    }

    public function getHost(){
        return $this->host;
    }

    public function getPort(){
        return $this->port;
    }

    public function getWebPath(){
        return $this->webpath;
    }


}
