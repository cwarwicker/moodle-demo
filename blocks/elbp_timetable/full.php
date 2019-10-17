<?php

/**
 * Full timetable in the format of a one-day calendar
 * 
 * @copyright 2012 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 */

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/elbp/lib.php';

$ELBP = ELBP\ELBP::instantiate( array("load_plugins" => false) );
$DBC = new ELBP\DB();

// Need to be logged in to view this page
require_login();


// Set up PAGE
$PAGE->set_context( context_course::instance(1) );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp_timetable/full.php');
$PAGE->set_title( get_string('fulltimetable', 'block_elbp_timetable') );
$PAGE->set_heading( get_string('fulltimetable', 'block_elbp_timetable') );
$PAGE->set_cacheable(true);
$ELBP->loadJavascript();
$ELBP->loadCSS();

// If course is set, put that into breadcrumb
$PAGE->navbar->add( get_string('fulltimetable', 'block_elbp_timetable'), $CFG->wwwroot . '/blocks/elbp_timetable/full.php', navigation_node::TYPE_CUSTOM);
$PAGE->navbar->add( fullname($USER), null, navigation_node::TYPE_CUSTOM);

echo $OUTPUT->header();

try {
    $TT = \ELBP\Plugins\Plugin::instaniate("elbp_timetable");
    $TT->loadStudent($USER->id);    
    $TT->connect();
    $TT->setAccess( array("user"=>true) );
    $TT->buildCSS();
    $TT->buildFull( array("format"=>true) );    
} catch (\ELBP\ELBPException $e){
    echo $e->getException();
}


$TPL = new ELBP\Template();
try {
    $TPL->load($CFG->dirroot . '/blocks/elbp/tpl/footer.html');
    $TPL->display();
} catch (\ELBP\ELBPException $e){
    echo $e->getException();
}

echo $OUTPUT->footer();