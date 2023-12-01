<?php
namespace statshow;

class Tpl {
    private $template;
    private $variables;

    public function __construct($template) {
        $content = file_get_contents(STATPATH.'/'.$template);
        $this->template = $content;
    }

    public function assign($key, $val){
        $this->variables[$key] = $val;
    }

    public function render($variables = []) {
        $output = $this->template;
        if(!empty($variables)){
            $this->variables = $variables;
        }
        if($this->variables){
            foreach ($this->variables as $key => $value) {
                $output = str_replace('{{' . $key . '}}', $value, $output);
            }
        }
        return $output;
    }
}
