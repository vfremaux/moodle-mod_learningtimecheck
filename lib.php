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
defined('MOODLE_INTERNAL') || die();

define('LTC_EMAIL_NO', 0);
define('LTC_EMAIL_STUDENT', 1);
define('LTC_EMAIL_TEACHER', 2);
define('LTC_EMAIL_BOTH', 3);

define('LTC_TEACHERMARK_NO', 2);
define('LTC_TEACHERMARK_YES', 1);
define('LTC_TEACHERMARK_UNDECIDED', 0);

define('LTC_MARKING_STUDENT', 0);
define('LTC_MARKING_TEACHER', 1);
define('LTC_MARKING_BOTH', 2);
define('LTC_MARKING_EITHER', 3);

define('LTC_AUTOUPDATE_CRON_NO', 0);
define('LTC_AUTOUPDATE_CRON_YES', 2);

define('LTC_AUTOUPDATE_NO', 0);
define('LTC_AUTOUPDATE_YES', 2);

define('LTC_AUTOPOPULATE_NO', 0);
define('LTC_AUTOPOPULATE_SECTION', 2);
define('LTC_AUTOPOPULATE_CURRENT_PAGE', 2);
define('LTC_AUTOPOPULATE_CURRENT_PAGE_AND_SUBS', 3);
define('LTC_AUTOPOPULATE_CURRENT_TOP_PAGE', 4);
define('LTC_AUTOPOPULATE_COURSE', 1);

define('LTC_OVERRIDE_CREDIT', 0);
define('LTC_OVERRIDE_DECLAREDOVERCREDITIFHIGHER', 1);
define('LTC_OVERRIDE_DECLAREDCAPEDBYCREDIT', 2);
define('LTC_OVERRIDE_DECLARED', 3);

define('LTC_MAX_INDENT', 10);

require_once($CFG->dirroot.'/mod/learningtimecheck/locallib.php');
require_once($CFG->dirroot.'/lib/completionlib.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/compatlib.php');

function learningtimecheck_supports($feature) {

    switch($feature) {

        case FEATURE_GROUPS: {
            return true;
        }

        case FEATURE_GROUPINGS: {
            return true;
        }

        case FEATURE_GROUPMEMBERSONLY: {
            return true;
        }

        case FEATURE_MOD_INTRO:  {
            return true;
        }

        case FEATURE_GRADE_HAS_GRADE: {
            return true;
        }

        case FEATURE_COMPLETION_HAS_RULES: {
            return true;
        }

        case FEATURE_BACKUP_MOODLE2: {
            return true;
        }

        case FEATURE_SHOW_DESCRIPTION: {
            return true;
        }

        default: {
            return null;
        }
    }
}

/**
 * Tells wether a feature is supported or not. Gives back the
 * implementation path where to fetch resources.
 * @param string $feature a feature key to be tested.
 */
