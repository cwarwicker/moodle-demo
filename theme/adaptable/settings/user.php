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
 * User settings
 *
 * @package    theme_adaptable
 * @copyright  &copy; 2019 - Coventry University
 * @author     G J Barnard - {@link http://moodle.org/user/profile.php?id=442195}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Login page heading.
$temp = new admin_settingpage('theme_adaptable_user', get_string('usersettings', 'theme_adaptable'));
$temp->add(new admin_setting_heading('theme_adaptable_user', get_string('usersettingsheading', 'theme_adaptable'),
    format_text(get_string('usersettingsdesc', 'theme_adaptable'), FORMAT_MARKDOWN)));

// Custom course title.
$name = 'theme_adaptable/customcoursetitle';
$title = get_string('customcoursetitle', 'theme_adaptable');
$description = get_string('customcoursetitledesc', 'theme_adaptable');
$setting = new admin_setting_configtext($name, $title, $description, '', PARAM_TEXT);
$temp->add($setting);

// Custom course subtitle.
$name = 'theme_adaptable/customcoursesubtitle';
$title = get_string('customcoursesubtitle', 'theme_adaptable');
$description = get_string('customcoursesubtitledesc', 'theme_adaptable');
$setting = new admin_setting_configtext($name, $title, $description, '', PARAM_TEXT);
$temp->add($setting);

$ADMIN->add('theme_adaptable', $temp);
