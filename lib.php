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

/**
 * @package mod_learningtimecheck
 * @category mod
 * @author  David Smith <moodle@davosmith.co.uk> as checklist
 * @author Valery Fremaux
 * @version Moodle 2.7
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

/*
 * Library of functions and constants for module learningtimecheck
 * This file should have two well differenced parts:
 *   - All the core Moodle functions, neeeded to allow
 *     the module to work integrated in Moodle.
 *   - All the learningtimecheck specific functions, needed
 *     to implement all the module logic. Please, note
 *     that, if the module become complex and this lib
 *     grows a lot, it's HIGHLY recommended to move all
 *     these module specific functions to a new php file,
 *     called "locallib.php" (see forum, quiz...). This will
 *     help to save some memory when Moodle is performing
 *     actions across all modules.
 */

define("LEARNINGTIMECHECK_EMAIL_NO", 0);
define("LEARNINGTIMECHECK_EMAIL_STUDENT", 1);
define("LEARNINGTIMECHECK_EMAIL_TEACHER", 2);
define("LEARNINGTIMECHECK_EMAIL_BOTH", 3);

define("LEARNINGTIMECHECK_TEACHERMARK_NO", 2);
define("LEARNINGTIMECHECK_TEACHERMARK_YES", 1);
define("LEARNINGTIMECHECK_TEACHERMARK_UNDECIDED", 0);

define("LEARNINGTIMECHECK_MARKING_STUDENT", 0);
define("LEARNINGTIMECHECK_MARKING_TEACHER", 1);
define("LEARNINGTIMECHECK_MARKING_BOTH", 2);
define("LEARNINGTIMECHECK_MARKING_EITHER", 3);

define("LEARNINGTIMECHECK_AUTOUPDATE_CRON_NO", 0);
define("LEARNINGTIMECHECK_AUTOUPDATE_CRON_YES", 2);

define("LEARNINGTIMECHECK_AUTOUPDATE_NO", 0);
define("LEARNINGTIMECHECK_AUTOUPDATE_YES", 2);

define("LEARNINGTIMECHECK_AUTOPOPULATE_NO", 0);
define("LEARNINGTIMECHECK_AUTOPOPULATE_SECTION", 2);
define("LEARNINGTIMECHECK_AUTOPOPULATE_CURRENT_PAGE", 2);
define("LEARNINGTIMECHECK_AUTOPOPULATE_CURRENT_PAGE_AND_SUBS", 3);
define("LEARNINGTIMECHECK_AUTOPOPULATE_CURRENT_TOP_PAGE", 4);
define("LEARNINGTIMECHECK_AUTOPOPULATE_COURSE", 1);

define("LEARNINGTIMECHECK_MAX_INDENT", 10);

require_once($CFG->dirroot.'/mod/learningtimecheck/locallib.php');
require_once($CFG->dirroot.'/lib/completionlib.php');

