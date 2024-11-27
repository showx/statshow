<?php
namespace statshow;

class HttpResponse {
    private $statusCode;
    private $headers;
    private $body;

    public function __construct() {
        $this->statusCode = 200;
        $this->headers = [];
        $this->body = '';
    }

    public function getStatusCode() {
        return $this->statusCode;
    }

    public function setStatusCode($code) {
        $this->statusCode = $code;
    }

    public function addHeader($key, $value) {
        $this->headers[$key] = $value;
    }

    public function setBody($body) {
        $this->body = $body;
    }

    public function build() {
        $response = "HTTP/1.1 $this->statusCode " . $this->getStatusCodeText() . "\r\n";

        $this->headers['Content-Length'] = strlen($this->body);

        foreach ($this->headers as $key => $value) {
            $response .= "$key: $value\r\n";
        }

        $response .= "\r\n" . $this->body;

        error_log("Response headers: " . print_r($this->headers, true));
        error_log("Response body length: " . strlen($this->body));

        return $response;
    }

    private function getStatusCodeText() {
        $statusTexts = [
            200 => 'OK',
            404 => 'Not Found',
            500 => 'Internal Server Error'
        ];

        return $statusTexts[$this->statusCode] ?? 'Unknown';
    }
}