function learningtimecheck_supports_feature($feature) {
    global $CFG;
    static $supports;

    $config = get_config('learningtimecheck');

    if (!isset($supports)) {
        $supports = array(
            'pro' => array(
                'format' => array('xls', 'csv', 'pdf', 'json'),
                'time' => array('student', 'tutor'),
                'calculation' => array('coupling')
            ),
            'community' => array(
                'format' => array('xls', 'csv'),
                'time' => array('student'),
            ),
        );
    }

    // Check existance of the 'pro' dir in plugin.
    if (is_dir(__DIR__.'/pro')) {
        if ($feature == 'emulate/community') {
            return 'pro';
        }
        if (empty($config->emulatecommunity)) {
            $versionkey = 'pro';
        } else {
            $versionkey = 'community';
        }
    } else {
        $versionkey = 'community';
    }

    list($feat, $subfeat) = explode('/', $feature);

    if (!array_key_exists($feat, $supports[$versionkey])) {
        return false;
    }

    if (!in_array($subfeat, $supports[$versionkey][$feat])) {
        return false;
    }

    // Special condition for pdf dependencies.
    if (($feature == 'format/pdf') && !is_dir($CFG->dirroot.'/local/vflibs')) {
        return false;
    }

    return $versionkey;
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
    $learningtimecheck->maxgrade = 0; // Obsolete field.
    $learningtimecheck->id = $DB->insert_record('learningtimecheck', $learningtimecheck);

    // Hard fixed values.
    $learningtimecheck->autoupdate = LTC_AUTOUPDATE_CRON_YES;
    $learningtimecheck->useritemsallowed = 0;

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
    global $DB;

    $learningtimecheck->timemodified = time();
    $learningtimecheck->id = $learningtimecheck->instance;

    $learningtimecheck->maxgrade = 0;

    $newcompletionenabled = $learningtimecheck->cplpercentenabled;
    $oldcompletionenabled = $DB->get_field('learningtimecheck', 'cplpercentenabled', array('id' => $learningtimecheck->id));
    $newcompletion = $learningtimecheck->completionpercent;
    $oldcompletion = $DB->get_field('learningtimecheck', 'completionpercent', array('id' => $learningtimecheck->id));

    $newcompletionmandatoryenabled = $learningtimecheck->cplmandatoryenabled;
    $oldcompletionmandatoryenabled = $DB->get_field('learningtimecheck', 'cplmandatoryenabled', array('id' => $learningtimecheck->id));
    $newcompletionmandatory = $learningtimecheck->completionmandatory;
    $oldcompletionmandatory = $DB->get_field('learningtimecheck', 'completionmandatory', array('id' => $learningtimecheck->id));

    // Ensure we will resync all mark states from the beginning.
    $learningtimecheck->lastcompiledtime = 0;

    $DB->update_record('learningtimecheck', $learningtimecheck);

    // Add or remove all calendar events, as needed.
    $course = $DB->get_record('course', array('id' => $learningtimecheck->course) );
    $cm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheck->id, $course->id);
    $chk = new learningtimecheck_class($cm->id, 0, $learningtimecheck, $cm, $course);

    if (($newcompletionenabled != $oldcompletionenabled) ||
            ($newcompletion != $oldcompletion) ||
                    ($newcompletionmandatoryenabled != $oldcompletionmandatoryenabled) ||
                            ($newcompletionmandatory != $oldcompletionmandatory)) {
        // Invalidate completion info of users if something has changed in completion setup.
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

    return $result;
}

/**
 * Force the course view to refresh LTC completion info for the user.
 * @param objectref &$cminfo
 */
