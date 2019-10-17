<?php

/**
 * <title>
 * 
 * @copyright 2012 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 */

require_once 'elbp_timetable.class.php';

class block_elbp_timetable extends block_base
{
    
    var $TT;
    var $ELBP;
    var $DBC;
    var $blockwww;
    var $blocklang;
    
    public function init()
    {
        global $CFG, $USER;
        $this->title = get_string('timetable', 'block_elbp_timetable');   
        $this->blockwww = $CFG->wwwroot . '/blocks/elbp_timetable/';
        $this->blocklang = get_string_manager()->load_component_strings('block_elbp_timetable', $CFG->lang, true);
        
    }
    
    public function get_content()
    {
        
        global $SITE, $CFG, $COURSE, $USER;
        
        if (!$USER->id){
            return false;
        }
        
        $this->blockwww = $CFG->wwwroot . '/blocks/elbp_timetable/';
        
        try
        {
            $this->TT = \ELBP\Plugins\Plugin::instaniate("elbp_timetable", "/blocks/elbp_timetable/");
            $this->TT->connect();
            $this->TT->loadStudent($USER->id, true);
            $this->ELBP = ELBP\ELBP::instantiate( array("load_plugins" => false) );
            $this->DBC = new ELBP\DB();
        }
        catch (\ELBP\ELBPException $e){
            echo $e->getException();
            exit;
        }
        
        $access = $this->ELBP->getCoursePermissions($COURSE->id);
        
        $this->content = new \stdClass();
        $this->content->footer = '';
        
        // Timetable can't work without it being enabled, as it calls normal methods on object
        if (!$this->TT || !$this->TT->isEnabled()){
            return $this->content;
        }
        
        $dayNumberFormat = $this->TT->getSetting('mis_day_number_format');
        
        if ($dayNumberFormat)
        {
        
            $this->content->text = '';

            $this->content->text .= '<p class="elbp_centre"><small><a href="'.$this->blockwww.'full.php">'.$this->blocklang['showfulltimetable'].'</a></small></p>';

            // Today
            $this->content->text .= '<strong>'.$this->blocklang['today'].'</strong><br>';
            
            $today = date($dayNumberFormat);

            $classes = $this->TT->getClassesByDay( $today );

            foreach( (array)$classes as $class)
            {
                $link = ($class->getCourseID()) ? "<a href='{$CFG->wwwroot}/course/view.php?id={$class->getCourseID()}' target='_blank'>".$class->getDescription()."</a>" : $class->getDescription();
                $this->content->text .= "<span title='".$class->getTextInfo()."'><u>".$class->getStartTime()."-".$class->getEndTime().":</u> {$link}<br></span>";
            }

            $this->content->text .= '<br>';        

            // Tomorrow
            $this->content->text .= '<strong>'.$this->blocklang['tomorrow'].'</strong><br>';

            // Increment day number
            $tommorow = $today + 1;
            
            if ($dayNumberFormat == "N" && $tommorow > 7) $tommorow = 1;
            elseif ($dayNumberFormat == "w" && $tommorow > 6) $tommorow = 0;
            
            $classes = $this->TT->getClassesByDay( $tommorow );

            foreach( (array)$classes as $class)
            {
                $link = ($class->getCourseID()) ? "<a href='{$CFG->wwwroot}/course/view.php?id={$class->getCourseID()}' target='_blank'>".$class->getDescription()."</a>" : $class->getDescription();
                $this->content->text .= "<span title='".$class->getTextInfo()."'><u>".$class->getStartTime()."-".$class->getEndTime().":</u> {$link}<br></span>";
            }
        
        }
        else
        {
            $this->content->text = get_string('err:daynumformat', 'block_elbp_timetable');
        }
        
        if ($access['god']){
            $this->content->text .= '<br><ul class="list"><li><a href="'.$this->blockwww.'config.php"><img src="'.$this->blockwww.'pix/cog.png" alt="Img" class="icon" /> '.get_string('config', 'block_elbp_timetable').'</a></li></ul>';
        }
        
        return $this->content;
        
    }
    
}