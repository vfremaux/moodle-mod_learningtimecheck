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
 * @author Valery Fremaux
 * @version Moodle 2.7
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/report/learningtimecheck/lib.php');

/*
 * Remove the '//' at the start of the next line to output lots of
 * helpful information during the cron update. Do NOT use this if you
 * have made the core modifications outlined in core_modifications.txt
 */

// Wraps Post 2.6 log events to legacy events.
function learningtimecheck_standardlog_autoupdate($courseid, $component, $target, $action, $cmid, $userid,
                                                  $url, $learningtimechecks) {
    $module = str_replace('mod_', '', $component);

    switch ($module) {
        case 'quiz': {
            $action = ($action == 'submitted') ? 'close attempt' : null;
            break;
        }

        case 'forum': {
            $action = ($action == 'created' && ($target == 'post' || $target == 'discussion')) ? 'add post' : null;
            break;
        }

        case 'resource': {
            $action = ($action == 'created' && ($target == 'post')) ? 'view' : null;
            break;
        }

        case 'page': {
            $action = ($action == 'viewed') ? 'view' : null;
            break;
        }

        case 'url': {
            $action = ($action == 'viewed') ? 'view' : null;
            break;
        }

        case 'hotpot': {
            $action = ($action == 'submitted' && $target == 'attempt') ? 'submit' : null;
            break;
        }

        case 'wiki': {
            $action = ($action == 'created' && $target == 'page') ? 'edit' : null;
            break;
        }

        case 'learningtimecheck': {
            $action = ($action == 'completed') ? 'complete' : null;
            break;
        }

        case 'choice': {
            $action = ($action == 'submitted' && $target == 'answer') ? 'choose' : null;
            break;
        }

        case 'lams': {
            $action = ($action == 'submitted' && $target == 'answer') ? 'view' : null;
            break;
        }

        case 'scorm': {
            $action = ($action == 'submitted' && $target == 'answer') ? 'view' : null;
            break;
        }

        case 'assignment': {
            // Assignment should be definitely disabled over 2.7.
            $action = null;
            break;
        }

        case 'assign': {
            $action = ($action == 'submitted' && $target == 'assessable') ? 'submit' : null;
            break;
        }

        case 'journal': {
            $action = ($action == 'submitted' && $target == 'assessable') ? 'add entry' : null;
            break;
        }

        case 'lesson': {
            $action = ($action == 'ended' && $target == 'lesson') ? 'end' : null;
            break;
        }

        case 'realtimequiz': {
            $action = ($action == 'ended' && $target == 'lesson') ? 'submit' : null;
            break;
        }

        case 'workshop': {
            $action = ($action == 'ended' && $target == 'lesson') ? 'submit' : null;
            break;
        }

        case 'glossary': {
            $action = ($action == 'ended' && $target == 'lesson') ? 'add entry' : null;
            break;
        }

        case 'data': {
            $action = ($action == 'ended' && $target == 'lesson') ? 'add' : null;
            break;
        }

        case 'chat': {
            $action = ($action == 'ended' && $target == 'lesson') ? 'talk' : null;
            break;
        }

        case 'feedback': {
            $action = ($action == 'ended' && $target == 'lesson') ? 'submit' : null;
            break;
        }

        case 'magtest': {
            $action = ($action == 'ended' && $target == 'lesson') ? 'submit' : null;
            break;
        }

        case 'questionnaire': {
            $action = ($action == 'ended' && $target == 'lesson') ? 'submit' : null;
            break;
        }

        case 'scheduler': {
            $action = ($action == 'added' && $target == 'booking') ? 'submit' : null;
            break;
        }

        case 'flashcard': {
            $action = ($action == 'played') ? 'submit' : null;
            break;
        }
    }
    if ($action) {
        learningtimecheck_autoupdate($courseid, $module, $action, $cmid, $userid, $url, $learningtimechecks);
    }
}

