<?php

/**
 * <title>
 * 
 * @copyright 2012 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 */

namespace ELBP\Plugins;

// The calendar doesn't seem to work without the timezone being UTC, I think it's to do with the DateTime calls, not sure
date_default_timezone_set("UTC");

require_once $CFG->dirroot . '/blocks/elbp/lib.php';
require_once $CFG->dirroot . '/blocks/elbp_timetable/classes/Lesson.class.php';

/**
 * 
 */
class elbp_timetable extends Plugin {
    
    
    const DEFAULT_START_HOUR = 9;
    const DEFAULT_END_HOUR = 22;
    const DEFAULT_MINUTES = 15;
    
    protected $tables = array(
        'lbp_timetable'
    );
            
    /**
     * Construct timetable object
     * @param type $install
     */
    function __construct($install = false) {
        if ($install){
            parent::__construct( array(
                "name" => strip_namespace(get_class($this)),
                "title" => "Timetable",
                "path" => "/blocks/elbp_timetable/",
                "version" => \ELBP\ELBP::getBlockVersionStatic()
            ) );
        }
        else
        {
            parent::__construct( strip_namespace(get_class($this)) );
        }
    }
 
    /**
     * Connect to MIS
     */
    public function connect(){
        
        if ($this->getSetting("use_direct_mis") == 1){
            $this->loadMISConnection();
            if ($this->connection && $this->connection->connect()){
                $core = $this->getMainMIS();
                if ($core){
                    $pluginConn = new \ELBP\MISConnection($core->id);
                    if ($pluginConn->isValid()){
                        $this->useMIS = true;
                        $this->plugin_connection = $pluginConn;
                        $this->setupMisRequirements();
                    }
                }
            }
        }
        
    }
    
    /**
     * Get the MIS settings and values
     */
    private function setupMisRequirements(){
        
        $this->mis_settings = array();
        
        // Settings
        $this->mis_settings['view'] = $this->getSetting('mis_view_name');
        $this->mis_settings['postconnection'] = $this->getSetting('mis_post_connection_execute');
        $this->mis_settings['dateformat'] = $this->getSetting('mis_date_format');
        if (!$this->mis_settings['dateformat']) $this->mis_settings['dateformat'] = 'd-m-Y';
        $this->mis_settings['mis_username_or_idnumber'] = $this->getSetting('mis_username_or_idnumber');
        if (!$this->mis_settings['mis_username_or_idnumber']) $this->mis_settings['mis_username_or_idnumber'] = 'username';
        
        // Mappings
        $reqFields = array("id", "daynum", "dayname", "username", "lesson", "staff", "course", "room", "starttime", "endtime", "startdate", "enddate");
        foreach($reqFields as $reqField)
        {
            $this->mis_settings['mapping'][$reqField] = $this->plugin_connection->getFieldMap($reqField);
        }
        
        // If there are any queries to be executed after connection, run them
        if ($this->mis_settings['postconnection'] && !empty($this->mis_settings['postconnection'])){
            $this->connection->query($this->mis_settings['postconnection']);
        }
        
    }
    
    
        
    
    /**
     * Get the default start hour value incase we haven't set it in settings
     * @return type
     */
    public function getDefaultStartHour(){
        return self::DEFAULT_START_HOUR;
    }
    
    /**
     * Get the default end hour value incase we haven't set it in settings
     * @return type
     */
    public function getDefaultEndHour(){
        return self::DEFAULT_END_HOUR;
    }
    
    /**
     * Get the default minutes value.
     * >>BEDCOLL Decide which way to do these getDefault* methods on timetable class
     * @return type
     */
    public function getDefaultMinutes(){
        $minutes = $this->getSetting('minutes');
        return ($minutes) ? $minutes : self::DEFAULT_MINUTES;
    }
    