function learningtimecheck_cm_info_dynamic(&$cminfo) {
    global $COURSE, $USER, $DB;

    // Trigger a completion update for the current learningtimecheck and user.
    $completioninfo = new completion_info($COURSE);
    $cm = $DB->get_record('course_modules', array('id' => $cminfo->id));
    $enablestate = $completioninfo->is_enabled($cm);
    if ($enablestate == COMPLETION_TRACKING_AUTOMATIC) {
        $completioninfo->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
    }
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

    $groupinssel = '';
    if (isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly && $learningtimecheck->autopopulate) {
        $groupings = learningtimecheck_class::get_user_groupings($user->id, $learningtimecheck->course);
        $groupings[] = 0;
        $groupingssel = ' AND grouping IN ('.implode(',', $groupings).') ';
    }
    $sel = 'learningtimecheck = ? AND userid = 0 AND itemoptional = '.LTC_OPTIONAL_NO;
    $sel .= ' AND hidden = '.LTC_HIDDEN_NO.$groupingssel;
    $items = $DB->get_records_select('learningtimecheck_item', $sel, array($learningtimecheck->id), '', 'id');
    if (!$items) {
        return null;
    }

    $total = count($items);
    list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));

    $sql = "userid = ? AND item $isql AND ";
    if ($learningtimecheck->teacheredit == LTC_MARKING_STUDENT) {
        $sql .= 'usertimestamp > 0';
        $order = 'usertimestamp DESC';
    } else {
        $sql .= 'teachermark = '.LTC_TEACHERMARK_YES;
        $order = 'teachertimestamp DESC';
    }
    $params = array_merge(array($user->id), $iparams);

    $checks = $DB->get_records_select('learningtimecheck_check', $sql, $params, $order);

    $return = null;
    if ($checks) {
        $return = new stdClass;

        $ticked = count($checks);
        $check = reset($checks);
        if ($learningtimecheck->teacheredit == LTC_MARKING_STUDENT) {
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
    return false; // True if anything was printed, otherwise false.
}

/**
 * Called by the block course_overview
 * @todo something is weird in there... needs deep debug
 */
function learningtimecheck_print_overview($courses, &$htmlarray) {
    global $USER, $DB;

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
        $showall = true;
        if ($learningtimecheck->teacheredit == LTC_MARKING_STUDENT) {
            $context = context_module::instance($learningtimecheck->coursemodule);
            $showall = !has_capability('mod/learningtimecheck:updateown', $context);
        }

        $progressbar = learningtimecheck_class::print_user_progressbar($learningtimecheck->id, $USER->id,
                                                               '270px', true, true,
                                                               !$config->showcompletemymoodle);
        if (empty($progressbar)) {
            continue;
        }

        /*
         * Do not worry about hidden items / groupings as automatic items cannot have dates
         * (and manual items cannot be hidden / have groupings)
         */
        if ($showall) {
            // Show all items whether or not they are checked off (as this user is unable to check them off).
            $dateitems = $DB->get_records_select('learningtimecheck_item',
                                                  'learningtimecheck = ?',
                                                  array($learningtimecheck->id));
        } else {
            // Show only items that have not been checked off.
            $sql = '
                SELECT
                    i.*
                FROM
                    {learningtimecheck_item} i
                JOIN
                    {learningtimecheck_check} c
                ON
                    c.item = i.id
                WHERE
                    i.learningtimecheck = ? AND
                    c.userid = ? AND
                    usertimestamp = 0
            ';
            $dateitems = $DB->get_records_sql($sql, array($learningtimecheck->id, $USER->id));
        }

        $viewurl = new moodle_url('/mod/learningtimecheck/view.php', array('id' => $learningtimecheck->coursemodule));
        $str = '<div class="learningtimecheck overview"><div class="name">'.$strlearningtimecheck.': ';
        $str .= '<a title="'.$strlearningtimecheck.'" href="'.$viewurl.'">';
        $str .= $learningtimecheck->name.'</a></div>';
        $str .= '<div class="info">';
        $str .= '<div class="ltc-progress-bar">'.$progressbar.'</div>';
        foreach ($dateitems as $item) {
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

    include_once($CFG->dirroot.'/mod/learningtimecheck/autoupdatelib.php');

    if ($lastcron) {
        // Subtract 5 seconds just in case a log slipped through during the last cron update.
        $lastlogtime = $lastcron - 5;
    } else {
        $lastlogtime = 0;
    }

    mtrace("Compiling from ".userdate($lastlogtime));

    // Find all autoupdating learningtimechecks.
    $learningtimechecks = $DB->get_records_select('learningtimecheck', 'autopopulate > 0 OR autoupdate > 0');
    if (!$learningtimechecks) {
        // No learningtimechecks to update.
        mtrace("No automatic update learningtimechecks found");
        set_config('lastcompiled', time() - 30, 'learningtimecheck');
        return true;
    }

    mtrace("\n\tChecking completions");

    /*
     * Process all the completion changes since the last cron update
     * Need the cmid, userid and newstate
     */
    $completionupdate = 0;

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
        JOIN
            {learningtimecheck_item} ltci
        ON
            ltci.moduleid = m.id
        WHERE
            c.timemodified > ?
    ";
    $params = array($lastlogtime);
    $completions = $DB->get_records_sql($sql, $params);
    if (defined("DEBUG_LTC_AUTOUPDATE")) {
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

    // Match up these learningtimechecks with the courses they are in.
    $courses = array();
    foreach ($learningtimechecks as $learningtimecheck) {
        $course = $DB->get_record('course', array('id' => $learningtimecheck->course));
        $ci = new completion_info($course);
        if (!$ci->is_enabled()) {
            if (array_key_exists($learningtimecheck->course, $courses)) {
                $courses[$learningtimecheck->course][$learningtimecheck->id] = $learningtimecheck;
            } else {
                $courses[$learningtimecheck->course] = array($learningtimecheck->id => $learningtimecheck);
            }
        }
    }

    if (!empty($courses)) {
        $courseids = implode(',', array_keys($courses));

        mtrace("Looking for updates in courses: $courseids");

        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers(learningtimecheck_class::get_reader_source());
        $reader = reset($readers);

        if (!empty($reader)) {
            // No log reader found.
            mtrace("No logs reader.");
            set_config('lastcompiled', time() - 30, 'learningtimecheck');

            $logupdate = 0;
            $totalcount = 0;

            $logs = array();
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
            } else if ($reader instanceof \logstore_legacy\log\store) {
                echo "Getting old logs";
                $select = '
                    l.time >= ? AND
                    l.course IN ('.$courseids.') AND
                    cmid > 0';
                $logs = get_logs($select, array($lastlogtime), 'l.time ASC', '', '', $totalcount);
            }

            // Process all logs since the last cron update.
            if ($logs) {
                if (defined("DEBUG_LTC_AUTOUPDATE")) {
                    mtrace("Found ".count($logs)." log updates to check");
                }
                foreach ($logs as $log) {
                    $logupdate += learningtimecheck_autoupdate($log->course, $log->module, $log->action, $log->time, $log->cmid,
                                                               $log->userid, $log->url, $courses[$log->course]);
                }
            }

            if ($logupdate) {
                mtrace("\n\tUpdated $logupdate checkmark(s) from log changes");
            } else {
                mtrace("\n\tNo checkmarks need updating from log changes");
            }
        }
    }

    return true;
}

/**
 * This function is needed by LTC upgrader to cleanup old versions.
 */
function learningtimecheck_grade_item_delete($learningtimecheck) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    if (!isset($learningtimecheck->courseid)) {
        $learningtimecheck->courseid = $learningtimecheck->course;
    }

    return grade_update('mod/learningtimecheck', $learningtimecheck->courseid, 'mod', 'learningtimecheck',
                        $learningtimecheck->id, 0, null, array('deleted' => 1));
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

    $key = 'reset_learningtimecheck_progress';
    $label = get_string('resetlearningtimecheckprogress', 'learningtimecheck');
    $mform->addElement('checkbox', $key, $label);
}

function learningtimecheck_reset_course_form_defaults($course) {
    return array('reset_learningtimecheck_progress' => 1);
}

function learningtimecheck_reset_userdata($data) {
    global $DB;

    $status = array();
    $component = get_string('modulenameplural', 'learningtimecheck');
    $typestr = get_string('resetlearningtimecheckprogress', 'learningtimecheck');
    $status[] = array('component' => $component, 'item' => $typestr, 'error' => false);

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

    $result = $type; // Default return value.

    if ($learningtimecheck->cplpercentenabled && $learningtimecheck->completionpercent) {
        list($ticked, $total) = learningtimecheck_class::get_user_progress($cm->instance, $userid, LTC_OPTIONAL_YES);
        $value = ($total) ? $learningtimecheck->completionpercent <= ($ticked * 100 / $total) : false;
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    if ($learningtimecheck->cplmandatoryenabled && $learningtimecheck->completionmandatory) {
        list($ticked, $total) = learningtimecheck_class::get_user_progress($cm->instance, $userid, LTC_OPTIONAL_NO);
        $value = ($total) ? $learningtimecheck->completionmandatory <= ($ticked * 100 / $total) : false;
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
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function learningtimecheck_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {

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

    $sort = 'sortorder, itemid, filepath, filename';
    if ($files = $fs->get_area_files($context->id, 'mod_learningtimecheck', $filearea, $itemid, $sort, false)) {
        $file = array_pop($files);

        // Finally send the file.
        send_stored_file($file, 0, 0, $forcedownload);
    }

    return false;
}

