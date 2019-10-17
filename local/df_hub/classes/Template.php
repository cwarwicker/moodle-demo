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
//

/**
 * Class for simple HTML templating
 *
 * @package    local
 * @subpackage df_hub
 * @copyright  2019 Conn Warwicker <conn@cmrwarwicker.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace DF;

/**
 *
 */
class Template {

    private $variables;
    private $output;

    /**
     * Construct the template and set the global variables all templates will need access to
     * @global type $CFG
     * @global type $OUTPUT
     * @global type $USER
     */
    public function __construct() {

        global $CFG, $OUTPUT, $USER;

        $string = get_string_manager()->load_component_strings('local_df_hub', $CFG->lang);

        $this->variables = array();
        $this->output = '';

        $this->set("string", $string);
        $this->set("CFG", $CFG);
        $this->set("OUTPUT", $OUTPUT);
        $this->set("USER", $USER);

    }


    /**
     * Set a variable to be used in the template
     * @param type $var
     * @param type $val
     * @return \GT\Template
     */
    public function set($var, $val, $final = false)
    {

        $this->variables[$var] = $val;
        return $this;

    }

    /**
     * Check if variable has already been set
     * @param type $var
     * @return type
     */
    public function exists($var)
    {
        return (isset($this->variables[$var]));
    }

    /**
     * Get the output if we don't want to call display() and instead use it some other way
     * @return type
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Get all variables set in the template
     * @return type
     */
    public function getVars()
    {
        return $this->variables;
    }

    public function clearVars(){
        $this->variables = array();
        return $this;
    }


    /**
     * Load a template file
     * @param type $template
     * @return type
     * @throws \GT\GTException
     */
    public function load($template)
    {

        global $CFG;

        // Reset the output
        $this->output = '';

        // If the file doesn't exist, throw an exception
        if (!file_exists($template)){
            throw new \Exception( \get_string('error:template', 'local_df_hub') . ': ' . $template );
        }

        // Extract any variables into the template
        if (!empty($this->variables)){
            extract($this->variables);
        }

        flush();
        ob_start();
            include $template;
        $output = ob_get_clean();

        $this->output = $output;
        return $this->output;

    }

    /**
     * Echo the template file
     */
    public function display()
    {
        echo $this->output;
    }

}