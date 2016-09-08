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

$string['learningtimecheck:addinstance'] = 'Add a new LT Checklist';
$string['learningtimecheck:edit'] = 'Create and edit LT Checklist';
$string['learningtimecheck:emailoncomplete'] = 'Receive completion emails';
$string['learningtimecheck:preview'] = 'Preview a LT Checklist';
$string['learningtimecheck:updatelocked'] = 'Update locked LT Checklist marks';
$string['learningtimecheck:updateother'] = 'Update students\' LT Checklist marks';
$string['learningtimecheck:updateown'] = 'Update your LT Checklist marks';
$string['learningtimecheck:viewmenteereports'] = 'View mentee progress (only)';
$string['learningtimecheck:viewreports'] = 'View students\' progress';
$string['learningtimecheck:viewcoursecalibrationreport'] = 'View course calibration report';
$string['learningtimecheck:viewtutorboard'] = 'View the tutor time board';
$string['learningtimecheck:forceintrainingsessions'] = 'Can force time report in training session reports';

$string['addcomments'] = 'Add comments';
$string['additem'] = 'Add';
$string['additemalt'] = 'Add a new item to the list';
$string['additemhere'] = 'Insert new item after this one';
$string['addownitems'] = 'Add your own items';
$string['addownitems-stop'] = 'Stop adding your own items';
$string['allowmodulelinks'] = 'Allow module links';
$string['allreports'] = 'Reports';
$string['anygrade'] = 'Any';
$string['applytoall'] = 'Apply to all';
$string['autopopulate'] = 'Show course modules in checklist';
$string['autoupdate'] = 'Check-off when modules complete';
$string['autoupdate_task'] = 'Autoupdating user checks';
$string['autoupdatenote'] = 'It is the \'student\' mark that is automatically updated - no updates will be displayed for \'Teacher only\' checklist';
$string['autoupdatewarning_both'] = 'There are items on this list that will be automatically updated (as students complete the related activity). However, as this is a \'student and teacher\' checklist the progress bars will not update until a teacher agrees the marks given.';
$string['autoupdatewarning_student'] = 'There are items on this list that will be automatically updated (as students complete the related activity).';
$string['autoupdatewarning_teacher'] = 'Automatic updating has been switched on for this checklist, but these marks will not be displayed as only \'teacher\' marks are shown.';
$string['average'] = '(AVG)';
$string['backtocourse'] = 'Back to course';
$string['backtosite'] = 'Back to home';
$string['badlearningtimecheckid'] = 'Bad checklist ID';
$string['both'] = 'les deux';
$string['calendardescription'] = 'This event was added by the checklist: $a';
$string['canceledititem'] = 'Cancel';
$string['changetextcolour'] = 'Next text colour';
$string['checkeditemsdeleted'] = 'Checked items deleted';
$string['checks'] = 'Check marks';
$string['collapseheaders'] = 'Collapse headers';
$string['comments'] = 'Comments';
$string['completionpercent'] = 'Percentage of items that should be checked-off:';
$string['completionpercentgroup'] = 'Require checked-off';
$string['configautoupdateusecron'] = 'Use cron for automatic updates';
$string['configautoupdateusecron_desc'] = 'If enabled, the cron will process the autoupdate of checks with some little delay. If you can accept this delay, this avoids user interface to compile those check states that may
take some while, when browsing inside the learningtimecheck activity.';
$string['configcsvencoding'] = 'CSV encoding';
$string['configcsvencoding_desc'] = 'CSV file encoding';
$string['configcsvfieldseparator'] = 'CSV field separator';
$string['configcsvfieldseparator_desc'] = 'CSV field separator';
$string['configcsvlineseparator'] = 'CSV line separator';
$string['configcsvlineseparator_desc'] = 'CSV line separator';
$string['configapplyfiltering'] = 'Apply filtering';
$string['configapplyfiltering_desc'] = 'If enabled the reports filtering will affect the learningtimacheck module and avoid markes to be generated when invalid event.';
$string['configinitialautocapture'] = 'Initial autocapture';
$string['configinitialautocapture_desc'] = 'What autocapture mode is defaulting when creating a learningtimecheck instance';
$string['configinitiallymandatory'] = 'Items initially mandatory';
$string['configinitiallymandatory_desc'] = 'If checked, all the automatically generated items will have optionality off. If not checked all items discovered in sections will be marked as optional';
$string['configinitialcredittimeon'] = 'Credit time initially on';
$string['configinitialcredittimeon_desc'] = 'If checked, credit time are initially enabled when creating a new learningtimecheck';
$string['configlastcompiled'] = 'Last cron compilation date';
$string['configlastcompiled_desc'] = 'changing this date will reconsider all events that may be missed in the past';
$string['configintegrateusestats'] = 'Integrate use_stats results';
$string['configintegrateusestats_desc'] = 'If enabled, use stats tracking measurements will be integrated into learningtimecheck reports';
$string['configlearningtimecheckautoupdate'] = 'Before allowing this you must make a few changes to the core Moodle code, please see mod/learningtimecheck/README.txt for details';
$string['configlearningtimecheckautoupdateusecron'] = 'Checks will be automatically detected using log events';
$string['configshowcompletemymoodle'] = 'Show completed checklists on \'My Moodle\' page';
$string['configshowcompletemymoodle_desc'] = 'If this is unchecked then completed checklist  will be hidden from the \'My Moodle\' page';
$string['configshowmymoodle'] = 'Show checklist  on \'My Moodle\' page';
$string['configshowmymoodle_desc'] = 'If this is unchecked then checklist activities (with progress bars) will no longer appear on the \'My Moodle\' page';
$string['confirmdeleteitem'] = 'Are you sure you want to permanently delete this checklist item?';
$string['configcouplecredittomandatoryoption'] = 'Credit/Mandatory coupling';
$string['configcouplecredittomandatoryoption_desc'] = 'If set, affecting a positive credit time to an item will turn it mandatory';
$string['coursecalibrationreport'] = 'Course Learning Time Calibration';
$string['coursecompletionboard'] = 'Course completion';
$string['coursetotaltime'] = 'Course time';
$string['credit'] = 'Credit';
$string['credittime'] = 'Credit time: ';
$string['itemcredittime'] = 'Credit time: {$a} min.';
$string['deleteitem'] = 'Delete this item';
$string['disabled'] = 'Disabled';
$string['itemsdone'] = 'Items done';
$string['edit'] = 'Edit learningtimecheck';
$string['editchecks'] = 'Edit checks';
$string['editdatesstart'] = 'Edit dates';
$string['editdatesstop'] = 'Stop editing dates';
$string['editingoptions'] = 'Editing options';
$string['edititem'] = 'Edit this item';
$string['emailoncomplete'] = 'Email when learningtimecheck is complete:';
$string['emailoncompletesubject'] = 'User {$a->user} has completed checklist \'{$a->learningtimecheck}\'';
$string['emailoncompletesubjectown'] = 'You have completed checklist \'{$a->learningtimecheck}\'';
$string['enablecredit'] = 'Enable credit time report(*)';
$string['enablecredit_desc'] = ' (*) If enabled, this time will be used in Training Session Report (add-on), in place of the measured time from user logs.';
$string['estimated'] = 'Declared (self estimated)';
$string['errornosuchuser'] = 'This user does\'nt exist any more';
$string['errorbadinstance'] = 'The learningtimecheck instance is missing : cmid {$a} ';
$string['errornoeditcapability'] = 'You do not have permission to export items from this learningtimecheck';
$string['expandheaders'] = 'Expand headers';
$string['expectedtutored'] = 'Planned tutor time expense';
$string['export'] = 'Export items';
$string['exportexcel'] = 'Export as XLS';
$string['exportpdf'] = 'Export as PDF';
$string['forceupdate'] = 'Update checks for all automatic items';
$string['fullview'] = 'View checklist details';
$string['fullviewdeclare'] = 'View checklist details, and declare time counterparts if needed';
$string['gradetocomplete'] = 'Grade to complete:';
$string['guestsno'] = 'You do not have permission to view this checklist';
$string['headingitem'] = 'This item is a heading - it will not have a checkbox beside it';
$string['hiddenbymodule'] = 'This item is hidden because the course module it refers is not visible';
$string['import'] = 'Import items';
$string['importfile'] = 'Choose file to import';
$string['importfromcourse'] = 'Whole course';
$string['importfrompage'] = 'Current course page';
$string['importfrompageandsubs'] = 'Current course page and subs';
$string['importfromsection'] = 'Current section';
$string['importfromtoppage'] = 'All chapter (from top page)';
$string['indentitem'] = 'Indent item';
$string['isdeclarative'] = 'Is declarative';
$string['ismandatory'] = 'Mandatory';
$string['itemcomplete'] = 'Completed';
$string['items'] = 'Checklist items';
$string['itemstodo'] = 'Items to do';
$string['learningvelocities'] = 'Learning Velocity';
$string['uservelocity'] = 'Learner Velocity';
$string['days'] = 'Days';
$string['hours'] = 'Hours';
$string['back'] = 'Back';
$string['lastcompiledtime'] = 'Last compiled time';
$string['learningtimecheck'] = 'Learning Time Check';
$string['learningtimecheck_autoupdate_use_cron'] = 'Enable autoupdate by cron';
$string['learningtimecheckautoupdate'] = 'Allow checklist to automatically update';
$string['learningtimecheckfor'] = 'Checklist for';
$string['learningtimecheckintro'] = 'Introduction';
$string['learningtimechecksettings'] = 'Settings';
$string['linktomodule'] = 'Link to this module';
$string['listpreview'] = 'Preview of the checklist';
$string['myprogress'] = 'My progress';
$string['lockteachermarks'] = 'Lock teacher marks';
$string['lockteachermarkswarning'] = 'Note: Once you have saved these marks, you will be unable to change any \'Yes\' marks';
$string['marktypes'] = 'Mark types';
$string['mandatory'] = 'mandatory';
$string['optional'] = 'optional';
$string['modulename'] = 'Learning Time Check';
$string['modulenameplural'] = 'Learning Time Checks';
$string['moduletotaltime'] = 'Module time';
$string['moveitemdown'] = 'Move item down';
$string['moveitemup'] = 'Move item up';
$string['noitems'] = 'No items in the checklist';
$string['nousers'] = 'No users in this context';
$string['nochecks'] = 'No checks for achieved activity';
$string['noinstances'] = 'There are no instances of learningtimecheck';
$string['completionboard'] = 'Board of achievements';
$string['optionalhide'] = 'Hide optional items';
$string['optionalitem'] = 'This item is optional';
$string['optionalshow'] = 'Show optional items';
$string['percentcomplete'] = 'Required items';
$string['percentcompleteall'] = 'All items';
$string['pluginadministration'] = 'Checklist administration';
$string['pluginname'] = 'Learning Time Check';
$string['preview'] = 'Preview';
$string['progress'] = 'Progress';
$string['progressbar'] = 'Progress';
$string['ratioleft'] = '% left';
$string['realtutored'] = 'Real tutor time expense';
$string['removeauto'] = 'Remove course module items';
$string['report'] = 'View Progress';
$string['reportedby'] = 'Reported by {$a} ';
$string['reports'] = 'Global reports';
$string['reporttablesummary'] = 'Table showing the items on the checklist that each student has completed';
$string['requireditem'] = 'This item is required - it must be completed';
$string['resetlearningtimecheckprogress'] = 'Reset checklist progress and user items';
$string['checksrefreshed'] = 'Checks have been refreshed.';
$string['refresh'] = 'Refresh check states (may be long)';
$string['saveall'] = 'Save all';
$string['savechecks'] = 'Save';
$string['showfulldetails'] = 'Show full details';
$string['showprogressbars'] = 'Show progress bars';
$string['studenthasdeclared'] = 'Student has declared : <b>$a</b> minutes ';
$string['studentmarkno'] = 'Enabled';
$string['studentmarkyes'] = 'Disabled';
$string['summators'] = 'Averages and totals: ';
$string['teacheralongsidecheck'] = 'Student and teacher';
$string['teachercomments'] = 'Teachers can add comments';
$string['teachercredittime'] = 'Teacher Credit time';
$string['teachercredittimeforitem'] = 'Teacher time for item:';
$string['teachercredittimeforusers'] = 'Teacher time for all users:';
$string['teachercredittimeperuser'] = 'Per user teacher time:';
$string['teacherdate'] = 'Date a teacher last updated this item';
$string['teacheredit'] = 'Updates by';
$string['teacherid'] = 'The teacher who last updated this mark';
$string['teachermark'] = 'Teacher mark: ';
$string['teachermarkno'] = 'Teacher states that you have NOT completed this';
$string['teachermarkundecided'] = 'Teacher has not yet marked this';
$string['teachermarkyes'] = 'Teacher states that you have completed this';
$string['eithercheck'] = 'Either';
$string['teachernoteditcheck'] = 'Student only';
$string['teacheroverwritecheck'] = 'Teacher only';
$string['teachertimetodeclare'] = 'Teacher declared time';
$string['teachertimetodeclareperuser'] = 'Teacher declared time for user';
$string['theme'] = 'learningtimecheck display theme';
$string['timedone'] = 'Time done';
$string['timeduefromcompletion'] = 'This due date is forced by the activity completion settings';
$string['timeleft'] = 'Time remaining';
$string['timesource'] = 'Time source';
$string['timetodeclare'] = 'Work Time ';
$string['toggledates'] = 'Toggle dates';
$string['toggledates'] = 'Toggle names & dates';
$string['totalcourse'] = 'Course total';
$string['totalcourseratio'] = 'Ratio upon all course';
$string['coursetotalitems'] = 'total items for course';
$string['totalcoursetime'] = 'Total course time';
$string['totalestimatedtime'] = 'Total estimated time';
$string['totalized'] = '(TOT)';
$string['totalteacherestimatedtime'] = 'Total teacher estimated time';
$string['tutorboard'] = 'Tutor board';
$string['uncheckoptional'] = 'Uncheck to make it optional';
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
$string['view_pageitem_progress'] = 'View as own progressbar for students';
$string['view_pageitem_withoutlinks'] = 'View as blocks with no links';
$string['viewall'] = 'View all students';
$string['viewallcancel'] = 'Cancel';
$string['viewallsave'] = 'Save';
$string['viewwithoutlinks'] = 'without activity links';
$string['viewsinglereport'] = 'View progress for this user';
$string['viewsingleupdate'] = 'Update progress for this user';
$string['yesnooverride'] = 'Yes, cannot override';
$string['yesoverride'] = 'Yes, can override';
$string['save'] = 'Save';
$string['errornodate'] = 'Error : a filter must have a datetime';
$string['errornologop'] = 'Error : additional filters must have a logical operator defined';
$string['itemenable'] = 'Enable item';
$string['itemdisable'] = 'Ignore item';

