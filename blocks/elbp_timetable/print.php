<?php

/**
 * Print the fll timetable in the format of a one-day calendar
 * 
 * @copyright 2012 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 */

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/elbp/lib.php';

$ELBP = ELBP\ELBP::instantiate();
$DBC = new ELBP\DB();

// Need to be logged in to view this page
require_login();

try {
    $TT = \ELBP\Plugins\Plugin::instaniate("elbp_timetable");
    $TT->loadStudent($USER->id);
    $TT->connect();
    $TPL = new ELBP\Template();
    $TPL->set("TT", $TT);
    $TPL->load($CFG->dirroot . '/blocks/elbp_timetable/tpl/print.html');
    $TPL->display();
} catch (\ELBP\ELBPException $e){
    echo $e->getException();
}