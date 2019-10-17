<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version details
 *
 * @package   theme_adaptable
 * @copyright 2015-2019 Jeremy Hopkins (Coventry University)
 * @copyright 2015-2019 Fernando Acedo (3-bits.com)
 * @copyright 2017-2019 Manoj Solanki (Coventry University)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

defined('MOODLE_INTERNAL') || die();

// Set HTTPS if needed.
if (empty($CFG->loginhttps)) {
    $wwwroot = $CFG->wwwroot;
} else {
    $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
}

// Check if this is a course or module page and check setting to hide site title.
// If not one of these pages, by default show it (set $hidesitetitle to false).
if ( (strstr($PAGE->pagetype, 'course')) ||
     (strstr($PAGE->pagetype, 'mod')) && ($this->page->course->id > 1) ) {
    $hidesitetitle = !empty(($PAGE->theme->settings->coursepageheaderhidesitetitle)) ? true : false;
} else {
    $hidesitetitle = false;
}

// Screen size.
theme_adaptable_initialise_zoom($PAGE);
$setzoom = theme_adaptable_get_zoom();

theme_adaptable_initialise_full($PAGE);
$setfull = theme_adaptable_get_full();

// Navbar.
if (isset($PAGE->theme->settings->stickynavbar) && $PAGE->theme->settings->stickynavbar == 1
    && $PAGE->pagetype != "grade-report-grader-index" && $PAGE->bodyid != "page-grade-report-grader-index") {
    $fixedheader = true;
} else {
    $fixedheader = false;
}

// JS calls.
$PAGE->requires->js_call_amd('theme_adaptable/bsoptions', 'init', array($fixedheader));
$PAGE->requires->js_call_amd('theme_adaptable/drawer', 'init');

// Layout.
$left = (!right_to_left());  // To know if to add 'pull-right' and 'desktop-first-column' classes in the layout for LTR.

$hasmiddle = $PAGE->blocks->region_has_content('middle', $OUTPUT);
$hasfootnote = (!empty($PAGE->theme->settings->footnote));

$hideheadermobile = $PAGE->theme->settings->hideheadermobile;
$hidealertsmobile = $PAGE->theme->settings->hidealertsmobile;
$hidesocialmobile = $PAGE->theme->settings->hidesocialmobile;

// Navbar Menu.
$shownavbar = false;
if (
    (isloggedin() && !isguestuser()) ||
    (!empty($PAGE->theme->settings->enablenavbarwhenloggedout)) ) {

    // Remove menu navbar in Quiz pages even if they don't use SEB.
    if ($PAGE->pagetype != "mod-quiz-attempt") {
        $shownavbar = true;
    }
}
// Load header background image if exists.
$headerbg = '';

if (!empty($PAGE->theme->settings->headerbgimage)) {
    $headerbg = ' style="background-image: url('.$PAGE->theme->setting_file_url('headerbgimage', 'headerbgimage').');
                         background-position: 0 0; background-repeat: no-repeat; background-size: cover;"';
}

// Choose the header style.  There styles available are:
// "style1"  (original header)
// "style2"  (2 row header).

$adaptableheaderstyle = "style1";

if (!empty($PAGE->theme->settings->headerstyle)) {
    $adaptableheaderstyle = $PAGE->theme->settings->headerstyle;
}

// User image, name in user menu dropdown.
$userpic = '';
$username = '';
$usermenu = '';
// Only used when user is logged in.
if (isloggedin()) {
    // User icon.
    $userpic = $OUTPUT->user_picture($USER, array('link' => false, 'visibletoscreenreaders' => false,
               'size' => 50, 'class' => 'userpicture'));
    // User name.
    $username = format_string(fullname($USER));

    // User menu dropdown.
    if (!empty($PAGE->theme->settings->usernameposition)) {
        $usernameposition = $PAGE->theme->settings->usernameposition;
        if ($usernameposition == 'right') {
            $usernamepositionleft = false;
        } else {
            $usernamepositionleft = true;
        }
    } else {
        $usernamepositionleft = true;
    }

    // Set template data.
    $data = [
        'username' => $username,
        'userpic' => $userpic,
        'showusername' => $PAGE->theme->settings->showusername,
        'usernamepositionleft' => $usernamepositionleft,
        'userprofilemenu' => $OUTPUT->user_profile_menu(),
    ];
    $usermenu = $OUTPUT->render_from_template('theme_adaptable/usermenu', $data);
}

