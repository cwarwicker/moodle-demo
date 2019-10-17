<?php
define('PARENT_PORTAL', TRUE); # We are running from the index script

require_once '../../config.php';
require_once 'lib.php';

$PAGE->set_context( context_system::instance() );

$user = @$_GET['user'];
$code = @$_GET['code'];

if (!$user || !$code) exit;

$check = $DB->get_record("portal_users", array("id" => $user, "passwordresetcode" => $code, "deleted" => 0));
if (!$check){
    
    echo "INVALID PASSWORD RESET CODE OR USER ACCOUNT";
    exit;
    
}

$Portal = new \PP\Portal();

// Form submission
if (isset($_POST['pp_reset']) && !empty($_POST['pp_new_pass']) && !empty($_POST['pp_new_pass_2']))
{
    
    // Check passwords
    $pw = trim($_POST['pp_new_pass']);
    $pw2 = trim($_POST['pp_new_pass_2']);
    
    if (strlen($pw) < 6){
        $Portal->errors['reset'][] = $Portal->string['pw6chars'];
    }
        
    if ($pw !== $pw2){
        $Portal->errors['reset'][] = $Portal->string['pwnomatch'];
    }
    
    // If no errors
    if (!$Portal->errors)
    {
        
        // Update user
        $salt = $Portal->generateRandomCode(20);
        $password = $Portal->buildPassword($pw, $salt);
        
        $obj = new \stdClass();
        $obj->id = $check->id;
        $obj->password = $password;
        $obj->passwordsalt = $salt;
        // May as well confirm them as well, since they must have gone through email to get code
        $obj->confirmed = 1;
        $obj->passwordresetcode = null;
        
        $DB->update_record("portal_users", $obj);
        
        // Email
        $content = $Portal->string['resetemail2'];
        $Portal->email($check->email, $Portal->string['passwordreset'], $content);
        
        $Portal->success['reset'][] = $Portal->string['pwresetconfirmed'];
        
        
    }
    
    
}

$TPL = new \PP\Template();
$TPL->set("title", $Portal->string['passwordreset'])
    ->set("errors", $Portal->displayAnyErrors('reset'))
    ->set("success", $Portal->displayAnySuccess('reset'))
    ->set("Portal", $Portal)
    ->set("user", $check);

$TPL->load( $Portal->dir . 'tpl/header.html' );
$TPL->display();

$TPL->load( $Portal->dir . 'tpl/reset.html' );
$TPL->display();

$TPL->load( $Portal->dir . 'tpl/footer.html' );
$TPL->display();

