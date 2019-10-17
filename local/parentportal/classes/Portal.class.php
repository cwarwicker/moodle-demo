<?php

/**
 * Parent/Guardian Portal
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

namespace PP;



/**
 * 
 */
class Portal {
        
    public $dir;
    public $www;
    public $errors = array();
    public $success = array();
    public $user = false;
    private $students = array();
    private $page = false;
    private $student = false;
    public $string = array();
    private $crumbs = array();
    private $pluginsEnabled = array();
    
    private $version = 2016101800;
    private $dbversion = false;
    
    public function __construct() {
        
        global $CFG, $DB;
        
        $this->dir = $CFG->dirroot . '/local/parentportal/';
        $this->www = $CFG->wwwroot . '/local/parentportal/';
        
        include $CFG->dirroot . '/local/parentportal/lang/'.$CFG->lang.'/local_parentportal.php';
        $this->string = $string;
        
        if ($this->isAuthenticated())
        {
            $this->loadUser($_SESSION['pp_user']->id);
        }
        
        $studentID = \optional_param('studentid', -1, PARAM_INT);
        if ($studentID > 0){
            $this->loadStudent($studentID);
        }
        
        $this->loadAction();
                
        $this->dbversion = get_config('local_parentportal', 'version');
        $this->installed = get_config('local_parentportal', 'installed');
        
        if ($this->isInstalled())
        {
            $this->pluginsEnabled = $this->getSetting('plugins_enabled');
            if ($this->pluginsEnabled){
                $this->pluginsEnabled = explode(",", $this->pluginsEnabled);
            }
        }
        
    }
    
    public function isSignupEnabled()
    {
        
        $value = $this->getSetting('signup_enabled');
        return ($value !== 0 && $value !== '0');
        
    }
    
    public function isInstalled(){
        
        return ($this->installed == 1);
        
    }
    
