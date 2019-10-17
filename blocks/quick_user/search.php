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

/**
 * AJAX search for quick user block
 *
 * @package    block_quick_user
 * @copyright  2016 Conn Warwicker <conn@cmrwarwicker.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../config.php';
require_once 'locallib.php';

// Is ELBP installed and visible?
$elbp = ($DB->get_record("block", array("name" => "elbp", "visible" => 1))) ? true : false;

require_login();

$search = required_param('search', PARAM_TEXT);
$courseID = required_param('course', PARAM_INT);

if ($search == ''){
    exit;
}

// Check valid course
$course = get_course($courseID);
if (!$course){
    exit;
}

$context = context_course::instance($COURSE->id);
if (!has_capability('block/quick_user:search', $context)){
    exit;
}

$PAGE->set_context($context);
        
$output = "";

// Exact Results
$output .= "<p class='quick_user_centre quick_user_bold'>".get_string('exactresults', 'block_quick_user')."</p>";

$results = $DB->get_records_select("user", "(username = ? OR idnumber = ? OR ".$DB->sql_concat("firstname", "' '", "lastname")." = ?) AND deleted = 0", array($search, $search, $search), "lastname ASC, firstname ASC, username ASC", "*", 0, 101);

if (!$results){
    $output .= "<em>".get_string('noresults', 'block_quick_user')."...</em>";
} else {
    
    $n = 0;
    
    foreach($results as $result)
    {
        
        if ($n >= 100){
            break;
        }
        
        $output .= block_quick_user_get_user_info($result, $courseID);        
        $n++;
        
    }
    
    // if more
    if (count($results) > 100){
        $output .= "<p class='quick_user_centre'><small>".get_string('moreresults', 'block_quick_user')."</small></p>";
    }
    
}


$output .= "<br><br>";


// Similar Results
$output .= "<p class='quick_user_centre quick_user_bold'>".get_string('similarresults', 'block_quick_user')."</p>";

$results = $DB->get_records_select("user", "(".$DB->sql_like("username", "?", false, false)." OR ".$DB->sql_like( $DB->sql_concat("firstname", "' '", "lastname"), "?", false, false ).") AND (username != ? AND ".$DB->sql_concat("firstname", "' '", "lastname")." != ?) AND deleted = 0", array('%'.$search.'%', '%'.$search.'%', $search, $search), "lastname ASC, firstname ASC, username ASC", "*", 0, 101);
if (!$results){
    $output .= "<em>".get_string('noresults', 'block_quick_user')."...</em>";
} else {
    
    $n = 0;
    
    foreach($results as $result)
    {
        
        if ($n >= 100){
            break;
        }
       
        $output .= block_quick_user_get_user_info($result, $courseID);        
        
        $n++;
        
    }
    
    if (count($results) > 100){
        $output .= "<p class='quick_user_centre'><small>".get_string('moreresults', 'block_quick_user')."</small></p>";
    }
    
}

echo $output;
exit;