function learningtimecheck_supports($feature) {
    switch($feature) {
    case FEATURE_GROUPS:                  return true;
    case FEATURE_GROUPINGS:               return true;
    case FEATURE_GROUPMEMBERSONLY:        return true;
    case FEATURE_MOD_INTRO:               return true;
    case FEATURE_GRADE_HAS_GRADE:         return false;
    case FEATURE_COMPLETION_HAS_RULES:    return true;
    case FEATURE_BACKUP_MOODLE2:          return true;
    case FEATURE_SHOW_DESCRIPTION:        return true;

    default: return null;
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $learningtimecheck An object from the form in mod_form.php
 * @return int The id of the newly inserted learningtimecheck record
 */
function learningtimecheck_add_instance($learningtimecheck) {
    global $DB;

    $learningtimecheck->timecreated = time();
    $learningtimecheck->maxgrade = 0; // Obsolete field
    $learningtimecheck->id = $DB->insert_record('learningtimecheck', $learningtimecheck);

    // Hard fixed values
    $learningtimecheck->autoupdate = LEARNINGTIMECHECK_AUTOUPDATE_CRON_YES;
    $learningtimecheck->useritemsallowed = 0;

    learningtimecheck_grade_item_update($learningtimecheck);

    return $learningtimecheck->id;
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $learningtimecheck An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function learningtimecheck_update_instance($learningtimecheck) {
    global $DB, $CFG;

    $learningtimecheck->timemodified = time();
    $learningtimecheck->id = $learningtimecheck->instance;

    $learningtimecheck->maxgrade = 0;

    $newcompletion = $learningtimecheck->completionpercent;
    $oldcompletion = $DB->get_field('learningtimecheck', 'completionpercent', array('id' => $learningtimecheck->id));

    // Ensure we will resync all mark states from the beginning
    $learningtimecheck->lastcompiledtime = 0;

    $DB->update_record('learningtimecheck', $learningtimecheck);

    // Add or remove all calendar events, as needed
    $course = $DB->get_record('course', array('id' => $learningtimecheck->course) );
    $cm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheck->id, $course->id);
    $chk = new learningtimecheck_class($cm->id, 0, $learningtimecheck, $cm, $course);

    if ($newcompletion != $oldcompletion) {
        $ci = new completion_info($course);
        $context = context_module::instance($cm->id);
        $users = get_users_by_capability($context, 'mod/learningtimecheck:updateown', 'u.id', '', '', '', '', '', false);
        foreach ($users as $user) {
            $ci->update_state($cm, COMPLETION_UNKNOWN, $user->id);
        }
    }

    $chk->update_all_autoupdate_checks();

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function learningtimecheck_delete_instance($id) {
    global $DB;

    if (! $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $id) )) {
        return false;
    }

    $result = true;

    $items = $DB->get_records('learningtimecheck_item', array('learningtimecheck' => $learningtimecheck->id), '', 'id');
    if (!empty($items)) {
        $items = array_keys($items);
        $result = $DB->delete_records_list('learningtimecheck_check', 'item', $items);
        $result = $DB->delete_records_list('learningtimecheck_comment', 'itemid', $items);
        $result = $result && $DB->delete_records('learningtimecheck_item', array('learningtimecheck' => $learningtimecheck->id));
    }
    $result = $result && $DB->delete_records('learningtimecheck', array('id' => $learningtimecheck->id));

    learningtimecheck_grade_item_delete($learningtimecheck);

    return $result;
}

function learningtimecheck_update_all_grades() {
    global $DB;

    /*
    $learningtimechecks = $DB->get_records('learningtimecheck');
    foreach ($learningtimechecks as $learningtimecheck) {
        learningtimecheck_update_grades($learningtimecheck);
    }
    */
}

/**
 * the grading strategy is based on mandatory items, calculating :
 * checked / total * maxgrade
 */
function learningtimecheck_update_grades($learningtimecheck, $userid = 0) {
    global $CFG, $DB;

    /*
    $params = array('learningtimecheck' => $learningtimecheck->id,
                    'userid' => 0,
                    'itemoptional' => LEARNINGTIMECHECK_OPTIONAL_NO,
                    'hidden' => LEARNINGTIMECHECK_HIDDEN_NO );
    $items = $DB->get_records('learningtimecheck_item', $params, '', 'id, grouping');
    if (!$items) {
        return;
    }
    if (!$course = $DB->get_record('course', array('id' => $learningtimecheck->course) )) {
        return;
    }
    if (!$cm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheck->id, $course->id)) {
        return;
    }

    $checkgroupings = false; // Don't check items against groupings unless we really have to.
    if (isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly && $learningtimecheck->autopopulate) {
        foreach ($items as $item) {
            if ($item->grouping) {
                $checkgroupings = true;
                break;
            }
        }
    }

    if ($learningtimecheck->teacheredit == LEARNINGTIMECHECK_MARKING_STUDENT) {
        $date = ', MAX(c.usertimestamp) AS datesubmitted';
        $where = 'c.usertimestamp > 0';
    } else {
        $date = ', MAX(c.teachertimestamp) AS dategraded';
        $where = 'c.teachermark = '.LEARNINGTIMECHECK_TEACHERMARK_YES;
    }

    if ($checkgroupings) {
        if ($userid) {
            $users = $DB->get_records('user', array('id' => $userid), null, 'id,'.get_all_user_name_fields(true, ''));
        } else {
            $context = context_module::instance($cm->id);
            if (!$users = get_users_by_capability($context, 'mod/learningtimecheck:updateown', 'u.id,'.get_all_user_name_fields(true, 'u'), '', '', '', '', '', false)) {
                return;
            }
        }

        $grades = array();

        // With groupings, need to update each user individually (as each has different groupings).
        foreach ($users as $userid => $user) {
            $groupings = learningtimecheck_class::get_user_groupings($userid, $course->id);

            $total = 0;
            $itemlist = '';
            foreach ($items as $item) {
                if ($item->grouping) {
                    if (!in_array($item->grouping, $groupings)) {
                        continue;
                    }
                }
                $itemlist .= $item->id.',';
                $total++;
            }

            if (!$total) { // No items - set score to 0.
                $ugrade = new stdClass;
                $ugrade->userid = $userid;
                $ugrade->rawgrade = 0;
                $ugrade->date = time();

            } else {
                $itemlist = substr($itemlist, 0, -1); // Remove trailing ','

                $sql = "
                    SELECT
                        ? AS userid,
                        (SUM(CASE WHEN '.$where.' THEN 1 ELSE 0 END) * ? / ? ) AS rawgrade
                        {$date}
                    FROM 
                        {learningtimecheck_check} c
                    WHERE 
                        c.item IN ($itemlist) AND 
                        c.userid = ? 
                ";

                $ugrade = $DB->get_record_sql($sql, array($userid, $learningtimecheck->maxgrade, $total, $userid));
                if (!$ugrade) {
                    $ugrade = new stdClass;
                    $ugrade->userid = $userid;
                    $ugrade->rawgrade = 0;
                    $ugrade->date = time();
                }
            }

            $ugrade->firstname = $user->firstname;
            $ugrade->lastname = $user->lastname;

            $grades[$userid] = $ugrade;
        }

    } else {
        // No need to check groupings, so update all student grades at once.

        if ($userid) {
            $users = $userid;
        } else {
            $context = context_module::instance($cm->id);
            if (!$users = get_users_by_capability($context, 'mod/learningtimecheck:updateown', 'u.id', '', '', '', '', '', false)) {
                return;
            }
            $users = array_keys($users);
        }

        $total = count($items);

        list($usql, $uparams) = $DB->get_in_or_equal($users);
        list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));

        $sql = '
            SELECT 
                u.id AS userid, 
                '.get_all_user_name_fields(true, 'u').',
                (SUM(CASE WHEN '.$where.' THEN 1 ELSE 0 END) * ? / ? ) AS rawgrade'.$date.'
            FROM 
                {user} u
            LEFT JOIN 
                {learningtimecheck_check} c 
            ON 
                u.id = c.userid
            WHERE 
                u.id '.$usql.' AND
                c.item '.$isql.'
            GROUP BY
                u.id,
                u.firstname,
                u.lastname
        ';

        $params = array_merge($uparams, $iparams);
        $params = array_merge(array($learningtimecheck->maxgrade, $total), $params);

        $grades = $DB->get_records_sql($sql, $params);
    }

    foreach ($grades as $grade) {
        // Log completion of learningtimecheck
        if ($grade->rawgrade == $learningtimecheck->maxgrade) {
            if ($learningtimecheck->emailoncomplete) {
                $timelimit = time() - 1 * 60 * 60; // Do not send another email if this learningtimecheck was already 'completed' in the last hour
                $filter = "l.time > ? AND l.cmid = ? AND l.userid = ? AND l.action = 'complete'";
                get_logs($filter, array($timelimit, $cm->id, $grade->userid), '', 1, 1, $logcount);
                if ($logcount == 0) {
                    if (!isset($context)) {
                        $context = context_module::instance($cm->id);
                    }

                    // Prepare email content.
                    $details = new stdClass();
                    $details->user = fullname($grade);
                    $details->learningtimecheck = s($learningtimecheck->name);
                    $details->coursename = $course->fullname;

                    if ($learningtimecheck->emailoncomplete == LEARNINGTIMECHECK_EMAIL_TEACHER || $learningtimecheck->emailoncomplete == LEARNINGTIMECHECK_EMAIL_BOTH) {
                        // Email will be sended to the all teachers who have capability.
                        $subj = get_string('emailoncompletesubject', 'learningtimecheck', $details);
                        $content = get_string('emailoncompletebody', 'learningtimecheck', $details);
                        $content .= new moodle_url('/mod/checklst/view.php', array('id' => $cm->id));

                        if ($recipients = get_users_by_capability($context, 'mod/learningtimecheck:emailoncomplete', 'u.*', '', '', '', '', '', false)) {
                            foreach ($recipients as $recipient) {
                                email_to_user($recipient, $grade, $subj, $content, '', '', '', false);
                            }
                        }
                    }
                    if ($learningtimecheck->emailoncomplete == LEARNINGTIMECHECK_EMAIL_STUDENT || $learningtimecheck->emailoncomplete == LEARNINGTIMECHECK_EMAIL_BOTH) {
                        //email will be sended to the student who complete this learningtimecheck
                        $subj = get_string('emailoncompletesubjectown', 'learningtimecheck', $details);
                        $content = get_string('emailoncompletebodyown', 'learningtimecheck', $details);
                        $content .= new moodle_url('/mod/checklst/view.php', array('id' => $cm->id));

                        $recipient_stud = $DB->get_record('user', array('id' => $grade->userid) );
                        email_to_user($recipient_stud, $grade, $subj, $content, '', '', '', false);
                    }
                }
            }

            $context = context_module::instance($cm->id);
            // Trigger module viewed event.
            $eventparams = array(
                'objectid' => $learningtimecheck->id,
                'context' => $context,
            );

            $event = \mod_learningtimecheck\event\course_module_completed::create($eventparams);
            $event->add_record_snapshot('course_modules', $cm);
            $event->add_record_snapshot('course', $course);
            $event->trigger();

        }
        $ci = new completion_info($course);
        if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $ci->update_state($cm, COMPLETION_UNKNOWN, $grade->userid);
        }
    }

    learningtimecheck_grade_item_update($learningtimecheck, $grades);
    */
}

function learningtimecheck_grade_item_delete($learningtimecheck) {
    global $CFG;

    /*
    require_once($CFG->libdir.'/gradelib.php');
    if (!isset($learningtimecheck->courseid)) {
        $learningtimecheck->courseid = $learningtimecheck->course;
    }

    return grade_update('mod/learningtimecheck', $learningtimecheck->courseid, 'mod', 'learningtimecheck', $learningtimecheck->id, 0, null, array('deleted' => 1));
    */
}

function learningtimecheck_grade_item_update($learningtimecheck, $grades=null) {
    global $CFG;

    /*
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($learningtimecheck->courseid)) {
        $learningtimecheck->courseid = $learningtimecheck->course;
    }

    $params = array('itemname'=>$learningtimecheck->name);
    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademax']  = $learningtimecheck->maxgrade;
    $params['grademin']  = 0;

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/learningtimecheck', $learningtimecheck->courseid, 'mod', 'learningtimecheck', $learningtimecheck->id, 0, $grades, $params);
    */
}


/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function learningtimecheck_user_outline($course, $user, $mod, $learningtimecheck) {
    global $DB, $CFG;

    $groupins_sel = '';
    if (isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly && $learningtimecheck->autopopulate) {
        $groupings = learningtimecheck_class::get_user_groupings($user->id, $learningtimecheck->course);
        $groupings[] = 0;
        $groupings_sel = ' AND grouping IN ('.implode(',', $groupings).') ';
    }
    $sel = 'learningtimecheck = ? AND userid = 0 AND itemoptional = '.LEARNINGTIMECHECK_OPTIONAL_NO;
    $sel .= ' AND hidden = '.LEARNINGTIMECHECK_HIDDEN_NO.$groupings_sel;
    $items = $DB->get_records_select('learningtimecheck_item', $sel, array($learningtimecheck->id), '', 'id');
    if (!$items) {
        return null;
    }

    $total = count($items);
    list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));

    $sql = "userid = ? AND item $isql AND ";
    if ($learningtimecheck->teacheredit == LEARNINGTIMECHECK_MARKING_STUDENT) {
        $sql .= 'usertimestamp > 0';
        $order = 'usertimestamp DESC';
    } else {
        $sql .= 'teachermark = '.LEARNINGTIMECHECK_TEACHERMARK_YES;
        $order = 'teachertimestamp DESC';
    }
    $params = array_merge(array($user->id), $iparams);

    $checks = $DB->get_records_select('learningtimecheck_check', $sql, $params, $order);

    $return = null;
    if ($checks) {
        $return = new stdClass;

        $ticked = count($checks);
        $check = reset($checks);
        if ($learningtimecheck->teacheredit == LEARNINGTIMECHECK_MARKING_STUDENT) {
            $return->time = $check->usertimestamp;
        } else {
            $return->time = $check->teachertimestamp;
        }
        $percent = sprintf('%0d', ($ticked * 100) / $total);
        $return->info = get_string('progress', 'learningtimecheck').': '.$ticked.'/'.$total.' ('.$percent.'%)';
    }

    return $return;
}


