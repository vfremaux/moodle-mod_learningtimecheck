<?php

// This file is part of the learningtimecheck plugin for Moodle - http://moodle.org/
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

$string['learningtimecheck:addinstance'] = 'Add a new learningtimecheck';
$string['learningtimecheck:edit'] = 'Create and edit learningtimechecks';
$string['learningtimecheck:emailoncomplete'] = 'Receive completion emails';
$string['learningtimecheck:preview'] = 'Preview a learningtimecheck';
$string['learningtimecheck:updatelocked'] = 'Update locked learningtimecheck marks';
$string['learningtimecheck:updateother'] = 'Update students\' learningtimecheck marks';
$string['learningtimecheck:updateown'] = 'Update your learningtimecheck marks';
$string['learningtimecheck:viewmenteereports'] = 'View mentee progress (only)';
$string['learningtimecheck:viewreports'] = 'View students\' progress';
$string['learningtimecheck:viewcoursecalibrationreport'] = 'View course calibration report';
$string['learningtimecheck:viewtutorboard'] = 'View the tutor time board';

$string['addcomments'] = 'Add comments';
$string['additem'] = 'Add';
$string['additemalt'] = 'Add a new item to the list';
$string['additemhere'] = 'Insert new item after this one';
$string['addownitems'] = 'Add your own items';
$string['addownitems-stop'] = 'Stop adding your own items';
$string['allowmodulelinks'] = 'Allow module links';
$string['anygrade'] = 'Any';
$string['autopopulate'] = 'Show course modules in learningtimecheck';
$string['autoupdate'] = 'Check-off when modules complete';
$string['autopopulate_help'] = 'This will automatically add a list of all the resources and activities in the current course into the learningtimecheck.<br />
This list will be updated with any changes in the course, whenever you visit the \'Edit\' page for the learningtimecheck.<br />
Items can be hidden from the list, by clicking on the \'hide\' icon beside them.<br />
To remove the automatic items from the list, change this option back to \'No\', then click on \'Remove course module items\' on the \'Edit\' page.';
$string['autoupdate_help'] = 'This will automatically check-off items in your learningtimecheck when you complete the relevant activity in the course.<br />
\'Completing\' an activity varies from one activity to another - \'view\' a resource, \'submit\' a quiz or assignment, \'post\' to a forum or join in with a chat, etc.<br />
If a Moodle 2.0 completion tracking is switched on for a particular activity, that will be used to tick-off the item in the list<br />
For details of exactly what causes an activity to be marked as \'complete\', ask your site administrator to look in the file \'mod/learningtimecheck/autoupdate.php\'<br />
Note: it can take up to 60 seconds for a student\'s activity to be reflected in their learningtimecheck';
$string['autoupdatenote'] = 'It is the \'student\' mark that is automatically updated - no updates will be displayed for \'Teacher only\' learningtimechecks';

