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
 * This file contains the definition for the library class for gradetracker feedback plugin
 *
 * @package   assignfeedback_gradetracker
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/blocks/gradetracker/lib.php';

/**
 * Library class for comment feedback plugin extending feedback plugin base class.
 *
 * @package   assignfeedback_comments
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_feedback_gradetracker extends assign_feedback_plugin {

    /**
     * Get the name of the online comment feedback plugin.
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignfeedback_gradetracker');
    }
    
    public function is_enabled(){
        return (block_instance('gradetracker') !== false);
    }
    
    public function can_upgrade($type, $version) {
        return false;
    }
    
    public function supports_quickgrading() {
        return false;
    }
    
    public function save(\stdClass $grade, \stdClass $data) {
        
        global $USER;
        
        $User = new \GT\User($USER->id);
        $userID = $grade->userid;
        
        // Can't get it from the post anymore, Moodle 3.1 doesn't submit the grading in the post
//        $criteria = (isset($_POST['gt_criteria'])) ? $_POST['gt_criteria'] : false; # Doesn't work 3.1
        
        // Have to copy how they do it in /lib/ajax/service.php
        $rawjson = file_get_contents('php://input');
        $requests = json_decode($rawjson, true);
        $args = array();
        foreach($requests as $request)
        {
            if ($request['methodname'] == 'mod_assign_submit_grading_form')
            {
                parse_str(json_decode($request['args']['jsonformdata']), $args);
            }
        }
                
        $criteria = (isset($args['gt_criteria'])) ? $args['gt_criteria'] : false;
                
        if (!$userID || !$criteria) return true;
        
        foreach($criteria as $qualID => $units)
        {
            
            $qual = new \GT\Qualification\UserQualification($qualID);
            if ($qual->isValid())
            {
                
                $qual->loadStudent($userID);
                
                foreach($units as $unitID => $crits)
                {

                    // Do we have the permissions to edit this unit?
                    if (!$User->canEditUnit($qualID, $unitID)){
                        continue;
                    }

                    // Is the student actually on this qualification and this unit?
                    if (!$qual->getStudent()->isOnQualUnit($qualID, $unitID, "STUDENT")){
                        continue;
                    }

                    $unit = $qual->getUnit($unitID);
                    if ($unit)
                    {

                        foreach($crits as $critID => $value)
                        {
                            
                            $criterion = $unit->getCriterion($critID);
                            if ($criterion)
                            {
                                
                                $award = new \GT\CriteriaAward($value);
                                $criterion->setUserAward($award);
                                
                                if (isset($args['gt_criteria_comments'][$qualID][$unitID][$critID]))
                                {
                                    $comment = $args['gt_criteria_comments'][$qualID][$unitID][$critID];
                                    $criterion->setUserComments($comment);
                                }
                                
                                $criterion->saveUser();
                                
                            }
                            
                        }

                    }

                }
            
            }
            
        }
        
        return true;
        
    }
    

    /**
     * Get form elements for the grading page
     *
     * @param stdClass|null $grade
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool true if elements were added to the form
     */
    public function get_form_elements_for_user($grade, MoodleQuickForm $mform, stdClass $data, $userid) {
        
        global $CFG, $USER, $User;
                
        if (!$this->is_enabled()) return false;
  
        $User = new \GT\User($USER->id);
        
        require_once $CFG->dirroot . '/blocks/gradetracker/lib.php';
        
        $quals = \GT\Activity::getQualsLinkedToCourseModule( $this->assignment->get_course_module()->id );
        if ($quals)
        {
            foreach($quals as $qualID)
            {
                
                $qual = new \GT\Qualification\UserQualification($qualID);
                $qual->loadStudent( $userid );
                
                // Is the user actually on this qual?
                if (!$qual->getStudent()->isOnQual($qualID, "STUDENT")) continue;
                        
                $mform->addElement('html', '<a href="'.$CFG->wwwroot.'/blocks/gradetracker/grid.php?type=student&id='.$userid.'&qualID='.$qual->getID().'" target="_blank">'.$qual->getDisplayName().'</a><br>');
                
                $units = \GT\Activity::getUnitsLinkedToCourseModule($this->assignment->get_course_module()->id, $qual->getID());
                if ($units)
                {
                    
                    foreach($units as $unitID)
                    {
                        
                        $unit = $qual->getUnit($unitID);
                        if (!$unit) continue;
                        
                        // Is the user actually on this qual unit?
                        if (!$qual->getStudent()->isOnQualUnit($qualID, $unitID, "STUDENT")) continue;
                        
                        // Can we edit this?
                        $access = ($User->canEditUnit($qualID, $unitID)) ? 'ae' : 'v';
                        
                        $mform->addElement('html', '<i>'.$unit->getDisplayName() . '</i><br>');
                        
                        // Criteria
                        $criteria = \GT\Activity::getCriteriaLinkedToCourseModule($this->assignment->get_course_module()->id, false, $qual->getID(), $unit, true);
                        if ($criteria)
                        {
                            
                            $table = new html_table();
                            $table->head = array( get_string('criterion', 'block_gradetracker'), get_string('value', 'block_gradetracker') );
                            if ($access == 'ae'){
                                $table->head[] = get_string('comments', 'block_gradetracker');
                            }
                            
                            foreach($criteria as $criterion)
                            {
                                
                                $row = array();
                                $row[] = $criterion->getName();
                                $row[] = $criterion->getCell($access, 'external');                                
                                if ($access == 'ae')
                                {
                                    $row[] = "<textarea name='gt_criteria_comments[{$qual->getID()}][{$unit->getID()}][{$criterion->getID()}]'>{$criterion->getUserComments()}</textarea>";
                                }
                                
                                $table->data[] = $row;
                                
                            }
                                                        
                            $mform->addElement('html', '<div class="gt_hook_criterion_select">'.html_writer::table($table).'</div>');
                            
                        }
                        
                    }
                    
                }
                
                $mform->addElement('html', '<br>');
                
            }
        }
        
        return true;
        
    }
    
    
}
