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


// Find all classes in /classes and include them
df_require_classes('classes');


/**
 * Require all the php classes in given directory & sub directories
 * @global type $CFG
 * @param type $dir
 */
function df_require_classes($dir){

    global $CFG;

    // Find sub folders
    if ($open = opendir($CFG->dirroot . '/local/df_hub/' . $dir))
    {
        while ( ($file = readdir($open)) !== false )
        {

            if ($file == '.' || $file == '..') continue;

            if (strpos($file, '.php') === false)
            {
                df_require_classes($dir . '/' . $file);
            }

        }
    }

    // Now just load any files in this folder
    foreach( glob("{$CFG->dirroot}/local/df_hub/{$dir}/*.php") as $file ){
        require_once $file;
    }

}

/**
 * Convert to html entities
 * @param type $txt
 * @return type
 */
function df_html($txt, $nl2br = false)
{
    return ($nl2br) ? nl2br(  htmlspecialchars($txt, ENT_QUOTES) ) : htmlspecialchars($txt, ENT_QUOTES);
}

/**
 * Get an image URL from Moodle
 * This used to be using the normal $OUTPUT->image_url() or $OUTPUT->pix_url(), but it doesn't work in AJAX calls, as $OUTPUT is not initialised, so changed to $PAGE->theme
 * @global type $PAGE
 * @param type $imagename
 * @param type $component
 * @return type
 */
function df_image_url($imagename, $component = 'moodle'){

    global $PAGE;

    if (method_exists($PAGE->theme, 'image_url')){
        return $PAGE->theme->image_url($imagename, $component);
    } else {
        return $PAGE->theme->pix_url($imagename, $component);
    }


}

/**
 * Get how long ago a timestamp was
 * http://phppot.com/php/php-time-ago-function/
 * @param type $timestamp
 * @return type
 */
function df_time_ago($timestamp) {

  if ($timestamp < 1) return "never";
  
   $strTime = array("second", "minute", "hour", "day", "month", "year");
   $length = array("60","60","24","30","12","10");

   $currentTime = time();
   if($currentTime >= $timestamp) {
        $diff = time() - $timestamp;
        for($i = 0; $diff >= $length[$i] && $i < count($length)-1; $i++) {
            $diff = $diff / $length[$i];
        }
        $diff = round($diff);
        return $diff . " " . $strTime[$i] . "(s) ago ";
   }

}