function learningtimecheck_autoupdate($courseid, $module, $action, $cmid, $userid, $url, $learningtimechecks = null) {
    global $CFG, $DB;

    $config = get_config('learningtimecheck');

    if ($userid == 0) {
        return 0;
    }
    if ($module == 'course') {
        return 0;
    }
    if ($module == 'user') {
        return 0;
    }
    if ($module == 'role') {
        return 0;
    }
    if ($module == 'notes') {
        return 0;
    }
    if ($module == 'calendar') {
        return 0;
    }
    if ($module == 'message') {
        return 0;
    }
    if ($module == 'admin/mnet') {
        return 0;
    }
    if ($module == 'blog') {
        return 0;
    }
    if ($module == 'tag') {
        return 0;
    }
    if ($module == 'blocks/tag_youtube') {
        return 0;
    }
    if ($module == 'login') {
        return 0;
    }
    if ($module == 'library') {
        return 0;
    }
    if ($module == 'upload') {
        return 0;
    }

    if (
        (($module == 'survey') && ($action == 'submit'))
        || (($module == 'quiz') && ($action == 'close attempt'))
        || (($module == 'forum') && (($action == 'add post')||($action == 'add discussion')))
        || (($module == 'resource') && ($action == 'view'))
        || (($module == 'page') && ($action == 'view'))
        || (($module == 'url') && ($action == 'view'))
        || (($module == 'hotpot') && ($action == 'submit'))
        || (($module == 'wiki') && ($action == 'edit'))
        || (($module == 'learningtimecheck') && ($action == 'complete'))
        || (($module == 'choice') && ($action == 'choose'))
        || (($module == 'lams') && ($action == 'view'))
        || (($module == 'scorm') && ($action == 'view'))
        || (($module == 'mplayer') && ($action == 'view'))
        || (($module == 'assignment') && ($action == 'upload'))
        || (($module == 'assign') && ($action == 'submit'))
        || (($module == 'journal') && ($action == 'add entry'))
        || (($module == 'lesson') && ($action == 'end'))
        || (($module == 'realtimequiz') && ($action == 'submit'))
        || (($module == 'workshop') && ($action == 'submit'))
        || (($module == 'glossary') && ($action == 'add entry'))
        || (($module == 'data') && ($action == 'add'))
        || (($module == 'chat') && ($action == 'talk'))
        || (($module == 'feedback') && ($action == 'submit'))
        || (($module == 'magtest') && ($action == 'submit'))
        ) {

        if (defined("DEBUG_LTC_AUTOUPDATE")) {
            mtrace("Possible update needed - courseid: $courseid, module: $module, action: $action, cmid: $cmid, userid: $userid, url: $url");
        }

        if ($cmid == 0) {
            $matches = array();
            if (!preg_match('/id=(\d+)/i', $url, $matches)) {
                return 0;
            }
            $cmid = $matches[1];
        }

        if (!$learningtimechecks) {
            $learningtimechecks = $DB->get_records_select('learningtimecheck',
                                                  'course = ? AND autoupdate > 0',
                                                  array($courseid));

            if (empty($learningtimechecks)) {
                if (defined("DEBUG_LTC_AUTOUPDATE")) {
                    mtrace("No suitable learningtimechecks to update in course $courseid");
                }
                // No learningtimechecks in this course that are auto-updating.
                return 0;
            }
        }

        if (!empty($CFG->enablecompletion)) {
            /*
             * Completion is enabled on this site, so we need to check if this module
             * can do completion (and then wait for that to indicate the module is complete)
             */
            $coursecompletion = $DB->get_field('course',
                                               'enablecompletion',
                                               array('id' => $courseid));
            if ($coursecompletion) {
                $cmcompletion = $DB->get_field('course_modules',
                                               'completion',
                                               array('id' => $cmid));
                if ($cmcompletion) {
                    if (defined("DEBUG_LTC_AUTOUPDATE")) {
                        mtrace("This course module has completion enabled - allow that to control any learningtimecheck items");
                    }
                    return 0;
                }
            }
        }

        /*
         * Find all learningtimecheck_item records which are related to these $learningtimechecks which have a
         * moduleid matching $module and any information about checks they might have.
         */
        list($csql, $cparams) = $DB->get_in_or_equal(array_keys($learningtimechecks));
        $params = array_merge(array($userid, $cmid), $cparams);

        $sql = "
            SELECT
                i.id itemid,
                i.learningtimecheck,
                c.id checkid,
                c.usertimestamp
            FROM
                {learningtimecheck_item} i
            LEFT JOIN
                {learningtimecheck_check} c
            ON
                (c.item = i.id AND c.userid = ?)
            WHERE
                i.moduleid = ? AND
                i.learningtimecheck $csql AND
                i.itemoptional < 2
        ";
        $items = $DB->get_records_sql($sql, $params);

        /*
         * itemoptional - 0: required; 1: optional; 2: heading;
         * not loading defines from mod/learningtimecheck/locallib.php to reduce overhead
         */
        if (empty($items)) {
            if (defined("DEBUG_LTC_AUTOUPDATE")) {
                mtrace("No learningtimecheck items linked to this course module");
            }
            return 0;
        }

        $reportconfig = get_config('report_learningtimecheck');

        $updatecount = 0;
        $createcount = 0;
        foreach ($items as $item) {

            if (!array_key_exists($item->learningtimecheck, $ltccontext)) {
                // Make a local cache of reusable contexts.
                $cm = get_coursemodule_from_instance('learningtimecheck', $item->learningtimecheck);
                $context = context_module::instance($cm->id);
                $ltccontext[$item->learningtimecheck] = $context;
            }

            if ($item->checkid) {
                if ($item->usertimestamp) {
                    continue;
                }
                $check = new stdClass;
                $check->id = $item->checkid;
                $check->userid = $userid;
                $check->usertimestamp = $logtime;
                if (report_learningtimecheck_is_valid($check, $reportconfig, $ltccontext[$item->learningtimecheck]) ||
                         !$config->applyfiltering) {
                    // Checks eventual working day and workingtime rules.
                    $DB->update_record('learningtimecheck_check', $check);
                    $updatecount++;
                }
            } else {
                $check = new stdClass;
                $check->item = $item->itemid;
                $check->userid = $userid;
                $check->usertimestamp = $logtime;
                $check->teachertimestamp = 0;
                $check->teachermark = 0;
                /*
                 * LTC_TEACHERMARK_UNDECIDED - not loading from mod/learningtimecheck/lib.php
                 * to reduce overhead
                 */

                if (report_learningtimecheck_is_valid($check, $reportconfig, $ltccontext[$item->learningtimecheck]) ||
                        !$config->applyfiltering) {
                    // Checks eventual working day and workingtime rules.
                    $check->id = $DB->insert_record('learningtimecheck_check', $check);
                    $createcount++;
                }
            }
        }
        if (defined("DEBUG_LTC_AUTOUPDATE")) {
            mtrace("$updatecount learningtimecheck items updated from this log entry");
            mtrace("$createcount learningtimecheck items created from this log entry");
        }
    }

    return 0;
}

