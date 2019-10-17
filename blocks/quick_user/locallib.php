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
 * Script called by AJAX to return search results
 * 
 * @copyright 04-Jul-2013
 * @package DuckFusion
 * @version 1
 * @author Conn Warwicker <conn@cmrwarwicker.com>
 */

defined('MOODLE_INTERNAL') || die();

function block_quick_user_get_user_info($user, $courseID)
{
    
    global $CFG, $OUTPUT, $USER, $ELBP;
    
    $return = "";
    
    // Get their name, username and last access
    $title = fullname($user) . 
            ( (has_capability('moodle/user:editprofile', context_user::instance($user->id))) ? " ({$user->username})" : "" ) . 
            " ";
    $title .= get_string('lastaccess', 'block_quick_user') . ": " . format_time(time() - $user->lastaccess) . " " . get_string('ago', 'block_quick_user');
    
    $return .= $OUTPUT->user_picture($user, array("courseid" => $courseID, "size" => 20));
    $return .= "<a href='{$CFG->wwwroot}/user/profile.php?id={$user->id}' target='_blank' title='{$title}'>".fullname($user)."</a><br>";
    
    $context = context_course::instance($courseID);
    
    $return .= "<div class='quick_user_centre'>";

    // Loginas
    if (has_capability('moodle/user:loginas', $context) && $user->id <> $USER->id && !is_siteadmin($user->id)){
        $return .= "<a href='{$CFG->wwwroot}/course/loginas.php?id={$courseID}&user={$user->id}&sesskey=".sesskey()."' title='".get_string('loginas')." ".fullname($user)."'><img src='".$OUTPUT->image_url('t/lock')."' /></a>&nbsp;&nbsp;&nbsp;&nbsp;";
    }

    // Message
    $return .= "<a href='{$CFG->wwwroot}/message/index.php?id={$user->id}' title='".get_string('sendmessage', 'block_quick_user')." ".fullname($user)."' target='_blank'><img src='".$OUTPUT->image_url('t/messages')."' /></a>&nbsp;&nbsp;&nbsp;&nbsp;";

    // ELBP block installed?
    if ( $ELBP ){
        $return .= "<a href='{$CFG->wwwroot}/blocks/elbp/view.php?id={$user->id}' title='".get_string('viewelbp', 'block_elbp')." ".fullname($user)."' target='_blank'><img src='".$OUTPUT->image_url('t/user')."' /></a>&nbsp;&nbsp;&nbsp;&nbsp;";
    }

    $return .= "</div>";
                
    $return .= "<br>";
    
    return $return;
    
}

