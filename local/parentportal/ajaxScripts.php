<?php
define('PARENT_PORTAL', TRUE); # We are running from the index script

require_once '../../config.php';
require_once $CFG->dirroot . '/local/parentportal/lib.php';
$portal = new \PP\Portal();

$PAGE->set_context( \context_course::instance(1) );

include $CFG->dirroot . '/local/parentportal/lang/'.$CFG->lang.'/local_parentportal.php';

$action = required_param('action', PARAM_TEXT);
$params = $_POST['params'];
$params = (object) $params;

switch($action)
{
    
    
    case 'load_display_elbp':
        
        require_once $CFG->dirroot . '/blocks/elbp/lib.php';
        $ELBP = \ELBP\ELBP::instantiate();
        $elbp_portal = $ELBP->getPlugin("elbp_portal");
        $elbp_portal->ajax("load_display_type", (array)$params, $ELBP);
        exit;
        
    break;

    case 'update_status_elbp':
        
        require_once $CFG->dirroot . '/blocks/elbp/lib.php';
        $ELBP = \ELBP\ELBP::instantiate();
        $elbp_portal = $ELBP->getPlugin("elbp_portal");
        $elbp_portal->ajax("update_request_status", (array)$params, $ELBP);
        exit;
        
    break;

    
    // Use the AJAX search for parent accounts
    case 'search_parents':

        // Stop if we;re not admin
        if(!$portal->isAdmin()) exit;

        if(isset($params->search))
        {

            $results = $portal->searchParents($params->search);
            
            // If there are results build options for them
            if($results)
            {
                foreach($results as $result)
                {
                    $fname = htmlentities($result->firstname, ENT_QUOTES);
                    $lname = htmlentities($result->lastname, ENT_QUOTES);
                    $email = htmlentities($result->email, ENT_QUOTES);
                    echo " $('#parentAccountSelect').append('<option value=\"{$result->id}\">{$fname} {$lname} ({$email})</option>'); ";
                }
            }
                        
        }
        
    break;
    
    // use the AJAX student search - this will only find students with links to parents
    case 'search_students':
        
        // Stop if we're not admin
        if(!$portal->isAdmin()) exit;

        if(isset($params->search))
        {
            
            $results = $portal->searchStudents($params->search);
            
            // If there are results build options for them
            if($results)
            {
                foreach($results as $result)
                {
                    $name = htmlentities( fullname($result), ENT_QUOTES );
                    echo " $('#studentAccountSelect').append('<option value=\"{$result->id}\">{$name} ({$result->username})</option>'); ";
                }
            }
            
            if ( count($results) > 100 )
            {
                echo " $('#studentAccountSelect').append('<option value=\"\">{$string['toomany']}</option>'); ";
            }
            
        }
        
    break;
    
    // Load the info for a parent, such as their details, their links to students, etc...
    case 'load_parent':
        
        // Stop if we're not admin
        if(!$portal->isAdmin()) exit;
        
        $field = $portal->getIDField();

        if(isset($params->id))
        {
            
            $result = $portal->searchParent($params->id);
            
            if($result)
            {
                
                // If parent's account is not confirmed display a link to confirm it for them
                $result->isConfirmed = ($result->confirmed) ? 'Yes' : '<small><a href=\'#\' onclick=\'pp_confirmParentAccount('.$result->id.');return false;\'>[Confirm Account]</a></small>';
                
                $output = "";
                $output .= "<table class='accountInfo'>";
                    $output .= "<tr><td colspan='2' class='c'><a href='{$portal->www}?page=user&user={$result->id}'><img src='".$OUTPUT->image_url('t/editstring', 'core')."' alt='edit' /></a><br><br></td></tr>";
                    $output .= "<tr><td>ID</td><td>{$result->id}</td></tr>";
                    $output .= "<tr><td>{$string['name']}</td><td>{$result->firstname} {$result->lastname}</td></tr>";
                    $output .= "<tr><td>{$string['email']}</td><td>{$result->email}</td></tr>";
                $output .= "</table>";
                $output .= "<br>";    
                $output .= "<p class='c'><b>{$string['currentrequests']}:</b></p>";
                $output .= "<br>";
                    
                if(isset($result->links))
                {
                    $output .= "<table class='accountInfo' id='requestHistory'>";
                    $output .= "<tr><th>{$string['student']}</th><th>{$string['status']}</th><th>{$string['time']}</th></tr>";

                    foreach($result->links as $link)
                    {
                        $class = strtolower($portal->getStatusName($link->status));
                        $link->status = $portal->getStatusName($link->status);
                        $output .= "<tr class='{$class}'><td><a href='#' onclick='pp_loadStudentInfo({$link->userid});return false;'>".pp_fullname($link)." ({$link->$field})</a></td><td>{$link->status}</td><td>".date('D jS M Y, H:i', $link->requesttime)."</td></tr>";
                    }

                    $output .= "</table>";
                }
                    
                echo <<<JS
                $('#accountOutput').html("{$output}");
JS;
            }
            else
            {
                echo " $('#accountOutput').html('<em>Unable to load parent</em>'); ";
            }
            
        }
        
    break;
    
    // Load a student's info, such as details, parent links, etc...
    case 'load_student':
        
        // Stop if we're not admin
        if(!$portal->isAdmin()) exit;
        
        $field = $portal->getIDField();

        if(isset($params->id))
        {
            
            $result = $portal->searchStudent($params->id);
            
            if($result)
            {
                
                $output = "";
                $output .= "<table class='accountInfo'>";
                
                    $output .= "<tr><td>ID</td><td>{$result->$field}</td></tr>";
                    $output .= "<tr><td>Name</td><td>{$result->firstname} {$result->lastname}</td></tr>";
                    $output .= "<tr><td>Email</td><td>{$result->email}</td></tr>";

                $output .= "</table>";
                $output .= "<br>";    
                $output .= "<p class='c'><b>{$string['currentrequests']}:</b></p>";
                $output .= "<br>";
                
                if(isset($result->links))
                {
                    $output .= "<table class='accountInfo' id='requestHistory'>";
                    $output .= "<tr><th>{$string['user']}</th><th>{$string['status']}</th><th>{$string['time']}</th><th></th><th></th></tr>";

                    foreach($result->links as $link)
                    {
                        $statusID = $link->status;
                        $class = strtolower($portal->getStatusName($link->status));
                        $link->status = $portal->getStatusName($link->status);
                        $buttons = "";
                        $buttons .= "<td><a href='#' onclick='pp_cancelAccess({$link->userid}, {$result->id});return false;' class='P{$link->userid}S{$result->id}_REMOVE'><img src='pix/delete.png' alt='remove' title='Cancel Access' /></a></td>";
                        $buttons .= "<td><a href='#' onclick='pp_confirmAccess({$link->userid}, {$result->id});return false;' class='P{$link->userid}S{$result->id}_REMOVE'><img src='pix/small_tick.png' alt='confirm' title='Confirm Access' /></a></td>";
                        $output .= "<tr class='{$class}' id='P{$link->userid}S{$result->id}_ROW'><td><a href='#' onclick='pp_loadParentInfo({$link->userid});return false;'>{$link->firstname} {$link->lastname}</a></td><td id='P{$link->userid}S{$result->id}_STATUS'>{$link->status}</td><td>".date('D jS M Y, H:i', $link->requesttime)."</td>{$buttons}</tr>";
                    }

                    $output .= "</table>";
                }
                    
                echo <<<JS
                $('#accountOutput').html("{$output}");
JS;
                
            }
            else
            {
                echo " $('#accountOutput').html('<em>Unable to load student</em>'); ";
            }
        
        }
        
    break;
    
    
       
    
    
    // Cancel the access a parent has to a particular student
    case 'cancel_access':
        
        // Stop if not admin
        if(!$portal->isAdmin()) exit;

        // Check if parent has access
        $check = $DB->get_record("portal_requests", array("portaluserid" => $params->id, "userid" => $params->sid)); 
        if(!$check) exit;
        
        $check->status = PP_STATUS_CANCELLED;
        $DB->update_record("portal_requests", $check);
        
        $portal->logHistory('portal', $portal->getUserID(), $params->id, $params->sid, PP_STATUS_CANCELLED);
                
        echo " $('#P{$params->id}S{$params->sid}_STATUS').text('Cancelled'); $('#P{$params->id}S{$params->sid}_ROW').attr('class', 'cancelled'); $('#P{$params->id}S{$params->sid}_REMOVE').remove(); ";
        
    break;
    
    
    // Cancel the access a parent has to a particular student
    case 'confirm_access':
        
        // Stop if not admin
        if(!$portal->isAdmin()) exit;

        // Check if parent has access
        $check = $DB->get_record("portal_requests", array("portaluserid" => $params->id, "userid" => $params->sid)); 
        if(!$check) exit;
        
        $check->status = PP_STATUS_CONFIRMED;
        $DB->update_record("portal_requests", $check);
        
        $portal->logHistory('portal', $portal->getUserID(), $params->id, $params->sid, PP_STATUS_CONFIRMED);
                
        echo " $('#P{$params->id}S{$params->sid}_STATUS').text('Confirmed'); $('#P{$params->id}S{$params->sid}_ROW').attr('class', 'confirmed'); $('.P{$params->id}S{$params->sid}_REMOVE').remove(); ";
        
    break;
    
    
}