// Select fonts used.
$fontname = '';
$fontheadername = '';
$fonttitlename = '';
$fontweight = '';
$fontheaderweight = '';
$fonttitleweight = '';
$fontssubset = '';

switch ($PAGE->theme->settings->fontname) {
    case 'default':
        // Get the default font used by the browser.
    break;

    default:
        // Get the Google fonts.
        $fontname = str_replace(" ", "+", $PAGE->theme->settings->fontname);
        $fontheadername = str_replace(" ", "+", $PAGE->theme->settings->fontheadername);
        $fonttitlename = str_replace(" ", "+", $PAGE->theme->settings->fonttitlename);

        $fontweight = ':400,400i';
        $fontheaderweight = ':400,400i';
        $fonttitleweight = ':700,700i';
        $fontssubset = '';

        // Get the Google Font weights.
        $fontweight = ':'.$PAGE->theme->settings->fontweight.','.$PAGE->theme->settings->fontweight.'i';
        $fontheaderweight = ':'.$PAGE->theme->settings->fontheaderweight.','.$PAGE->theme->settings->fontheaderweight.'i';
        $fonttitleweight = ':'.$PAGE->theme->settings->fonttitleweight.','.$PAGE->theme->settings->fonttitleweight.'i';

        // Get the Google fonts subset.
        if (!empty($PAGE->theme->settings->fontsubset)) {
            $fontssubset = '&subset='.$PAGE->theme->settings->fontsubset;
        } else {
            $fontssubset = '';
        }
    break;
}

// Get the HTML for the settings bits.
$html = theme_adaptable_get_html_for_settings($OUTPUT, $PAGE);

if (right_to_left()) {
    $regionbsid = 'region-bs-main-and-post';
} else {
    $regionbsid = 'region-bs-main-and-pre';
}

// Social icons class.
$showicons = "";
$showicons = $PAGE->theme->settings->blockicons;
if ($showicons == 1) {
    $showiconsclass = "showblockicons";
} else {
    $showiconsclass = " ";
}

$standardscreenwidthclass = 'standard';
if (!empty($PAGE->theme->settings->standardscreenwidth)) {
    $standardscreenwidthclass = $PAGE->theme->settings->standardscreenwidth;
}

// Setting for default screen view. Does not override user's preference.
$defaultview = "";
$defaultview = $PAGE->theme->settings->viewselect;
if ($defaultview == 1 && $setfull == "") {
    $setfull = "fullin";
}



// HTML header.
echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="icon" href="<?php echo $OUTPUT->favicon(); ?>" />