$string['autoupdatewarning_both'] = 'There are items on this list that will be automatically updated (as students complete the related activity). However, as this is a \'student and teacher\' learningtimecheck the progress bars will not update until a teacher agrees the marks given.';
$string['autoupdatewarning_student'] = 'There are items on this list that will be automatically updated (as students complete the related activity).';
$string['autoupdatewarning_teacher'] = 'Automatic updating has been switched on for this learningtimecheck, but these marks will not be displayed as only \'teacher\' marks are shown.';
$string['backtocourse'] = 'Back to course';
$string['backtosite'] = 'Back to home';
$string['calendardescription'] = 'This event was added by the learningtimecheck: $a';
$string['credit'] = 'Credit';
$string['canceledititem'] = 'Cancel';
$string['changetextcolour'] = 'Next text colour';
$string['checkeditemsdeleted'] = 'Checked items deleted';
$string['learningtimecheck'] = 'learningtimecheck';
$string['pluginadministration'] = 'learningtimecheck administration';
$string['learningtimecheckautoupdate'] = 'Allow learningtimechecks to automatically update';
$string['learningtimecheckfor'] = 'learningtimecheck for';
$string['learningtimecheckintro'] = 'Introduction';
$string['learningtimechecksettings'] = 'Settings';
$string['checks'] = 'Check marks';
$string['comments'] = 'Comments';
$string['completionpercentgroup'] = 'Require checked-off';
$string['completionpercent'] = 'Percentage of items that should be checked-off:';
$string['configlearningtimecheckautoupdate'] = 'Before allowing this you must make a few changes to the core Moodle code, please see mod/learningtimecheck/README.txt for details';
$string['configshowcompletemymoodle'] = 'If this is unchecked then completed learningtimechecks will be hidden from the \'My Moodle\' page';
$string['configshowmymoodle'] = 'If this is unchecked then learningtimecheck activities (with progress bars) will no longer appear on the \'My Moodle\' page';
$string['confirmdeleteitem'] = 'Are you sure you want to permanently delete this learningtimecheck item?';
$string['coursecalibrationreport'] = 'Rapport de calibration des temps de cours';
$string['coursecompletionboard'] = 'Course completion';
$string['credittime'] = 'Credit time: ';
$string['deleteitem'] = 'Delete this item';
$string['disabled'] = 'Disabled';
$string['duedatesoncalendar'] = 'Add due dates to calendar';
$string['edit'] = 'Edit learningtimecheck';
$string['editchecks'] = 'Edit checks';
$string['editdatesstart'] = 'Edit dates';
$string['editdatesstop'] = 'Stop editing dates';
$string['edititem'] = 'Edit this item';
$string['enablecredit'] = 'Enable credit time';
$string['isdeclarative'] = 'Est déclaratif';
$string['both'] = 'les deux';
$string['teachercredittime'] = 'Teacher Credit time';
$string['totalcoursetime'] = 'Total course time';
$string['totalestimatedtime'] = 'Total estimated time';
$string['totalteacherestimatedtime'] = 'Total teacher estimated time';
$string['emailoncomplete'] = 'Email when learningtimecheck is complete:';
$string['emailoncomplete_help'] = 'When a learningtimecheck is complete, a notification email can be sent: to the student who completed it, to all the teachers on the course or to both.<br />
An administrator can control who receives this email using the capability \'mod:learningtimecheck/emailoncomplete\' - by default all teachers and non-editing teachers have this capability.';
$string['emailoncompletesubject'] = 'User {$a->user} has completed learningtimecheck \'{$a->learningtimecheck}\'';
$string['emailoncompletesubjectown'] = 'You have completed learningtimecheck \'{$a->learningtimecheck}\'';
$string['emailoncompletebody'] = 'User {$a->user} has completed learningtimecheck \'{$a->learningtimecheck}\' in the course \'{$a->coursename}\' 
View the learningtimecheck here:';
$string['emailoncompletebodyown'] = 'You have completed learningtimecheck \'{$a->learningtimecheck}\' in the course \'{$a->coursename}\' 
View the learningtimecheck here:';