    /**
     * Get all the student's classes
     * @param type $params
     * @return \ELBP\Plugins\Timetable\Lesson
     */
    public function getAllClasses($params)
    {
                       
        $classes = array();
            
        // If we're using MIS
        if ($this->useMIS)
        {
            
            if (isset($params['days']))
            {
                                                            
                // Loop through days and get any classes then
                foreach( (array)$params['days'] as $day )
                {

                    $dayNumberFormat = $this->getSetting('mis_day_number_format');
                    $dayNumber = date($dayNumberFormat, $day['unix']);
                                        
                    $userField = $this->mis_settings['mis_username_or_idnumber'];
                    $username = $this->student->$userField;
                    
                    $fields = $this->plugin_connection->getAllMappingsForSelect(true);
                                        
                    $query = $this->connection->query("SELECT {$fields} FROM {$this->connection->wrapValue($this->mis_settings['view'])}
                                                         WHERE {$this->connection->wrapValue($this->plugin_connection->getFieldMap('username'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('username')}
                                                         AND {$this->connection->wrapValue($this->plugin_connection->getFieldMap('daynum'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('daynum')}",
                                                                 array(
                                                                        $this->plugin_connection->getFieldMap('username') => $username,
                                                                        $this->plugin_connection->getFieldMap('daynum') => $dayNumber
                                                                      )
                                                                 );
                    
                    
                    $results = $this->connection->getRecords($query);
                                                                                                    
                    if ($results)
                    {
                        
                        foreach ($results as $result)
                        {
                            
                            /**
                             * Dates MUST be in the format: dd-mm-yyyy
                             */
                            if (isset($result[$this->plugin_connection->getFieldAliasOrMap('startdate')]))
                            {
                                
                                // Either:
                                // start date <= weekstart AND enddate >= weekend - e.g. started last week or this week and ends next week or this week
                                // OR
                                // start date >= weekstart AND start date <= weekend - e.g. started this week
                                                                                                
                                $dateFormatStart = \DateTime::createFromFormat('d-m-Y', $result[$this->plugin_connection->getFieldAliasOrMap('startdate')]);
                                $dateFormatEnd = \DateTime::createFromFormat('d-m-Y', $result[$this->plugin_connection->getFieldAliasOrMap('enddate')]);

                                if ($dateFormatStart && $dateFormatEnd)
                                {
                                
                                    $ymdStart = $dateFormatStart->format("Ymd");
                                    $ymdEnd = $dateFormatEnd->format("Ymd");

                                    if ( ( $ymdStart <= $params['weekStart']['ymd'] && $ymdEnd >= $params['weekEnd']['ymd']) 
                                            || 
                                         ( $ymdStart >= $params['weekStart']['ymd'] && $ymdStart <= $params['weekEnd']['ymd'] ) 
                                       )  {
                                        $classes[] = new \ELBP\Plugins\Timetable\Lesson($this, $result);
                                    }
                                
                                }
                                
                                
                            }
                            else
                            {                          
                                $classes[] = new \ELBP\Plugins\Timetable\Lesson($this, $result);
                            }
                        }
                    }
                                        

                }
                                 
                return $classes;
                
                
            }
            else
            {
                
                $fields = $this->plugin_connection->getAllMappingsForSelect(true);
                
                // Get all classes in the external db for this user, not filtering by start/end date, etc...
                $results = $this->connection->select( $this->getMisSetting("view"),
                                                        array( $this->plugin_connection->getFieldMap("username") => $params['username'] ),
                                                        $fields
                                                    );
                                
                if ($results){
                    foreach ($results as $result)
                    {
                        $classes[] = new \ELBP\Plugins\Timetable\Lesson($this, $result);
                    }
                }
                
                return $classes;
            }
            
            
            
        }
        
        
        // Else we're using Moodle DB
        else
        {
            
            // Loop through days and get any classes then
            foreach( (array)$params['days'] as $day )
            {
                
                $dayNumberFormat = $this->getSetting('mis_day_number_format');
                $dayNumber = date($dayNumberFormat, $day['unix']);
                
                $dbParams = array( $this->student->id, $params['weekStart']['ymd'], $params['weekEnd']['ymd'], $params['weekStart']['ymd'], $params['weekEnd']['ymd'], $dayNumber );
                
                $results = $this->DB->get_records_select("lbp_timetable", "userid = ? AND (  (startdate <= ? AND enddate >= ?) OR (startdate >= ? AND startdate <= ?) ) AND daynumber = ?", $dbParams, "id ASC", "id");
                          
                if ($results){
                    foreach ($results as $result)
                    {
                        $classes[] = new \ELBP\Plugins\Timetable\Lesson($this, $result->id);
                    }
                }
                
            }
            
        }        
                        
        return $classes;
        
    }
    
    /**
     * Find all the classes on a given date
     * @param DateTime $date
     * @return array
     */
    public function getClassesByDate($date)
    {
        
        $classes = array();
        
        
        // If we're using MIS
        if ($this->useMIS)
        {
            
            $unix = strtotime( $date->format("Ymd") );
            $dayNumberFormat = $this->getSetting('mis_day_number_format');
            $dayNumber = date($dayNumberFormat, $unix);
            
            $userField = $this->mis_settings['mis_username_or_idnumber'];
            $username = $this->student->$userField;
            
            $fields = $this->plugin_connection->getAllMappingsForSelect(true);
                                                
            $query = $this->connection->query("SELECT {$fields} FROM {$this->connection->wrapValue($this->mis_settings['view'])}
                                                 WHERE {$this->connection->wrapValue($this->plugin_connection->getFieldMap('username'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('username')}
                                                 AND {$this->connection->wrapValue($this->plugin_connection->getFieldMap('daynum'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('daynum')}",
                                                         array(
                                                                $this->plugin_connection->getFieldMap('username') => $username,
                                                                $this->plugin_connection->getFieldMap('daynum') => $dayNumber,
                                                              )
                                                         );
            
                                                 
            $results = $this->connection->getRecords($query);
            
            if ($results){
                
                foreach($results as $result)
                {
                   
                    /*
                     * For the day filtering to work, the dates MUST be in the format: dd-mm-yyyy
                     */
                    if (isset($result[$this->plugin_connection->getFieldAliasOrMap('startdate')]))
                    {
                        
                        $dateFormatStart = \DateTime::createFromFormat('d-m-Y', $result[$this->plugin_connection->getFieldAliasOrMap('startdate')]);
                        $dateFormatEnd = \DateTime::createFromFormat('d-m-Y', $result[$this->plugin_connection->getFieldAliasOrMap('enddate')]);
                        
                        if ($dateFormatStart && $dateFormatEnd)
                        {
                            $ymdStart = $dateFormatStart->format("Ymd");
                            $ymdEnd = $dateFormatEnd->format("Ymd");

                            $today = $date->format("Ymd");

                            if ($ymdStart <= $today && $ymdEnd >= $today)
                            {
                                $classes[] = new \ELBP\Plugins\Timetable\Lesson($this, $result);
                            }
                        }
                        
                    }
                    else
                    {
                        $classes[] = new \ELBP\Plugins\Timetable\Lesson($this, $result);
                    }
                    
                }
                
            }
                       
            
        }
        
        
        // Else we're using Moodle DB
        else
        {
            
            $unix = strtotime( $date->format("Ymd") );
            $dayNumberFormat = $this->getSetting('mis_day_number_format');
            $dayNumber = date($dayNumberFormat, $unix);
            
            $params = array($this->student->id, $dayNumber, $date->format("Ymd"), $date->format("Ymd"));
            $results = $this->DB->get_records_select("lbp_timetable", 
                                                    "userid = ? AND daynumber = ? AND startdate <= ? AND enddate >= ?",
                                                    $params,
                                                    "starttime ASC", 
                                                    "id");            
            foreach ((array)$results as $result)
            {
                $classes[] = new \ELBP\Plugins\Timetable\Lesson($this, $result->id);
            }
            
        }
        
        return $classes; 
        
    }
    
    /**
     * Get all the student's timetable slots in a given month
     * @param type $params
     * @return type
     */
    private function getClassesByMonth($params)
    {
        
        $classes = array();
        
        $month = $params['monthStart']->format("m");
        
        // Loop through days until we've moved onto another month
        $day = $params['monthStart'];
        
        while($day->format("m") == $month)
        {
            
            // Get classes for day
            $dayClasses = $this->getClassesByDate($day);
            
            // Sort
            $dayClasses = \ELBP\Plugins\Timetable\Lesson::sortLessons($dayClasses);
                        
            // Add to array to return
            $classes[$day->format("d")] = $dayClasses;
            
            // Increment day
            $unix = strtotime("+1 day", $day->format("U"));
            $day = \DateTime::createFromFormat("U", $unix);
            
        }
        
        return $classes;
        
    }
    
    /**
     * Get all student's classes for particular day
     * @param type $dayNumber
     */
    public function getClassesByDay($dayNumber, $options = null)
    {
     
        $classes = array();
                
        // If we're using MIS
        if ($this->useMIS)
        {
            
            $today = date('Ymd');
            $userField = $this->mis_settings['mis_username_or_idnumber'];
            
            if (isset($options['username'])) $username = $options['username'];
            else $username = $this->student->$userField;
            
            $fields = $this->plugin_connection->getAllMappingsForSelect(true);
            
            $query = $this->connection->query("SELECT {$fields} 
                                                 FROM {$this->connection->wrapValue($this->mis_settings['view'])}
                                                 WHERE {$this->connection->wrapValue($this->plugin_connection->getFieldMap('username'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('username')}
                                                 AND {$this->connection->wrapValue($this->plugin_connection->getFieldMap('daynum'))} {$this->connection->comparisonOperator()} :{$this->plugin_connection->getFieldMap('daynum')}
                                                 ORDER BY {$this->connection->wrapValue($this->plugin_connection->getFieldMapQueryClause('starttime'))}",
                                                         array(
                                                                $this->plugin_connection->getFieldMap('username') => $username,
                                                                $this->plugin_connection->getFieldMap('daynum') => $dayNumber,
                                                              )
                                                         );
            
            $results = $this->connection->getRecords($query);
            
                                               
            if ($results){
                
                foreach ($results as $result)
                {
                    
                    /**
                     * For the date filtering to work, the dates MUST be in the format: dd-mm-yyyy
                     */
                    if (isset($result[$this->plugin_connection->getFieldAliasOrMap('startdate')]))
                    {
                        
                        $dateFormatStart = \DateTime::createFromFormat('d-m-Y', $result[$this->plugin_connection->getFieldAliasOrMap('startdate')]);
                        $dateFormatEnd = \DateTime::createFromFormat('d-m-Y', $result[$this->plugin_connection->getFieldAliasOrMap('enddate')]);
                        
                        if ($dateFormatStart && $dateFormatEnd)
                        {
                        
                            $ymdStart = $dateFormatStart->format("Ymd");
                            $ymdEnd = $dateFormatEnd->format("Ymd");

                            $today = date('Ymd');

                            if ($ymdStart <= $today && $ymdEnd >= $today)
                            {
                                $classes[] = new \ELBP\Plugins\Timetable\Lesson($this, $result);
                            }
                        
                        }
                        
                    }
                    else
                    {
                        $classes[] = new \ELBP\Plugins\Timetable\Lesson($this, $result);
                    }
                    
                }
            }
       
        }
        
        
        // Else we're using Moodle DB
        else
        {
            
            $today = date('Ymd');
            $params = array($this->student->id, $dayNumber, $today, $today);
            $results = $this->DB->get_records_select("lbp_timetable", 
                                                    "userid = ? AND daynumber = ? AND startdate <= ? AND enddate >= ?",
                                                    $params,
                                                    "starttime ASC", 
                                                    "id");            
            foreach ((array)$results as $result)
            {
                $classes[] = new \ELBP\Plugins\Timetable\Lesson($this, $result->id);
            }
            
        }
        
        return $classes;        
        
    }
    
    /**
    * Get colour for day slot on timetable
    * @param string $day Short day name
    * @return string Colour 
    */
    private function getDayClass($day)
    {
        switch($day)
        {
            case 'MON':
                return "elbp_timetable_monday";
            break;
            case 'TUE':
                return "elbp_timetable_tuesday";
            break;
            case 'WED':
                return "elbp_timetable_wednesday";
            break;
            case 'THU':
                return "elbp_timetable_thursday";
            break;
            case 'FRI':
                return "elbp_timetable_friday";
            break;
            case 'SAT':
                return 'elbp_timetable_saturday';
            break;
            case 'SUN':
                return 'elbp_timetable_sunday';
            break;
            default:
                return "elbp_timetable_printing";
            break;

        }
    }
    
    /**
     * Get the background colour for a given day name
     * @param type $day
     * @return string
     */
    public function getDayColour($day)
    {
        
        // See if this student has set their own colour for this day
        if ($this->student){
            $colour = $this->getSetting($day . '_colour', $this->student->id);
            if ($colour) return $colour;
        }
        
        // Nope, is there a default set by the Moodle adminsitrator?
        $colour = $this->getSetting($day . '_colour');
        if ($colour) return $colour;
        
        // Nope, fine I'll decide for you
        
        switch($day)
        {
            case 'monday':
                return '#FFAF79';
            break;
            case 'tuesday':
                return '#ADD8E6';
            break;
            case 'wednesday':
                return '#FFFFE0';
            break;
            case 'thursday':
                return '#DDA0DD';
            break;
            case 'friday':
                return '#BBFF99';
            break;
            case 'saturday':
                return '#D43D1A';
            break;
            case 'sunday':
                return '#284942';
            break;
        }
        
    }
    
    /**
     * Calculate the font colour for a specified background colour
     * @return string
     */
    public function calculateFontColour($bgColour)
    {

        // If first chracter is a hash, get rid of it
        if (substr($bgColour, 0, 1) == '#'){
            $bgColour = substr($bgColour, 1, strlen($bgColour) - 1);
        }
        
        $r = hexdec( substr($bgColour, 0, 2) );
        $g = hexdec( substr($bgColour, 2, 2) );
        $b = hexdec( substr($bgColour, 4, 2) );
        
        $r = $r / 255;
        $g = $g / 255;
        $b = $b / 255;

        return ( (0.213 * $r) + (0.715 * $g) + (0.072 * $b) < 0.5 ) ? '#fff': '#000' ;

    }
    
    /**
     * Build up the CSS to be applied for days
     * @param type $return
     * @return type
     */
    public function buildCSS( $return = false )
    {
        
        $mon = $this->getDayColour('monday');
        $monFont = $this->calculateFontColour( $mon );

        $tue = $this->getDayColour('tuesday');
        $tueFont = $this->calculateFontColour( $tue );

        $wed = $this->getDayColour('wednesday');
        $wedFont = $this->calculateFontColour( $wed );

        $thu = $this->getDayColour('thursday');
        $thuFont = $this->calculateFontColour( $thu );

        $fri = $this->getDayColour('friday');
        $friFont = $this->calculateFontColour( $fri );
        
        $sat = $this->getDayColour('saturday');
        $satFont = $this->calculateFontColour( $sat );
        
        $sun = $this->getDayColour('sunday');
        $sunFont = $this->calculateFontColour( $sun );

        $output = <<<CSS
        <style type='text/css'>
            

            td.elbp_timetable_monday, td.elbp_timetable_monday a
            {
                background-color: {$mon};
                color: {$monFont};
            }

            td.elbp_timetable_tuesday, td.elbp_timetable_tuesday a
            {
                background-color: {$tue};
                color: {$tueFont};
            }

            td.elbp_timetable_wednesday, td.elbp_timetable_wednesday a
            {
                background-color: {$wed};
                color: {$wedFont};
            }

            td.elbp_timetable_thursday, td.elbp_timetable_thursday a
            {
                background-color: {$thu};
                color: {$thuFont};
            }

            td.elbp_timetable_friday, td.elbp_timetable_friday a
            {
                background-color: {$fri};
                color: {$friFont};
            }
            
            td.elbp_timetable_saturday, td.elbp_timetable_saturday a
            {
                background-color: {$sat};
                color: {$satFont};
            }
            
            td.elbp_timetable_sunday, td.elbp_timetable_sunday a
            {
                background-color: {$sun};
                color: {$sunFont};
            }

            
        </style>
CSS;
                
        if ($return) return $output;
        
        echo $output;
        
    }
    
    /**
     * Get the name of a day from its number
     * @param type $num
     * @return boolean
     */
    private function getDayName($num)
    {
        
        $format = $this->getSetting('mis_day_number_format');
        
        if ($format == "N"){
        
            $days = array(
                1 => "MON",
                2 => "TUE",
                3 => "WED",
                4 => "THU",
                5 => "FRI",
                6 => "SAT",
                7 => "SUN"
            );
        
        } elseif ($format ==  "w"){
            
            $days = array(
                0 => "SUN",
                1 => "MON",
                2 => "TUE",
                3 => "WED",
                4 => "THU",
                5 => "FRI",
                6 => "SAT"
            );
            
        } else {
            return false;
        }
        
        return (isset($days[$num])) ? $days[$num] : false;
        
    }
    
    /**
     * Round a number to a multiple of another number
     * @param type $num
     * @param type $multiple
     * @return string
     */
    private function roundToMultiple($num, $multiple)
    {
        $num = round($num / $multiple) * $multiple;
        if ($num == 0) $num = "00";
        return $num;
    }
    
    /**
     * Build the matrix for a single day calendar
     * @param type $params
     */
    private function buildSingleMatrix($params)
    {
                
        // Get student's classes for this day
        $classes = $this->getClassesByDate($params['day']);
                
        $matrix = $this->buildMultiDimensionalArray();
        
        foreach($classes as $class)
        {
            
            $st = $class->getStartTime();
            if (strpos($st, ":") !== false){
                $start = explode(":", $st);
                $startHour = $start[0];
                $startMin = $start[1]; 
            } else {
                $startHour = substr($st, 0, 2);
                $startMin = substr($st, 2, 2);
            }
            
            $et = $class->getEndTime();
            if (strpos($et, ":") !== false){
                $end = explode(":", $et);
                $endHour = $end[0];
                $endMin = $end[1]; 
            } else {
                $endHour = substr($et, 0, 2);
                $endMin = substr($et, 2, 2);
            }
                                   
            // Before we start looping through them, first sort out the minutes
            // It's assumed in the timetable that classes start on a multiple of 15, so either at :00, :15, :30, :45
            // However some start at random times, like :25, so in that case we would want the nearest multiple of 15
            $startMin = $this->roundToMultiple($startMin, $this->getDefaultMinutes());
            $endMin = $this->roundToMultiple($endMin, $this->getDefaultMinutes());
            
            // Loop hours from start to finish
            while($startHour <= $endHour)
            {

                // Loop round all minutes in hour
                $lastMinutes = $this->getLastMinutes();
                
                while($startMin <= $lastMinutes)
                {
                    
                    // If in the final hour, stop if we're >= the end minutes
                    if($startHour == $endHour && $startMin >= $endMin){
                        break;
                    }
                    
                    if (strlen($startHour) == 1) $startHour = "0".$startHour;
                    if ($startMin < 10 && strlen($startMin) == 1) $startMin = "0".$startMin;

                    // Add to array
                    $matrix[$startHour.".".$startMin] = $class;

                    $startMin += $this->getDefaultMinutes();

                }

                $startMin = "00";
                $startHour++;

            }
            
        }
        
        return $matrix;
        
    }
    
    /**
     * Build full matrix
     * @param type $params
     * @return type
     */
    private function buildMatrix($params)
    {
        
        // First let's get all of the student's classes
        $classes = $this->getAllClasses($params);
        
        $matrix['MON'] = $this->buildMultiDimensionalArray();
        $matrix['TUE'] = $this->buildMultiDimensionalArray();
        $matrix['WED'] = $this->buildMultiDimensionalArray();
        $matrix['THU'] = $this->buildMultiDimensionalArray();
        $matrix['FRI'] = $this->buildMultiDimensionalArray();
        $matrix['SAT'] = $this->buildMultiDimensionalArray();
        $matrix['SUN'] = $this->buildMultiDimensionalArray();
                
        // Loop through records in DB and add to array
        foreach($classes as $class)
        {
                        
            $st = $class->getStartTime();
            if (strpos($st, ":") !== false){
                $start = explode(":", $st);
                $startHour = $start[0];
                $startMin = $start[1]; 
            } else {

                $startHour = substr($st, 0, 2);
                $startMin = substr($st, 2, 2);

            }

            $et = $class->getEndTime();
            if (strpos($et, ":") !== false){
                $end = explode(":", $et);
                $endHour = $end[0];
                $endMin = $end[1]; 
            } else {

                $endHour = substr($et, 0, 2);
                $endMin = substr($et, 2, 2);

            }
                       
            $day = $this->getDayName($class->getDayNumber());

            // Before we start looping through them, first sort out the minutes
            // It's assumed in the timetable that classes start on a multiple of 15, so either at :00, :15, :30, :45
            // However some start at random times, like :25, so in that case we would want the nearest multiple of 15
            $startMin = $this->roundToMultiple($startMin, $this->getDefaultMinutes());
            $endMin = $this->roundToMultiple($endMin, $this->getDefaultMinutes());
            
            // Loop hours from start to finish
            while($startHour <= $endHour)
            {

                // Loop round all minutes in hour
                $lastMinutes = $this->getLastMinutes();
                
                while($startMin <= $lastMinutes)
                {

                    // If in the final hour, stop if we're >= the end minutes
                    if($startHour == $endHour && $startMin >= $endMin){
                        break;
                    }
                    
                    if (strlen($startHour) == 1) $startHour = "0".$startHour;
                    if ($startMin < 10 && strlen($startMin) == 1) $startMin = "0".$startMin;

                    // Add to array
                    $matrix[$day][$startHour.".".$startMin] = $class;

                    $startMin += $this->getDefaultMinutes();

                }

                $startMin = "00";
                $startHour++;

            }


        }
        
        return $matrix;
    
    }
    
    /**
    * Build up a multi-dimensional array of times [09.15] => 0, [09.30] => 0, etc...
    * @return array 
    */
    private function buildMultiDimensionalArray()
    {

        $array = array();
        
        // Have we defined a start hour for the timetable? If not, use 9 as the default
        $hour = $this->getSetting('start_hour');
        if (!$hour) $hour = $this->getDefaultStartHour();
        
        $min = 0;
        
        // Have we defined an end hour? If not, use 22 as the default
        $endHour = $this->getSetting('end_hour');
        if (!$endHour) $endHour = $this->getDefaultEndHour();

        while($hour <= $endHour)
        {

            $lastMinutes = $this->getLastMinutes();
            while($min <= $lastMinutes)
            {

                if($hour == $endHour && $min > 0){
                    break;
                }

                $dispMin = $min;
                if($min < 10 && strlen($min) == 1){
                    $dispMin = "0".$min;
                }

                $dispHour = $hour;
                if($hour < 10 && strlen($hour) == 1){
                    $dispHour = "0".$hour;
                }

                $array[$dispHour . "." . $dispMin] = 0;

                $min += $this->getDefaultMinutes();

            }

            $min = 0;
            $hour++;
        }
        
        return $array;

    }
   
    /**
      * Check to see if next slot has the same lesson ID as the current date
      * @param array &$matrix The specific day element of the matrix, e.g. $matrix['MON']
      * @param int $hour
      * @param int $min
      * @param int $id The lesson ID
      * @return int The counter of lessons after it which have the same ID
      */
     private function checkNextSlots(&$matrix, $hour, $min, $id)
     {

         $cnt = 0;
                         
         // Have we defined an end hour? If not, use 22 as the default
         $endHour = $this->getSetting('end_hour');
         if (!$endHour) $endHour = $this->getDefaultEndHour();

         // Loop through all hours
         while($hour <= $endHour)
         {

             // Loop through all minutes in hour
             $lastMinutes = $this->getLastMinutes();
             
             while($min <= $lastMinutes)
             {

                 // Stop if greated than 21 (9PM)
                 if($hour == $endHour && $min > 0){
                     break;
                 }
                 
                 if ($hour < 10 && strlen($hour) == 1) $hour = "0".$hour;
                 if ($min < 10 && strlen($min) == 1) $min = "0".$min;

                 // Increment the counter if this matrix element has the same ID as the previous one
                 $matrixObj = $matrix[$hour.".".$min];
                 if(is_object($id) && is_object($matrixObj) && $matrixObj->getID() == $id->getID()){
                     $matrix[$hour.".".$min] = null;
                     $cnt++;
                 }

                 // If it doesn't have the same ID, then stop completely
                 else
                 {
                     break 2;
                 }

                 $min += $this->getDefaultMinutes();

             }

             // Reset minute to 00 for next hour & increment hour
             $min = "00";
             $hour++;

         }

         return $cnt;

     }
     
     /**
      * Work out what the last minutes section should be
      * E.g. if we're doing the timetable in 15 minute slots, the last one would be :45
      * if we're doing it in 5 minute slots, the last would be :55
      * etc...
      */
     private function getLastMinutes(){
         $minutes = $this->getDefaultMinutes();
         $divide = 60 / $minutes;
         return ($minutes * ($divide - 1));
     }
     
     /**
      * Build a full event calendar layout for the student, so they can move through months, years, etc... and see what they have 
      * on each day
      * Actual slots will be much smaller than the one-week layout, but can be expanded/hovered over to view full info
      */
     public function buildCalendar(){
         
         if (!$this->student) return false;
         
         $output = "";
    
         $output .= "<h1 class='elbp_timetable'>".fullname($this->student).": ".get_string('timetable', 'block_elbp_timetable')."</h1>";

         
         echo $output;
         
     }
        
    /**
     * Build the student's full one-week timetable
     * @return string
     */
    public function buildFull($params){
        
        if (!$this->student) return false;
        
        $TPL = new \ELBP\Template();
        $TPL->set("access", $this->access);
        $TPL->load($this->CFG->dirroot . '/blocks/elbp_timetable/tpl/fullcalendar.html');
        $TPL->display();
        
    }
    
    /**
    * Get the content of a specific TD element
    * @global type $CFG
    * @param mixed &$ID Lesson ID of given day & time. COuld be int > 0 if it's a valid lesson, 0 if it's nothing, null if it's been removed to make way for rowspan
    * @param int &$CNT The counter to be used as a rowspan on the element
    * @param int $startHour
    * @param int $startMin
    * @param string $day Short day name
    * @return string Content to be displayed 
    */
   private function getContent(&$ID, &$CNT, $startHour, $startMin, $day, $params)
   {
              
       // If lesson ID > 0 then display TD with counter as rowspan
       if(is_object($ID)){
           
           $class = $ID;
           
           $cssClass = ($params['format']) ? $this->getDayClass($day) : 'elbp_timetable_printing' ;
           
           $teacher = "<br><em>{$class->getStaff()}</em>";
           
           $lesson = ($params['format'] && $class->getCourseID()) ? "<a href='{$this->CFG->wwwroot}/course/view.php?id={$class->getCourseID()}' target='_blank'>{$class->getDescription()}</a>" : "{$class->getDescription()}" ;

           return "<td rowspan='{$CNT}' class='{$cssClass}'><strong>{$lesson}</strong><br>{$class->getStartTime()} - {$class->getEndTime()}<br>".get_string('room', 'block_elbp_timetable').": {$class->getRoom()}{$teacher}</td>";
       }
       // Else if the content equals 0, then there's just no lesson then, so display blank TD
       elseif($ID === 0){
           $cssClass = '';
           if (isset($params['days'][$day]) && date('Ymd') == $params['days'][$day]['date']){
               $cssClass = 'elbp_today';
           }
           return "<td class='elbp_timetable_noBorder elbp_remove_{$day} {$cssClass}'></td>";
       }
       // Otherwise TD must be null, because it was removed due to lesson spreading over multiple hours, so
       // display nothing at all (except a comment so I can see where it is)
       else
       {
           return "<!-- TD Removed ({$startHour}.{$startMin}) -->";
       }
       
   }
   
   /**
    * Get the text version which will go on the block content. Looping through all the days in the week.
    * @param type $extraInfo
    * @return boolean
    */
   public function getTextTimetableDays($extraInfo = false)
   {
       
       $dayNumberFormat = $this->getSetting('mis_day_number_format');
       if ($dayNumberFormat == "N"){
           $start = 1;
           $end = 7;
       } elseif ($dayNumberFormat == "w"){
           $start = 0;
           $end = 6;
       } else {
           return false;
       }
       
       $output = "";
       
       for ($i = $start; $i <= $end; $i++)
       {
           $output .= $this->getTextTimetable($i, $extraInfo);
       }
       
       return $output;
       
   }
    
   /**
    * Get a text list of timetable slots
    * @param int $day Day number
    * @param bool $extraInfo If true we're viewing the expanded version, so we have more space for extra info if we want it
    * @return string
    */
   public function getTextTimetable($day, $extraInfo = false)
   {
       
       $classes = $this->getClassesByDay( $day );
       $dayName = $this->getDayName($day);
       
       // Weekend
       if (($dayName == "SAT" || $dayName == "SUN") && empty($classes)){
           return '';
       }
       
       $output = '';
       $output .= '<div id="TTT_'.$dayName.'_'.$day.'">';
       $output .= '<strong>'.$dayName.'</strong><br>';


       if ($classes)
       {
            foreach($classes as $class)
            {
                $output .= "<span title='".$class->getTextInfo()."'><u>".$class->getStartTime()."-".$class->getEndTime().":</u>";
                if ($class->getCourseID()) $output .= "<a href='{$this->CFG->wwwroot}/course/view.php?id={$class->getCourseID()}' target='_blank'>";
                  $output .= " " . $class->getDescription();
                if ($class->getCourseID()) $output .= "</a>";  
                if ($class->getRoom()) $output .= " (".get_string('room', 'block_elbp_timetable')." {$class->getRoom()}) ";
                if ($extraInfo && $class->getStaff()) $output .= " - " . $class->getStaff();
                $output .= "</span><br>";
            }
       }
       else
       {
            $output .= "<em>".get_string('norecordsfound', 'block_elbp_timetable') . "</em><br>";
       }

       $output .= '<br>';   
       $output .= '</div>';
      
       return $output;
       
   }
    
    /**
     * Install the plugin
     * @global type $DB
     * @return type
     */
    public function install(){
        
        global $DB;
        
        $return = true;
        $pluginID = $this->createPlugin();
        $return = $return && $pluginID;
                
        // This is a core ELBP plugin, so the extra tables it requires are handled by the core ELBP install.xml
        
        
        // Default settings
        $settings = array();
        $settings['link_course_by'] = 'shortname';
        $settings['start_hour'] = '09';
        $settings['end_hour'] = '17';
        $settings['minutes'] = '30';
        $settings['monday_colour'] = '#ffaf79';
        $settings['tuesday_colour'] = '#add8e6';
        $settings['wednesday_colour'] = '#0000ff';
        $settings['thursday_colour'] = '#dda0dd';
        $settings['friday_colour'] = '#bbff99';
        $settings['saturday_colour'] = '#d43d1a';
        $settings['sunday_colour'] = '#284942';
        $settings['mis_day_number_format'] = 'w';
         
        // Not 100% required on install, so don't return false if these fail
        foreach ($settings as $setting => $value){
            $DB->insert_record("lbp_settings", array("pluginid" => $pluginID, "setting" => $setting, "value" => $value));
        }
        
        return $return;
        
    }
    
    /**
     * Truncate related tables and uninstall plugin
     * @global \ELBP\Plugins\type $DB
     */
    public function uninstall() {
        
        global $DB;
        
        if ($this->tables){
            foreach($this->tables as $table){
                $DB->execute("TRUNCATE {{$table}}");
            }
        }
        
        parent::uninstall();
        
    }
    
    
    /*
     *  This method should run any upgrades required of the plugin
     *      E.g. DB might be version 10
     *      Months later they might git hub over the latest version of the plugin, which is now version 15
     *      "10" passed into upgrade() and it would run all the upgrades up to the latest version and then set DB version to 15
     *      Basically the same as the standard Moodle upgrade procedure, but will need to handle the updating of the DB version ourselves
     *      as obviously Moodle won't know about our table with version numbers in
     */
    function upgrade(){
        
        global $DB;
        
        $dbman = $DB->get_manager();
        $version = $this->version; # This is the current DB version we will be using to upgrade from     
                        
        if ($version < 2014022706)
        {
                        
            // Define index uid_indx (not unique) to be added to lbp_timetable
            $table = new \xmldb_table('lbp_timetable');
            $index = new \xmldb_index('uid_indx', XMLDB_INDEX_NOTUNIQUE, array('userid'));

            // Conditionally launch add index uid_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
            
            $index = new \xmldb_index('uidday_indx', XMLDB_INDEX_NOTUNIQUE, array('userid', 'daynumber'));

            // Conditionally launch add index uidday_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
            
            
            $index = new \xmldb_index('uiddayst_indx', XMLDB_INDEX_NOTUNIQUE, array('userid', 'daynumber', 'starttime'));

            // Conditionally launch add index uiddayst_indx
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
            
            
            // elbp_timetable savepoint reached
            $this->version = 2014022706;
            $this->updatePlugin();
            
            
        }
        
        if ($version < 2014031100){
            
            // Changing nullability of field course on table lbp_timetable to null
            $table = new \xmldb_table('lbp_timetable');
            $field = new \xmldb_field('course', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'userid');

            // Launch change of nullability for field course
            $dbman->change_field_notnull($table, $field);
            
             // elbp_timetable savepoint reached
            $this->version = 2014031100;
            $this->updatePlugin();
            
        }
        
                
        
        
    }
    
    /**
     * Get the content for the expanded view
     * @param type $params
     * @return type
     */
    function getDisplay($params = array()){
        
        $output = "";
        
        $TPL = new \ELBP\Template();
                
        try {
            $output .= $TPL->load($this->CFG->dirroot . '/blocks/elbp_timetable/tpl/expanded.html');
        } catch (\ELBP\ELBPException $e){
            $output .= $e->getException();
        }

        return $output;
        
    }
    
    /**
     * Get the content for the summary box
     * @return type
     */
    function getSummaryBox(){
        
        $TPL = new \ELBP\Template();
                
        $this->connect();
        
        $TPL->set("obj", $this);
                
        try {
            return $TPL->load($this->CFG->dirroot . '/blocks/elbp_timetable/tpl/summary.html');
        }
        catch (\ELBP\ELBPException $e){
            return $e->getException();
        }
        
    }
    
    
    /**
     * Handle ajax requests sent to plugin
     * @global type $USER
     * @param type $action
     * @param type $params
     * @param type $ELBP
     * @return boolean
     */
    function ajax($action, $params, $ELBP){
        
        global $USER, $MSGS;
        
        $TPL = new \ELBP\Template();
        
        switch($action)
        {
            
            case 'load_display_type':
                                                                
                 // Correct params are set?
                if (!$params || !isset($params['studentID']) || !$this->loadStudent($params['studentID'])) return false;
                                
                 // We have the permission to do this?
                $access = $ELBP->getUserPermissions($params['studentID']);
                if (!$ELBP->anyPermissionsTrue($access)) return false;
                                
                $this->setAccess($access);
                                
                $TPL = new \ELBP\Template();
                $TPL->set("obj", $this);
                $TPL->set("student", $this->student);
                $TPL->set("access", $this->access);
                                
                try {
                    //$method = 'ajax_'.$params['type'];
                    //$this->$method($TPL);
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp_timetable/tpl/'.$params['type'].'.html' );
                    $TPL->display();                    
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                exit;                
                
            break;
            
            
            case 'load_colours_form':
                
                // Load Student
                $userID = ($params['student'] > 0) ? $params['student'] : $USER->id;
                $this->loadStudent($userID);
                
                try {
                    $TPL->set("TT", $this);
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp_timetable/tpl/colour_settings_form.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                
                exit;
                
            break;
        
            case 'save_colours_form':
                
                // Load Student
                $userID = ($params['student'] > 0) ? $params['student'] : $USER->id;
                $this->loadStudent($userID);
                
                // If 3-digit, set to 6 digit
                if (strlen($params['MON']) == 4) $params['MON'] = $params['MON'][0].$params['MON'][1].$params['MON'][1].$params['MON'][2].$params['MON'][2].$params['MON'][3].$params['MON'][3];
                if (strlen($params['TUE']) == 4) $params['TUE'] = $params['TUE'][0].$params['TUE'][1].$params['TUE'][1].$params['TUE'][2].$params['TUE'][2].$params['TUE'][3].$params['TUE'][3];
                if (strlen($params['WED']) == 4) $params['WED'] = $params['WED'][0].$params['WED'][1].$params['WED'][1].$params['WED'][2].$params['WED'][2].$params['WED'][3].$params['WED'][3];
                if (strlen($params['THU']) == 4) $params['THU'] = $params['THU'][0].$params['THU'][1].$params['THU'][1].$params['THU'][2].$params['THU'][2].$params['THU'][3].$params['THU'][3];
                if (strlen($params['FRI']) == 4) $params['FRI'] = $params['FRI'][0].$params['FRI'][1].$params['FRI'][1].$params['FRI'][2].$params['FRI'][2].$params['FRI'][3].$params['FRI'][3];
                if (strlen($params['SAT']) == 4) $params['SAT'] = $params['SAT'][0].$params['SAT'][1].$params['SAT'][1].$params['SAT'][2].$params['SAT'][2].$params['SAT'][3].$params['SAT'][3];
                if (strlen($params['SUN']) == 4) $params['SUN'] = $params['SUN'][0].$params['SUN'][1].$params['SUN'][1].$params['SUN'][2].$params['SUN'][2].$params['SUN'][3].$params['SUN'][3];
                
                // Set colour settings
                $this->updateSetting("monday_colour", $params['MON'], $this->student->id);
                $this->updateSetting("tuesday_colour", $params['TUE'], $this->student->id);
                $this->updateSetting("wednesday_colour", $params['WED'], $this->student->id);
                $this->updateSetting("thursday_colour", $params['THU'], $this->student->id);
                $this->updateSetting("friday_colour", $params['FRI'], $this->student->id);
                $this->updateSetting("saturday_colour", $params['SAT'], $this->student->id);
                $this->updateSetting("sunday_colour", $params['SUN'], $this->student->id);
                
                exit;
                
            break;
        
            case 'get_font_colour':
                echo $this->calculateFontColour($params['background']);
            break;
        
            case 'load_calendar':
                
                // Load Student
                $params['student'] = ($params['student'] > 0) ? $params['student'] : $USER->id;
                $this->loadStudent($params['student']);
                $this->connect();
                $func = 'ajax_cal_'.$params['type'];
                $this->$func($TPL, $params);
                
                $TPL->set("MSGS", $MSGS);
                
                try {
                    $TPL->set("TT", $this);
                    $TPL->set("access", $this->access);
                    $TPL->load( $this->CFG->dirroot . '/blocks/elbp_timetable/tpl/cal/'.$params['type'].'.html' );
                    $TPL->display();
                } catch (\ELBP\ELBPException $e){
                    echo $e->getException();
                }
                
                exit;
                
            break;
            
        }
        
    }
    
    /**
     * Calendar - Daily
     * @param type $TPL
     * @param type $params
     */
    private function ajax_cal_day($TPL, $params)
    {
        
        $dayAdd = (isset($params['add'])) ? $params['add'] : 0;
        
        // Given a day of the year number (0-365) find the date
        if (!isset($params['dayNum'])) $params['dayNum'] = date('z');
        if (!isset($params['year'])) $params['year'] = date('Y');
        
        // Add the add
        $timestr = ($dayAdd < 0) ? "" : "+";
        $unix = strtotime($timestr . $dayAdd . " days");
        
        // If day is > 365 DateTime will just set the date to the next year anyway, so no need for me to mess around with it        
        $params['day'] = \DateTime::createFromFormat('U', $unix);
        
        $params['format'] = true;
        
        $output = $this->buildFullCalendarDay($params);
        
        $TPL->set("output", $output);
        $TPL->set("dayAdd", $dayAdd);
        $TPL->set("day", $params['day']);
        
    }
    
    /**
     * Calendar - weekly
     * @param type $TPL
     */
    private function ajax_cal_week($TPL, $params){
        
        $weekAdd = (isset($params['add'])) ? $params['add'] : 0;
        
        // Given a week number, find the first day of the week and the last day of the week
        if (!isset($params['week'])) $params['week'] = date('W');
        if (!isset($params['year'])) $params['year'] = date('Y');
        
        $params['week'] = $params['week'] + $weekAdd;
        if ($params['week'] > 52){
            $params['week'] = 1;
            $params['year']++;
        }
                        
        $ws = new \DateTime();
        $ws->setISODate($params['year'], $params['week']);
        
        $weekStart = array();
        $weekStart['unix'] = strtotime("{$ws->format("d")} {$ws->format("M")} {$ws->format("Y")}");
        $weekStart['ymd'] = $ws->format("Ymd");
        
        $weekEnd = array();
        $weekEnd['unix'] = strtotime("+ 6 days", $weekStart['unix']);
        $weekEnd['ymd'] = date("Ymd", $weekEnd['unix']);
               
        // Week Title
        $weekTitle =  date('d M Y', $weekStart['unix']) . " - " . date('d M Y', $weekEnd['unix']);
        
        $TPL->set("weekTitle", $weekTitle);
                
        $days = array();
        
        // Unix for YMDing
        $days['MON']['unix'] = $weekStart['unix'];
        $days['TUE']['unix'] = strtotime("+ 1 days", $weekStart['unix']);
        $days['WED']['unix'] = strtotime("+ 2 days", $weekStart['unix']);
        $days['THU']['unix'] = strtotime("+ 3 days", $weekStart['unix']);
        $days['FRI']['unix'] = strtotime("+ 4 days", $weekStart['unix']);
        $days['SAT']['unix'] = strtotime("+ 5 days", $weekStart['unix']);
        $days['SUN']['unix'] = $weekEnd['unix'];
        
        // Ymd for DB querying
        $days['MON']['date'] = $weekStart['ymd'];
        $days['TUE']['date'] = date('Ymd', $days['TUE']['unix']);
        $days['WED']['date'] = date('Ymd', $days['WED']['unix']);
        $days['THU']['date'] = date('Ymd', $days['THU']['unix']);
        $days['FRI']['date'] = date('Ymd', $days['FRI']['unix']);
        $days['SAT']['date'] = date('Ymd', $days['SAT']['unix']);
        $days['SUN']['date'] = $weekEnd['ymd'];
        
        // Date string for output of days
        $days['MON']['str'] = date('F d', $days['MON']['unix']);
        $days['TUE']['str'] = date('F d', $days['TUE']['unix']);
        $days['WED']['str'] = date('F d', $days['WED']['unix']);
        $days['THU']['str'] = date('F d', $days['THU']['unix']);
        $days['FRI']['str'] = date('F d', $days['FRI']['unix']);
        $days['SAT']['str'] = date('F d', $days['SAT']['unix']);
        $days['SUN']['str'] = date('F d', $days['SUN']['unix']);
                      
        
        // Build full week
        $params['weekStart'] = $weekStart;
        $params['weekEnd'] = $weekEnd;
        $params['days'] = $days;
        $params['format'] = true;
        
        $output = $this->buildFullCalendarWeek($params);
        
        $defaultDayWidth = 14;
        
        $dayNumFormat = $this->getSetting('mis_day_number_format');
        
        $satNum = 6;
        
        if ($dayNumFormat == "N"){
            $sunNum = 7;
        } else {
            $sunNum = 0;
        }
        
        // If nothing on saturday, don't display it
        if (!$this->getClassesByDay($satNum)){
            $defaultDayWidth += 2.5;
            $output .= "<script>
                            $('.elbp_remove_SAT').remove();
                            $('.elbp_timetable_day').css('width', '{$defaultDayWidth}%');
                        </script>";
        }
        
        // If nothing on Sunday, don#'t display it
        if (!$this->getClassesByDay($sunNum)){
            $defaultDayWidth += 2.5;
            $output .= "<script>
                            $('.elbp_remove_SUN').remove();
                            $('.elbp_timetable_day').css('width', '{$defaultDayWidth}%');
                        </script>";
        }
        
        $TPL->set("output", $output);
        $TPL->set("days", $days);
        $TPL->set("weekAdd", $weekAdd);
        
    }
    
    /**
     * Get the monthly calendar view
     * @param type $TPL
     * @param type $params
     */
    private function ajax_cal_month($TPL, $params)
    {
        
        $monthAdd = (isset($params['add'])) ? $params['add'] : 0;
        
        // Given a day of the year number (0-365) find the date
        if (!isset($params['month'])) $params['month'] = date('m');
        if (!isset($params['year'])) $params['year'] = date('Y');
        
        // Add the add
        $params['month'] += $monthAdd;
        
        if ($params['month'] < 1){
            $params['month'] = 12 + $params['month'];
            $params['year']--;
        }
        
        if ($params['month'] > 12){
            $params['month'] = $params['month'] - 12;
            $params['year']++;
        }
        
        // Get day of the 1st of the month
        $unix = strtotime("01-{$params['month']}-{$params['year']}");

        $params['monthStart'] = \DateTime::createFromFormat('U', $unix);
        
        $params['format'] = true;
        
        $output = $this->buildFullCalendarMonth($params);
                
        $TPL->set("output", $output);
        $TPL->set("monthAdd", $monthAdd);
        $TPL->set("month", $params['monthStart']);
        
    }
    
    /**
     * Get the yearly calendar view
     * @param type $TPL
     * @param type $params
     */
    private function ajax_cal_year($TPL, $params)
    {
        
        $yearAdd = (isset($params['add'])) ? $params['add'] : 0;
        
        // Year to view
        if (!isset($params['year'])) $params['year'] = date('Y');
        
        // Add the add
        $params['year'] += $yearAdd;
        
        $params['format'] = true;
        
        $output = $this->buildFullCalendarYear($params);
                
        $TPL->set("output", $output);
        $TPL->set("yearAdd", $yearAdd);
        $TPL->set("year", $params['year']);
        
        
    }
    
    /**
     * Build the <tbody> output of the daily calendar
     * @param type $params
     */
    private function buildFullCalendarDay($params)
    {
        
        if (!$this->student) return false;
                                        
        $output = "";
        
        // Build Matrix
        $matrix = $this->buildSingleMatrix($params);
                                
        // Have we defined a start hour for the timetable? If not, use 9 as the default
        $startHour = $this->getSetting('start_hour');
        if (!$startHour) $startHour = $this->getDefaultStartHour();
                
        // Have we defined an end hour? If not, use 22 as the default
        $endHour = $this->getSetting('end_hour');
        if (!$endHour) $endHour = $this->getDefaultEndHour();
        
        $dayOfWeek = strtoupper($params['day']->format("D"));
                
        $startMin = "00";
        
        // Loop through times for this one day
        while($startHour <= $endHour)
        {
                        
            // Prepend zero if 1-9
            if ($startHour < 10 && strlen($startHour) == 1) $startHour = "0".$startHour;
            
            // Loop mins in hour
            $lastMinutes = $this->getLastMinutes();
            
            while($startMin <= $lastMinutes)
            {

                if($startHour == $endHour && $startMin > 0){
                    break;
                }
                
                if ($startMin < 10 && strlen($startMin) == 1) $startMin = "0".$startMin;

                // So we're at hh.mm now we want to get that data for each day

                $ID = $matrix[$startHour.".".$startMin];

                $CNT = 0 ;

                // Check counters
                // Loop through each next slot (+15 mins) on given day and see if it's the same lesson ID.
                // Return the number of lessons in a row to use as rowspan value

                // Counter
                if(is_object($ID)){
                    $CNT = $this->checkNextSlots($matrix, $startHour, $startMin, $ID);
                }

                // Content
                $CONTENT = $this->getContent($ID, $CNT, $startHour, $startMin, $dayOfWeek, $params);

                // If all days on this time are empty, skip it            
                $output .= "<tr><th class='elbp_timetable_time'>{$startHour}:{$startMin}</th>{$CONTENT}</tr>\n\n";

                $startMin += $this->getDefaultMinutes();

            }

            $startMin = "00";
            $startHour++;

        }
        
        return $output;
        
    }


    /**
     * Build the <tbody> output of the weekly calendar
     * @param type $params
     * @return string
     */
    private function buildFullCalendarWeek($params)
    {
                
        if (!$this->student) return false;
                
        $output = "";
                
        // Build Matrix
        $matrix = $this->buildMatrix($params);
                        
        // Have we defined a start hour for the timetable? If not, use 9 as the default
        $startHour = $this->getSetting('start_hour');
        if (!$startHour) $startHour = $this->getDefaultStartHour();
                
        // Have we defined an end hour? If not, use 22 as the default
        $endHour = $this->getSetting('end_hour');
        if (!$endHour) $endHour = $this->getDefaultEndHour();
                
        $startMin = "00";

        // Loop hours
        while($startHour <= $endHour)
        {
                        
            if ($startHour < 10 && strlen($startHour) == 1) $startHour = "0".$startHour;
            
            // Loop mins in hour
            $lastMinutes = $this->getLastMinutes();
            
            while($startMin <= $lastMinutes)
            {

                if($startHour == $endHour && $startMin > 0){
                    break;
                }
                
                
                if ($startMin < 10 && strlen($startMin) == 1) $startMin = "0".$startMin;

                // So we're at hh.mm now we want to get that data for each day

                $ID['MON'] = $matrix['MON'][$startHour.".".$startMin];
                $ID['TUE'] = $matrix['TUE'][$startHour.".".$startMin];
                $ID['WED'] = $matrix['WED'][$startHour.".".$startMin];
                $ID['THU'] = $matrix['THU'][$startHour.".".$startMin];
                $ID['FRI'] = $matrix['FRI'][$startHour.".".$startMin];
                $ID['SAT'] = $matrix['SAT'][$startHour.".".$startMin];
                $ID['SUN'] = $matrix['SUN'][$startHour.".".$startMin];
                
                $CNT = array();

                // Check counters
                // Loop through each next slot (+15 mins) on given day and see if it's the same lesson ID.
                // Return the number of lessons in a row to use as rowspan value
                
                // Monday Counter
                if(is_object($ID['MON'])){
                    $CNT['MON'] = $this->checkNextSlots($matrix['MON'], $startHour, $startMin, $ID['MON']);
                }
                
                // Monday Content
                $CONTENT['MON'] = $this->getContent($ID['MON'], $CNT['MON'], $startHour, $startMin, 'MON', $params);


                // Tuesday Counter
                if(is_object($ID['TUE'])){
                    $CNT['TUE'] = $this->checkNextSlots($matrix['TUE'], $startHour, $startMin, $ID['TUE']);
                }

                // Tuesday Content
                $CONTENT['TUE'] = $this->getContent($ID['TUE'], $CNT['TUE'], $startHour, $startMin, 'TUE', $params);


                // Wednesday Counter
                if(is_object($ID['WED'])){
                    $CNT['WED'] = $this->checkNextSlots($matrix['WED'], $startHour, $startMin, $ID['WED']);
                }

                // Wednesday Content
                $CONTENT['WED'] = $this->getContent($ID['WED'], $CNT['WED'], $startHour, $startMin, 'WED', $params);


                // Thursday Counter
                if(is_object($ID['THU'])){
                    $CNT['THU'] = $this->checkNextSlots($matrix['THU'], $startHour, $startMin, $ID['THU']);
                }

                // Thursday Content
                $CONTENT['THU'] = $this->getContent($ID['THU'], $CNT['THU'], $startHour, $startMin, 'THU', $params);


                // Friday Counter
                if(is_object($ID['FRI'])){
                    $CNT['FRI'] = $this->checkNextSlots($matrix['FRI'], $startHour, $startMin, $ID['FRI']);
                }

                // Friday Content
                $CONTENT['FRI'] = $this->getContent($ID['FRI'], $CNT['FRI'], $startHour, $startMin, 'FRI', $params);
                
                
                // Saturday Counter
                if(is_object($ID['SAT'])){
                    $CNT['SAT'] = $this->checkNextSlots($matrix['SAT'], $startHour, $startMin, $ID['SAT']);
                }

                // Saturday Content
                $CONTENT['SAT'] = $this->getContent($ID['SAT'], $CNT['SAT'], $startHour, $startMin, 'SAT', $params);
                
                
                // Sunday Counter
                if(is_object($ID['SUN'])){
                    $CNT['SUN'] = $this->checkNextSlots($matrix['SUN'], $startHour, $startMin, $ID['SUN']);
                }

                // Sunday Content
                $CONTENT['SUN'] = $this->getContent($ID['SUN'], $CNT['SUN'], $startHour, $startMin, 'SUN', $params);

                // If all days on this time are empty, skip it            
                $output .= "<tr><th class='elbp_timetable_time'>{$startHour}:{$startMin}</th>{$CONTENT['MON']}{$CONTENT['TUE']}{$CONTENT['WED']}{$CONTENT['THU']}{$CONTENT['FRI']}{$CONTENT['SAT']}{$CONTENT['SUN']}</tr>\n\n";

                $startMin += $this->getDefaultMinutes();

            }

            $startMin = "00";
            $startHour++;

        }
                
        return $output;
        
    }
    
    /**
     * Build the monthly calendar content
     * @param type $params
     * @return string|boolean
     */
    private function buildFullCalendarMonth($params)
    {
        
        if (!$this->student) return false;
                
        $classes = $this->getClassesByMonth($params);
        
        $output = "";
                
        $monthStart = $params['monthStart'];
        $dayNumberFormat = $this->getSetting('mis_day_number_format');
        $startDayNum = $monthStart->format($dayNumberFormat);
        $monthDays = count($classes);
        $numDone = 0;
        
        // Loop through all days in month
        while($numDone < $monthDays)
        {
                        
            $output .= "<tr>";
            
            // Loop weekly - Mon-Sun
            if ($dayNumberFormat == "N"){
                $iStart = 1;
                $iEnd = 7;
            } else {
                $iStart = 0;
                $iEnd = 6;
            }
            
            // Loop weekly
            for($i = $iStart; $i <= $iEnd; $i++)
            {
                
                // Month hasn't started yet or month has ended
                if ($i < $startDayNum || $numDone >= $monthDays){
                    $output .= "<td>-</td>";
                    continue;
                }
                
                // Month has started
                $numDone++;
                
                // Day content
                $dayNum = $numDone;
                if ($dayNum < 10) $dayNum = "0".$dayNum;
                
                $ymd = $monthStart->format("Ym") . $dayNum;
                $cssClass = ($ymd == date('Ymd')) ? 'elbp_today' : '';
                
                $day = \DateTime::createFromFormat("U", strtotime("{$dayNum}-{$monthStart->format("m")}-{$monthStart->format("Y")}")); 
                                
                if (isset($classes[$dayNum]) && $classes[$dayNum] && $day) $cssClass .= ' ' . $this->getDayClass( strtoupper($day->format("D")) );
                
                $output .= "<td style='position:relative;' class='{$cssClass}'>";
                    
                    $output .= "<div class='elbp_right day_number_no_colour'>{$dayNum}</div>";
                
                    $output .= "<div class='day_info'>";
                        $output .= "<ul>";
                            if (isset($classes[$dayNum]))
                            {
                                foreach($classes[$dayNum] as $class)
                                {
                                    $output .= "<li><a href='#' onclick='ELBP.Timetable.popup_class_info({$class->getID()}, \"{$dayNum} {$monthStart->format("F")} {$monthStart->format("Y")}\");return false;'><b>{$class->getStartTime()}</b> {$class->getDescription()}</a><div id='class_info_{$class->getID()}' style='display:none;'>{$class->getPopupInfo()}</div></li>";
                                }
                            }
                        $output .= "</ul>";
                    $output .= "</div>";
                
                $output .= "</td>";
                                
            }
            
            $output .= "</tr>";
            $startDayNum = $iStart;
            
            
        }
               
        
                
        return $output;
        
    }
    
    /**
     * Build the yearly calendar content
     * @param type $params
     * @return string|boolean
     */
    private function buildFullCalendarYear($params)
    {
        
        if (!$this->student) return false;
        
        $output = array();
        
        // Loop months
        for ($month = 1; $month <= 12; $month++)
        {
                        
            // Get the start of the month day
            $monthStart = \DateTime::createFromFormat("U", strtotime("01-{$month}-{$params['year']}"));
            if ($monthStart)
            {
            
                $classes = $this->getClassesByMonth(array("monthStart"=>$monthStart));

                $dayNumberFormat = $this->getSetting('mis_day_number_format');
                $startDayNum = $monthStart->format($dayNumberFormat);
                $monthDays = count($classes);
                $numDone = 0;
                $rowsDone = 0;

                $output[$month] = "";

                // Loop days in month
                while($numDone < $monthDays)
                {

                    $output[$month] .= "<tr>";

                    // Loop weekly - Mon-Sun
                    if ($dayNumberFormat == "N"){
                        $iStart = 1;
                        $iEnd = 7;
                    } else {
                        $iStart = 0;
                        $iEnd = 6;
                    }


                    for($i = $iStart; $i <= $iEnd; $i++)
                    {

                        // Month hasn't started yet or month has ended
                        if ($i < $startDayNum || $numDone >= $monthDays){
                            $output[$month] .= "<td></td>";
                            continue;
                        }

                        // Month has started
                        $numDone++;

                        // Day content
                        $dayNum = $numDone;
                        if ($dayNum < 10) $dayNum = "0".$dayNum;

                        $day = \DateTime::createFromFormat("U", strtotime("{$dayNum}-{$month}-{$params['year']}"));

                        $ymd = $monthStart->format("Ym") . $dayNum;
                        $cssClass = ($ymd == date('Ymd')) ? 'elbp_today' : '';
                        $tdTitle = "";

                        if (isset($classes[$dayNum]) && $classes[$dayNum] && $day){
                            $cssClass .= ' ' . $this->getDayClass(strtoupper($day->format("D")));
                            $cssClass .= ' elbp_tooltip';

                            $tdTitle .= "<small>{$day->format("D d M")}:</small><br><br>";

                            // Class info
                            foreach($classes[$dayNum] as $class){
                                $tdTitle .= $class->getTooltipContent();
                            }
                        }

                        $output[$month] .= "<td style='position:relative;' class='{$cssClass}' title='{$tdTitle}'><div class='day_number_no_colour'>{$dayNum}</div><div><br></div></td>";                        

                    }

                    $output[$month] .= "</tr>";
                    $startDayNum = $iStart;
                    $rowsDone++;

                }

                // We want 6 rows even if one is blank, so that all tables are the same height
                while($rowsDone < 6){
                    $output[$month] .= "<tr><td><br></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                    $rowsDone++;
                }

            }
            
            
        }
                
        return $output;
        
    }
    
    /**
     * Save the configuration
     * @global type $MSGS
     * @param type $settings
     * @return boolean
     */
    public function saveConfig($settings) {
        
        global $MSGS;
        
        
        if (isset($settings['submitmistest_allclasses']) && !empty($settings['testusername']))
        {
            $username = $settings['testusername'];
            $this->runTestMisQuery($username, "all_classes");
            return false;
        }
        elseif (isset($settings['submitmistest_todayclasses']) && !empty($settings['testusername']))
        {
            $username = $settings['testusername'];
            $this->runTestMisQuery($username, "todays_classes");
            return false;
        }
        
        elseif (isset($settings['submitconfig']))
        {
                        
            // Mappings first if they are there
            if (isset($settings['mis_map']))
            {
                
                // Get the plugin's core MIS connection
                $core = $this->getMainMIS();
                if (!$core)
                {
                    $MSGS['errors'][] = get_string('nocoremis', 'block_elbp_timetable');
                    return false;
                }
                
                // Set the mappings
                $conn = new \ELBP\MISConnection($core->id);
                if ($conn->isValid())
                {
                
                    foreach($settings['mis_map'] as $name => $field)
                    {
                        $field = trim($field);
                        $alias = (isset($settings['mis_alias'][$name]) && !empty($settings['mis_alias'][$name])) ? $settings['mis_alias'][$name] : null;
                        $func = (isset($settings['mis_func'][$name]) && !empty($settings['mis_func'][$name])) ? $settings['mis_func'][$name] : null;
                        $conn->setFieldMap($name, $field, $alias, $func);
                    }
                
                }
                
                unset($settings['mis_map']);
                unset($settings['mis_alias']);
                unset($settings['mis_func']);
                
            }
            
            parent::saveConfig($settings);
            return true;
            
        }
        
        elseif (isset($settings['submit_import']) && isset($_FILES['file']) && !$_FILES['file']['error']){
            
            $result = $this->runImport($_FILES['file']);
            $MSGS['result'] = $result;
            return false;
            
        }
        
        
    }
    
        
    /**
     * This will take the MIS connection and field details you have provided in the settings and run a test query to see
     * if it returns what you expect
     * @param string $username - The username to run the query against
     */
    public function runTestMisQuery($username, $query){
        
        global $CFG, $MSGS;
        
        // This query will select all records it can find for a specified username/idnumber
        
        $view = $this->getSetting("mis_view_name");
        if (!$view){
            $MSGS['errors'][] = 'mis_view_name';
            return false;
        }
        
//        $dateformat = $this->getSetting("mis_date_format");
//        if (!$dateformat){
//            $MSGS['errors'][] = 'mis_date_format';
//            return false;
//        }
        
        // Core MIS connection
        $core = $this->getMainMIS();
        if (!$core){
            $MSGS['errors'][] = get_string('nocoremis', 'block_elbp_timetable');
            return false;
        }
        
        $conn = new \ELBP\MISConnection($core->id);
        if (!$conn->isValid()){
            $MSGS['errors'][] = get_string('connectioninvalid', 'block_elbp_timetable');
            return false;
        }
        
        $reqFields = array("id", "daynum", "dayname", "username", "lesson", "staff", "course", "room", "starttime", "endtime", "startdate", "enddate");
        
        foreach($reqFields as $reqField)
        {
            if (!$conn->getFieldMap($reqField)){
                $MSGS['errors'][] = get_string('missingreqfield', 'block_elbp_timetable') . ": " . $reqField;
                return false;
            }
        }
        
        $this->connect();
                
        switch($query)
        {
            case 'all_classes':
                // Run the query
                $classes = $this->getAllClasses( array("username" => $username) );
                $MSGS['testoutput'] = $classes;
            break;
            case 'todays_classes':
                // Run the query
                $classes = $this->getClassesByDay( date( $this->getSetting('mis_day_number_format') ), array("username" => $username) );
                $MSGS['testoutput'] = $classes;
            break;
        }
        
        // Debugging on?
        if ($CFG->debug >= 32767){
            $MSGS['sql'] = $this->connection->getLastSQL();
        } 
                
    }
    
    /**
     * Get the required headers for the csv import
     * @return string
     */
    private function getImportCsvHeaders(){
        $headers = array();
        $headers[] = 'username';
        $headers[] = 'courseshortname';
        $headers[] = 'description';
        $headers[] = 'startdate(yyyymmdd)';
        $headers[] = 'enddate(yyyymmdd)';
        $headers[] = 'starttime(hhmm)';
        $headers[] = 'endtime(hhmm)';
        $headers[] = 'daynumber';
        $headers[] = 'staff';
        $headers[] = 'room';
        return $headers;
    }
    
    /**
     * Create the import csv
     * @global type $CFG
     * @param bool $reload - If i ever change it so it uses the custom attributes as file headers, we can force a reload 
     * from the attributes page when its saved
     * @return string|boolean
     */
    public function createTemplateImportCsv($reload = false){
        
        global $CFG;
        
        $file = $CFG->dataroot . '/ELBP/' . $this->name . '/templates/template.csv';
        $code = $this->createDataPathCode($file);
        
        // If it already exists and we don't want to reload it, just return
        if (file_exists($file) && !$reload){
            return $code;
        }
                
        // Now lets create the new one - The headers are going to be in English so we can easily compare headers
        $headers = $this->getImportCsvHeaders();
        
        // Using "w" we truncate the file if it already exists
        $fh = fopen($file, 'w');
        if ($fh === false){
            return false;
        }
        
        $fp = fputcsv($fh, $headers);
        
        if ($fp === false){
            return false;
        }
        
        fclose($fh);        
        return $code;       
        
    }
    
    
     /**
     * Create the import csv
     * @global type $CFG
     * @param bool $reload - If i ever change it so it uses the custom attributes as file headers, we can force a reload 
     * from the attributes page when its saved
     * @return string|boolean
     */
    public function createExampleImportCsv($reload = false){
        
        global $CFG, $DB;
                
        $file = $CFG->dataroot . '/ELBP/' . $this->name . '/templates/example.csv';
        $code = $this->createDataPathCode($file);
        
        // If it already exists and we don't want to reload it, just return
        if (file_exists($file) && !$reload){
            return $code;
        }
                
        // Now lets create the new one - The headers are going to be in English so we can easily compare headers
        $headers = $this->getImportCsvHeaders();
        
        // Using "w" we truncate the file if it already exists
        $fh = fopen($file, 'w');
        if ($fh === false){
            return false;
        }
        
        $fp = fputcsv($fh, $headers);
        
        if ($fp === false){
            return false;
        }
        
        // Count users
        $cntUsers = $DB->count_records("user");
        $cntCourses = $DB->count_records("course");
        
        $mins = array('00', '15', '30', '45');
        $startDates = array('20180901', '20180924', '20180101', '20190901');
        $endDates = array('20190701', '20190721', '20181231', '20200701');
        $names = array('John', 'Mark', 'Jimmy', 'Geoff', 'Paul', 'Lisa', 'Sarah', 'Henry', 'Rocky', 'Hannah');
        $lnames = array('Smith', 'Rolands', 'Warwick', 'Terry', 'Knight', 'Matthews', 'Rhodes', 'Hudson', 'Darko', 'Johnson');
        
        $courseField = $this->getSetting('import_course_field');
        if (!$courseField){
            $courseField = 'shortname';
        }
        
        $userField = $this->getSetting('import_user_field');
        if (!$userField){
            $userField = 'username';
        }
        
        
        
        // Now some rows
        for($i = 0; $i <= 50; $i++)
        {
            
            // Select random user
            $userID = mt_rand(1, $cntUsers);
            $user = $DB->get_record("user", array("id" => $userID, "deleted" => 0));
            if ($user)
            {
                
                $data = array();
                $data[] = $user->$userField;
                
                $rand = mt_rand(1,2);
                if ($rand == 1){
                    
                    $courseID = mt_rand(1, $cntCourses);
                    $course = $DB->get_record("course", array("id" => $courseID));
                    if ($course)
                    {
                        $data[] = $course->$courseField;
                        $data[] = $course->fullname;
                    }
                    else
                    {
                        $data[] = 'C101_18';
                        $data[] = 'Some fake course and stuff';
                    }
                    
                    
                } else {
                    // Overall, not for a course
                    $data[] = '';
                    $data[] = '';
                }
                
                // Start/End dates
                $k = mt_rand(0,3);
                $data[] = $startDates[$k];
                $data[] = $endDates[$k];
               
                
                // Start/End times
                $hour = mt_rand(8,12);
                if ($hour < 10) $hour = "0".$hour;
                $min = $mins[array_rand($mins)];
                
                $data[] = ''.$hour.$min.'';
                
                $hour = mt_rand(13,17);
                if ($hour < 10) $hour = "0".$hour;
                $min = $mins[array_rand($mins)];
                
                $data[] = ''.$hour.$min.'';
                
                // Day number
                $dayNum = mt_rand(1,7);
                $data[] = $dayNum;
                
                // Staff
                $k = mt_rand(0, 3);
                $staff = array();
                for($j = 0; $j < $k; $j++){
                    $staff[] = $names[array_rand($names)] . ' ' . $lnames[array_rand($lnames)];
                }
                
                $data[] = implode(", ", $staff);
                
                
                // Room
                $rand = mt_rand(1, 2);
                if ($rand == 2){
                    $data[] = (strtoupper(chr(97 + mt_rand(0, 25)))) . mt_rand(100, 150);
                } else {
                    $data[] = '';
                }
                
                fputcsv($fh, $data);
                
            }
            
        }
        
        
        
        fclose($fh);        
        return $code;       
        
    }
    
    /**
     * Run the csv data import
     * @global \ELBP\Plugins\type $DB
     * @param type $file
     * @param type $fromCron
     * @return type
     */
    public function runImport($file, $fromCron = false){
        
        global $DB;
        
        // If cron, mimic $_FILES element
        if ($fromCron){
            $file = array(
                'tmp_name' => $file
            );
        }
        
        $output = "";
        
        $start = explode(" ", microtime());
        $start = $start[1] + $start[0];
        
        $output .= "*** " . get_string('import:begin', 'block_elbp') . " ".date('H:i:s, D jS M Y')." ***<br>";
        $output .= "*** " . get_string('import:openingfile', 'block_elbp') . " ({$file['tmp_name']}) ***<br>";
                
        // CHeck file exists
        if (!file_exists($file['tmp_name'])){
            return array('success' => false, 'error' => get_string('filenotfound', 'block_elbp') . " ( {$file['tmp_name']} )");
        }
        
        // Check mime type of file to make sure it is csv
        $fInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fInfo, $file['tmp_name']);
        finfo_close($fInfo);
                
        // Has to be csv file, otherwise error and return
        if ($mime != 'text/csv' && $mime != 'text/plain'){
            return array('success' => false, 'error' => get_string('uploads:invalidmimetype', 'block_elbp') . " ( {$mime} )");
        }
        
        // Open file
        $fh = fopen($file['tmp_name'], 'r');
        if (!$fh){
            return array('success' => false, 'error' => get_string('uploads:cantopenfile', 'block_elbp'));
        }
        
        // Compare headers
        $headerRow = fgetcsv($fh);
        $headers = $this->getImportCsvHeaders();
        
        if ($headerRow !== $headers){
            $str = get_string('import:headersdontmatch', 'block_elbp');
            $str = str_replace('%exp%', implode(', ', $headers), $str);
            $str = str_replace('%fnd%', implode(', ', $headerRow), $str);
            return array('success' => false,'error' => $str);
        }
                
        
        // Headers are okay, so let's rock and roll
        $i = 1;
        $validUsernames = array(); // Save us checking same username multiple times - saves processing time
        $validCourses = array(); // Save us checking same course multiple times - saves processing time
        $errorCnt = 0;
        
        
        
        // Which field are we looking at?
        $courseField = $this->getSetting('import_course_field');
        if (!$courseField){
            $courseField = 'shortname';
        }
        
        $userField = $this->getSetting('import_user_field');
        if (!$userField){
            $userField = 'username';
        }
        
        
        while( ($row = fgetcsv($fh)) !== false )
        {
            
            $i++;
            
            $row = array_map('trim', $row);
            
            $username = $row[0];
            $course = $row[1];
            $description = $row[2];
            $startdate = $row[3];
            $enddate = $row[4];
            $starttime = $row[5];
            $endtime = $row[6];
            $daynumber = $row[7];
            $staff = $row[8];
            $room = $row[9];
            
            
            // If any of the required columns are empty, erroy
            if (elbp_is_empty($username) || elbp_is_empty($starttime) || elbp_is_empty($endtime) || elbp_is_empty($startdate) || elbp_is_empty($enddate) || elbp_is_empty($daynumber)){
                $output .= "[{$i}] " . get_string('import:colsempty', 'block_elbp') . " : (".implode(',', $row).")<br>";
                $errorCnt++;
                continue;
            }
            
            
            // Make sure dates are in correct format: yyyymmdd
            if (!ctype_digit($startdate) || (ctype_digit($startdate) && strlen($startdate) <> 8) ){
                $output .= "[{$i}] " . get_string('import:format:yyyymmdd', 'block_elbp') . " : (".$startdate.")<br>";
                $errorCnt++;
                continue;
            }
            
            if (!ctype_digit($enddate) || (ctype_digit($enddate) && strlen($enddate) <> 8) ){
                $output .= "[{$i}] " . get_string('import:format:yyyymmdd', 'block_elbp') . " : (".$enddate.")<br>";
                $errorCnt++;
                continue;
            }
            
            
            
            
            // Make sure times are in correct format: hhmm
            if (!ctype_digit($starttime) || (ctype_digit($starttime) && strlen($starttime) <> 4) ){
                $output .= "[{$i}] " . get_string('import:format:hhmm', 'block_elbp') . " : (".$starttime.")<br>";
                $errorCnt++;
                continue;
            }
            
            if (!ctype_digit($endtime) || (ctype_digit($endtime) && strlen($endtime) <> 4) ){
                $output .= "[{$i}] " . get_string('import:format:hhmm', 'block_elbp') . " : (".$endtime.")<br>";
                $errorCnt++;
                continue;
            }
            
            
            
            
            // Now put a colon in the middle of the times, sinec that's how we are doing it for some reason I may have understood at some point when drunk
            $exp = str_split($starttime, 2);
            $starttime = $exp[0] . ":" . $exp[1];
            
            $exp = str_split($endtime, 2);
            $endtime = $exp[0] . ":" . $exp[1];
            
            
            // Check username exists
            $user = false;
            
            if (!array_key_exists($username, $validUsernames)){
                                
                $user = $DB->get_record("user", array($userField => $username, "deleted" => 0));
                
                if ($user){
                    $validUsernames[$username] = $user;
                } else {
                    
                    // If we have set it to create non-existent users, create it now
                    if ($this->getSetting('import_create_user_if_not_exists') == 1){
                        $user = \elbp_create_user_from_username($username);
                    } 

                    if ($user){
                        $validUsernames[$username] = $user;
                        $output .= "[{$i}] " . get_string('createduser', 'block_elbp') . " : {$username} [{$user->id}]<br>";
                    } else {
                        $output .= "[{$i}] " . get_string('nosuchuser', 'block_elbp') . " : {$username}<br>";
                        $errorCnt++;
                        continue;
                    }
                    
                }
                
                // Wipe student's current records
                $DB->delete_records("lbp_timetable", array("userid" => $user->id));
                
            } else {
                $user = $validUsernames[$username];
            }
            
            // Otherwise it IS in validUsernames, so we already know its fine - carry on
            
            
            
            // Course is optional, if it is set, then check if its valid
            $courseRecord = false;
            
            if (!empty($course)){
                
                if (!array_key_exists($course, $validCourses)){
                                        
                    $courseRecord = $DB->get_record("course", array($courseField => $course), "id, shortname, idnumber, fullname");
                    if ($courseRecord){
                        $validCourses[$course] = $courseRecord;
                    } else {
                        
                        // If we have set it to create non-existent courses, create it now
                        if ($this->getSetting('import_create_course_if_not_exists') == 1){
                            $courseRecord = \elbp_create_course_from_shortname($course);
                        } 
                        
                        if ($courseRecord){
                            $validCourses[$course] = $courseRecord;
                            $output .= "[{$i}] " . get_string('createdcourse', 'block_elbp') . " : {$course} [{$courseRecord->id}]<br>";
                        } else {
                            $output .= "[{$i}] " . get_string('nosuchcourse', 'block_elbp') . " : {$course}<br>";
                            $errorCnt++;
                            continue;
                        }
                        
                        
                    }
                    
                } else {
                    $courseRecord = $validCourses[$course];
                }
                
            }
            
            
            // Make sure daynumber is int
            if (!ctype_digit($daynumber) || (ctype_digit($daynumber) && strlen($daynumber) <> 1) ){
                $output .= "[{$i}] " . get_string('import:format:daynumber', 'block_elbp') . " : (".$daynumber.")<br>";
                $errorCnt++;
                continue;
            }
            
            
            
            // Description, staff and room are all optional and will just be put in as strings anyway
            
            
            
            // At this point everything is okay, so let's actually import the data
            $courseID = (isset($courseRecord) && $courseRecord) ? $courseRecord->id : null;
            
            // Insert record
            $ins = new \stdClass();
            $ins->userid = $user->id;
            $ins->course = $courseID;
            $ins->description = $description;
            $ins->startdate = $startdate;
            $ins->enddate = $enddate;
            $ins->starttime = $starttime;
            $ins->endtime = $endtime;
            $ins->daynumber = $daynumber;
            $ins->staff = $staff;
            $ins->room = $room;
            
            if ($DB->insert_record("lbp_timetable", $ins)){
                $output .= "[{$i}] " . get_string('import:insertedrecord', 'block_elbp') . " - ".fullname($user)." ({$user->username}) [".implode(',', $row)."]<br>";
            } else {
                $output .= "[{$i}] " . get_string('couldnotinsertrecord', 'block_elbp') . " - ".fullname($user)." ({$user->username}) [".implode(',', $row)."]<br>";
            }
            
            
            
            
            
        }
        
        fclose($fh);
        
        $i--; // Header row doesn't count
        
        $str = get_string('import:finished', 'block_elbp');
        $str = str_replace('%num%', $errorCnt, $str);
        $str = str_replace('%ttl%', $i, $str);
        $output .= "*** " . $str . " ***<br>";
        
        $finish = explode(" ", microtime());
        $finish = $finish[1] + $finish[0];
        $output .= "*** ".str_replace('%s%', ($finish - $start) , get_string('import:scripttime', 'block_elbp'))." ***<br>";
                
        return array('success' => true, 'output' => $output);
        
    }
    
    
    /**
     * Run the cron
     * @return boolean
     */
    public function cron(){
        
        // Work out if it needs running or not
        $cronLastRun = $this->getSetting('cron_last_run');
        if (!$cronLastRun) $cronLastRun = 0;
        
        $now = time();
        
        $type = $this->getSetting('cron_timing_type');
        $hour = $this->getSetting('cron_timing_hour');
        $min = $this->getSetting('cron_timing_minute');
        $file = $this->getSetting('cron_file_location');
        
        if ($type === false || $hour === false || $min === false || $file === false) {
            mtrace("Cron settings are missing. (Type:{$type})(Hour:{$hour})(Min:{$min})(File:{$file})");
            return false;
        }
        
        mtrace("Last run: {$cronLastRun}");
        mtrace("Current time: " . date('H:i', $now) . " ({$now})");
        
        switch($type)
        {
            
            // Run every x hours, y minutes
            case 'every':
                
                $diff = 60 * $min;
                $diff += (3600 * $hour);
                
                mtrace("Cron set to run every {$hour} hours, {$min} mins");
                
                // If the difference between now and the last time it was run, is more than the "every" soandso, then run it
                /**
                 * For example:
                 * 
                 * Run every 1 hours, 30 minutes
                 * diff = 5400 seconds
                 * 
                 * Last run: 0 (never)
                 * Time now: 17:45
                 * 
                 * (now unixtimestamp - 0) = No. seconds ago it was run (in this case it'll be millions, sinec its never run)
                 * Is that >= 5400? Yes it is, so run it
                 * 
                 * 
                 * Another example:
                 * 
                 * Run every 1 hours, 30 minutes
                 * diff = 5400 seconds
                 * 
                 * Last run: 15:00
                 * Time now: 16:45
                 * 
                 * (timestamp - timestamp of 15:00) = 6300
                 * Is 6300 >= 5400? - Yes, so run it
                 * 
                 * 
                 * Another example:
                 * 
                 * Run every 3 hours
                 * diff = 10800 seconds
                 * 
                 * Last run: 15:00
                 * Time now: 16:00
                 * 
                 * (16:00 timestamp - 15:00 timestamp) = 3600 seconds
                 * is 3600 >= 10800? - No, so don't run it
                 * 
                 */
                if ( ($now - $cronLastRun) >= $diff )
                {
                    
                    mtrace("Cron set to run...");
                    $result = $this->runImport($file, true);
                    if ($result['success'])
                    {
                        $result['output'] = str_replace("<br>", "\n", $result['output']);
                        mtrace($result['output']);
                        
                        // Now we have finished, delete the file
                        if ( unlink($file) === true ){
                            mtrace("Deleted file: " . $file);
                        } else {
                            mtrace("Could not delete file: " . $file);
                        }
                        
                    }
                    else
                    {
                        mtrace('Error: ' . $result['error']);
                    }
                    
                    // Set last run to now
                    $this->updateSetting('cron_last_run', $now);                    
                    
                }
                else
                {
                    mtrace("Cron not ready to run");
                }
                
            break;
            
            
            // Run at a specific time every day
            case 'specific':
                
                if ($hour < 10) $hour = "0".$hour;
                if ($min < 10) $min = "0".$min;
                
                $hhmm = $hour . $min;
                $nowHHMM = date('Hi');
                
                $unixToday = strtotime("{$hour}:{$min}:00");
                
                mtrace("Cron set to run at {$hour}:{$min}, every day");
                
                /**
                 * 
                 * Example:
                 * 
                 * Run at: 15:45 every day
                 * Current time: 15:00
                 * Last run: 0
                 * hhmm = 1545
                 * nowHHMM = 1500
                 * is 1500 >= 1545? - No, don't run
                 * 
                 * Another example:
                 * 
                 * Run at: 15:45 every day
                 * Current time: 16:00
                 * Last run: 0
                 * is 1600 >= 1545? - yes
                 * is 0 < unixtimestamp of 15:45 today? - yes, okay run it
                 * 
                 * Another example:
                 * 
                 * Run at: 15:45 every day
                 * Current time: 16:00
                 * Last run: 15:45 today
                 * is 1600 >= 1545 - yes
                 * is (unixtimestamp of 15:45 today < unixtimestamp of 15:45 today? - no
                 *                  * 
                 * 
                 */
                
                
                if ( ( $nowHHMM >= $hhmm ) && $cronLastRun < $unixToday )
                {
                    mtrace("Cron set to run...");
                    $result = $this->runImport($file, true);
                    if ($result['success'])
                    {
                        $result['output'] = str_replace("<br>", "\n", $result['output']);
                        mtrace($result['output']);
                        
                        // Now we have finished, delete the file
                        if ( unlink($file) === true ){
                            mtrace("Deleted file: " . $file);
                        } else {
                            mtrace("Could not delete file: " . $file);
                        }
                        
                    }
                    else
                    {
                        mtrace('Error: ' . $result['error']);
                    }
                    
                    // Set last run to now
                    $this->updateSetting('cron_last_run', $now);
                    
                    
                }
                else
                {
                    mtrace("Cron not ready to run");
                }
                
                
            break;
            
        }
        
        return true;
        
    }
    
    
    
    
    
}