<?php
// HTML head.
echo $OUTPUT->standard_head_html() ?>
    <!-- CSS print media -->
    <link rel="stylesheet" type="text/css" href="<?php echo $wwwroot; ?>/theme/adaptable/style/print.css" media="print">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Twitter Card data -->
    <meta name="twitter:card" value="summary">
    <meta name="twitter:site" value="<?php echo $SITE->fullname; ?>" />
    <meta name="twitter:title" value="<?php echo $OUTPUT->page_title(); ?>" />

    <!-- Open Graph data -->
    <meta property="og:title" content="<?php echo $OUTPUT->page_title(); ?>" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo $wwwroot; ?>" />
    <meta name="og:site_name" value="<?php echo $SITE->fullname; ?>" />

    <!-- Chrome, Firefox OS and Opera on Android topbar color -->
    <meta name="theme-color" content="<?php echo $PAGE->theme->settings->maincolor; ?>" />

    <!-- Windows Phone topbar color -->
    <meta name="msapplication-navbutton-color" content="<?php echo $PAGE->theme->settings->maincolor; ?>" />

    <!-- iOS Safari topbar color -->
    <meta name="apple-mobile-web-app-status-bar-style" content="<?php echo $PAGE->theme->settings->maincolor; ?>" />

    <?php
    // Load fonts.
    if ((!empty($fontname)) && ($fontname != 'default') && ($fontname != 'custom')) {
        ?>
    <!-- Load Google Fonts -->
    <link href='https://fonts.googleapis.com/css?family=<?php echo $fontname.$fontweight.$fontssubset; ?>'
    rel='stylesheet'
    type='text/css'>
    <?php
    }
    ?>

    <?php
    if ((!empty($fontheadername)) && ($fontheadername != 'default') && ($fontname != 'custom')) {
    ?>
        <link href='https://fonts.googleapis.com/css?family=<?php echo $fontheadername.$fontheaderweight.$fontssubset; ?>'
        rel='stylesheet'
        type='text/css'>
    <?php
    }
    ?>

    <?php
    if ((!empty($fonttitlename)) && ($fonttitlename != 'default') && ($fontname != 'custom')) {
    ?>
        <link href='https://fonts.googleapis.com/css?family=<?php echo $fonttitlename.$fonttitleweight.$fontssubset; ?>'
        rel='stylesheet'
        type='text/css'>
    <?php
    }
    ?>
</head>

<body <?php echo $OUTPUT->body_attributes(array('two-column', $setzoom, 'header-'.$adaptableheaderstyle)); ?>>

<?php
echo $OUTPUT->standard_top_of_body_html();

// Development or wrong moodle version alert.
// echo $OUTPUT->get_dev_alert();.
?>

<div id="page" class="container-fluid <?php echo "$setfull $showiconsclass $standardscreenwidthclass"; ?>">

<?php
    // If the device is a mobile and the alerts are not hidden or it is a desktop then load and show the alerts.
if (((theme_adaptable_is_mobile()) && ($hidealertsmobile == 1)) || (theme_adaptable_is_desktop())) {
    // Display alerts.
    echo $OUTPUT->get_alert_messages();
}

// Background image in Header.
?>


