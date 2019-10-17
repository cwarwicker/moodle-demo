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

namespace DF;

class Site
{

  private $title;
  private $url;
  private $dir;
  private $data;
  private $version;
  private $build;

  private $uin;
  private $privacy;
  private $admin;
  private $email;
  private $plugins = array();



  private $availablePlugins = array(
    'assignfeedback_gradetracker',
    'block_bc_dashboard',
    'block_elbp',
    'block_elbp_timetable',
    'block_gradetracker',
    'block_quick_course',
    'block_quick_user',
    'local_parentportal'
  );

  public function __construct()
  {

    global $CFG;

    $siteCourse = \get_site();

    $this->title = $siteCourse->fullname;
    $this->url = $CFG->wwwroot;
    $this->dir = $CFG->dirroot;
    $this->data = $CFG->dataroot;
    $this->version = \moodle_major_version();
    $this->build = \get_config(null, 'version');

    // Load any saved settings
    $this->uin = \get_config('local_df_hub', 'uin');
    $this->privacy = \get_config('local_df_hub', 'privacy');
    $this->admin = \get_config('local_df_hub', 'admin');
    $this->email = \get_config('local_df_hub', 'email');
    $this->notifications = \get_config('local_df_hub', 'notifications');

    // Check plugins
    $this->plugins = $this->loadInstalledPlugins();

  }

  public function getTitle(){
    return $this->title;
  }

  public function getURL(){
    return $this->url;
  }

  public function getDir(){
    return $this->dir;
  }

  public function getData(){
    return $this->data;
  }

  public function getVersion(){
    return $this->version;
  }

  public function getBuild(){
    return $this->build;
  }

  public function isRegistered(){
    return ($this->getUIN() !== false);
  }

  public function getUIN(){
    return $this->uin;
  }

  public function getPrivacy(){
    return $this->privacy;
  }

  public function getNotifications(){
    return $this->notifications;
  }

  public function getAdminName(){

    $adminObj = \get_admin();
    return ($this->admin !== false) ? $this->admin : \fullname($adminObj);

  }

  public function getAdminEmail(){
    $adminObj = \get_admin();
    return ($this->email !== false) ? $this->email : $adminObj->email;
  }

  public function loadInstalledPlugins(){

    $plugins = array();

    foreach($this->availablePlugins as $component){

      // Is it installed?
      $version = \get_config($component, 'version');
      if ($version !== false){
        $plugins[] = $component;
      }

    }

    return $plugins;

  }

  public function getInstalledPlugins(){
    return $this->plugins;
  }

  public function getPluginVersion($component){
    return \get_config($component, 'version');
  }

  private function getPluginDirectory($component){

    list($plugintype, $pluginname) = \core_component::normalize_component($component);
    return \core_component::get_plugin_directory($plugintype, $pluginname);

  }

  public function getPluginStats($component){

    global $CFG;

    $stats = array();

    $directory = $this->getPluginDirectory($component);

    $file = $directory . '/classes/df_hub/stats.php';
    if (file_exists($file)){
      include $file;
    }

    return $stats;

  }

  /**
   * Get the date this was last updated, in an optional date format or just unix timestamp
   * @param  boolean $format [description]
   * @return [type]          [description]
   */
  public function getLastUpdated($format = false){
      $unix = \get_config('local_df_hub', 'lastupdate');
      return ($unix && $format) ? date($format, $unix) : $unix;
  }

  public function printRegistrationForm(){

    $TPL = new \DF\Template();
    $TPL->set('site', $this);
    $TPL->load( $this->dir . '/local/df_hub/tpl/register_form.html' );
    $TPL->display();

  }

}