    public function install( $admin ){
        
        global $CFG, $DB;
     
        $dbman = $DB->get_manager();
        
        // Is the old /portal version installed? - If so, remove that old setting
        $oldVersion = get_config('core', 'parent_portal_version');
        if ($oldVersion){
            unset_config('parent_portal_version', 'core');
        }
        
        
        
        // Create tables
        
        // Portal Users Table
        $table = new \xmldb_table("portal_users");
        $table->add_field("id", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field("firstname", XMLDB_TYPE_CHAR, "255", null, XMLDB_NOTNULL);
        $table->add_field("lastname", XMLDB_TYPE_CHAR, "255", null, XMLDB_NOTNULL);
        $table->add_field("email", XMLDB_TYPE_CHAR, "255", null, XMLDB_NOTNULL);
        $table->add_field("password", XMLDB_TYPE_CHAR, "255", null, XMLDB_NOTNULL);
        $table->add_field("passwordsalt", XMLDB_TYPE_CHAR, "255", null, XMLDB_NOTNULL);
        $table->add_field("passwordresetcode", XMLDB_TYPE_CHAR, "255", null, null);
        $table->add_field("confirmationcode", XMLDB_TYPE_CHAR, "255", null, XMLDB_NOTNULL);
        $table->add_field("confirmed", XMLDB_TYPE_INTEGER, "1", null, XMLDB_NOTNULL, null, "0");
        $table->add_field("deleted", XMLDB_TYPE_INTEGER, "1", null, XMLDB_NOTNULL, null, "0");
        $table->add_field("isadmin", XMLDB_TYPE_INTEGER, "1", null, XMLDB_NOTNULL, null, "0");

        // Keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            \pp_trace("created portal_users table");
        }
        
        
        // Portal Requests Table
        $table = new \xmldb_table("portal_requests");
        $table->add_field("id", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field("portaluserid", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL);
        $table->add_field("userid", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL);
        $table->add_field("requesttime", XMLDB_TYPE_INTEGER, "20", null, XMLDB_NOTNULL);
        $table->add_field("status", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL, null, "0");
        $table->add_field("password", XMLDB_TYPE_CHAR, "255", null, null, null, null);

        // Keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('puidfk', XMLDB_KEY_FOREIGN, array('portaluserid'), 'portal_users', array('id'));
        $table->add_key('uidfk', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
            
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            \pp_trace("created portal_requests table");
        } 
        
        
        // Portal Request History Table
        $table = new \xmldb_table("portal_request_history");
        $table->add_field("id", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field("actionbyusertype", XMLDB_TYPE_CHAR, "255", null, XMLDB_NOTNULL);   
        $table->add_field("actionbyuserid", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL);
        $table->add_field("portaluserid", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL);
        $table->add_field("userid", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL);
        $table->add_field("status", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL);
        $table->add_field("requesttime", XMLDB_TYPE_INTEGER, "20", null, XMLDB_NOTNULL);
        
        // Keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('puidfk', XMLDB_KEY_FOREIGN, array('portaluserid'), 'portal_users', array('id'));
        $table->add_key('uidfk', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        
        
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            \pp_trace("created portal_request_history table");
        }
        
        
        // Portal Settings Table
        $table = new \xmldb_table("portal_settings");
        $table->add_field("id", XMLDB_TYPE_INTEGER, "10", null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field("setting", XMLDB_TYPE_CHAR, "255", null, XMLDB_NOTNULL);   
        $table->add_field("value", XMLDB_TYPE_TEXT, "255");   
        
        // Keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            \pp_trace("created portal_settings table");
        }
        
        
        // Create admin user and insert settings, if old version not already installed
        if (!$oldVersion)
        {
        
            // Create admin user
            $obj = new \stdClass();
            $obj->firstname = "Admin";
            $obj->lastname = "Account";
            $obj->email = $admin->email;
            $obj->passwordsalt = $this->generateRandomCode(10);
            $obj->password = $this->buildPassword($admin->password, $obj->passwordsalt);
            $obj->confirmationcode = "";
            $obj->confirmed = 1;
            $obj->isadmin = 1;
            $DB->insert_record("portal_users", $obj);

            // Insert default settings
            $settings = array();
            $settings['welcome_email'] = "Welcome to the Parent Portal system!

    To confirm your account please visit the following URL:

    %confirm%

    If you have any problems please contact your Learning Technologies team";

            $settings['accessrequests_type'] = 'idnumber';
            $settings['signup_code'] = '';
            $settings['idnumber_field'] = 'username';
            $settings['site_title'] = 'Parent Portal';

            foreach($settings as $setting => $value)
            {
                $obj = new \stdClass();
                $obj->setting = $setting;
                $obj->value = $value;
                $DB->insert_record("portal_settings", $obj);
            }
        
        }
        
        // Try and create the folder in moodle data
        if (!is_dir($CFG->dataroot . '/parent_portal/')){
            mkdir($CFG->dataroot . '/parent_portal/', 0775);
        }
        
        
        // if old version installed, remove the old ELBP plugin
        if ($oldVersion){
            
            require_once $CFG->dirroot . '/blocks/elbp/lib.php';
            $ELBP = new \ELBP\ELBP();
            $elbpPlugin = $ELBP->getPlugin('elbp_portal', true);
            if ($elbpPlugin){
                $elbpPlugin->uninstall();
            }
            
        }
       
        // Set installed setting
        set_config('installed', '1', 'local_parentportal');
        
        header('Location:' . $this->www);
        exit;
        
        
    }
    
    public function update()
    {
     
        global $CFG, $DB;
        
        
        
    }
    
    
    
    public function addBreadCrumb($title, $link = false){
        
        $this->crumbs[] = array(
            "title" => $title,
            "link" => $link
        );
        
    }
    
    public function getBreadcrumbs(){
        
        $output = "";
        
        if ($this->crumbs){
            
            $cnt = count($this->crumbs);
            $n = 0;
            
            foreach($this->crumbs as $crumb){
                
                if ($crumb['link']){
                    $output .= "<a href='{$crumb['link']}'>";
                }
                
                $output .= $crumb['title'];
                
                if ($crumb['link']){
                    $output .= "</a>";
                }
                
                $n++;
                
                if ($cnt > $n){
                    $output .= "<span class='seperator'></span>";
                }
                
            }
            
        }
        
        return $output;
        
    }
    
    public function accept($id){
        
        global $DB, $USER;
        
        $obj = $DB->get_record("portal_requests", array("id" => $id));
        $obj->status = PP_STATUS_CONFIRMED;
        $DB->update_record("portal_requests", $obj);
        $this->success[] = $this->string['reqaccepted'];
        
        $this->logHistory('moodle', $USER->id, $obj->portaluserid, $obj->userid, PP_STATUS_CONFIRMED);
        
        $parentUser = $DB->get_record("portal_users", array("id" => $obj->portaluserid));
        if ($parentUser)
        {
            $student = $DB->get_record("user", array("id" => $obj->userid));
            $subject = $this->string['updateemailsubject'];
            $message = $this->string['updateemailcontent'];
            $message = str_replace("%u%", fullname($student), $message);
            $message = str_replace("%s%", $this->getStatusName(PP_STATUS_CONFIRMED), $message);
            $this->email($parentUser->email, $subject, $message);

        }
        
        return true;
        
    }
    
    public function reject($id){
        
        global $DB, $USER;
        
        $obj = $DB->get_record("portal_requests", array("id" => $id));
        $obj->status = PP_STATUS_REJECTED;
        $DB->update_record("portal_requests", $obj);
        $this->success[] = $this->string['reqrejected'];
        
        $this->logHistory('moodle', $USER->id, $obj->portaluserid, $obj->userid, PP_STATUS_REJECTED);
        
        $parentUser = $DB->get_record("portal_users", array("id" => $obj->portaluserid));
        if ($parentUser)
        {
        
            $student = $DB->get_record("user", array("id" => $obj->userid));
            $subject = $this->string['updateemailsubject'];
            $message = $this->string['updateemailcontent'];
            $message = str_replace("%u%", fullname($student), $message);
            $message = str_replace("%s%", $this->getStatusName(PP_STATUS_REJECTED), $message);
            $this->email($parentUser->email, $subject, $message);
            
        }
        
        return true;
        
    }
    
    public function checkResponseAccess($id, $password){
        
        global $DB, $USER;
        
        return $DB->get_record_sql("SELECT u.*, r.id as reqid, r.requesttime
                                       FROM {portal_users} u
                                       INNER JOIN {portal_requests} r ON r.portaluserid = u.id
                                       WHERE r.id = ?
                                       AND r.password = ?
                                       AND r.userid = ?
                                       AND r.status = ?", array($id, $password, $USER->id, PP_STATUS_PENDING));
                
    }
    
    public function getSiteTitle(){
        
        $title = $this->getSetting('site_title');
        return ($title) ? $title : $this->string['parentportal'];
        
    }
    
    public function getSetting($setting){
        
        global $DB;
        
        $check = $DB->get_record("portal_settings", array("setting" => $setting));
        return ($check) ? $check->value : false;
        
    }
    
    public function updateSetting($setting, $value){
        
        global $DB;
                
        $check = $DB->get_record("portal_settings", array("setting" => $setting));
        
        if ($check){
            
            $check->value = $value;
            return $DB->update_record("portal_settings", $check);
            
        } else {
            
            $ins = new \stdClass();
            $ins->setting = $setting;
            $ins->value = $value;
            return $DB->insert_record("portal_settings", $ins);
            
        }
        
    }
    
    /**
     * Is a PLP plugin enabled?
     * @param type $plugin
     * @return type
     */
    public function isPluginEnabled($plugin){
        
        
        return (is_array($this->pluginsEnabled) && in_array($plugin, $this->pluginsEnabled));
        
    }
    
    public function getStudents(){
        return $this->students;
    }
    
    public function loadInstall(&$TPL){
        
        if (isset($_POST['install'])){
            
            $email = $_POST['admin_email'];
            $pass = $_POST['admin_pass'];
            $pass2 = $_POST['admin_pass_confirm'];
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
                $this->errors[] = $this->string['invalidemail'];
            }
            
            if (strlen($pass) < 6){
                $this->errors[] = $this->string['pw6chars'];
            }
            
            if ($pass !== $pass2){
                $this->errors[] = $this->string['pwnomatch'];
            }
            
            if (!$this->errors){
                
                $admin = new \stdClass();
                $admin->email = $email;
                $admin->password = $pass;
                $this->install( $admin );
                
            }
            
        }
        
    }
    
    public function loadAction(){
        
        $action = (isset($_GET['action'])) ? $_GET['action'] : false;
        
        if ($action)
        {
            
            switch($action)
            {
                
                case 'request':
                    
                    $method = $this->getAccessMethod();
                    if ($method == 'idnumberdob')
                    {
                        if (isset($_POST['sID']) && !empty($_POST['sID']) && isset($_POST['sDOB']) && !empty($_POST['sDOB']))
                        {
                            $this->request($_POST['sID'], $_POST['sDOB']);
                        }
                    }
                    elseif($method == 'idnumber')
                    {
                        if (isset($_POST['sID']) && !empty($_POST['sID']))
                        {
                            $this->request($_POST['sID']);
                        }
                    }
                    
                break;
                
                case 'deleterequest':
                    
                    if (isset($_GET['sID']) && !empty($_GET['sID']))
                    {
                        $this->deleteRequest($_GET['sID']);
                    }
                    
                break;
                
                case 'updateprofile':
                    
                    if (isset($_POST['update_profile']))
                    {
                        $this->updateProfile();
                    }
                    
                break;
                
                case 'updatesettings':
                    
                    if (isset($_POST['update_settings']))
                    {
                        $this->updateSettings();
                    }
                    
                break;
                
                case 'createaccount':
                    
                    if (isset($_POST['createAccount']))
                    {
                        $this->createAccount();
                    }
                    
                break;
                
                case 'forgotpassword':
                    
                    if (isset($_POST['forgot_email']) && !empty($_POST['forgot_email']))
                    {
                        $this->resetPassword($_POST['forgot_email']);
                    }
                    
                break;
                
//                case 'resetpassword':
//                    
//                    if (isset($_GET['user']) && isset($_GET['code']))
//                    {
//                        $this->confirmPasswordReset($_GET['user'], $_GET['code']);
//                    }
//                    
//                break;
                
                case 'updateuser':
                    
                    if (isset($_POST['update_user']) && isset($_GET['user']) && ctype_digit($_GET['user']))
                    {
                        $this->updateUser($_GET['user']);
                    }
                    
                break;
                
                case 'signup':
                    
                    if (isset($_POST['pp_signup']))
                    {
                        $this->signUp();
                    }
                    
                break;
                
                case 'import':
                    
                    if ($this->isAdmin() && isset($_POST['submit_import']))
                    {
                        $this->import();                                                
                    }
                    
                break;
                
                
            }
            
        }
        
    }
    
    
    private function import(){
    
        global $CFG, $DB;
        
        if (!$this->isAdmin()) return false;
        if (!isset($_FILES['csv'])) return false;
        
        $csv = $_FILES['csv'];
        
        if ($csv['error'] > 0){
            $this->errors[] = $this->string['fileuploaderror'];
            return false;
        }
        
        // Open the file
        $file = fopen($csv['tmp_name'], 'r');
        if (!$file){
            $this->errors[] = $this->string['fileuploaderror'];
            return false;
        }
        
        $row = 0;
        
        while (($data = fgetcsv($file)) !== false)
        {
            
            $row++;
            
            // Skip header row
            if ($row == 1) continue;
                
            $pFirstName = trim($data[0]);
            $pLastName = trim($data[1]);
            $pEmail = trim($data[2]);
            $username = trim($data[3]);
            $pPassword = trim($data[4]);
            
            // Make sure fields are filled in
            if ($pFirstName == '' || $pLastName == '' || $pEmail == '' || $username == ''){
                $this->errors[] = $this->string['invalidcsvrow'] . ': ' . print_r($data, true);
                continue;
            }
            
            // Create the account
            $password = ($pPassword != '') ? $pPassword : $this->generateRandomCode(6);
            $userID = $this->doCreateAccount($pFirstName, $pLastName, $pEmail, $password, 1);
            if (!$userID){
                $this->errors[] = $this->string['createuserfail'] . ': ' . print_r($data, true);
                continue;
            }
            
            
            // Check student exists
            $field = $this->getIDField();
            $student = $DB->get_record("user", array($field => $username));
            if (!$student){
                $this->errors[] = $this->string['nosuchuser'] . ': ' . $username;
                continue;
            }
            
            // Create the link
            $this->doImportParentLink($userID, $student->id);
            $this->success[] = $this->string['linkedusers'] . ': ' . $pEmail . ' -> ' . $username;
            
        }
        
        
        fclose($file);
                
        
    }
    
    /**
     * Create a link between parent and student and confirm it
     * @global \PP\type $CFG
     * @global \PP\type $DB
     * @param type $parentID
     * @param type $studentID
     */
    private function doImportParentLink($parentID, $studentID){
        
        global $CFG, $DB;
        
        $check = $DB->get_record_select("portal_requests", "portaluserid = ? AND userid = ?", array($parentID, $studentID));
        
        // Add request            
        if ($check)
        {
            $id = $check->id;
            $check->status = PP_STATUS_CONFIRMED;
            $check->requesttime = time();
            $DB->update_record("portal_requests", $check);
        }
        else
        {
            $ins = new \stdClass();
            $ins->portaluserid = $parentID;
            $ins->userid = $studentID;
            $ins->requesttime = time();
            $ins->status = PP_STATUS_CONFIRMED;
            $ins->password = $this->generateRandomCode(6); # So they can respond from click of link without logging into anything
            $id = $DB->insert_record("portal_requests", $ins);
        }

        $this->logHistory('portal', $this->user->id, $parentID, $studentID, PP_STATUS_CONFIRMED);
        
        
    }
    
    /**
     * Sign up method
     * @global \PP\type $CFG
     * @global \PP\type $DB
     * @return boolean
     */
    private function signUp(){
        
        global $CFG, $DB;
        
        if (!$this->isSignupEnabled())
        {
            $this->errors['signup'][] = $this->string['signupnotenabled'];
        }
        
        $firstname = trim($_POST['pp_signup_firstname']);
        $lastname = trim($_POST['pp_signup_lastname']);
        $email = trim($_POST['pp_signup_email']);
        $email2 = trim($_POST['pp_signup_email_confirm']);
        $pw = trim($_POST['pp_signup_pass']);
        $pw2 = trim($_POST['pp_signup_pass_confirm']);
        
        if ($this->hasSignupCode()){
            $code = trim($_POST['pp_signup_code']);
        }
                
        if (empty($firstname)){
            $this->errors['signup'][] = $this->string['fieldcannotbeempty'] . ': firstname';
        }
        
        if (empty($lastname)){
            $this->errors['signup'][] = $this->string['fieldcannotbeempty'] . ': lastname';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) !== false){
            $this->errors['signup'][] = $this->string['invalidemail'];
        }
        
        if ($email !== $email2){
            $this->errors['signup'][] = $this->string['emailsdontmatch'];
        } 
        
        $check = $DB->get_record("portal_users", array("email" => $email, "deleted" => 0));
        if ($check){
            $this->errors['signup'][] = $this->string['emailexists'];
        }
        
        
        
        if (strlen($pw) < 6){
            $this->errors['signup'][] = $this->string['pw6chars'];
        }
        
        if ($pw !== $pw2){
            $this->errors['signup'][] = $this->string['pwnomatch'];
        }
        
        if (isset($code) && $code !== $this->getSignupCode()){
            $this->errors['signup'][] = $this->string['invalidsignupcode'];
        }
        
        if (!$this->errors)
        {
            
            $txtpassword = $pw;
            $salt = $this->generateRandomCode(20);
            $password = $this->buildPassword($pw, $salt);
            
            $obj = new \stdClass();
            $obj->firstname = $firstname;
            $obj->lastname = $lastname;
            $obj->email = $email;
            $obj->password = $password;
            $obj->passwordsalt = $salt;
            $obj->confirmed = 0;
            $obj->confirmationcode = $this->generateRandomCode(10);
            
            $id = $DB->insert_record("portal_users", $obj);
                        
            // Email
            $content = $this->getSetting('welcome_email');
            $content = str_replace("%email%", $email, $content);
            $content = str_replace("%confirm%", $CFG->wwwroot . '/local/parentportal/confirm.php?id=' . $id . '&code=' . $obj->confirmationcode, $content);
                        
            if (!$this->email($obj->email, $this->getSiteTitle(), $content)){
                $str = $this->string['errorsendingemail'];
                $supportEmail = $this->getSetting('support_email');
                if ($supportEmail){
                    $str = str_replace('%support%', $this->getSetting('support_email'), $str);
                }
                $this->errors['signup'][] = $str;
                return false;
            }
            
            // Success
            $this->success['signup'][] = $this->string['signupsuccessful'];
            
            return true;
        }
        
        $GLOBALS['firstname'] = $firstname;
        $GLOBALS['lastname'] = $lastname;
        $GLOBALS['email'] = $email;
        $GLOBALS['email_confirm'] = $email2;
        if (isset($code)){
            $GLOBALS['code'] = $code;
        }
        return false;
        
        
    }
    
    private function getSignupCode(){
        return $this->getSetting('signup_code');
    }
    
    private function updateUser($userID){
        
        global $DB;
        
        if (!$this->isAdmin())
        {
            return false;
        }
                
        $account = $DB->get_record("portal_users", array("id" => $userID));
        if (!$account){
            $this->errors[] = $this->string['nosuchuser'];
        }
        
        $firstname = trim($_POST['update_firstname']);
        $lastname = trim($_POST['update_lastname']);
        $email = trim($_POST['update_email']);
        $newpw = trim($_POST['update_new_password']);
        
        if (empty($firstname)){
            $this->errors[] = $this->string['fieldcannotbeempty'] . ': firstname';
        }
        if (empty($lastname)){
            $this->errors[] = $this->string['fieldcannotbeempty'] . ': lastname';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false){
            $account->email = $email;
        } else {
            $this->errors[] = $this->string['invalidemail'];
        }
        
        // Password, if submitted
        if (!empty($newpw)){
        
            if (strlen($newpw) < 6){
                $this->errors[] = $this->string['pw6chars'];
            }
                        
            if (!$this->errors){
                
                $account->passwordsalt = $this->generateRandomCode(20);
                $account->password = $this->buildPassword($newpw, $account->passwordsalt);
                
            }
        
        }
        
        // Confirmed
        if (isset($_POST['update_confirmed']) && $_POST['update_confirmed'] == 1){
            $account->confirmed = 1;
        } else {
            $account->confirmed = 0;
        }
        
        // Deleted
        if (isset($_POST['update_deleted']) && $_POST['update_deleted'] == 1){
            $account->deleted = 1;
        } else {
            $account->deleted = 0;
        }
        
        // Admin
        if (isset($_POST['update_is_admin']) && $_POST['update_is_admin'] == 1){
            $account->isadmin = 1;
        } else {
            $account->isadmin = 0;
        }
        
        if (!$this->errors){
            
            $account->firstname = $firstname;
            $account->lastname = $lastname;
                        
            $this->success[] = $this->string['profileupdated'];
            $DB->update_record("portal_users", $account);        
            return $this->loadUser($this->user->id);
        }
        
        return false;
        
    }
    
    public function hasSignupCode(){
        
        $code = $this->getSetting('signup_code');
        if (!$code) return false;
        
        $code = trim($code);
        return (!empty($code));
        
    }
    
    public function getLogoURL(){
        
        $logo = $this->getSetting('site_logo');
        return ($logo) ? $logo : $this->www . 'pix/logo.png';
        
    }
    
    public function getDateFormat(){
        
        $field = $this->getSetting('dob_format');
        return ($field) ? $field : 'dd-mm-yy';
        
    }
    
    public function getIDField(){
        
        $field = $this->getSetting('idnumber_field');
        return ($field) ? $field : 'username';
        
    }
    
    public function getAccessMethod(){
        
        $method = $this->getSetting('accessrequests_type');
        return ($method) ? $method : 'idnumber';
        
    }

    
    public function email($to, $subject, $content){
                
        // EMail to user
        $user = new \stdClass();
        $user->id = -1;
        $user->email = $to;
        
        $adminUser = $this->getAdminUser();
        if ($adminUser)
        {
            return email_to_user($user, $adminUser, $subject, $content, nl2br($content));
        }
        
        return false;
        
    }
    
    private function resetPassword($email){
        
        global $DB;
        
        $check = $DB->get_record("portal_users", array("email" => $email, "deleted" => 0));
        if ($check)
        {
            
            // Generate password reset code
            $code = $this->generateRandomCode(10);
            $check->passwordresetcode = $code;
            $DB->update_record("portal_users", $check);

            $content = $this->string['resetemail1'];
            $content .= "\n\n";
            $content .= $this->www . 'reset.php?user=' . $check->id . '&code=' . $code;
            
            $this->email($check->email, $this->string['passwordreset'], $content);
            
        }
        
        $this->success['login'][] = $this->string['ifemail-sent'];
        
    }
    
    private function createAccount(){
        
        global $CFG, $DB;
        
        $firstname = trim($_POST['createAccountFirstName']);
        $lastname = trim($_POST['createAccountLastName']);
        $email = trim($_POST['createAccountEmail']);
        $password = trim($_POST['createAccountPassword']);
        $confirmpassword = trim($_POST['createAccountPasswordConfirm']);
        
        if (empty($firstname)){
            $this->errors[] = $this->string['firstnamevalidation'];
        }
        
        if (empty($lastname)){
            $this->errors[] = $this->string['lastnamevalidation'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $this->errors[] = $this->string['invalidemail'];
        }
        
        $check = $DB->get_record("portal_users", array("email" => $email));
        if ($check){
            $this->errors[] = $this->string['emailexists'];
        }
        
        if (strlen($password) < 6){
            $this->errors[] = $this->string['pw6chars'];
        }

        if ($password !== $confirmpassword){
            $this->errors[] = $this->string['pwnomatch'];
        }
        
        if (!$this->errors)
        {
            
            // Create the account
            $this->doCreateAccount($firstname, $lastname, $email, $password);
            
            // Success
            $this->success[] = $this->string['accountcreated'];
            
            
        }
        
        
    }
    
    /**
     * Create the portal user account
     * @global \PP\type $CFG
     * @global \PP\type $DB
     * @param type $firstname
     * @param type $lastname
     * @param type $email
     * @param type $password
     * @param type $confirmed
     */
    private function doCreateAccount($firstname, $lastname, $email, $password, $confirmed = 0)
    {
        
        global $CFG, $DB;
        
        // CHeck doesn't exist
        $check = $DB->get_record("portal_users", array("email" => $email, "deleted" => 0));
        if ($check) return $check->id;
        
        $txtpassword = $password;
        $salt = $this->generateRandomCode(20);
        $password = $this->buildPassword($password, $salt);

        $obj = new \stdClass();
        $obj->firstname = $firstname;
        $obj->lastname = $lastname;
        $obj->email = $email;
        $obj->password = $password;
        $obj->passwordsalt = $salt;
        $obj->confirmed = $confirmed;
        $obj->confirmationcode = $this->generateRandomCode(10);

        $id = $DB->insert_record("portal_users", $obj);

        $obj->maildisplay = 2; # ?

        // Email
        $content = $this->getSetting('welcome_email');
        $content = str_replace("%email%", $email, $content);
        $content = str_replace("%password%", $txtpassword, $content);
        $content = str_replace("%confirm%", $CFG->wwwroot . '/local/parentportal/confirm.php?id=' . $id . '&code=' . $obj->confirmationcode, $content);

        $this->email($obj->email, $this->getSiteTitle(), $content);
        
        return $id;
        
    }
    
    private function updateSettings(){
        
        global $CFG;
        
        $settings = $_POST;
        unset($settings['update_settings']);
        
        
        // Checkboxes
        if (!isset($settings['signup_enabled']))
        {
            $settings['signup_enabled'] = 0;
        }
        
        // Multiple select
        if (isset($settings['plugins_enabled']))
        {
            $settings['plugins_enabled'] = implode(",", $settings['plugins_enabled']);
        }

        // Delete current user guide
        if (isset($settings['delete_user_guide']))
        {
            unset($settings['delete_user_guide']);
            $file = $this->getSetting('user_guide');
            if ($file !== false && !is_null($file))
            {
                unlink( $CFG->dataroot . '/parent_portal/' . $file );
                $this->updateSetting('user_guide', null);
            }
        }
        
        // Notification - trim
        if (isset($settings['notification'])){
            $settings['notification'] = trim($settings['notification']);
        }
        
        // User guide file
        if (isset($_FILES['user_guide']) && !$_FILES['user_guide']['error'])
        {
            // Try and save the file
            $name = $_FILES['user_guide']['name'];
            move_uploaded_file($_FILES['user_guide']['tmp_name'], $CFG->dataroot . '/parent_portal/' . $name);
            $settings['user_guide'] = $name;
        }

        
        // The rest of the settings
        if ($settings)
        {
            foreach($settings as $setting => $value)
            {
                $this->updateSetting($setting, $value);
            }
        }
         
        $this->success[] = $this->string['settingsupdated'];
        
    }
    
    private function updateProfile(){
        
        global $DB;
        
        $firstname = trim($_POST['update_firstname']);
        $lastname = trim($_POST['update_lastname']);
        $email = trim($_POST['update_email']);
        $currentpw = trim($_POST['update_current_password']);
        $newpw = trim($_POST['update_new_password']);
        $confirmpw = trim($_POST['update_confirm_password']);
        
        $obj = new \stdClass();
        $obj->id = $this->user->id;
        if (!empty($firstname)){
            $obj->firstname = $firstname;
        }
        if (!empty($lastname)){
            $obj->lastname = $lastname;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false){
            $obj->email = $email;
        } else {
            $this->errors[] = $this->string['invalidemail'];
        }
        
        if (!empty($currentpw) || !empty($newpw) || !empty($confirmpw)){
        
            if ($this->buildPassword($currentpw, $this->user->passwordsalt) !== $this->user->password){
                $this->errors[] = $this->string['invalidpw'];
            }
            
            if (strlen($newpw) < 6){
                $this->errors[] = $this->string['pw6chars'];
            }
            
            if ($newpw !== $confirmpw){
                $this->errors[] = $this->string['pwnomatch'];
            }
            
            
            if (!$this->errors){
                
                $obj->passwordsalt = $this->generateRandomCode(20);
                $obj->password = $this->buildPassword($newpw, $obj->passwordsalt);
                
            }
        
        }
        
        if (!$this->errors){
            $this->success[] = $this->string['profileupdated'];
            $DB->update_record("portal_users", $obj);        
            return $this->loadUser($this->user->id);
        }
        
        return false;
        
    }
    
    private function deleteRequest($sID){
        
        global $DB;
        
        $check = $DB->get_record_select("portal_requests", "portaluserid = ? AND userid = ?", array($this->user->id, $sID));
                
        if ($check)
        {
            $check->status = PP_STATUS_CANCELLED;
            $check->requestlastupdatedby = 'p_' . $this->user->id;
            $DB->update_record("portal_requests", $check);
            
            $this->logHistory('portal', $this->user->id, $this->user->id, $check->userid, PP_STATUS_CANCELLED);
            
            $this->reloadStudents();
            
        }
        
    }
    
    private function request($username, $dob = false){
        
        global $CFG, $DB;
                
        $field = $this->getIDField();
                
        $params = array();
        $params[$field] = $username;                
        $user = $DB->get_record("user", $params);
        
        if (!$user)
        {
            $this->errors[] = $this->string['nosuchuser'];
            return false;
        }
        
        // If checking date of birth
        if ($dob)
        {
            
            $fieldName = $this->getSetting('dob_field');
            $fieldLocation = $this->getSetting('dob_location');
            if (!$fieldLocation) $fieldLocation = 'custom';
            
            
            // Custom Profile Field
            if ($fieldLocation == 'custom')
            {
            
                $field = $DB->get_record("user_info_field", array("shortname" => $fieldName));
                if (!$field)
                {
                    $this->errors[] = sprintf($this->string['invaliddobfield'], $this->getSetting('support_email'));
                    return false;
                }

                $fieldData = $DB->get_record("user_info_data", array("userid" => $user->id, "fieldid" => $field->id));
                if (!$fieldData)
                {
                    $this->errors[] = $this->string['nosuchuser'];
                    return false;
                }
                else
                {

                    if ($fieldData->data != $dob)
                    {
                        $this->errors[] = $this->string['nosuchuser'];
                        return false;
                    }

                }
            
            }
            elseif ($fieldLocation == 'db')
            {
                
                if (!isset($user->$fieldName))
                {
                    $this->errors[] = sprintf($this->string['invaliddobfield'], $this->getSetting('support_email'));
                    return false;
                }
                
                if ($user->$fieldName != $dob)
                {
                    $this->errors[] = $this->string['nosuchuser'];
                    return false;
                }
                                
            }
                        
        }
        
                
        
        // If they already have a request with this student that hasn't been rejected, they can't send another
        $check = $DB->get_record_select("portal_requests", "portaluserid = ? AND userid = ?", array($this->user->id, $user->id));
        if ($check && $check->status >= PP_STATUS_PENDING)
        {
            $this->errors[] = $this->string['alreadyexistingrequest'];
            return false;
        }
        
        if (isset($_POST['confirm']))
        {
        
            // Add request            
            if ($check && $check->status < PP_STATUS_PENDING)
            {
                $id = $check->id;
                $pw = $check->password;
                $check->status = PP_STATUS_PENDING;
                $check->requesttime = time();
                $DB->update_record("portal_requests", $check);
            }
            else
            {
                $ins = new \stdClass();
                $ins->portaluserid = $this->user->id;
                $ins->userid = $user->id;
                $ins->requesttime = time();
                $ins->status = PP_STATUS_PENDING; # Pending
                $ins->password = $this->generateRandomCode(6); # So they can respond from click of link without logging into anything
                $id = $DB->insert_record("portal_requests", $ins);
                $pw = $ins->password;
            }
            
            $this->logHistory('portal', $this->user->id, $this->user->id, $user->id, PP_STATUS_PENDING);
            
            // Send msg to user to let them know
            $content = "";
            $content .= $this->string['requestmessagecontent'];
            $content .= "\n\n";
            $content .= $this->user->firstname . " " . $this->user->lastname . " ({$this->user->email})\n\n";
            $content .= "{$this->string['respondtorequest']}:\n\n<a href='{$this->www}?page=respond&action=respond&req={$id}&password={$pw}'>{$this->www}?page=respond&action=respond&req={$id}&password={$pw}</a>";
            $content .= "\n\n";
            $content .= $this->string['alsoonelbp'];
            $content .= "\n\n";
            $content .= "<a href='{$CFG->wwwroot}/blocks/elbp/view.php'>{$CFG->wwwroot}/blocks/elbp/view.php</a>";

            // This is sending a message to a MOODLE user, not Portal user, so use Moodle functions
            $from = $this->getAdminUser();
            if (!$from) $from = $user;
            \message_post_message($from, $user, nl2br($content), FORMAT_HTML);
            
            $this->reloadStudents();
            
            $field = $this->getIDField();

            $this->success[] = $this->string['requestsentto'] . " " . \fullname($user) . " ({$user->$field})";
            
        }
        
        
        
    }
    
   
    
    private function getAdminUser(){
        
        global $DB;
        $username = $this->getSetting('admin_user');
        return ($username) ? $DB->get_record("user", array("username" => $username)) : false;
        
    }
    
    /**
     * 
     * @global type $DB
     * @param type $type
     * @param type $actionByUserID
     * @param type $portalUserID
     * @param type $studentID
     * @param type $status
     * @return type
     */
    public function logHistory($type, $actionByUserID, $portalUserID, $studentID, $status){
        
        global $DB;
        
        $obj = new \stdClass();
        $obj->actionbyusertype = $type;
        $obj->actionbyuserid = $actionByUserID;
        $obj->portaluserid = $portalUserID;
        $obj->userid = $studentID;
        $obj->status = $status;
        $obj->requesttime = time();
        return $DB->insert_record("portal_request_history", $obj);
        
    }
    
    public function getUserID(){
        
        return ($this->user) ? $this->user->id : false;
        
    }
    
    public function getVersion(){
        return $this->version;
    }
    
    public function getDBVersion(){
        return $this->dbversion;
    }
    
    public function loadUser($id){
        
        global $DB;
        
        $this->user = $DB->get_record("portal_users", array("id" => $id));
        
        if ($this->user)
        {
            $this->reloadStudents();
            $cnt = 0;
            $id = false;
            
            // If we only have one confirmed student, load them by default
            if ($this->students)
            {
                foreach($this->students as $student)
                {
                    if ($student->requestStatus == 1)
                    {
                        $cnt++;
                        $id = $student->id;
                    }
                }
            }
            
            if ($cnt == 1 && $id){
                $this->loadStudent($id);
            }
            
            
        }
        
        return $this->user;
        
    }
    
    public function loadStudentFromMoodle($studentID){
        
        global $DB;
                
        $this->student = $DB->get_record("user", array("id" => $studentID, "deleted" => 0, "confirmed" => 1));
        return $this->student;
                        
    }
    
    public function loadStudent($studentID){
        
        global $DB;
        
        if (!$this->user) return false;
        
        $check = $DB->get_record("portal_requests", array("portaluserid" => $this->user->id, "userid" => $studentID, "status" => PP_STATUS_CONFIRMED));
        if ($check){
        
            $this->student = $DB->get_record("user", array("id" => $studentID, "deleted" => 0, "confirmed" => 1));
            return $this->student;
        
        }
        
        return false;
        
    }
    
    public function getStudentRequests(){
        
        if (!$this->student) return false;
        
        global $DB;
        
        // Find student's links
        $sql = "SELECT req.id, u.id as userid, u.firstname, u.lastname, u.email, req.requesttime, req.status, req.password
                FROM {portal_requests} req
                INNER JOIN {portal_users} u ON u.id = req.portaluserid
                WHERE req.userid = ?
                ORDER BY req.requesttime DESC";

        return $DB->get_records_sql($sql, array($this->student->id));
        
    }
    
    public function getStudentHistory(){
        
        if (!$this->student) return false;
        
        global $DB;
        
        // Find student's links
        $sql = "SELECT req.id, u.id as userid, u.firstname, u.lastname, u.email, req.requesttime, req.status, req.actionbyusertype, req.actionbyuserid
                FROM {portal_request_history} req
                INNER JOIN {portal_users} u ON u.id = req.portaluserid
                WHERE req.userid = ?
                ORDER BY req.requesttime DESC";
        
        $records = $DB->get_records_sql($sql, array($this->student->id));
        
        $user = $DB->get_record("user", array("id" => $this->student->id));
        
        if ($records)
        {
            foreach($records as $record)
            {
                
                $record->moodleUser = $user;
                
            }
        }

        return $records;
        
    }
    
    public function updateRequestFromMoodle($request){
        
        global $DB, $USER;
                
        $DB->update_record("portal_requests", $request);
        $this->logHistory('moodle', $USER->id, $request->portaluserid, $request->userid, $request->status);
        
        $parentUser = $DB->get_record("portal_users", array("id" => $request->portaluserid));
        if ($parentUser)
        {
            $student = $DB->get_record("user", array("id" => $request->userid));
            $subject = $this->string['updateemailsubject'];
            $message = $this->string['updateemailcontent'];
            $message = str_replace("%u%", fullname($student), $message);
            $message = str_replace("%s%", $this->getStatusName($request->status), $message);
            $this->email($parentUser->email, $subject, $message);
        }
    }
    
    private function reloadStudents()
    {
        
        global $DB;
        
        $this->students = array();
        
        // Load associated students
        $records = $DB->get_records_select("portal_requests", "portaluserid = ? AND status >= ?", array($this->user->id, PP_STATUS_PENDING));
        if ($records)
        {
            foreach($records as $record)
            {
                $obj = $DB->get_record("user", array("id" => $record->userid));
                $obj->requestStatus = $record->status;
                $this->students[] = $obj;
            }
        }

        // Order students
        usort($this->students, function($a, $b){
            return ( ($a->lastname . ' ' . $a->firstname) > ($b->lastname . ' ' . $b->firstname) );
        });
        
    }
    
    public function isAdmin(){
        
        return ($this->user && $this->user->isadmin == 1);
        
    }
    
    public function isAuthenticated(){
        
        return ( isset($_SESSION['pp_user']) && isset($_SESSION['pp_user']->id) && ctype_digit($_SESSION['pp_user']->id));
        
    }
    
    public function buildPassword($pass, $salt){
        
        $password = $pass . '//' . $salt;
        $password = hash("SHA512", $password);
        return $password;
        
    }
    
    public function login(){
        
        global $DB;
        
        $email = trim($_POST['pp_login_email']);
        $pass = $_POST['pp_login_pass'];
        
        // First check if user with this email exists at all so we can get their password salt
        $check = $DB->get_record("portal_users", array("email" => $email, "deleted" => 0), "passwordsalt, confirmed");
        if (!$check){
            $this->errors['login'][] = $this->string['invalidlogin'];
            return false;
        }

        if ($check && $check->confirmed <> 1){
            $this->errors['login'][] = $this->string['accountnotconfirmed'];
            return false;
        }
        
        $pass = $this->buildPassword($pass, $check->passwordsalt);
        $check = $DB->get_record("portal_users", array("email" => $email, "password" => $pass, "deleted" => 0));
                
        if (!$check){
            $this->errors['login'][] = $this->string['invalidlogin'];
            return false;
        }
        
                
        $user = new \stdClass();
        $user->id = $check->id;
        $user->firstname = $check->firstname;
        $user->lastname = $check->lastname;
        $user->email = $check->email;
        $_SESSION['pp_user'] = $user;
        $_SESSION['pp_ssn'] = $this->generateRandomCode(12);
        
        return true;
        
        
    }
    
    /**
     * Generate a random string, used for passwords and confirmation codes
     * @param type $length
     * @return string
     */
    public function generateRandomCode($length)
    {
        
        $str = 'AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz01234567890123456789';
        $num = strlen($str) - 1;
        $ret = '';
        
        for($i = 0; $i < $length; $i++)
        {
            $rand = mt_rand(0, $num);
            $ret .= $str[$rand];
        }
       
        return $ret;
        
    }
    
    public function displayLogin(){
        
        $email = \pp_('pp_login_email');
        $pass = \pp_('pp_login_pass');
                
        $TPL = new \PP\Template();
        $TPL->set("title", $this->string['login'])
            ->set("email", $email)
            ->set("pass", $pass)
            ->set("errors", $this->displayAnyErrors('login'))
            ->set("Portal", $this)
            ->set("signupEnabled", $this->isSignupEnabled());
        
        $TPL->load( $this->dir . 'tpl/header.html' );
        $TPL->display();
        
        $TPL->load( $this->dir . 'tpl/login.html' );
        $TPL->display();
        
        $TPL->load( $this->dir . 'tpl/footer.html' );
        $TPL->display();
        
        
    }
    
    public function display(){
        
        
        $TPL = new \PP\Template();
        $TPL->set("user", $_SESSION['pp_user'])
            ->set("title", $this->string['portal'])
            ->set("Portal", $this)
            ->set("contentTitle", $this->string['content']);
        
        $page = (isset($_GET['page'])) ? $_GET['page'] : 'main';
        if (!file_exists($this->dir . 'tpl/'.$page.'.html')){
            $page = '404';
        }
        
        // If it's a plugin, is it enabled?
        if (in_array($page, array(
            'grades',
            'attendance',
            'coursereports',
            'timetable',
            'comments'
        )) && !$this->isPluginEnabled($page)){
            $page = '404';
        }
        
        $this->loadPage($page, $TPL);
                
        $TPL->load( $this->dir . 'tpl/header.html' );
        $TPL->display();
        
        $TPL->load( $this->dir . 'tpl/'.$page.'.html' );
        $TPL->display();
        
        $TPL->load( $this->dir . 'tpl/footer.html' );
        $TPL->display();
        
    }
        
    private function loadPage($page, $TPL)
    {
     
        global $CFG, $DB;
        
        $this->page = $page;
        
        $field = $this->getIDField();
        
        switch($page)
        {
            
            case 'request':
                
                $this->addBreadCrumb( $this->string['accessrequests'] );
                
                $TPL->set("contentTitle", $this->string['addnew']);
                $TPL->set("field", $this->getIDField());
                
                if (isset($_POST['sID']) && !isset($_POST['confirm'])){
                    
                    $method = $this->getAccessMethod();
                    
                    $params = array();
                    $params[$field] = $_POST['sID'];
                    $user = $DB->get_record("user", $params);
                    
                    if ($user && $method == 'idnumberdob')
                    {
                        
                        $dobOk = false;
                        
                        $fieldLocation = $this->getSetting('dob_location');
                        if (!$fieldLocation) $fieldLocation = 'custom';
                        $fieldName = $this->getSetting('dob_field');
                        
                        if ($fieldLocation == 'custom')
                        {
                        
                            $field = $DB->get_record("user_info_field", array("shortname" => $fieldName));
                            if ($field)
                            {

                                $fieldData = $DB->get_record("user_info_data", array("userid" => $user->id, "fieldid" => $field->id));
                                if ($fieldData && $fieldData->data == $_POST['sDOB'])
                                {
                                    $dobOk = true;
                                    $user->dob = $_POST['sDOB'];
                                }
                            }
                        
                        }
                        elseif ($fieldLocation == 'db')
                        {
                            
                            if (isset($user->$fieldName) && $user->$fieldName == $_POST['sDOB'])
                            {
                                $dobOk = true;
                                $user->dob = $_POST['sDOB'];
                            }
                            
                        }
                        
                        if (!$dobOk)
                        {
                            $user = false;
                        }
                    
                    }
                    
                    $TPL->set("foundUser", $user);
                    
                }
                
            break;
        
            case 'settings':
                
                $this->addBreadCrumb( $this->string['settings'] );
                $TPL->set("contentTitle", $this->string['settings']);
                
            break;
        
            case 'coursereports':                
                
                require_once $CFG->dirroot . '/blocks/elbp/lib.php';
                require_once $CFG->dirroot . '/blocks/elbp/ELBP.class.php';
                $ELBP = new \ELBP\ELBP( array('load_plugins' => false) );
                
                if ($this->student)
                {
                    $ELBP->loadStudent($this->student->id);                
                }
                    
                try {
                    $OBJ = \ELBP\Plugins\Plugin::instaniate("CourseReports");
                    if ($this->student)
                    {
                        $OBJ->loadStudent($this->student->id);
                    }
                    $TPL->set("OBJ", $OBJ);
                    $TPL->set("reports", $OBJ->getPeriodicalReports(0, true));
                    $TPL->set("contentTitle", $OBJ->getTitle());
                    $TPL->set("ELBP", $ELBP);
                    $this->addBreadCrumb( $OBJ->getTitle() );
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                
            break;
        
            case 'attendance':
                                
                require_once $CFG->dirroot . '/blocks/elbp/lib.php';
                require_once $CFG->dirroot . '/blocks/elbp/ELBP.class.php';
                $ELBP = new \ELBP\ELBP( array('load_plugins' => false) );
                
                if ($this->student)
                {
                    $ELBP->loadStudent($this->student->id);                
                }
                    
                try {
                    $ATT = \ELBP\Plugins\Plugin::instaniate("Attendance");
                    if ($this->student)
                    {
                        $ATT->loadStudent($this->student->id);
                    }
                    $TPL->set("ATT", $ATT);
                    $TPL->set("contentTitle", $ATT->getTitle());
                    $TPL->set("ELBP", $ELBP);
                    $this->addBreadCrumb( $ATT->getTitle() );
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                         
                
                
            break;
        
            case 'comments':
                                
                require_once $CFG->dirroot . '/blocks/elbp/lib.php';
                require_once $CFG->dirroot . '/blocks/elbp/ELBP.class.php';
                $ELBP = new \ELBP\ELBP( array('load_plugins' => false) );
                if ($this->student)
                {
                    $ELBP->loadStudent($this->student->id);                
                }     
                
                try {
                    $Comments = \ELBP\Plugins\Plugin::instaniate("Comments");
                    if ($this->student)
                    {
                        $Comments->loadStudent($this->student->id);
                    }
                    $TPL->set("comments", $Comments->getUserCommentsPublishedToPortal());
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                                                
                $this->addBreadCrumb( $Comments->getTitle() );
                $TPL->set("OBJ", $Comments);
                $TPL->set("contentTitle", $Comments->getTitle());
                
            break;
        
            case 'timetable':
                                
                require_once $CFG->dirroot . '/blocks/elbp/lib.php';
                require_once $CFG->dirroot . '/blocks/elbp/ELBP.class.php';
                $ELBP = new \ELBP\ELBP( array('load_plugins' => false) );
                
                if ($this->student)
                {
                    $ELBP->loadStudent($this->student->id);                
                }
                
                $css = $ELBP->loadCSS(true);
                $css .= "<link rel='stylesheet' type='text/css' href='{$CFG->wwwroot}/blocks/elbp_timetable/styles.css' />";
                $TPL->set("css", $css);
                $TPL->set("js", $ELBP->loadJavascript(true));
                                  
                try {
                    $TT = \ELBP\Plugins\Plugin::instaniate("elbp_timetable");
                    if ($this->student)
                    {
                        $TT->loadStudent($this->student->id);
                    }
                    $TPL->set("TT", $TT);
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                                
                $this->addBreadCrumb( $TT->getTitle() );
                $TPL->set("contentTitle", $TT->getTitle());
                
            break;
        
            case 'grades':
                
                $qualID = optional_param('qualID', false, PARAM_INT);
                $this->addBreadCrumb( get_string('pluginname', 'block_gradetracker') );
                $TPL->set("output", $this->loadGradeTracker($qualID));
                $TPL->set("contentTitle", get_string('pluginname', 'block_gradetracker'));
                
            break;
        
        
        
            case 'stats':
                                
                if ($this->isAdmin())
                {
                
                    $this->addBreadCrumb( $this->string['stats'] );
                    
                    // Stats
                    $numberOfAccounts = $DB->count_records("portal_users");
                    $numberOfActiveAccounts = $DB->count_records("portal_users", array("confirmed" => 1));
                    $numberOfStudents = $this->countStudentAccounts(); # Need setting to define what student account is
                    $numberOfRequests = $DB->count_records("portal_requests");
                    $numberOfPendingRequests = $DB->count_records("portal_requests", array("status" => 0));
                    $numberOfConfirmedRequests = $DB->count_records("portal_requests", array("status" => 1));
                    $numberOfRejectedCancelledRequests = $DB->count_records("portal_requests", array("status" => -1));
                    $numberOfStudentsWithLinks = $DB->count_records_sql("SELECT COUNT(u.id) 
                                                                         FROM {user} u
                                                                         INNER JOIN {portal_requests} r ON r.userid = u.id
                                                                         WHERE r.status = 1");

                    $TPL->set("contentTitle", $this->string['stats']);
                    $TPL->set("numberOfAccounts", $numberOfAccounts)
                        ->set("numberOfActiveAccounts", $numberOfActiveAccounts)
                        ->set("numberOfStudents", $numberOfStudents)
                        ->set("numberOfRequests", $numberOfRequests)
                        ->set("numberOfPendingRequests", $numberOfPendingRequests)
                        ->set("numberOfConfirmedRequests", $numberOfConfirmedRequests)
                        ->set("numberOfRejectedCancelledRequests", $numberOfRejectedCancelledRequests)
                        ->set("numberOfStudentsWithLinks", $numberOfStudentsWithLinks);
                
                }
                
            break;
        
            case 'users':
                
                if ($this->isAdmin())
                {
                    
                    $this->addBreadCrumb( $this->string['users'] );
                    $TPL->set("portalAccounts", $this->getPortalAccounts());
                    $TPL->set("contentTitle", $this->string['users']);
                                        
                }
                
            break;
            
            case 'user':
                
                if ($this->isAdmin())
                {
                    
                    $this->addBreadCrumb( $this->string['userprofile'], $this->www . '?page=users' );
                    $userID = $_GET['user'];
                    $account = $DB->get_record("portal_users", array("id" => $userID));
                    if ($account){
                        $this->addBreadCrumb( $account->firstname . ' ' . $account->lastname );
                    }
                    $TPL->set("account", $account);
                    $TPL->set("contentTitle", $this->string['user']);
                                        
                }
                
            break;
            
            case 'help':
                $this->addBreadCrumb( $this->string['help'] );
                $TPL->set("contentTitle", $this->string['help']);
            break;
        
            case 'main':
                
                require_once $CFG->dirroot . '/blocks/elbp/lib.php';
                require_once $CFG->dirroot . '/blocks/elbp/ELBP.class.php';
                
                global $ELBP;
                $ELBP = new \ELBP\ELBP( array('load_plugins' => false) );
                if ($this->student)
                {
                    
                    $ELBP->loadStudent($this->student->id);
                    $access = $ELBP->getUserPermissions($this->student->id);
                    
                    try {
                        $OBJ = \ELBP\Plugins\Plugin::instaniate("StudentProfile");
                        if ($this->student)
                        {
                            $OBJ->loadStudent($this->student->id);
                            $OBJ->setAccess($access);
                        }
                        $TPL->set("StudentProfile", $OBJ);
                    } catch (\ELBP\ELBPException $e){
                        echo $e->getException();
                    }
                    
                    // Course Reports
                    $CR = \ELBP\Plugins\Plugin::instaniate("CourseReports");
                    $CR->loadStudent($this->student->id);
                    $TPL->set("CR", $CR);
                    $TPL->set("reports", $CR->getPeriodicalReports(0, true));
                    
                    
                    // Comments
                    $Comments = \ELBP\Plugins\Plugin::instaniate("Comments");
                    $Comments->loadStudent($this->student->id);
                    $TPL->set("comments", $Comments->getUserCommentsPublishedToPortal());
                    $TPL->set("commentsObj", $Comments);
                    
                    
                    // GT
                    // Try new gradetracker block
                    $GT = \ELBP\Plugins\Plugin::instaniate("elbp_gradetracker");
                    // Try old bcgt block
                    if (!$GT){
                        $GT = \ELBP\Plugins\Plugin::instaniate("elbp_bcgt");
                    }
                    if ($GT)
                    {
                        $GT->loadStudent($this->student->id);
                        $TPL->set("quals", $GT->getSimpleQualsTargets());
                    }
                   
                                        
                }
                                
                $TPL->set("contentTitle", $this->string['snapshot']);
                
            break;
        
        }
        
        
        
    }
    
    public function getUserCourses()
    {
        global $DB;
        if (!$this->student)
        {
            return false;
        }
        return $DB->get_records_sql('SELECT DISTINCT c.* FROM {course} c INNER JOIN {context} cx on cx.instanceid = c.id INNER JOIN {role_assignments} ra on ra.contextid = cx.id WHERE ra.userid = ? AND ra.roleid = 5', array($this->getStudentID()));
    }
    
    
    private function getPortalAccounts(){
        
        global $DB;
        
        return $DB->get_records("portal_users", array("deleted" => 0), "lastname, firstname");
        
    }
    
    
    
    /**
     * Load the grade tracker of a particular student, listing all their current qualifications
     * @global type $CFG
     * @return string
     */
    private function loadGradeTracker($qualID = false)
    {
        
        global $CFG, $OUTPUT;
                
        if (!$this->student) return false;
        
        require_once $CFG->dirroot . '/blocks/gradetracker/lib.php';
        
        $GT = new \GT\GradeTracker();
        $Student = new \GT\User($this->student->id);
                
        $output = "";        
        $output .= $GT->loadJavascript(true);
        $output .= $GT->loadCSS(true, 'portal');
        
        $quals = $Student->getQualifications("STUDENT");
        if (!$quals){
            return "<p class='c'><em>".fullname($this->student)." is not on any qualifications which are currently supported in our Grade Tracking system.</em></p>";
        }
        
        
        $output .= "<div id='gradeHolder'>";
            $output .= "<div id='grade_tracker_content'>";
            
                foreach($quals as $qual)
                {
                    $output .= '<div id="grade_tracker_'.$qual->getID().'" class="grade_tracker_ind">';
                    $output .= '<h3>';
                    $output .= '<a href="'.$CFG->wwwroot.'/local/parentportal/?page=grades&studentid='.$Student->id.'&qualID='.$qual->getID().'">'.$qual->getDisplayName().'</a>';
                    $output .= '</h3>';
                            $output .= '<div class="grade_tracking_grid_ilp" id="grid_'.$qual->getID().'">';
                            $output .= '</div>';
                    $output .= '</div>';
                }
                
                if ($qualID)
                {
                    
                    $output .= "<hr>";
                    
                    $qualification = new \GT\Qualification\UserQualification($qualID);                    
                    if ($qualification->isValid() && $Student->isOnQual($qualID, "STUDENT"))
                    {
                        $qualification->loadStudent($Student);
                        $output .= "<a name='qual_anchor_{$qualification->getID()}'></a>";
                        $output .= "<h2 class='c'>{$qualification->getDisplayName()}</h2>";
                        $output .= $qualification->getStudentGrid('return', array(
                            'student' => $Student,
                            'access' => 'v',
                            'courseID' => 0,
                            'external' => true,
                            'extSsn' => $this->ssn()
                        ));
                        $output .= "<script>$(document).ready( function(){ pp_scroll_to_anchor('qual_anchor_{$qualification->getID()}'); } );</script>";
                    }
                    else
                    {
                        $output .= "Cannot load qualification";
                    }
                    
                }
            
            $output .= "</div>";
        $output .= "</div>";
                
        return $output;
        
    }
    
    /**
     * Get the external session string to check in GT
     * @return type
     */
    public function ssn(){
        return 'portal:'.$_SESSION['pp_ssn'];
    }
    
     /**
     * Loop through an array of errors and display them all
     * @return type
     */
    public function displayAnySuccess($type = false)
    {
        $output = "";
        $e = "";
                
        if ($type)
        {
            
            if(isset($this->success[$type]) && $this->success[$type])
            {
                
                foreach($this->success[$type] as $success)
                {
                    $e .= $success . "<br>";
                }

                $output .= $this->displaySuccessMsg($e, true);

            }
            
        }
        else
        {
        
            if($this->success)
            {
                
                foreach($this->success as $success)
                {
                    $e .= $success . "<br>";
                }

                $output .= $this->displaySuccessMsg($e, true);

            }
        
        }
        
        return $output;
    }
    
    
    
    /**
     * Loop through an array of errors and display them all
     * @return type
     */
    public function displayAnyErrors($type = false)
    {
        $output = "";
        $e = "";
        
        if ($type)
        {
            
            if(isset($this->errors[$type]) && $this->errors[$type])
            {
                foreach($this->errors[$type] as $error)
                {
                    $e .= $error . "<br>";
                }

                $output .= $this->displayErrorMsg($e, true);

            }
            
        }
        else
        {
        
            if($this->errors)
            {
                foreach($this->errors as $error)
                {
                    $e .= $error . "<br>";
                }

                $output .= $this->displayErrorMsg($e, true);

            }
        
        }
        
        return $output;
    }
    
    
    /**
     * Display an error message in a red error box
     * @param type $msg
     * @param type $return
     * @return type
     */
    public function displaySuccessMsg($msg, $return = false)
    {
        $output = "<span class='success'>{$msg}</span><br>";
        if ($return) return $output;
        else echo $output;
    }
    
   /**
     * Display an error message in a red error box
     * @param type $msg
     * @param type $return
     * @return type
     */
    public function displayErrorMsg($msg, $return = false)
    {
        $output = "<span class='error'>{$msg}</span><br>";
        if ($return) return $output;
        else echo $output;
    }
    
    
    public function listStudents()
    {
    
        global $OUTPUT;
        
        $field = $this->getIDField();
                
        $output = "";
        
            $output .= "<div class='c'><a href='{$this->www}?page=request'><img src='pix/add-icon.png' /> <small>{$this->string['addnew']}</small></a></div>";
            
            $output .= "<table>";
            
                if ($this->students)
                {
                    foreach($this->students as $student)
                    {
                     
                        $link = "";
                        $link .= "?studentid={$student->id}";
                        $link .= "&page={$this->page}";
                        
                        $class = ($this->getStudentID() == $student->id) ? 'chosen' : '';
                        
                        if ($student->requestStatus == PP_STATUS_CONFIRMED)
                        {
                            $output .= "<tr><td colspan='2'><a href='{$link}' class='{$class}'>".$OUTPUT->user_picture($student, array("size" => 35, "link" => false)) . " " . \fullname($student)." <small>({$student->$field})</small></a></td><td><a title='{$this->string['deleterequest']}' href='#' onclick='pp_deleteRequest({$student->id});return false;'><img src='pix/delete.png' /></a></td></tr>";
                        }
                        elseif ($student->requestStatus == PP_STATUS_PENDING)
                        {
                            $output .= "<tr title='{$this->string['pendingresponse']}'><td>".$OUTPUT->user_picture($student, array("size" => 35, "link" => false)) . " " . \fullname($student)." <small>({$student->$field})</small></td><td><img src='pix/pending.png' /></td><td><a href='#' onclick='pp_deleteRequest({$student->id});return false;' title='{$this->string['cancelrequest']}'><img src='pix/delete.png' /></a></td></tr>";
                        }
                                            
                    }
                    
                }
                else
                {
                    $output .= "<tr><td class='c' colspan='2'>{$this->string['nostudents']}</td><td></td></tr>";
                }
            
            $output .= "</table>";
        
        return $output;
        
    }
    
    public function countStudentAccounts(){
        
        global $DB;
        
        $sql = "SELECT COUNT(u.id) FROM {user} u WHERE u.deleted = 0 ";
        $where = $this->getSetting('students_sql');
        if ($where && !empty($where))
        {
            $sql .= "AND {$where} ";
        }
        $sql .= "ORDER BY u.lastname, u.firstname";
                
        return $DB->count_records_sql($sql);
        
    }
    
    public function getStudentAccounts(){
        
        global $DB;
        
        $sql = "SELECT u.* FROM {user} u WHERE u.deleted = 0 ";
        $where = $this->getSetting('students_sql');
        if ($where && !empty($where))
        {
            $sql .= "AND {$where} ";
        }
        $sql .= "ORDER BY u.lastname ASC, u.firstname ASC";
                        
        return $DB->get_records_sql($sql);
        
    }
    
    public function getStudent(){
        return $this->student;
    }
    
    public function getStudentID(){
        
        if ($this->student){
            return $this->student->id;
        } else {
            return -1;
        }
        
    }
    
    public function printUserBar(){
        
        if (!$this->student) return false;
        
        global $OUTPUT;
        
        $output = "";
        $output .= "<p class='c'>".$OUTPUT->user_picture($this->student, array('size' => 100, 'link' => false))."<br><b>".\fullname($this->student)." ({$this->student->username})</b></p><br><br>";
        return $output;
        
    }
    
    
     /**
     * Search for a parent account by first name, last name, email, or combination of first name & last name
     * @param type $search
     */
    public function searchParents($search)
    {
        
        global $DB;
        
        $sql = "SELECT * FROM {portal_users}
                WHERE 
                (
                    ( CONCAT(firstname, ' ', lastname) LIKE ? )
                    OR
                    ( lastname LIKE ? )
                    OR
                    ( firstname LIKE ? )
                    OR
                    ( email LIKE ? )
                )
                AND deleted = 0";
                
        $records = $DB->get_records_sql($sql, array('%'.$search.'%', '%'.$search.'%', '%'.$search.'%', '%'.$search.'%'));
        
        return $records;
        
    }
    
    
     /**
     * Search for a student account by first name, last name, email, or combination of first name & last name
     * Only returns students who have links to them in the parent_requests table. If a student isn't found, then
     * they have no requests of any status
     * @param type $search
     */
    public function searchStudents($search)
    {
        
        global $DB;
        
        $field = $this->getIDField();
        
        $sql = "SELECT u.* FROM {user} u WHERE u.deleted = 0 ";
        $where = $this->getSetting('students_sql');
        if ($where && !empty($where))
        {
            $sql .= "AND {$where} ";
        }
        
        $sql .= " AND (
            ( CONCAT(u.firstname, ' ', u.lastname) LIKE ? )
            OR
            ( u.lastname LIKE ? )
            OR
            ( u.firstname LIKE ? )
            OR
            ( u.{$field} LIKE ? )
        )";
        
        $sql .= "ORDER BY u.lastname ASC, u.firstname ASC";
                
        $records = $DB->get_records_sql($sql, array('%'.$search.'%', '%'.$search.'%', '%'.$search.'%', '%'.$search.'%'), 0, 101);
        
        return $records;
        
    }
    
    
     /**
     * Search for a student account by first name, last name, email, or combination of first name & last name
     * Only returns students who have links to them in the parent_requests table. If a student isn't found, then
     * they have no requests of any status
     * @param type $search
     */
    public function searchStudent($id)
    {
        
        global $DB;
        
        $sql = "SELECT u.* FROM {user} u WHERE u.deleted = 0 ";
        $where = $this->getSetting('students_sql');
        if ($where && !empty($where))
        {
            $sql .= "AND {$where} ";
        }
        $sql .= " AND u.id = ?";
                        
        $record = $DB->get_record_sql($sql, array($id));
        if(!$record) return false;
        
        // Find student's links
        $sql = "SELECT req.id, u.id as userid, u.firstname, u.lastname, u.email, req.requesttime, req.status
                FROM {portal_requests} req
                INNER JOIN {portal_users} u ON u.id = req.portaluserid
                WHERE req.userid = ? AND u.deleted = 0
                ORDER BY req.requesttime DESC"; # WHy am I ordering by this instead of time?

        $links = $DB->get_records_sql($sql, array($record->id));
        if($links)
        {
            $record->links = $links;
        }
        
        
        return $record;
        
    }
    
    
    
    /**
     * Given an id, load the info of a particular parent - their account info and their links
     * @param type $id 
     */
    public function searchParent($id)
    {
        
        global $CFG, $DB;
        
        $field = $this->getIDField();
        
        $record = $DB->get_record("portal_users", array("id" => $id, "deleted" => 0));
        if(!$record) return false;
        
        // FInd parent's links
        $sql = "SELECT req.id, u.id as userid, u.firstname, u.lastname, u.email, u.{$field}, req.requesttime, req.status
                FROM {portal_requests} req
                INNER JOIN {user} u ON u.id = req.userid
                WHERE req.portaluserid = ?
                ORDER BY req.requesttime DESC"; # WHy am I ordering by this instead of time?
                
        $links = $DB->get_records_sql($sql, array($record->id));
        if($links)
        {
            $record->links = $links;
        }
        
        return $record;
        
    }
    
    public function getStatusImage($i){
        
        $array = array(
            PP_STATUS_REJECTED => 'rejected.png',
            PP_STATUS_CANCELLED => 'cancelled.png',
            PP_STATUS_PENDING => 'question.png',
            PP_STATUS_CONFIRMED => 'approved.png'
        );
        
        return $array[$i];
        
    }
    
    public function getStatusName($i){
        
        $array = array(
             PP_STATUS_REJECTED => 'Rejected',
             PP_STATUS_CANCELLED => 'Cancelled',
             PP_STATUS_PENDING => 'Pending',
             PP_STATUS_CONFIRMED => 'Confirmed'
        );
        
        return $array[$i];
        
    }
    
    public function jquery(){
        
        global $CFG;
        
        require $CFG->dirroot . '/lib/jquery/plugins.php';
        $jquery = array(
            'jquery' => (isset($plugins['jquery']['files'])) ? reset($plugins['jquery']['files']) : false,
            'ui' => (isset($plugins['ui']['files'])) ? reset($plugins['ui']['files']) : false
        );
        
        return $jquery;
        
    }
    
    public static function canUserAccessStudent($userID, $studentID){
        
        global $DB;
        $result = $DB->get_record("portal_requests", array("portaluserid" => $userID, "userid" => $studentID, "status" => PP_STATUS_CONFIRMED));
        return $result;
        
    }
    
}