<?php if ($adaptableheaderstyle == "style1") : ?>

    <header id="adaptable-page-header-wrapper" <?php echo $headerbg; ?> >

    <div id="above-header" class="mb-2 mb-lg-3">
        <div class="container">
            <nav class="navbar navbar-expand btco-hover-menu">

                <?php if ($shownavbar) { ?>
                <div id="adaptable-page-header-nav-drawer" data-region="drawer-toggle" class="d-lg-none mr-3">
                    <button id="drawer" aria-expanded="false" aria-controls="nav-drawer" type="button"
                            class="nav-link float-sm-left mr-1" data-side="left">
                        <i class="fa fa-bars fa-fw " aria-hidden="true"></i>
                        <span class="sr-only"><?php echo get_string('sidepanel'); ?></span>
                    </button>
                </div>
                <?php } ?>

                <div class="collapse navbar-collapse">
                <?php
                if (empty($PAGE->theme->settings->menuslinkright)) {
                    echo '<div class="my-auto m-1">' . $OUTPUT->get_top_menus() . '</div>';
                }
                ?>

                    <ul class="navbar-nav ml-auto my-auto">

                        <div class="pull-left">
                            <?php echo $OUTPUT->user_menu(); ?>
                        </div>

                        <?php
                        if (!empty($PAGE->theme->settings->menuslinkright)) {
                            echo '<div class="my-auto m-1">' . $OUTPUT->get_top_menus() . '</div>';
                        }
                        ?>

                        <?php
                        if (!empty($PAGE->theme->settings->smallscreenshowsearchicon)) {
                            $classes = 'd-xs-block d-sm-block d-md-none my-auto';
                        } else {
                            $classes = 'd-none';
                        }
                        ?>
                        <li class="nav-item <?php echo $classes; ?> mx-1 my-auto">
                            <a class="nav-link" href="<?php p($wwwroot) ?>/course/search.php">
                                <i class="icon fa fa-search fa-fw " title="Search" aria-label="Search"></i>
                            </a>
                        </li>

                        <?php

                        // Remove Messages and Notifications icons in Quiz pages even if they don't use SEB.
                        if ($PAGE->pagetype != "mod-quiz-attempt") {
                            echo '<div class="my-auto mx-1">' . $OUTPUT->navbar_plugin_output() . '</div>';
                        }

                        if (empty($PAGE->layout_options['langmenu']) || $PAGE->layout_options['langmenu']) {
                            echo '<li class="nav-item dropdown ml-2 my-auto">';
                            echo $OUTPUT->lang_menu();
                            echo '</li>';
                        }

                        if (!isloggedin() || isguestuser()) {
                            echo $OUTPUT->page_heading_menu();

                            if ($PAGE->theme->settings->displaylogin == 'box') {
                                // Login button.
                        ?>
                        <form id="pre-login-form" class="form-inline my-2 my-lg-0" action="<?php p($wwwroot) ?>/login/index.php"
                            method="post">
                        <input type="hidden" name="logintoken" value="<?php echo s(\core\session\manager::get_login_token()); ?>" />
                        <input type="text" name="username"
                                    placeholder="<?php echo get_string('loginplaceholder', 'theme_adaptable'); ?>" size="11">
                        <input type="password" name="password"
                                    placeholder="<?php echo get_string('passwordplaceholder', 'theme_adaptable'); ?>"  size="11">
                        <button class="btn-login" type="submit"><?php echo get_string('logintextbutton', 'theme_adaptable'); ?>
                        </button>
                        </form>
                        <?php
                            } else if ($PAGE->theme->settings->displaylogin == 'button') {
                        ?>
                            <form id="pre-login-form" class="form-inline my-2 my-lg-0"
                                action="<?php p($wwwroot) ?>/login/index.php" method="post">
                            <input type="hidden" name="logintoken"
                                value="<?php echo s(\core\session\manager::get_login_token()); ?>"/>
                            <button class="btn-login" type="submit">
                                <?php echo get_string('logintextbutton', 'theme_adaptable'); ?>
                            </button>
                            </form>
                        <?php
                            }
                        } else {
                            // Display user profile menu.
                            ?>

                            <li class="nav-item dropdown ml-3 ml-md-4 mr-2 mr-md-0">
                                <?php echo $usermenu; ?>
                            </li>

                        <?php
                        }
                    ?>

                    </ul>
                </div>
            </nav>
        </div>
    </div>

    <?php
    // If it is a mobile and the header is not hidden or it is a desktop then load and show the header.
    if (((theme_adaptable_is_mobile()) && ($hideheadermobile == 1)) || (theme_adaptable_is_desktop())) {
    ?>

    <div id="page-header" class="container">
        <div class="row">

        <div class="d-none d-lg-block col-lg-4">
        <?php
        // Site title or logo.
        if (!$hidesitetitle) {
            echo '<div class="d-flex align-items-start bd-highlight">';
            echo $OUTPUT->get_logo_title();
            echo '</div>';
        }
        ?>
        </div>

        <div class="col-lg-8 p-0 my-auto">
            <?php
            // Remove Search Box or Social icons in Quiz pages even if they don't use SEB.
            if ($PAGE->pagetype != "mod-quiz-attempt") {
                // Social icons.
                if ($PAGE->theme->settings->socialorsearch == 'social') {
                    // If it is a mobile and the social icons are not hidden or it is a desktop then load and show the social icons.
                    if (((theme_adaptable_is_mobile()) && ($hidesocialmobile == 1)) || (theme_adaptable_is_desktop())) {
                        ?>
                        <div class="socialbox pull-right">
                            <?php
                            echo $OUTPUT->socialicons();
                            ?>
                        </div>
                        <?php
                    }
                }
                    ?>

                <?php
                // Search box.
                if ( (!$hidesitetitle) && ($PAGE->theme->settings->socialorsearch == 'search') ) { ?>
                    <div class="searchbox d-none d-lg-block">
                        <form action="<?php echo $wwwroot; ?>/course/search.php">
                            <label class="hidden" for="search-1" style="display: none;"><?php echo get_string("searchcourses")?>
                            </label>
                            <div class="search-box grey-box bg-white clear-fix">
                                <input placeholder="<?php echo get_string("searchcourses", "theme_adaptable"); ?>"
                                        accesskey="6"
                                        class="search_tour bg-white no-border left search-box__input ui-autocomplete-input"
                                        type="text"
                                        name="search"
                                        id="search-1"
                                        autocomplete="off">
                                        <button title="<?php echo get_string("searchcourses", "theme_adaptable")?>"
                                                type="submit" class="no-border bg-white pas search-box__button">
                                                <abbr class="fa fa-search"
                                                    title="<?php echo get_string("searchcourses", "theme_adaptable");?>">
                                                </abbr>
                                        </button>
                            </div>
                        </form>
                    </div>
                <?php
                }
            }
                ?>

                <div id="course-header">
                    <?php echo $OUTPUT->course_header(); ?>
                </div>

            </div>
            </div>

    </div>

    <?php
    }
    ?>

