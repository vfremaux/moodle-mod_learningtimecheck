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

require_once($CFG->dirroot.'/mod/learningtimecheck/locallib.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/lib.php');

function learningtimecheck_get_instances($courseid, $usecredit = null) {
    global $DB;

    if ($usecredit) {
        $creditclause = ' AND usetimecounterpart = 1 ';
    } elseif ($usecredit === false) {
        $creditclause = ' AND usetimecounterpart = 0 ';
    } else {
        $creditclause = '';
    }

    if ($learningtimechecks = $DB->get_records_select('learningtimecheck', " course = ? $creditclause ", array($courseid))) {
        return $learningtimechecks;
    }
    return array();
}

/**
 * @param int $learningtimecheckid
 * @param int $cmid
 * @param int $userid
 * @return validated credittimes on course modules with several filters.
 * credittime values are normalized in secs.
 */
function learningtimecheck_get_credittimes($learningtimecheckorid = 0, $cmid = 0, $userid = 0) {
    global $CFG, $DB;

    if (is_numeric($learningtimecheckorid)) {
        $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $learningtimecheckorid));
    } else {
        $learningtimecheck = $learningtimecheckorid;
    }

    $learningtimecheckclause = ($learningtimecheck->id) ? " AND ci.learningtimecheck = {$learningtimecheck->id} " : '';
    $cmclause = ($cmid) ? " AND cm.id = $cmid " : '';
    $userclause = ($userid) ? " AND cc.userid = $userid " : '';
    $teachermarkclause = '';
    if ($learningtimecheck->teacheredit == LTC_MARKING_TEACHER || $learningtimecheck->teacheredit == LTC_MARKING_BOTH) {
        $markvalue = 'teachermark as ismarked,';
    } else {
        $markvalue = ' usertimestamp > 0 as ismarked,';
    }

    // get only teacher validated marks to assess the credit time
    $sql = "
        SELECT
            ci.id,
            cc.userid,
            ci.moduleid AS cmid,
            ci.credittime * 60 AS credittime,
            $markvalue
            m.name AS modname
        FROM
            {learningtimecheck_item} ci
        LEFT JOIN
            {learningtimecheck_check} cc
        ON
            ci.id = cc.item AND
            ci.userid = cc.userid
            $userclause
        LEFT JOIN
            {course_modules} cm
        ON
            cm.id = ci.moduleid
        LEFT JOIN
            {modules} m
        ON
            m.id = cm.module
        WHERE
            ci.enablecredit = 1
            $cmclause
            $learningtimecheckclause
    ";

    return $DB->get_records_sql($sql);
}

/**
 * @param int $learningtimecheckid
 * @param int $cmid
 * @param int $userid
 * @return validated credittimes on course modules with several filters.
 * credittime values are normalized in secs.
 */
function learningtimecheck_get_declaredtimes($learningtimecheckid, $cmid = 0, $userid = 0) {
    global $CFG, $USER, $DB;

    $learningtimecheckclause = ($learningtimecheckid) ? " AND ci.learningtimecheck = $learningtimecheckid " : '' ;
    $cmclause = ($cmid) ? " AND cm.id = $cmid " : '' ;
    $userclause = ($userid) ? " AND cc.userid = $userid " : '' ;
    $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => "$learningtimecheckid"));
    $teachermarkedclause = '';
    if ($learningtimecheck->teacheredit > LTC_MARKING_STUDENT) {
        $teachermarkedclause = " AND teachermark = 1 ";
    }

    // TODO : resolve inconsistancy for learningtimecheckid = 0 vs. explicit watcher status against learningtimecheck instance.
    $cklcm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheckid);
    $context = context_module::instance($cklcm->id);

    if (has_capability('mod/learningtimecheck:updateother', $context) && $userid == $USER->id) {

        // assessor case when self viewing 
        // get sum of teacherdelcaredtimes you have for each explicit module, or default module to learningtimecheck itself (NULL)
        // note the primary key is a pseudo key calculated for unicity, not for use.
        $sql = "
            SELECT
                MAX(ci.id) as id,
                cc.teacherid,
                ci.moduleid as cmid,
                SUM(cc.teacherdeclaredtime) * 60 as declaredtime,
                m.name as modname
            FROM
                {learningtimecheck_check} cc
            JOIN
                {learningtimecheck_item} ci
            ON
                ci.id = cc.item
            LEFT JOIN
                {course_modules} cm
            ON
                cm.id = ci.moduleid
            LEFT JOIN
                {modules} m
            ON 
                m.id = cm.module
            WHERE
                cc.teacherid = $userid
                $teachermarkedclause
                $learningtimecheckclause
                $cmclause
            GROUP BY
                cc.teacherid,
                cmid
        ";
        
        // echo "teacher $sql <br/>";
        
        return $DB->get_records_sql($sql);
    } else {

        // student case.

        // get only teacher validated marks to assess the declared time
        $sql = "
            SELECT
                ci.id,
                cc.userid,
                ci.moduleid as cmid,
                cc.declaredtime * 60 as declaredtime,
                m.name as modname
            FROM
                {learningtimecheck_check} cc
            JOIN
                {learningtimecheck_item} ci
            ON
                ci.id = cc.item AND
                ci.userid = cc.userid
                $userclause
                $learningtimecheckclause
            LEFT JOIN
                {course_modules} cm
            ON
                cm.id = ci.moduleid
            LEFT JOIN
                {modules} m
            ON 
                m.id = cm.module
            WHERE
                1 = 1
                $teachermarkedclause
                $cmclause
        ";

        // echo "Student : $sql <br/>";

        return $DB->get_records_sql($sql);
    }
}

/**
 * Get concerned checklists for a user or a course
 */
function learningtimecheck_get_checklists($uid, $courseid = 0) {
    global $DB;
    
    if ($courseid) {
        if ($records = $DB->get_records('learningtimecheck', array('course' => $courseid))) {
            foreach($records as $r) {
                $cm = get_coursemodule_from_instance('learningtimecheck', $r->id);
                $checklists[] = new learningtimecheck_class($cm->id, $uid, $r);
            }
            return $checklists;
        }
    } else {
        // TODO
        // returns all learningtimechecks concerned by the user
    }
}

function learningtimecheck_course_has_ltc_tracking($courseid) {
    global $DB;

    return $DB->record_exists('learningtimecheck', array('course' => $courseid));
}

function learningtimecheck_get_course_marks($courseid, $userid, $mandatory = false) {
    global $DB;

    $instances = $DB->get_records('learningtimecheck', array('course' => $courseid));

    $marks = array();
    if ($instances) {
        foreach ($instances as $ltcrec) {
            $cm = get_coursemodule_from_instance('learningtimecheck', $ltcrec->id);
            $ltc = new learningtimecheck_class($cm->id, $userid, $ltcrec, $cm);
            foreach ($ltc->items as $item) {
                if ($item->itemoptional == LTC_OPTIONAL_HEADING) {
                    continue;
                }
                if ($mandatory) {
                    if ($item->itemoptional != LTC_OPTIONAL_NO) {
                        continue;
                    }
                }
                $marks[$item->moduleid] = $item->checked;
            }
        }
    }

    return $marks;
}