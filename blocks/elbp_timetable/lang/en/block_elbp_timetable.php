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

$string['config'] = 'Block Configuration';
$string['crons:info'] = 'To import timetable data into your Moodle database automatically, you can set up a cron job. Below you will find a template csv file, which you should use for the importing, as well as information about what the file should be called and where it should be stored.';
$string['elbp_timetable:can_change_colours'] = 'Change Timetable Colours';
$string['elbp_timetable:addinstance'] = 'Add Instance';
$string['dataimport:warning'] = 'Since a timetable slot might change drastically, ie. be moved to a different time, day and room, we cannot 100% accurately update timetable records, therefore whenever data is imported into timetable, the current records for that student will be wiped and replaced with whatever is in your csv.';
$string['datasettings:info'] = 'Here you can define settings related to the data import, such as, if the script comes across a user that does not exist, should it create them? etc...';
$string['pluginname'] = 'Timetable Block';
$string['timetable'] = 'Timetable';
$string['showfulltimetable'] = 'Show Full Timetable';
$string['timetableconfig'] = 'Timetable Configuration';
$string['missettings'] = 'MIS Settings';
$string['mis:tablename'] = 'Table/View Name to Query for Timetable Data';
$string['today'] = 'Today';
$string['tomorrow'] = 'Tomorrow';
$string['coursetypeindata'] = 'Course Type in Data';
$string['coursetypeindata:desc'] = 'Should the timetable attempt to link lessons to their Moodle courses? And if so, is the course being stored going to be a Moodle course shortname, idnumber or fullname?';
$string['donotlink'] = 'Do not link to Moodle courses';
$string['shortname'] = '`shortname`';
$string['idnumber'] = '`idnumber`';
$string['fullname'] = '`fullname`';
$string['room'] = 'Room';
$string['fulltimetable'] = 'Full Timetable';
$string['print'] = 'Print';
$string['monday'] = 'Monday';
$string['tuesday'] = 'Tuesday';
$string['wednesday'] = 'Wednesday';
$string['thursday'] = 'Thursday';
$string['friday'] = 'Friday';
$string['saturday'] = 'Saturday';
$string['sunday'] = 'Sunday';
$string['timesettings'] = 'Time Settings';
$string['starthour'] = 'Start Hour';
$string['starthour:desc'] = 'The hour that the timetable begins. Default: 09';
$string['endhour'] = 'End Hour';
$string['endhour:desc'] = 'The hour that the timetable ends. Default: 17';
$string['minutes'] = 'Minutes';
$string['minutes:desc'] = 'How many minutes to break the timetable slots down by. Default: 30';
$string['coloursettings'] = 'Colour Settings';
$string['coloursetting:desc'] = 'This will be the default colour for the given day on the timetable';
$string['changecolours'] = 'Change Colours';
$string['thisweektimetable'] = 'This Week\'s Timetable';
$string['norecordsfound'] = 'No records found...';
$string['text'] = 'Text';
$string['oneweek'] = 'One Week Calendar';
$string['fullcalendar'] = 'Full Calendar';
$string['day'] = 'Day';
$string['week'] = 'Week';
$string['month'] = 'Month';
$string['year'] = 'Year';
$string['mon'] = 'Mon';
$string['tue'] = 'Tue';
$string['wed']= 'Wed';
$string['thu'] = 'Thu';
$string['fri'] = 'Fri';
$string['sat'] = 'Sat';
$string['sun'] = 'Sun';
$string['jumptotoday'] = 'Jump to Today';
$string['back'] = 'Back';
$string['forward'] = 'Forward';
$string['settings'] = 'Settings';
$string['courseruns'] = 'Course runs';
$string['teachingstaff'] = 'Teaching Staff';
$string['january'] = 'January';
$string['feburary'] = 'Feburary';
$string['march'] = 'March';
$string['april'] = 'April';
$string['may'] = 'May';
$string['june'] = 'June';
$string['july'] = 'July';
$string['august'] = 'August';
$string['september'] = 'September';
$string['october'] = 'October';
$string['november'] = 'November';
$string['december'] = 'December';
$string['monletter'] = 'M';
$string['tueletter'] = 'T';
$string['wedletter'] = 'W';
$string['thuletter'] = 'T';
$string['friletter'] = 'F';
$string['satletter'] = 'S';
$string['sunletter'] = 'S';
$string['nocoremis'] = 'No core MIS connection found for this plugin';
$string['settingsupdated'] = 'Settings Updated';
$string['mistest:username:desc'] = 'Enter a valid student username/idnumber and we will run a test query on that student, to see if it returns what you expect';
$string['connectioninvalid'] = 'Plugin MIS connection is invalid';
$string['missingreqfield'] = 'Missing required field mapping';
$string['runtest:allclasses'] = 'Get All Student\'s Classes';
$string['runtest:todayclasses'] = 'Get Student\'s Classes that run today';
$string['mis:username:desc'] = 'Do you want to use the moodle "username" field or the moodle "idnumber" field to reference a user in the external database?';

