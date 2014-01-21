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

defined('MOODLE_INTERNAL') || die();

$CFG->learningtimecheck_autoupdate_use_cron = true;

/* Remove the '//' at the start of the next line to output lots of
 * helpful information during the cron update. Do NOT use this if you
 * have made the core modifications outlined in core_modifications.txt
 */
//define("DEBUG_learningtimecheck_AUTOUPDATE", 1);

function learningtimecheck_autoupdate($courseid, $module, $action, $cmid, $userid, $url, $learningtimechecks=null) {
    global $CFG, $DB;

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
        || (($module == 'hotpot') && ($action == 'submit'))
        || (($module == 'wiki') && ($action == 'edit'))
        || (($module == 'learningtimecheck') && ($action == 'complete'))
        || (($module == 'choice') && ($action == 'choose'))
        || (($module == 'lams') && ($action == 'view'))
        || (($module == 'scorm') && ($action == 'view'))
        || (($module == 'assignment') && ($action == 'upload'))
        || (($module == 'journal') && ($action == 'add entry'))
        || (($module == 'lesson') && ($action == 'end'))
        || (($module == 'realtimequiz') && ($action == 'submit'))
        || (($module == 'workshop') && ($action == 'submit'))
        || (($module == 'glossary') && ($action == 'add entry'))
        || (($module == 'data') && ($action == 'add'))
        || (($module == 'chat') && ($action == 'talk'))
        || (($module == 'feedback') && ($action == 'submit'))
        ) {

        if (defined("DEBUG_learningtimecheck_AUTOUPDATE")) {
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
                if (defined("DEBUG_learningtimecheck_AUTOUPDATE")) {
                    mtrace("No suitable learningtimechecks to update in course $courseid");
                }
                return 0;
                // No learningtimechecks in this course that are auto-updating
            }
        }

        if (isset($CFG->enablecompletion) && $CFG->enablecompletion) {
            // Completion is enabled on this site, so we need to check if this module
            // can do completion (and then wait for that to indicate the module is complete)
            $coursecompletion = $DB->get_field('course',
                                               'enablecompletion',
                                               array('id'=>$courseid));
            if ($coursecompletion) {
                $cmcompletion = $DB->get_field('course_modules',
                                               'completion',
                                               array('id'=>$cmid));
                if ($cmcompletion) {
                    if (defined("DEBUG_learningtimecheck_AUTOUPDATE")) {
                        mtrace("This course module has completion enabled - allow that to control any learningtimecheck items");
                    }
                    return 0;
                }
            }
        }

        // Find all learningtimecheck_item records which are related to these $learningtimechecks which have a moduleid matching $module
        // and any information about checks they might have
        list($csql, $cparams) = $DB->get_in_or_equal(array_keys($learningtimechecks));
        $params = array_merge(array($userid, $cmid), $cparams);

        $sql = "SELECT i.id itemid, c.id checkid, c.usertimestamp FROM {learningtimecheck_item} i ";
        $sql .= "LEFT JOIN {learningtimecheck_check} c ON (c.item = i.id AND c.userid = ?) ";
        $sql .= "WHERE i.moduleid = ? AND i.learningtimecheck $csql AND i.itemoptional < 2";
        $items = $DB->get_records_sql($sql, $params);
        // itemoptional - 0: required; 1: optional; 2: heading;
        // not loading defines from mod/learningtimecheck/locallib.php to reduce overhead
        if (empty($items)) {
            if (defined("DEBUG_learningtimecheck_AUTOUPDATE")) {
                mtrace("No learningtimecheck items linked to this course module");
            }
            return 0;
        }

        $updatecount = 0;
        foreach ($items as $item) {
            if ($item->checkid) {
                if ($item->usertimestamp) {
                    continue;
                }
                $check = new stdClass;
                $check->id = $item->checkid;
                $check->usertimestamp = time();
                $DB->update_record('learningtimecheck_check', $check);
                $updatecount++;
            } else {
                $check = new stdClass;
                $check->item = $item->itemid;
                $check->userid = $userid;
                $check->usertimestamp = time();
                $check->teachertimestamp = 0;
                $check->teachermark = 0;
                // learningtimecheck_TEACHERMARK_UNDECIDED - not loading from mod/learningtimecheck/lib.php to reduce overhead

                $check->id = $DB->insert_record('learningtimecheck_check', $check);
                $updatecount++;
            }
        }
        if (defined("DEBUG_learningtimecheck_AUTOUPDATE")) {
            mtrace("$updatecount learningtimecheck items updated from this log entry");
        }
        if ($updatecount) {
            require_once($CFG->dirroot.'/mod/learningtimecheck/lib.php');
            foreach ($learningtimechecks as $learningtimecheck) {
                learningtimecheck_update_grades($learningtimecheck, $userid);
            }
            return $updatecount;
        }
    }

    return 0;
}

