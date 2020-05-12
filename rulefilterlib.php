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
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
defined('MOODLE_INTERNAL') || die;

/**
 * Computes a ruleset for all users and filter non matching users. Rules are usually
 * taken from the current user $SESSION, unless it is explicitely given by the second param.
 * this second case is used when delayed processing the reports by cron scheduling.
 *
 * @param ref &$users an input user array
 * @param string $rulefiltersdesc a serialized descriptor of filters. 
 * @return by ref, the filtered user array.
 */
function learningtimecheck_apply_rules(&$users, $rulefiltersdesc = null) {
    global $SESSION;

    static $LOGICALOPS = array(
        'and' => '&&',
        'or' => '||',
        'xor' => '^',
    );

    if (!is_null($rulefiltersdesc)) {
        $filterrules = json_decode($rulefiltersdesc);
        if (empty($filterrules)) {
           return;
        }
    } else {
        if (empty($SESSION->learningtimecheck->filterrules)) {
            return;
        }
        $filterrules = $SESSION->learningtimecheck->filterrules;
    }

    $result = true;
    foreach ($users as $uid => $user) {
        foreach ($filterrules as $rid => $filterrule) {
            $ruleres = learningtimecheck_execute_rule($filterrule, $user->id);
            $expr = (empty($filterrule->logop)) ? " \$result = \$ruleres; " : " \$result = \$result {$LOGICALOPS[$filterrule->logop]} \$ruleres; ";
            eval($expr);
        }
        if (!$result) {
            unset($users[$uid]);
        }
    }
}

/**
 * Executes a single rule
 * @param object $filterrule
 * @param int $userid
 */
