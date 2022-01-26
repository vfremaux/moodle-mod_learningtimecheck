learningtimecheck module
================

==Introduction==
This is a Moodle plugin for Moodle 1.9 & 2.x that allows a teacher to create a learningtimecheck for their students to work through.
The teacher can monitor all the student's progress, as they tick off each of the items in the list.
Note: This is the Moodle 2.x version.

Items can be indented and marked as optional or turned into headings; a range of different colours can be used for the items.
Students are presented with a simple chart showing how far they have progressed through the required/optional items and can add their own, private, items to the list.

==Changes==

* 2013-03-01 - Fixed the backup & restore of items linked to course modules.
* 2013-01-04 - Option to email students when their learningtimechecks are complete - added by Andriy Semenets
* 2013-01-03 - Fixed the 'show course modules in learningtimecheck' feature in Moodle 2.4
* 2012-12-07 - Moodle 2.4 compatibility fixes
* 2012-10-09 - Fixed email sending when learningtimechecks are complete (thanks to Longfei Yu for the bug report + fix)
* 2012-09-20 - CONTRIB-3921: broken images in intro text; CONTRIB-3904: error when resetting courses; CONTRIB-3916: learningtimechecks can be hidden from 'My Moodle' (either all learningtimechecks, or just completed learningtimechecks); issue with learningtimechecks updating from 'completion' fixed; CONTRIB-3897: teachers able to see who last updated the teacher mark
* 2012-09-19 - Split the 3 plugins (mod / block / grade report) into separate repos for better maintenance; added 'spinner' when updating server
* 2012-08-25 - Minor fix to grade update function
* 2012-08-06 - Minor fix to reduce chance of hitting max_input_vars limits when updated all student's checkmarks
* 2012-07-07 - Improved progress bar styling; Improved debugging of automatic updates (see below); Fixed minor debug warnings
* 2012-04-07 - mod/learningtimecheck:addinstance capability added (for M2.3); Russian / Ukranian translations from Andriy Semenets
* 2012-03-05 - Bug fix: grades not updating when new items added to a course (with 'import course activities' on)
* 2012-01-27 - French translation from Luiggi Sansonetti
* 2012-01-02 - Minor tweaks to improve Moodle 2.2+ compatibility (optional_param_array / context_module::instance )
* 2012-01-02 - CONTRIB-2979: remembers report settings (sort order, etc.) until you log out; CONTRIB-3308 - 'viewmenteereport' capability, allowing users to view reports of users they are mentors for

==Installation==
The learningtimecheck block and grade report are separate, optional plugins that can be downloaded from:
http://moodle.org/plugins/view.php?plugin=block_learningtimecheck
http://moodle.org/plugins/view.php?plugin=gradeexport_learningtimecheck

1. Unzip the contents of file you downloaded to a temporary folder.
2. Upload the files to the your moodle server, placing the 'mod/learningtimecheck' files in the '[moodlefolder]/mod/learningtimecheck', (optionally) the 'blocks/learningtimecheck' files in the '[moodlefolder]/blocks/learningtimecheck' folder and (optionally) the 'grade/export/learningtimecheck' files in the '[moodlefolder]/grade/export/learningtimecheck' folder.
3. Log in as administrator and click on 'Notifications' in the admin area to update the Moodle database, ready to use this plugin.

IMPORTANT: The 'Check-off modules when complete' option now works via cron, by default. This means that there can be a delay of up to 60 seconds (or more - depending on how often your site runs 'cron' updates), between a student completing an activity and their learningtimecheck being updated.
If you are not happy with this delay, then make the changes found in the file core_modifications.txt

Note: if you are upgrading from a previous version, please delete the file 'mod/learningtimecheck/settings.php' from the server, as it is no longer needed.

==Problems with automatic update?==

Whilst automatic updates are working fine in all situations I have tested, there have been some reports of these not updating check-marks correctly on some sites.
If this is the case on your site, there are a couple of things to try, before contacting me:
1. Make sure the learningtimecheck is set to 'Student only' - it is the student mark that is automatically updated, if this is not displayed, you won't see any changes.
2. Make sure cron updates are running on your Moodle server.
3. Edit [moodledir]/mod/learningtimecheck/autoupdate.php and remove the '//' from the start of the line 'define("DEBUG_learningtimecheck_AUTOUPDATE", 1)'. Run a manual cron update ( http://[siteurl]/admin/cron.php ) and check the detailed feedback for the learningtimecheck module.

==Adding a learningtimecheck block==
(Optional plugin)
1. Click 'Turn editing on', in a course view.
2. Under 'blocks', choose 'learningtimecheck'
3. Click on the 'Edit' icon in the new block to set which  learningtimecheck to display and (optionally) which group of users to display.

==Exporting learningtimecheck progress (Excel)==
(Optional plugin)
1. In a course, click 'Grades'
2. From the dropdown menu, choose 'Export => learningtimecheck Export'
3. Choose the learningtimecheck you want to export and click 'Export Excel'
If you want to change the user information that is included in the export ('First name', 'Surname', etc.), then edit the file 'grade/export/learningtimecheck/columns.php' - instructions can be found inside the file itself.

==Usage==
Click on 'Add an activity' and choose 'learningtimecheck'.
Enter all the usual information.
You can optionally allow students to add their own, private items to the list (this will not affect the overall progress, but may help students to keep note of anything extra they need to do).

You can then add items to the list.
Click on the 'tick' to toggle an item between required, optional and heading
Click on the 'edit' icon to change the text.
Click on the 'indent' icons to change the level of indent.
Click on the 'move' icons to move the item up/down one place.
Click on the 'delete' icon to delete the item.
Click on the '+' icon to insert a new item immediately below the current item.

Click on 'Preview', to get some idea of how this will look to students.
Click on 'Results', to see a chart of how the students are currently progressing through the learningtimecheck.

Students can now log in, click on the learningtimecheck, tick any items they have completed and then click 'Save' to update the database.
If you have allowed them to do so, they can click on 'Start Adding Items', then click on the green '+' icons to insert their own, private items to the list.

If you allow a learningtimecheck to be updated by teachers (either exclusively, or in addition to students), it can be updated by doing the following:
1. Click 'Results'
2. Click on the little 'Magnifying glass' icon, beside the student's name
3. Choose Yes / No for each item
4. Click 'Save'
5. (Optional) Click 'Add comments', enter/update/delete a comment against each item, Click 'Save'
5. Click 'View all Progress' to go back to the view with all the students shown.

==Further information==
Moodle plugins database entry: http://moodle.org/plugins/view.php?plugin=mod_learningtimecheck
Report a bug, or suggest an improvement: http://tracker.moodle.org/browse/CONTRIB/component/10608

==Contact details==
Any questions, suggested improvements to:
Davo Smith - moodle@davosmith.co.uk
Any enquiries about custom development to Synergy Learning: http://www.synergy-learning.com

2017081000
=================================

Renormalise completion storage with explicit enablers to rip off value confusion.

2019040600 - XXXX.009
=================================

Adds lockstudentinput to freeze declarative time from student changes
Adds declaredoverridepolicy to drive how declarative time will combine into reports and output.