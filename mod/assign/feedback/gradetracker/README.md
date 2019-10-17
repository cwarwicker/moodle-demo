# Grade Tracker Assignment Feedback Plugin

This plugin provides a Grade Tracker feedback type for the standard Moodle "assign" module. It works together with the Grade Tracker block and allows you to update any Grade Tracker criteria linked to an assignment, whilst grading the assignment.

If you wish to also have the functionality to create the links between criteria and an assignment, whilst creating/editing the assignment, that can be achieved by adding in a few lines of code changes to your assign module. See [here](https://github.com/cwarwicker/moodle-block_gradetracker/wiki/Activity-Links-Core-Changes) for more information.

Requirements
------------
- block_gradetracker installed
- Moodle 3.1 or higher

Installation
------------
- Download the zip file, using the green "Clone or download" button
- Extract the files 
- Rename the folder inside "moodle-assignfeedback_gradetracker-master" to just "gradetracker".
- Place the "gradetracker" folder inside the /mod/assign/feedback directory of your Moodle site and run through the normal plugin installation procedure.
- You may then need to enable the feedback type, in Site Admin -> Plugins -> Activity Modules -> Assignment -> Feedback Plugins

Licence
------------
http://www.gnu.org/copyleft/gpl.html
