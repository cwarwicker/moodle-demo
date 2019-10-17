<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Quick User block
 * *
 * @package    block_quick_user
 * @copyright  2016 Conn Warwicker <conn@cmrwarwicker.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_quick_user extends block_base
{
    
    public function init()
    {
        $this->title = get_string('pluginname', 'block_quick_user');        
    }
    
    public function get_content()
    {
        
        global $CFG, $PAGE, $COURSE;
        
        if ($this->content !== null){
            return $this->content;
        }
                
        $this->content = new stdClass();
        $this->content->text = '';
        
        $context = context_course::instance($COURSE->id);
        
        if (!has_capability('block/quick_user:search', $context)){
            return $this->content;
        }
        
        // Search bar
        $this->content->text .= "<p class='quick_user_centre'><small><a href='#' id='quick_user_clear'>".get_string('clear', 'block_quick_user')."</a></small></p>";
        $this->content->text .= "<div id='quick_user'><form id='quick_user_form' method='post' action=''><input type='text' id='quick_user_search' /></form></div>";
        $this->content->text .= "<br><div id='quick_user_results'></div>";
                
		$this->page->requires->js_call_amd('block_quick_user/module', 'init', array($COURSE->id));
        
        return $this->content;       
        
    }
    
}