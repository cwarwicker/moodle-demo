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
 * Class field
 *
 * @package   customfield_select
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_select;

defined('MOODLE_INTERNAL') || die;

/**
 * Class field
 *
 * @package customfield_select
 * @copyright 2018 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_controller extends \core_customfield\field_controller {
    /**
     * Customfield type
     */
    const TYPE = 'select';

    /**
     * Add fields for editing a select field.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
        $mform->addElement('header', 'header_specificsettings', get_string('specificsettings', 'customfield_select'));
        $mform->setExpanded('header_specificsettings', true);

        $mform->addElement('textarea', 'configdata[options]', get_string('menuoptions', 'customfield_select'));
        $mform->setType('configdata[options]', PARAM_TEXT);

        $mform->addElement('text', 'configdata[defaultvalue]', get_string('defaultvalue', 'core_customfield'), 'size="50"');
        $mform->setType('configdata[defaultvalue]', PARAM_TEXT);
    }

    /**
     * Returns the options available as an array.
     *
     * @param \core_customfield\field_controller $field
     * @return array
     */
    public static function get_options_array(\core_customfield\field_controller $field) : array {
        if ($field->get_configdata_property('options')) {
            $options = preg_split("/\s*\n\s*/", trim($field->get_configdata_property('options')));
        } else {
            $options = array();
        }
        return array_merge([''], $options);
    }

    /**
     * Validate the data from the config form.
     * Sub classes must reimplement it.
     *
     * @param array $data from the add/edit profile field form
     * @param array $files
     * @return array associative array of error messages
     */
    public function config_form_validation(array $data, $files = array()) : array {
        $options = preg_split("/\s*\n\s*/", trim($data['configdata']['options']));
        $errors = [];
        if (!$options || count($options) < 2) {
            $errors['configdata[options]'] = get_string('errornotenoughoptions', 'customfield_select');
        } else if (!empty($data['configdata']['defaultvalue'])) {
            $defaultkey = array_search($data['configdata']['defaultvalue'], $options);
            if ($defaultkey === false) {
                $errors['configdata[defaultvalue]'] = get_string('errordefaultvaluenotinlist', 'customfield_select');
            }
        }
        return $errors;
    }
}
