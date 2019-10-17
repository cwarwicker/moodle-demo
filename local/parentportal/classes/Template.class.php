<?php

/**
 * Class for HTML templating
 * 
 * @copyright 2012 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 */

namespace PP;

/**
 * 
 */
class Template {
    
    private $variables;
    private $output;
    
    public function __construct() {
        global $CFG, $OUTPUT, $USER;
                
        include $CFG->dirroot . '/local/parentportal/lang/'.$CFG->lang.'/local_parentportal.php';
        $this->string = $string;
        $this->variables = array();
        $this->output = '';
        
        $this->set("string", $this->string);
        $this->set("CFG", $CFG);
        $this->set("OUTPUT", $OUTPUT);
        $this->set("USER", $USER);
    }
    
    
    
    public function set($var, $val)
    {
        $this->variables[$var] = $val;
        return $this;
    }
    
    public function getVars()
    {
        return $this->variables;
    }
    
    public function load($template)
    {
                
        global $CFG;
        
        $this->output = ''; # Reset output
                        
        if (!file_exists($template)) $template = $CFG->dirroot . '/local/parentportal/tpl/404.html';
        if (!empty($this->variables)) extract($this->variables);
                
        flush();
        ob_start();
            include $template;
        $output = ob_get_clean();
        
        $this->output = $output;
        return $this->output;        
        
    }
    
    public function display()
    {
        echo $this->output;
    }
    
}