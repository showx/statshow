<?php
namespace statshow;
class HttpRequest {
    private $method;
    private $path;
    private $headers;
    private $body;

    public function parse($request) {
        error_log("Parsing HTTP request:\n" . $request);
        $this->headers = [];
        $this->body = '';

        $requestLines = explode("\r\n", $request);
        $this->parseRequestLine(array_shift($requestLines));

        $headerEnded = false;
        foreach ($requestLines as $line) {
            if ($headerEnded) {
                $this->body .= $line . "\r\n"; // 将请求主体信息拼接
            } else {
                if ($line === '') {
                    $headerEnded = true; // 标记头部结束，开始解析请求主体
                } else {
                    list($key, $value) = explode(': ', $line);
                    $this->headers[$key] = $value;
                }
            }
        }
    }

    private function parseRequestLine($requestLine) {
        $parts = explode(' ', $requestLine);
        $this->method = $parts[0];
        $this->path = $parts[1];
    }

    public function getMethod() {
        return $this->method;
    }

    public function getPath() {
        return $this->path;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function getBody() {
        return $this->body;
    }
}
