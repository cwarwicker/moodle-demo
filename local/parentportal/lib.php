<?php
require_once $CFG->dirroot . '/local/parentportal/classes/Template.class.php';
require_once $CFG->dirroot . '/local/parentportal/classes/Portal.class.php';

define('PP_STATUS_PENDING', 0);
define('PP_STATUS_CONFIRMED', 1);
define('PP_STATUS_CANCELLED', -1);
define('PP_STATUS_REJECTED', -2);


function pp_($var){
    
    return (isset($_POST[$var])) ? pp_html($_POST[$var]) : '';
    
}

function pp_html($txt, $nl2br = false){
    
    $txt = htmlentities($txt, ENT_QUOTES);
    if ($nl2br) $txt = nl2br($txt);
    return $txt;
    
}

function pp_trace($txt){
    
    if (!isset($GLOBALS['pp_trace'])) $GLOBALS['pp_trace'] = '';
    $GLOBALS['pp_trace'] .= $txt . "\n";
    
}

function pp_fullname($user){
    
    return $user->firstname .  " " . $user->lastname;
    
}