$string['and'] = 'AND';
$string['or'] = 'OR';
$string['xor'] = 'EXCLUSIVE OR';

$string['courseenroltime'] = 'Course enrol time';
$string['filtering'] = 'Output filtering';
$string['firstcheckaquired'] = 'First (mandatory) check acquired';
$string['checkcomplete'] = 'Checklist completed';
$string['coursestarted'] = 'Course started (first effective track)';
$string['coursecompleted'] = 'Course completed';
$string['lastcoursetrack'] = 'Last course track time ';
$string['onecertificateissued'] = 'One of certificates issue time ';
$string['allcertificatesissued'] = 'All certificate finished'; 
$string['usercreationdate'] = 'User creation date';
$string['sitefirstevent'] = 'First event for user';
$string['sitelastevent'] = 'Last event for user';
$string['firstcoursestarted'] = 'First course started';
$string['firstcoursecompleted'] = 'First course completed';
$string['usercohortaddition'] = 'User added in cohort';

$string['emailoncompletebody'] = 'User {$a->user} has completed checklist \'{$a->learningtimecheck}\' in the course \'{$a->coursename}\' 
View the learningtimecheck here:';

$string['emailoncomplete_help'] = 'When a checklist is complete, a notification email can be sent: to the student who completed it, to all the teachers on the course or to both.<br />
An administrator can control who receives this email using the capability \'mod:learningtimecheck/emailoncomplete\' - by default all teachers and non-editing teachers have this capability.';

