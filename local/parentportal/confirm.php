<?php
define('PARENT_PORTAL', TRUE); # We are running from the index script

require_once '../../config.php';
require_once 'lib.php';

$id = @$_GET['id'];
$code = @$_GET['code'];

if (!$id || !$code) exit;

$check = $DB->get_record("portal_users", array("id" => $id, "confirmationcode" => $code));
if (!$check){
    
    echo "INVALID CONFIRMATION CODE";
    exit;
    
}

if ($check->confirmed == 1){
    echo "ACCOUNT IS ALREADY CONFIRMED";
    exit;
}

$check->confirmed = 1;
$DB->update_record("portal_users", $check);

echo "ACCOUNT CONFIRMED SUCCESSFULLY<br>";
echo "<a href='{$CFG->wwwroot}/local/parentportal'>".get_string('login')."</a>";
exit;