/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function learningtimecheck_user_complete($course, $user, $mod, $learningtimecheck) {
    $chk = new learningtimecheck_class($mod->id, $user->id, $learningtimecheck, $mod, $course);

    $chk->user_complete();

    return true;
}


/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in learningtimecheck activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function learningtimecheck_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}


/**
 * Called by the block course_overview
 * @todo something is weird in there... needs deep debug
 */
function learningtimecheck_print_overview($courses, &$htmlarray) {
    global $USER, $CFG, $DB;

    return;

    $config = get_config('learningtimecheck');

    if (empty($config->showmymoodle)) {
        return; // Disabled via global config.
    }

    if (!isset($config->showcompletemymoodle)) {
        $config->showcompletemymoodle = 1;
    }

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return;
    }

    if (!$learningtimechecks = get_all_instances_in_courses('learningtimecheck', $courses)) {
        return;
    }

    $strlearningtimecheck = get_string('modulename', 'learningtimecheck');

    foreach ($learningtimechecks as $learningtimecheck) {
        $show_all = true;
        if ($learningtimecheck->teacheredit == LEARNINGTIMECHECK_MARKING_STUDENT) {
            $context = context_module::instance($learningtimecheck->coursemodule);
            $show_all = !has_capability('mod/learningtimecheck:updateown', $context);
        }

        $progressbar = learningtimecheck_class::print_user_progressbar($learningtimecheck->id, $USER->id,
                                                               '270px', true, true,
                                                               !$config->showcompletemymoodle);
        if (empty($progressbar)) {
            continue;
        }

        // Do not worry about hidden items / groupings as automatic items cannot have dates
        // (and manual items cannot be hidden / have groupings)
        if ($show_all) { // Show all items whether or not they are checked off (as this user is unable to check them off)
            $date_items = $DB->get_records_select('learningtimecheck_item',
                                                  'learningtimecheck = ?',
                                                  array($learningtimecheck->id)
           );
        } else { // Show only items that have not been checked off
            $date_items = $DB->get_records_sql('SELECT i.* FROM {learningtimecheck_item} i JOIN {learningtimecheck_check} c ON c.item = i.id '.
                                          'WHERE i.learningtimecheck = ? AND c.userid = ? AND usertimestamp = 0 ', array($learningtimecheck->id, $USER->id));
        }

        $viewurl = new moodle_url('/mod/learningtimecheck/view.php', array('id' => $learningtimecheck->coursemodule));
        $str = '<div class="learningtimecheck overview"><div class="name">'.$strlearningtimecheck.': '.
            '<a title="'.$strlearningtimecheck.'" href="'.$viewurl.'">'.
            $learningtimecheck->name.'</a></div>';
        $str .= '<div class="info">';
        $str .= '<div class="ltc-progress-bar">'.$progressbar.'</div>';
        foreach ($date_items as $item) {
            $str .= '<div class="ltc-items">'.$item->displaytext.'</div>';
        }
        $str .= '</div>';
        $str .= '</div>';
        if (empty($htmlarray[$learningtimecheck->course]['learningtimecheck'])) {
            $htmlarray[$learningtimecheck->course]['learningtimecheck'] = $str;
        } else {
            $htmlarray[$learningtimecheck->course]['learningtimecheck'] .= $str;
        }
    }
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function learningtimecheck_cron_task () {
    global $CFG, $DB;

    mtrace("Starting learningtimecheck task");

    $config = get_config('learningtimecheck');

    $lastcron = 0 + @$config->lastcompiled;
    /*
    if (!$lastcron) {
        // First time run - learningtimechecks will take care of any updates before now
        return true;
    }
    */

    include_once($CFG->dirroot.'/mod/learningtimecheck/autoupdatelib.php');
    if (!$config->autoupdateusecron) {
        mtrace("learningtimecheck cron updates disabled");
        return true;
    }

    if ($lastcron) {
        $lastlogtime = $lastcron - 5; // Subtract 5 seconds just in case a log slipped through during the last cron update
    } else {
        $lastlogtime = 0;
    }

    mtrace("Compiling from ".userdate($lastlogtime));

    // Find all autoupdating learningtimechecks
    $learningtimechecks = $DB->get_records_select('learningtimecheck', 'autopopulate > 0 OR autoupdate > 0');
    if (!$learningtimechecks) {
        // No learningtimechecks to update
        mtrace("No automatic update learningtimechecks found");
        return true;
    }

    // Match up these learningtimechecks with the courses they are in
    $courses = array();
    foreach ($learningtimechecks as $learningtimecheck) {
        if (array_key_exists($learningtimecheck->course, $courses)) {
            $courses[$learningtimecheck->course][$learningtimecheck->id] = $learningtimecheck;
        } else {
            $courses[$learningtimecheck->course] = array($learningtimecheck->id => $learningtimecheck);
        }
    }
    $courseids = implode(',', array_keys($courses));

    if (defined("DEBUG_LEARNINGTIMECHECK_AUTOUPDATE")) {
        mtrace("Looking for updates in courses: $courseids");
    }

    $logmanager = get_log_manager();
    $readers = $logmanager->get_readers(learningtimecheck_class::get_reader_source());
    $reader = reset($readers);

    if (empty($reader)) {
        return false; // No log reader found.
    }

    $logupdate = 0;
    $totalcount = 0;

    mtrace("checking logs ");
    if ($reader instanceof \logstore_standard\log\store) {
        $sql = "
            SELECT
                l.id,
                l.courseid as course,
                l.action,
                l.objectid as cmid,
                l.timecreated as time,
                l.userid as userid,
                '' as url,
                l.component as module
            FROM
                {logstore_standard_log} l
            WHERE
                timecreated >= ? AND
                courseid IN ($courseids) AND
                objectid > 0 AND
                component LIKE 'mod%'
        ";
        $logs = $DB->get_records_sql($sql, array($lastlogtime));
    } elseif ($reader instanceof \logstore_legacy\log\store) {
        echo "Getting old logs";
        $logs = get_logs("l.time >= ? AND l.course IN ($courseids) AND cmid > 0", array($lastlogtime), 'l.time ASC', '', '', $totalcount);
    } else {
        set_config('lastcompiled', time() - 30, 'learningtimecheck');
        return;
    }

    // Process all logs since the last cron update
    if ($logs) {
        if (defined("DEBUG_LEARNINGTIMECHECK_AUTOUPDATE")) {
            mtrace("Found ".count($logs)." log updates to check");
        }
        foreach ($logs as $log) {
            $logupdate += learningtimecheck_autoupdate($log->course, $log->module, $log->action, $log->time, $log->cmid, $log->userid, $log->url, $courses[$log->course]);
        }
    }

    if ($logupdate) {
        mtrace("\n\tUpdated $logupdate checkmark(s) from log changes");
    } else {
        mtrace("\n\tNo checkmarks need updating from log changes");
    }

    // Process all the completion changes since the last cron update
    // Need the cmid, userid and newstate
    $completionupdate = 0;
    list($msql, $mparam) = $DB->get_in_or_equal(array_keys($courses));
    $sql = "
        SELECT
            c.id,
            c.coursemoduleid,
            c.userid,
            c.completionstate,
            c.timemodified
        FROM
            {course_modules_completion} c
        JOIN
            {course_modules} m
        ON
            c.coursemoduleid = m.id
        WHERE
            c.timemodified > ? AND
            m.course $msql
    ";
    $params = array_merge(array($lastlogtime), $mparam);
    $completions = $DB->get_records_sql($sql, $params);
    if (defined("DEBUG_LEARNINGTIMECHECK_AUTOUPDATE")) {
        mtrace("Found ".count($completions)." completion updates to check");
    }
    foreach ($completions as $completion) {
        $completionupdate += learningtimecheck_completion_autoupdate($completion->coursemoduleid,
                                                             $completion->userid,
                                                             $completion->completionstate,
                                                             $completion->timemodified);
    }

    if ($completionupdate) {
        mtrace("\tUpdated $completionupdate checkmark(s) from completion changes");
    } else {
        mtrace("\tNo checkmarks need updating from completion changes");
    }

    set_config('lastcompiled', time() - 30, 'learningtimecheck');

    return true;
}


