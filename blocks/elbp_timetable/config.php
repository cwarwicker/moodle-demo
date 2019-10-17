<?php

/**
 * Configure the Timetable block
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

$view = optional_param('view', 'main', PARAM_ALPHA);

$access = $ELBP->getCoursePermissions(1);
if (!$access['god']){
    print_error( get_string('invalidaccess', 'block_elbp') );
}

// Need to be logged in to view this page
require_login();

try {
    $TT = \ELBP\Plugins\Plugin::instaniate("elbp_timetable");
} catch (\ELBP\ELBPException $e) {
    echo $e->getException();
    exit;
}


$TPL = new \ELBP\Template();
$MSGS['errors'] = '';
$MSGS['success'] = '';

// Submitted
if (!empty($_POST))
{
    if ($TT->saveConfig($_POST)){
        $MSGS['success'] = get_string('settingsupdated', 'block_elbp');
        $TPL->set("MSGS", $MSGS);
    }
}

// Set up PAGE
$PAGE->set_context( context_course::instance(1) );
$PAGE->set_url($CFG->wwwroot . '/blocks/elbp_timetable/config.php');
$PAGE->set_title( get_string('timetableconfig', 'block_elbp_timetable') );
$PAGE->set_heading( get_string('timetableconfig', 'block_elbp_timetable') );
$PAGE->set_cacheable(true);
$ELBP->loadJavascript();
$ELBP->loadCSS();

// If course is set, put that into breadcrumb
$PAGE->navbar->add( get_string('timetableconfig', 'block_elbp_timetable'), $CFG->wwwroot . '/blocks/elbp_timetable/config.php', navigation_node::TYPE_CUSTOM);

echo $OUTPUT->header();

$TPL->set("TT", $TT);
$TPL->set("view", $view);
$TPL->set("MSGS", $MSGS);

switch($view)
{
    case 'data':
        
        // Create directory for template csvs
        $TT->createDataDirectory('templates');
        
        $reload = (bool)optional_param('reload', 0, PARAM_INT);
        
        // If template csv doesn't exist, create it, otherwise get the file path
        $importFile = $TT->createTemplateImportCsv($reload);
        $TPL->set("importFile", $importFile);
        
        // If example csv doesn't exist, create it, otherwise get the file path
        $exampleFile = $TT->createExampleImportCsv($reload);
        $TPL->set("exampleFile", $exampleFile);
        
    break;

    case 'mis':
        
        $core = $TT->getMainMIS();
        if ($core){
            $conn = new \ELBP\MISConnection($core->id);
            $TPL->set("conn", $conn);
        }
        
    break;

}


try {
    $TPL->load( $CFG->dirroot . '/blocks/elbp_timetable/tpl/config.html' );
    $TPL->display();
} catch (\ELBP\ELBPException $e){
    echo $e->getException();
}

echo $OUTPUT->footer();