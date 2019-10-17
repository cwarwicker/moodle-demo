<?php

/**
 * <title>
 * 
 * @copyright 2013 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 * 
 */

namespace ELBP\Plugins;

if (!defined('PARENT_PORTAL')){
    define('PARENT_PORTAL', true);
}

require_once $CFG->dirroot . '/local/parentportal/lib.php';

/**
 * 
 */
class elbp_portal extends \ELBP\Plugins\Plugin {
    
    /**
     * Construct the plugin object
     * @param bool $install If true, we want to send the default info to the parent constructor, to install the record into the DB
     */
    public function __construct($install = false) {
        
        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Parent Portal",
                "path" => '/local/parentportal/',
                "version" => 2018061400
            ) );
        }
        else
        {
            parent::__construct( strip_namespace(get_class($this)) );
        }

    }
    
    
     /**
     * Install the plugin onto the ELBP (not the actual Portal install, just the ELBP plugin)
     */
    public function install()
    {
        $return = true;
        $return = $return && $this->createPlugin();
        
        
        
        return $return;
    }
    
    /**
     * Upgrade the plugin from an older version to newer
     */
    public function upgrade(){
        
        $result = true;
        $version = $this->version; # This is the current DB version we will be using to upgrade from     
        
        // [Upgrades here]
        
    }
    
    public function loadJavascript($simple = false) {
        
        global $CFG, $PAGE;
        
        $output = "";
        $output .= parent::loadJavascript($simple);
        
        if ($simple)
        {
            $output .= "<script type='text/javascript' src='{$CFG->wwwroot}/local/parentportal/scripts.js'></script>";
        }
        else
        {
            $PAGE->requires->js( "/local/parentportal/scripts.js" );
        }
        
        return $output;
        
    }
    
    /**
     * Load the summary box
     * @return type
     */
    public function getSummaryBox(){
        
        $Portal = new \PP\Portal();
        $Portal->loadStudentFromMoodle($this->student->id);
        
        $TPL = new \ELBP\Template();
        
        $TPL->set("requests", $Portal->getStudentRequests());
        $TPL->set("obj", $this);
        $TPL->set("Portal", $Portal);
                
        try {
            return $TPL->load($Portal->dir . 'tpl/elbp/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }
        
    }
    
    
     /**
     * Get the expanded view
     * @param type $params
     * @return type
     */
    public function getDisplay($params = array()){
                
        $output = "";
        
        $Portal = new \PP\Portal();
        $Portal->loadStudentFromMoodle($this->student->id);
        
        $TPL = new \ELBP\Template();
        $TPL->set("obj", $this);
        $TPL->set("access", $this->access);      
        $TPL->set("params", $params);
        $TPL->set("requests", $Portal->getStudentRequests());
        $TPL->set("Portal", $Portal);
        
        try {
            $output .= $TPL->load($Portal->dir . 'tpl/elbp/expanded.html');
        } catch (\ELBP\ELBPException $e){
            $output .= $e->getException();
        }

        return $output;
        
    }
    
    
    public function ajax($action, $params, $ELBP){
        
        global $DB, $USER;
        
        switch($action)
        {
            
            case 'update_request_status':
                
                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID']) || !isset($params['id']) || !isset($params['password']) || !isset($params['status'])) return false;
                
                if ($params['status'] < -2 || $params['status'] > 1) return false;
                
                $Portal = new \PP\Portal();
                $Portal->loadStudentFromMoodle($this->student->id);
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!\elbp_has_capability('block/elbp:portal_update_request', $access)) return false;
                
                // Check if all valid
                $request = $DB->get_record("portal_requests", array("id" => $params['id'], "userid" => $params['studentID'], "password" => $params['password']));
                if (!$request) return false;
                
                $request->status = $params['status'];
                $request->requestlastupdatedby = 'm_' . $USER->id;
                $Portal->updateRequestFromMoodle($request);
                
                echo "$('#request_row_{$params['id']}').effect( 'highlight', {color: '#ccff66'}, 3000 );";
                echo "$('#request_image_{$params['id']}').attr('src', '{$Portal->www}pix/elbp/{$Portal->getStatusImage($params['status'])}');";
                echo "$('#request_status_{$params['id']}').text('{$Portal->getStatusName($params['status'])}');";
                
                exit;
                
            break;
            
            case 'load_display_type':
                                
                // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;
                
                $Portal = new \PP\Portal();
                $Portal->loadStudentFromMoodle($this->student->id);
                
                // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                
                switch($params['type'])
                {
                    case 'history':
                        $requests = $Portal->getStudentHistory();
                    break;
                    default:
                        $requests = $Portal->getStudentRequests();
                    break;
                }
                                
                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this)
                    ->set("access", $access)
                    ->set("requests", $requests)
                    ->set("Portal", $Portal);
                
                try {
                    $TPL->load( $Portal->dir . 'tpl/elbp/'.$params['type'].'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;                
                
            break;
        }
        
    }
    
}