<?php endif; // End header style 1. ?>

<?php // Begin header style 2 (2 row header).  This includes a css class ID called "header2". ?>
<?php if ($adaptableheaderstyle == "style2") : ?>

    <header id="adaptable-page-header-wrapper" <?php echo $headerbg; ?> >

    <div id="header2" class="container">

      <div class="row">

        <div class="d-none d-lg-block col-lg-4">
        <div class="d-flex align-items-start bd-highlight">

            <?php
            // Site title or logo.
            if (!$hidesitetitle) {
                echo $OUTPUT->get_logo_title();
            }
            ?>

            <div id="course-header">
                <?php echo $OUTPUT->course_header(); ?>
            </div>
        </div>
        </div>

        <div class="col-lg-8 p-0 my-auto">

            <nav class="navbar navbar-expand btco-hover-menu">

                <?php if ($shownavbar) { ?>
                <div id="adaptable-page-header-nav-drawer" data-region="drawer-toggle" class="d-lg-none mr-3">
                    <button id="drawer" aria-expanded="false" aria-controls="nav-drawer" type="button"
                    class="nav-link float-sm-left mr-1" data-side="left">
                        <i class="fa fa-bars fa-fw " aria-hidden="true"></i>
                        <span class="sr-only"><?php echo get_string('sidepanel'); ?></span>
                    </button>
                </div>
                <?php } ?>

                <div class="collapse navbar-collapse">

                    <ul class="navbar-nav ml-auto">

                        <div class="my-auto">
                            <?php echo $OUTPUT->search_box(); ?>
                        </div>

                        <?php
                            echo '<div class="my-auto m-1">' . $OUTPUT->get_top_menus($showtext = false) . '</div>';
                        ?>

                        <div class="pull-left mr-2 my-auto">
                            <?php echo $OUTPUT->user_menu(); ?>
                        </div>

                        <?php
                        if (!empty($PAGE->theme->settings->smallscreenshowsearchicon)) {
                            $classes = 'd-xs-block d-sm-block d-md-none';
                        } else {
                            $classes = 'd-none';
                        }
                        ?>

                        <?php

                        echo '<div class="my-auto mx-1">' . $OUTPUT->navbar_plugin_output() . '</div>';

                        $showlangtext = false;

                        if (empty($PAGE->layout_options['langmenu']) || $PAGE->layout_options['langmenu']) {
                            echo '<div class="my-auto">' . $OUTPUT->lang_menu($showlangtext) . '</div>';
                        }

                        if (!isloggedin() || isguestuser()) {
                            echo $OUTPUT->page_heading_menu();

                            if ($PAGE->theme->settings->displaylogin == 'box') {
                                // Login button.
                        ?>
                        <form id="pre-login-form" class="form-inline my-auto m-1"
                            action="<?php p($wwwroot) ?>/login/index.php" method="post">
                        <input type="hidden" name="logintoken" value="<?php echo s(\core\session\manager::get_login_token()); ?>" />
                        <input type="text" name="username"
                                    placeholder="<?php echo get_string('loginplaceholder', 'theme_adaptable'); ?>" size="11">
                        <input type="password" name="password"
                                    placeholder="<?php echo get_string('passwordplaceholder', 'theme_adaptable'); ?>"  size="11">
                        <button class="btn-login" type="submit"><?php echo get_string('logintextbutton', 'theme_adaptable'); ?>
                        </button>
                        </form>
                        <?php
                            } else if ($PAGE->theme->settings->displaylogin == 'button') {
                        ?>
                            <form id="pre-login-form" class="form-inline my-auto m-1"
                                action="<?php p($wwwroot) ?>/login/index.php" method="post">
                            <input type="hidden" name="logintoken"
                                value="<?php echo s(\core\session\manager::get_login_token()); ?>"/>
                            <button class="btn-login" type="submit">
                                <?php echo get_string('logintextbutton', 'theme_adaptable'); ?>
                            </button>
                            </form>
                        <?php
                            }
                        } else {
                            // Display user profile menu.
                            ?>

                            <li class="nav-item dropdown ml-3 ml-md-2 mr-2 mr-md-0 my-auto">
                                <?php echo $usermenu; ?>
                            </li>

                        <?php
                        }
                    ?>

                    </ul>
                </div>
            </nav>
        </div>
      </div>
    </div>

