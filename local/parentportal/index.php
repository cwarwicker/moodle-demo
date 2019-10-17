<?php
/**
 * Index page for Parent Portal
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

define('PARENT_PORTAL', TRUE); # We are running from the index script

require_once '../../config.php';
require_once 'lib.php';

$page = @$_GET['page'];
if ($page == 'logout'){
    session_destroy();
    header('Location:'.$CFG->wwwroot . '/local/parentportal/');
    exit;
}

$PAGE->set_context( \context_course::instance(1) );
$PAGE->set_url( $CFG->wwwroot . $_SERVER['REQUEST_URI'] );

ob_start();

$Portal = new \PP\Portal();

// Check if we need to install it
if (!$Portal->isInstalled()){
    
    $TPL = new \PP\Template();
    $TPL->set("Portal", $Portal);
    $Portal->loadInstall($TPL);
    
    $TPL->load( $Portal->dir . 'tpl/install.html' );
    $TPL->display();
    
    exit;
}




if (!$Portal->isAuthenticated()){
    
    // This is a student responding from Moodle, so they won't be authenticated in the portal, as they have no account
    if ($page == 'respond' && isset($_GET['req']) && ctype_digit($_GET['req']) && isset($_GET['password'])){
        $check = $Portal->checkResponseAccess($_GET['req'], $_GET['password']);
        
        // POST responses
        if ($check)
        {
            if (isset($_POST['accept_request']))
            {
                $Portal->accept($check->reqid);
            }
            elseif (isset($_POST['reject_request']))
            {
                $Portal->reject($check->reqid);
            }
        }
        
        $TPL = new \PP\Template();
        $TPL->set("Portal", $Portal);
        $TPL->set("title", $Portal->string['respondtoreq']);
        $TPL->set("access", $check);
        
        $TPL->load( $Portal->dir . 'tpl/header.html' );
        $TPL->display();
        $TPL->load( $Portal->dir . 'tpl/respond.html' );
        $TPL->display();
        $TPL->load( $Portal->dir . 'tpl/footer.html' );
        $TPL->display();
        exit;
    }
    
    if (isset($_POST['pp_login'])){
        if ($Portal->login()){
            header('Location:'.$Portal->www);
            exit;
        }
    }
    
    
    $Portal->displayLogin();
    exit;
    
}

// CHeck if we need an update
if ($Portal->isAdmin() && $Portal->getVersion() > $Portal->getDBVersion())
{
    $Portal->update();
    echo nl2br($GLOBALS['pp_trace']);
    exit;
}

$Portal->display();