/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of newmodule. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $newmoduleid ID of an instance of this module
 * @return mixed boolean/array of students
 */
function learningtimecheck_get_participants($learningtimecheckid) {
    global $DB;

    $sql = "
        SELECT DISTINCT
            u.id,
            u.id
        FROM
            {user} u,
            {learningtimecheck_item} i,
            {learningtimecheck_check} c
        WHERE
            i.learningtimecheck = ? AND
            ((c.item = i.id AND
            c.userid = u.id) OR
            (i.userid = u.id))
    ";
    $return = $DB->get_records_sql($sql, array($learningtimecheckid));

    return $return;
}


/**
 * This function returns if a scale is being used by one learningtimecheck
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $newmoduleid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 */
function learningtimecheck_scale_used($learningtimecheckid, $scaleid) {
    return false;
}


/**
 * Checks if scale is being used by any instance of learningtimecheck.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any learningtimecheck
 */
function learningtimecheck_scale_used_anywhere($scaleid) {
    return false;
}


/**
 * Execute post-install custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function learningtimecheck_install() {
    return true;
}


/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function learningtimecheck_uninstall() {
    return true;
}

function learningtimecheck_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'learningtimecheckheader', get_string('modulenameplural', 'learningtimecheck'));
    $mform->addElement('checkbox', 'reset_learningtimecheck_progress', get_string('resetlearningtimecheckprogress', 'learningtimecheck'));
}

function learningtimecheck_reset_course_form_defaults($course) {
    return array('reset_learningtimecheck_progress' => 1);
}

function learningtimecheck_reset_userdata($data) {
    global $DB;

    $status = array();
    $component = get_string('modulenameplural', 'learningtimecheck');
    $typestr = get_string('resetlearningtimecheckprogress', 'learningtimecheck');
    $status[] = array('component'=>$component, 'item'=>$typestr, 'error'=>false);

    if (!empty($data->reset_learningtimecheck_progress)) {
        $learningtimechecks = $DB->get_records('learningtimecheck', array('course' => $data->courseid));
        if (!$learningtimechecks) {
            return $status;
        }

        list($csql, $cparams) = $DB->get_in_or_equal(array_keys($learningtimechecks));
        $items = $DB->get_records_select('learningtimecheck_item', 'learningtimecheck '.$csql, $cparams);
        if (!$items) {
            return $status;
        }

        $itemids = array_keys($items);
        $DB->delete_records_list('learningtimecheck_check', 'item', $itemids);
        $DB->delete_records_list('learningtimecheck_comment', 'itemid', $itemids);

        $sql = "learningtimecheck $csql AND userid <> 0";
        $DB->delete_records_select('learningtimecheck_item', $sql, $cparams);

        // Reset the grades
        foreach ($learningtimechecks as $learningtimecheck) {
            learningtimecheck_grade_item_update($learningtimecheck, 'reset');
        }
    }

    return $status;
}

function learningtimecheck_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid) {
        $learningtimechecks = $DB->get_records('learningtimecheck', array('course' => $courseid) );
        $course = $DB->get_record('course', array('id' => $courseid) );
    } else {
        $learningtimechecks = $DB->get_records('learningtimecheck');
        $course = null;
    }

    return true;
}

function learningtimecheck_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    if (!($learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $cm->instance)))) {
        throw new Exception("Can't find learningtimecheck {$cm->instance}");
    }

    $result = $type; // Default return value

    if ($learningtimecheck->completionpercent) {
        list($ticked, $total) = learningtimecheck_class::get_user_progress($cm->instance, $userid);
        $value = ($total) ? $learningtimecheck->completionpercent <= ($ticked * 100 / $total) : false;
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    return $result;
}

/**
 * Serves the files included in a learningtimecheck. Implements needed access control ;-)
 * At the moment, no specific areas to serve.
 * * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
 /*
function learningtimecheck_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;

    require_login($course);

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    $allfileareas = array();

    if (!in_array($filearea, $allfileareas)) {
        return false;
    }

    $itemid = (int)array_shift($args);

    $fs = get_file_storage();

    if ($files = $fs->get_area_files($context->id, 'mod_learningtimecheck', $filearea, $itemid, "sortorder, itemid, filepath, filename", false)) {
        $file = array_pop($files);

        // Finally send the file.
        send_stored_file($file, 0, 0, $forcedownload);
    }

    return false;
}
*/