$string['map:id'] = 'ID';
$string['map:id:desc'] = 'The primary "id" field of the table/view';
$string['map:daynum'] = 'Day Number';
$string['map:daynum:desc'] = 'The field of the table/view that contains the number value of the day, e.g. 1 = Monday, 2 = Tuesday, etc...';
$string['map:dayname'] = 'Day Name';
$string['map:dayname:desc'] = 'The field of the table/view that contains the string value of the day, e.g. Monday, Tuesday, etc...';
$string['map:username'] = 'Username';
$string['map:username:desc'] = 'The field of the table/view that contains the student username/idnumber';
$string['map:lessonname'] = 'Lesson Name';
$string['map:lessonname:desc'] = 'The field of the table/view that contains the name or description of the lesson';
$string['map:staff'] = 'Teaching Staff';
$string['map:staff:desc'] = 'The field of the table/view that contains the name or names of teaching staff on this lesson';
$string['map:course'] = 'Course';
$string['map:course:desc'] = 'The field of the table/view that contains the course code, that is reflected in the moodle course, either by shortname, idnumber or fullname (this setting can be changed in the main settings)';
$string['map:room'] = 'Room';
$string['map:room:desc'] = 'The field of the table/view that contains the room the lesson takes place in';
$string['map:starttime'] = 'Start Time';
$string['map:starttime:desc'] = 'The field of the table/view that contains a string/time value of the start time of the lesson. <br><b style="color:red;">This MUST be in the format 00:00, e.g. 12:30</b>';
$string['map:endtime'] = 'End Time';
$string['map:endtime:desc'] = 'The field of the table/view that contains a string/time value of the end time of the lesson. <br><b style="color:red;">This MUST be in the format 00:00, e.g. 13:30</b>';
$string['map:startdate'] = 'Start Date';
$string['map:startdate:desc'] = 'The field of the table/view that contains a string/date value of the start date of the lesson series. <br><b style="color:red;">This MUST be in the format dd-mm-yyyy, e.g. 21-09-2014</b>';
$string['map:enddate'] = 'End Date';
$string['map:enddate:desc'] = 'The field of the table/view that contains a string/date value of the end date of the lesson series. <br><b style="color:red;">This MUST be in the format dd-mm-yyyy, e.g. 31-07-2015</b>';
$string['dateformat'] = 'Date Format';
$string['dateformat:desc'] = 'The format that any date fields will be in. Examples: 19-12-1987 (d-m-Y), 12-19-1987 (m-d-Y), 19-DEC-1987 (d-M-Y), etc...';
$string['daynumberformat'] = 'Day Number Format';
$string['daynumberformat:desc'] = 'The format in which the day numbers will be in.';
$string['daynumberformat:N'] = '1 (Monday) - 7 (Sunday)';
$string['daynumberformat:w'] = '0 (Sunday) - 6 (Saturday)';

$string['err:daynumformat'] = 'Error: Day Number Format is not set. Your Moodle Administrator will need to fix this.';
$string['err:invalidformat:starttime'] = 'Error: Start time is in wrong format. Must be hh:mm, e.g. 09:30';
$string['err:invalidformat:endtime'] = 'Error: End time is in wrong format. Must be hh:mm, e.g. 09:30';
$string['err:invalidformat:startdate'] = 'Error: Start date is in the wrong format. Must be dd-mm-yyyy, e.g. 19-12-2014';
$string['err:invalidformat:enddate'] = 'Error: End date is in the wrong format. Must be dd-mm-yyyy, e.g. 19-12-2014';