function learningtimecheck_completion_autoupdate($cmid, $userid, $newstate) {
    global $DB, $CFG, $USER;

    if ($userid == 0) {
        $userid = $USER->id;
    }

    if (defined("DEBUG_learningtimecheck_AUTOUPDATE")) {
        mtrace("Completion status change for cmid: $cmid, userid: $userid, newstate: $newstate");
    }

    $sql = "SELECT i.id itemid, c.id checkid, c.usertimestamp, i.learningtimecheck FROM {learningtimecheck_item} i ";
    $sql .= "JOIN {learningtimecheck} cl ON i.learningtimecheck = cl.id ";
    $sql .= "LEFT JOIN {learningtimecheck_check} c ON (c.item = i.id AND c.userid = ?) ";
    $sql .= "WHERE cl.autoupdate > 0 AND i.moduleid = ? AND i.itemoptional < 2 ";
    $items = $DB->get_records_sql($sql, array($userid, $cmid));
    // itemoptional - 0: required; 1: optional; 2: heading;
    // not loading defines from mod/learningtimecheck/locallib.php to reduce overhead
    if (empty($items)) {
        if (defined("DEBUG_learningtimecheck_AUTOUPDATE")) {
            mtrace("No learningtimecheck items linked to this course module");
        }
        return 0;
    }

    $newstate = ($newstate == COMPLETION_COMPLETE || $newstate == COMPLETION_COMPLETE_PASS); // Not complete if failed
    $updatecount = 0;
    $updatelearningtimechecks = array();
    foreach ($items as $item) {
        if ($item->checkid) {
            if ($newstate) {
                if ($item->usertimestamp) {
                    continue;
                }
                $check = new stdClass;
                $check->id = $item->checkid;
                $check->usertimestamp = time();
                $DB->update_record('learningtimecheck_check', $check);
                $updatelearningtimechecks[] = $item->learningtimecheck;
                $updatecount++;
            } else {
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
            // learningtimecheck_TEACHERMARK_UNDECIDED - not loading from mod/learningtimecheck/lib.php to reduce overhead

            $check->id = $DB->insert_record('learningtimecheck_check', $check);
            $updatelearningtimechecks[] = $item->learningtimecheck;
            $updatecount++;
        }
    }
    if (!empty($updatelearningtimechecks)) {
        $updatelearningtimechecks = array_unique($updatelearningtimechecks);
        list($csql, $cparams) = $DB->get_in_or_equal($updatelearningtimechecks);
        $learningtimechecks = $DB->get_records_select('learningtimecheck', 'id '.$csql, $cparams);
        require_once($CFG->dirroot.'/mod/learningtimecheck/lib.php');
        foreach ($learningtimechecks as $learningtimecheck) {
            learningtimecheck_update_grades($learningtimecheck, $userid);
        }
    }

    if (defined("DEBUG_learningtimecheck_AUTOUPDATE")) {
        mtrace("Updated $updatecount learningtimecheck items from this completion status change");
    }

    return $updatecount;
}