$string['export'] = 'Export items';
$string['forceupdate'] = 'Update checks for all automatic items';
$string['fullview'] = 'View learningtimecheck details';
$string['fullviewdeclare'] = 'View learningtimecheck details, and declare time counterparts if needed';
$string['gradetocomplete'] = 'Grade to complete:';
$string['guestsno'] = 'You do not have permission to view this learningtimecheck';
$string['headingitem'] = 'This item is a heading - it will not have a checkbox beside it';
$string['import'] = 'Import items';
$string['importfile'] = 'Choose file to import';
$string['importfromcourse'] = 'Whole course';
$string['importfromsection'] = 'Current section';
$string['importfrompage'] = 'Current course page';
$string['importfrompageandsubs'] = 'Current course page and subs';
$string['indentitem'] = 'Indent item';
$string['itemcomplete'] = 'Completed';
$string['items'] = 'learningtimecheck items';
$string['linktomodule'] = 'Link to this module';
$string['lockteachermarks'] = 'Lock teacher marks';
$string['lockteachermarks_help'] = 'When this setting is enabled, once a teacher has saved a \'Yes\' mark, they will be unable to change it. Users with the capability \'mod/learningtimecheck:updatelocked\' will still be able to change the mark.';
$string['lockteachermarkswarning'] = 'Note: Once you have saved these marks, you will be unable to change any \'Yes\' marks';
$string['modulename'] = 'learningtimecheck';
$string['modulenameplural'] = 'learningtimechecks';
$string['moveitemdown'] = 'Move item down';
$string['moveitemup'] = 'Move item up';
$string['noitems'] = 'No items in the learningtimecheck';
$string['optionalhide'] = 'Hide optional items';
$string['optionalitem'] = 'This item is optional';
$string['optionalshow'] = 'Show optional items';
$string['percentcomplete'] = 'Required items';
$string['percentcompleteall'] = 'All items';
$string['pluginname'] = 'learningtimecheck';
$string['preview'] = 'Preview';
$string['progress'] = 'Progress';
$string['removeauto'] = 'Remove course module items';
$string['report'] = 'View Progress';
$string['reportedby'] = 'Reported by $a ';
$string['reporttablesummary'] = 'Table showing the items on the learningtimecheck that each student has completed';
$string['requireditem'] = 'This item is required - it must be completed';
$string['resetlearningtimecheckprogress'] = 'Reset learningtimecheck progress and user items';
$string['savechecks'] = 'Save';
$string['showcompletemymoodle'] = 'Show completed learningtimechecks on \'My Moodle\' page';
$string['showfulldetails'] = 'Show full details';
$string['showmymoodle'] = 'Show learningtimechecks on \'My Moodle\' page';
$string['showprogressbars'] = 'Show progress bars';
$string['studenthasdeclared'] = 'Student has declared : <b>$a</b> minutes ';
$string['teachercomments'] = 'Teachers can add comments';
$string['teacherdate'] = 'Date a teacher last updated this item';
$string['teacheredit'] = 'Updates by';
$string['teacherid'] = 'The teacher who last updated this mark';
$string['teachermarkundecided'] = 'Teacher has not yet marked this';
$string['teachermarkyes'] = 'Teacher states that you have completed this';
$string['teachermarkno'] = 'Teacher states that you have NOT completed this';
$string['teachernoteditcheck'] = 'Student only';
$string['teacheroverwritecheck'] = 'Teacher only';
$string['teacheralongsidecheck'] = 'Student and teacher';
$string['toggledates'] = 'Toggle names & dates';
$string['theme'] = 'learningtimecheck display theme';
$string['totalcourse'] = 'Course total';
$string['realtutored'] = 'Real tutor time expense';
$string['expectedtutored'] = 'Planned tutor time expense';
$string['tutorboard'] = 'Tutor board';
$string['toggledates'] = 'Toggle dates';
$string['unindentitem'] = 'Unindent item';
$string['unvalidate'] = 'Unvalidate';
$string['updatecompletescore'] = 'Save completion grades';
$string['updateitem'] = 'Update';
$string['userdate'] = 'Date the user last updated this item';
$string['useritemsallowed'] = 'User can add their own items';
$string['useritemsdeleted'] = 'User items deleted';
$string['usetimecounterpart'] = 'Use activity standard time counterpart: ';
$string['validate'] = 'Validate';
$string['view'] = 'View learningtimecheck';
$string['viewall'] = 'View all students';
$string['viewallcancel'] = 'Cancel';
$string['viewallsave'] = 'Save';
$string['viewsinglereport'] = 'View progress for this user';
$string['viewsingleupdate'] = 'Update progress for this user';
$string['yesnooverride'] = 'Yes, cannot override';
$string['yesoverride'] = 'Yes, can override';
$string['learningtimecheck_autoupdate_use_cron'] = 'Enable autoupdate by cron';
$string['configlearningtimecheckautoupdateusecron'] = 'Checks will be automatically detected using log events';