function learningtimecheck_execute_rule($filterrule, $userid) {
    global $COURSE, $DB, $CFG;

    if ($CFG->lang == 'fr' || $CFG->lang == 'es') {
        list($day, $month, $year) = explode('/', $filterrule->datetime);
        $filterdatetime = mktime(0, 0, 0, $month, $day, $year);
    } else {
        $filterdatetime = 0 + strtotime($filterrule->datetime);
    }
    $ruleops = learningtimecheck_class::get_ruleop_options();

    $logmanager = get_log_manager();
    $readers = $logmanager->get_readers(ltc_get_logreader_name());
    $reader = reset($readers);

    switch ($filterrule->rule) {
        case 'courseenroltime':
            $sql = "
                SELECT
                    MIN(ue.timestart)
                FROM
                    {user_enrolments} ue,
                    {enrol} e
                WHERE
                    e.courseid = ? AND
                    ue.enrolid = e.id AND
                    ue.userid = ?
            ";
            $time = $DB->get_field_sql($sql, array($COURSE->id, $userid));
            $statement = " \$result = {$time} {$ruleops[$filterrule->ruleop]} {$filterdatetime}; ";
            eval($statement);
            return $result;
            break;

        case 'firstcheckaquired':
            $sql = "
                SELECT
                    0 + MIN(declaredtime)
                FROM
                    {learningtimecheck_check} ltc,
                    {learningtimecheck_item} lti,
                    {learningtimecheck} lt
                WHERE
                    ltc.item = lti.id AND
                    lti.learningtimecheck = lt.id AND
                    lt.course = ? AND
                    ltc.userid = ?
            ";
            $time = 0 + $DB->get_field_sql($sql, array($COURSE->id, $userid));
            if ($time) {
                $statement = " \$result = {$time} {$ruleops[$filterrule->ruleop]} {$filterdatetime}; ";
                echo $statement;
                eval($statement);
                return $result;
            }
            return false;
            break;

        case 'checkcomplete':
            break;

        case 'coursestarted':

            if (empty($reader)) {
                return false; // No log reader found.
            }

            // We'll have to probably address directly the log tables (standard_log and log if used).
            if ($reader instanceof \logstore_standard\log\store) {
                // address standard log
                $time = $DB->get_field('logstore_standard_log', 'MIN(timecreated)', array('userid' => $userid, 'courseid' => $COURSE->id));
            } else if ($reader instanceof \logstore_standard\log\store) {
                // address legacy log table
                $time = $DB->get_field('log', 'MIN(time)', array('userid' => $userid, 'courseid' => $COURSE->id));
            } else {
                // Might be not supported or needs to be developed, such as external DB logging.
                throw new coding_exception('Not supported logstore');
            }

            $statement = " \$result = {$time} {$ruleops[$filterrule->ruleop]} {$filterdatetime}; ";
            eval($statement);
            return $result;
            break;

        case 'coursecompleted':
            $completion = $DB->get_record('course_completion', array('courseid' => $COURSE->id, 'userid' => $userid));
            $completiontime = $completion->timecompleted;
            $statement = " \$result = {$time} {$ruleops[$filterrule->ruleop]} {$filterdatetime}; ";
            break;

        case 'lastcoursetrack':

            if (empty($reader)) {
                return false; // No log reader found.
            }

            // We'll have to probably address directly the log tables (standard_log and log if used).
            if ($reader instanceof \logstore_standard\log\store) {
                // address standard log
                $time = $DB->get_field('logstore_standard_log', 'MAX(timecreated)', array('userid' => $userid, 'courseid' => $COURSE->id));
            } else if ($reader instanceof \logstore_standard\log\store) {
                // address legacy log table
                $time = $DB->get_field('log', 'MAX(time)', array('userid' => $userid, 'courseid' => $COURSE->id));
            } else {
                // Might be not supported or needs to be developed, such as external DB logging.
                throw new coding_exception('Not supported logstore');
            }

            $statement = " \$result = {$time} {$ruleops[$filterrule->ruleop]} {$filterdatetime}; ";
            eval($statement);
            return $result;

        case 'usercreationdate':
            $createdate = 0 + $DB->get_field('user', 'timecreated', array('id' => $userid));
            $statement = " \$result = {$createdate} {$ruleops[$filterrule->ruleop]} {$filterdatetime}; ";
            eval($statement);
            return $result;

        case 'sitefirstevent':

            if (empty($reader)) {
                return false; // No log reader found.
            }

            // We'll have to probably address directly the log tables (standard_log and log if used).
            if ($reader instanceof \logstore_standard\log\store) {
                // address standard log
                $firstevent = $DB->get_field('logstore_standard_log', 'MIN(timecreated)', array('userid' => $userid));
            } else if ($reader instanceof \logstore_standard\log\store) {
                // address legacy log table
                $firstevent = $DB->get_field('log', 'MIN(time)', array('userid' => $userid));
            } else {
                // Might be not supported or needs to be developed, such as external DB logging.
                throw new coding_exception('Not supported logstore');
            }

            // Needs at least an event to accept.
            if (!$firstevent) {
                return false;
            }

            $statement = " \$result = {$firstevent} {$ruleops[$filterrule->ruleop]} {$filterdatetime}; ";
            eval($statement);
            return $result;

        case 'sitelastevent':

            if (empty($reader)) {
                return false; // No log reader found.
            }
            // We'll have to probably address directly the log tables (standard_log and log if used).
            if ($reader instanceof \logstore_standard\log\store) {
                // Address standard log.
                $lastevent = $DB->get_field('logstore_standard_log', 'MAX(timecreated)', array('userid' => $userid));
            } else if ($reader instanceof \logstore_standard\log\store) {
                // Address legacy log table.
                $lastevent = $DB->get_field('log', 'MAX(time)', array('userid' => $userid));
            } else {
                // Might be not supported or needs to be developed, such as external DB logging.
                return false;
            }

            // Needs at least an event to accept.
            if (!$lastevent) {
                return false;
            }

            $statement = " \$result = {$lastevent} {$ruleops[$filterrule->ruleop]} {$filterdatetime}; ";
            eval($statement);
            return $result;

        case 'firstcoursestarted':
            /*
             * this means : i have started activity in at least one registered course (except SITE and MY page). this
             * can discriminate users just registered in global spaces from users being actually users in courses
             */

            if (empty($reader)) {
                return false; // No log reader found.
            }
            // We'll have to probably address directly the log tables (standard_log and log if used).
            if ($reader instanceof \logstore_standard\log\store) {
                // Address standard log.
                $select = " userid = ? AND courseid > 1 ";
                $params = array('userid' => $userid);
                $lastevent = $DB->count_record_select('logstore_standard_log', 'MIN(timecreated)', $select, $params);
            } else if ($reader instanceof \logstore_standard\log\store) {
                // Address legacy log table.
                $select = " userid = ? AND course > 1 ";
                $lastevent = $DB->count_records_select('log', 'MIN(time)', $select, array('userid' => $userid));
            } else {
                // Might be not supported or needs to be developed, such as external DB logging.
                return false;
            }

            if (!$mincourserecord) {
                return false;
            }

            $statement = " \$result = {$mincourserecord} {$ruleops[$filterrule->ruleop]} {$filterdatetime}; ";
            return $result;

        case 'firstcoursecompleted':
            // To be implemented.
            return true;

        case 'usercohortaddition':
            /*
             * This means actually : first addition to any cohort, as user filter cannot store contextual
             * information related to the currently used view.
             */
            $mincohortaddition = $DB->get_field('cohort_members', 'MIN(timeadded)', array('userid' => $userid));

            if (!$mincohortaddition) {
                return false;
            }

            $statement = " \$result = {$mincourserecord} {$ruleops[$filterrule->ruleop]} {$filterdatetime}; ";
            return $result;

        default:
            return true;
    }
}

function learningtimecheck_apply_namefilters(&$fullusers) {
    $firstnamefilter = optional_param('filterfirstname', false, PARAM_TEXT);
    $lastnamefilter = optional_param('filterlastname', false, PARAM_TEXT);

    if (!$firstnamefilter && !$lastnamefilter) {
        return;
    }

    if ($firstnamefilter) {
        foreach ($fullusers as $userid => $user) {
            if (!preg_match('/^'.$firstnamefilter.'/i', $user->firstname)) {
                unset($fullusers[$userid]);
            }
        }
    }

    if ($lastnamefilter) {
        foreach ($fullusers as $userid => $user) {
            if (!preg_match('/^'.$lastnamefilter.'/i', $user->lastname)) {
                unset($fullusers[$userid]);
            }
        }
    }
}