/**
 * Marks checks based on completion achievement
 * @param int $cmid the course module ID being completed
 * @param int $userid the user completion owner's id
 * @param int $newstate the state of completion
 * @param int $completiontime the time completion state was modified (i.e. completion state registered)
 */
function learningtimecheck_completion_autoupdate($cmid, $userid, $newstate, $completiontime) {
    global $DB, $USER;
    static $ltccontext = array();

    $config = get_config('learningtimecheck');

    if ($userid == 0) {
        $userid = $USER->id;
    }

    if (defined('DEBUG_LTC_AUTOUPDATE')) {
        mtrace("Completion status change for cmid: $cmid, userid: $userid, newstate: $newstate");
    }

    $sql = "
        SELECT
            c.id checkid,
            i.id itemid,
            c.usertimestamp,
            i.learningtimecheck
        FROM
            {learningtimecheck_item} i
        JOIN
            {learningtimecheck} cl
        ON
            i.learningtimecheck = cl.id
        LEFT JOIN
            {learningtimecheck_check} c
        ON
            (c.item = i.id AND c.userid = ?)
        WHERE
            cl.autoupdate > 0 AND
            i.moduleid = ? AND
            i.itemoptional < 2
    ";
    $items = $DB->get_records_sql($sql, array($userid, $cmid));
    /*
     * itemoptional - 0: required; 1: optional; 2: heading;
     * not loading defines from mod/learningtimecheck/locallib.php to reduce overhead
     */
    if (empty($items)) {
        if (defined('DEBUG_LTC_AUTOUPDATE')) {
            mtrace("No learningtimecheck items linked to this course module");
        }
        return 0;
    }

    $reportconfig = get_config('report_learningtimecheck');

    // Not complete if failed.
    $newstate = ($newstate == COMPLETION_COMPLETE || $newstate == COMPLETION_COMPLETE_PASS);
    $updatecount = 0;
    $createcount = 0;
    $updatelearningtimechecks = array();

    foreach ($items as $item) {

        if (!array_key_exists($item->learningtimecheck, $ltccontext)) {
            // Make a local cache of reusable contexts.
            $cm = get_coursemodule_from_instance('learningtimecheck', $item->learningtimecheck);
            $context = context_module::instance($cm->id);
            $ltccontext[$item->learningtimecheck] = $context;
        }

        if ($item->checkid) {
            if ($newstate) {
                /*
                 * New completion is available that is a positive completion trigger.
                 * Update the check if neeed.
                 */
                if ($item->usertimestamp) {
                    // Already checked before.
                    continue;
                }
                $check = new stdClass;
                $check->id = $item->checkid;
                $check->userid = $userid;
                $check->usertimestamp = $completiontime;
                if (report_learningtimecheck_is_valid($check, $reportconfig, $ltccontext[$item->learningtimecheck]) ||
                        !$config->applyfiltering) {
                    $DB->update_record('learningtimecheck_check', $check);
                    $updatelearningtimechecks[] = $item->learningtimecheck;
                    $updatecount++;
                }
            } else {
                /*
                 * Completion has been unmarked for any reason, so checklist should also 
                 * reflect this, whatever the time is valid or not.
                 */
                if (!$item->usertimestamp) {
                    continue;
                }
                $check = new stdClass;
                $check->id = $item->checkid;
                $check->usertimestamp = 0;
                $DB->update_record('learningtimecheck_check', $check);
                $updatelearningtimechecks[] = $item->learningtimecheck;
                $updatecount++;
            }
        } else {
            if (!$newstate) {
                continue;
            }
            $check = new stdClass;
            $check->item = $item->itemid;
            $check->userid = $userid;
            $check->usertimestamp = time();
            $check->teachertimestamp = 0;
            $check->teachermark = 0;
            // LTC_TEACHERMARK_UNDECIDED - not loading from mod/learningtimecheck/lib.php to reduce overhead.

            if (report_learningtimecheck_is_valid($check, $reportconfig, $ltccontext[$item->learningtimecheck]) ||
                    !$config->applyfiltering) {
                $check->id = $DB->insert_record('learningtimecheck_check', $check);
                $updatelearningtimechecks[] = $item->learningtimecheck;
                $createcount++;
            }
        }
    }

    if (defined('DEBUG_LTC_AUTOUPDATE')) {
        mtrace("Updated $updatecount learningtimecheck items from this completion status change");
        mtrace("Created $createcount learningtimecheck items from this completion status change");
    }

    return $updatecount;
}