$string['emailoncompletebodyown'] = 'You have completed checklist \'{$a->learningtimecheck}\' in the course \'{$a->coursename}\' 
View the checklist here:';

$string['modulename_help'] = 'This module help teachers and students to mark activites and to assess job done, while feeding course objectives with standard credit time or 
declared credit time. Off line checkable items can be added, also student declared offline tasks can be added to the marking board. Times validaed by teachers will be 
sent to Training Session reports to appear as required in course time reports.
';
$string['lockteachermarks_help'] = 'When this setting is enabled, once a teacher has saved a \'Yes\' mark, they will be unable to change it. 
Users with the capability \'mod/learningtimecheck:updatelocked\' will still be able to change the mark.
';
$string['autopopulate_help'] = 'This will automatically add a list of all the resources and activities in the current course into the LT Checklist.<br />
This list will be updated with any changes in the course, whenever you visit the \'Edit\' page for the checklist.<br />
Items can be hidden from the list, by clicking on the \'hide\' icon beside them.<br />
To remove the automatic items from the list, change this option back to \'No\', then click on \'Remove course module items\' on the \'Edit\' page.
';
$string['autoupdate_help'] = 'This will automatically check-off items in your LT Checklist when you complete the relevant activity in the course.<br />
\'Completing\' an activity varies from one activity to another - \'view\' a resource, \'submit\' a quiz or assignment, \'post\' to a forum or join in with a chat, etc.<br />
If a Moodle 2.0 completion tracking is switched on for a particular activity, that will be used to tick-off the item in the list<br />
For details of exactly what causes an activity to be marked as \'complete\', ask your site administrator to look in the file \'mod/learningtimecheck/autoupdatelib.php\'<br />
Note: it can take up to 60 seconds for a student\'s activity to be reflected in their LT Checklist
';
