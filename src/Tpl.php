<?php
namespace statshow;

class Tpl {
    private $template;
    private $variables;

    public function __construct($template) {
        $templatePath = STATPATH . '/' . $template;
        error_log("Loading template from: $templatePath");
        
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: $templatePath");
        }
        
        $content = file_get_contents($templatePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read template file: $templatePath");
        }
        
        $this->template = $content;
        error_log("Template loaded, length: " . strlen($content));
    }

    public function assign($key, $val){
        $this->variables[$key] = $val;
    }

    public function render($variables = []) {
        // 添加默认变量
        $defaultVars = [
            'webpath' => StatShow::$webpath
        ];
        
        $variables = array_merge($defaultVars, $variables);
        
        error_log("Rendering template with variables: " . print_r($variables, true));
        
        $output = $this->template;
        foreach ($variables as $key => $value) {
            $output = str_replace('{{' . $key . '}}', $value, $output);
        }
        
        error_log("Template rendered, length: " . strlen($output));
        return $output;
    }
}