<?php endif; // End header style 2. ?>


<?php
// Navbar Menu.
if ($shownavbar) {
?>

<div id="nav-drawer" data-region="drawer" class="d-print-none moodle-has-zindex closed" aria-hidden="true" tabindex="-1">
    <div id="nav-drawer-inner">
        <nav class="list-group">
            <ul class="list-unstyled components">

                <?php
                echo $OUTPUT->navigation_menu('main-navigation-drawer');

                if (empty($PAGE->theme->settings->disablecustommenu)) {
                    echo $OUTPUT->custom_menu_drawer();
                }
                if ($PAGE->theme->settings->enabletoolsmenus) {
                    echo $OUTPUT->tools_menu('tools-menu-drawer');
                }
                ?>

            </ul>
        </nav>

        <nav class="list-group m-t-1">
            <?php echo $OUTPUT->context_mobile_settings_menu(); ?>
            <a class="list-group-item list-group-item-action " href="<?php echo $CFG->wwwroot.'/admin/search.php'; ?>">
                <div class="m-l-0">
                    <div class="media">
                        <span class="media-left">
                            <i class="icon fa fa-wrench fa-fw" aria-hidden="true"></i>
                        </span>
                        <span class="media-body "><?php echo get_string('administrationsite'); ?></span>
                    </div>
                </div>
            </a>
        </nav>
    </div>
</div>

<div id="main-navbar" class="d-none d-lg-block">
    <div class="container">
        <div class="navbar navbar-expand-md btco-hover-menu">
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">

            <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">

            <nav role="navigation" aria-label="<?php echo get_string('sitelinkslabel', 'theme_adaptable'); ?>">
                <ul class="navbar-nav">
                    <?php echo $OUTPUT->navigation_menu('main-navigation'); ?>

                    <?php
                    if (empty($PAGE->theme->settings->disablecustommenu)) {
                        echo $OUTPUT->custom_menu();
                    }
                    if ($PAGE->theme->settings->enabletoolsmenus) {
                        echo $OUTPUT->tools_menu();
                    }
                    ?>
                </ul>
            </nav>

                <ul class="navbar-nav ml-auto">

                    <?php
                    $navbareditsettings = $PAGE->theme->settings->editsettingsbutton;
                    $showcog = true;
                    $showeditbuttons = false;

                    if ($navbareditsettings == 'button') {
                        $showeditbuttons = true;
                        $showcog = false;
                    } else if ($navbareditsettings == 'cogandbutton') {
                        $showeditbuttons = true;
                    }

                    $coursemenucontent = $OUTPUT->context_header_settings_menu();
                    if ($showcog) {
                        if ($coursemenucontent) {
                    ?>
                            <li class="nav-item mr-2">
                                <div class="context-header-settings-menu">
                                    <?php echo $coursemenucontent; ?>
                                </div>
                            </li>
                    <?php
                        }
                    }

                    $othermenucontent = $OUTPUT->region_main_settings_menu();
                    if ($showcog) {
                        if ($othermenucontent) {
                    ?>
                            <li class="nav-item mr-2">
                                <div id="region-main-settings-menu" class="region-main-settings-menu">
                                    <?php echo $othermenucontent; ?>
                                </div>
                            </li>
                    <?php
                        }
                    }
                    ?>

                    <?php
                    // Ensure to only hide the button on relevant pages.  Some pages will need the button, such as the
                    // dashboard page. Checking if the cog is being displayed above to figure out if it still needs to
                    // show (when there is no cog). Also show mod pages (e.g. Forum, Lesson) as these sometimes have
                    // a button for a specific purpose.
                    if ( ($showeditbuttons) || (
                        (empty($coursemenucontent)) &&
                        (empty($othermenucontent))
                        ) ||
                        (strstr($PAGE->pagetype, 'mod-'))
                    ) {
                        $pageheadingbutton = $OUTPUT->page_heading_button();
                        if (!empty($pageheadingbutton)) {
                        ?>
                        <li class="nav-item mx-0">
                             <div id="edittingbutton" class="breadcrumb-button">
                                <?php echo $pageheadingbutton; ?>
                            </div>
                        </li>
                        <?php
                        }
                    }
                    ?>

                    <?php
                    if (isloggedin()) {
                        if ($PAGE->theme->settings->enableshowhideblocks) { ?>
                           <li class="nav-item mr-1 hbl">
                               <a href="javascript:void(0);" class="nav-link moodlezoom"
                                   title="<?php echo get_string('hideblocks', 'theme_adaptable') ?>">
                                   <i class="fa fa-indent fa-lg"></i>
                                   <span class="zoomdesc"><?php echo get_string('hideblocks', 'theme_adaptable') ?></span>
                               </a>
                           </li>
                           <li class="nav-item mr-1 sbl">
                               <a href="javascript:void(0);" class="nav-link moodlezoom"
                                   title="<?php echo get_string('showblocks', 'theme_adaptable') ?>">
                                   <i class="fa fa-outdent fa-lg"></i>
                                   <span class="zoomdesc"><?php echo get_string('showblocks', 'theme_adaptable') ?></span>
                               </a>
                           </li>
                    <?php
                        }

                        if ($PAGE->theme->settings->enablezoom) { ?>
                            <li class="nav-item mx-0 hbll">
                                <a class="nav-link moodlewidth" href="javascript:void(0);"
                                    title="<?php echo get_string('fullscreen', 'theme_adaptable') ?>">
                                    <i class="fa fa-expand fa-lg"></i>
                                    <?php if ($PAGE->theme->settings->enablezoomshowtext) { ?>
                                    <span class="zoomdesc"><?php echo get_string('fullscreen', 'theme_adaptable') ?></span>
                                    <?php } ?>
                                </a>
                            </li>
                            <li class="nav-item mx-0 sbll">
                                <a class="nav-link moodlewidth" href="javascript:void(0);"
                                    title="<?php echo get_string('standardview', 'theme_adaptable') ?>">
                                    <i class="fa fa-compress fa-lg"></i>
                                    <?php if ($PAGE->theme->settings->enablezoomshowtext) { ?>
                                    <span class="zoomdesc"><?php echo get_string('standardview', 'theme_adaptable') ?></span>
                                    <?php } ?>
                                </a>
                            </li>
                    <?php
                        }
                    }
                    ?>
                </ul>

            </div>
        </div>
    </div>
</div>

<?php
}
?>

</header>

<?php

// Display News Ticker.
echo $OUTPUT->get_news_ticker();
