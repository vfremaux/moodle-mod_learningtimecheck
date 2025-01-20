<?php
// This file is part of Moodle - http://moodle.org/
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
 * Event observers used in learningtimecheck.
 *
 * @package    mod_learningtimecheck
 * @copyright  2014 Valery Fremaux <valery.fremaux@gmail.coml>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for mod_learningtimecheck.
 */
class mod_learningtimecheck_observer {

    /**
     * Mark or unmark LTC ticks.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function on_course_module_completion_updated(\core\event\course_module_completion_updated $event) {
        global $DB;

        $config = get_config('learningtimecheck');

        $completion = $DB->get_record('course_modules_completion', ['id' => $event->objectid]);

        // find some LTC items concerned. Might have several in distinct LTC instances.
        $sql = "
            SELECT
                ltci.*
            FROM
                {learningtimecheck_item} ltci,
                {learningtimecheck} ltc
            WHERE
                ltci.learningtimecheck = ltc.id AND
                ltc.course = ? AND
                ltci.moduleid = ?
        ";
        $params = [$event->courseid, $event->objectid];
        $items = $DB->get_records_sql($sql, $params);

        debug_trace($sql);
        debug_trace($params);
        debug_trace($items);

        if (empty($items)) {
            return;
        }

        $ltcmark = 0;
        switch ($completion->state) {
            case COMPLETION_INCOMPLETE: {
                $ltcmark = 0;
            }

            case COMPLETION_COMPLETE:
            case COMPLETION_COMPLETE_PASS: {
                $ltcmark = 1;
            }

            case COMPLETION_COMPLETE_FAIL: {
                if (!empty($config->defaultconsidergrades)) {
                    $ltcmark = 0;
                } else {
                    $ltcmark = 1;
                }
            }
        }

        foreach ($items as $i) {
            // Should be none or one.
            $check = $DB->get_record('learningtimecheck_check', ['itemid' => $i->id, 'userid' => $event->relateduserid]);
            if (!$check && $ltcmark) {
                $check = new StdClass;
                $check->item = $i->id;
                $check->usertimestamp = time();
                $check->declaredtime = 0;
                if ($event->userid != $event->relateduserid) {
                    // Some one (a teacher) is marking another user completion.
                    $check->teacherid = $event->userid;
                    $check->teachermark = LTC_TEACHERMARK_YES;
                    $check->teachertimestamp = time();
                }
                $check->teacherdeclaredtime = 0;
                $DB->insert_record('learningtimecheck_check', $check);
            } else {
                // The check exists.
                if ($ltcmark) {
                    if ($check->usertimestamp == 0) {
                        // Do not change an already registered date (although it should not happen, in fact).
                        $check->usertimestamp = time();
                    }
                    if ($event->userid != $event->relateduserid) {
                        // Some one (a teacher) is marking another user completion.
                        $check->teacherid = $event->userid;
                        $check->teachermark = LTC_TEACHERMARK_YES;
                        $check->teachertime = time();
                    }
                } else {
                    $check->teacherid = $event->userid;
                    $check->teachermark = LTC_TEACHERMARK_NO;
                    $check->teachertimestamp = 0;
                }
                $DB->update_record('learningtimecheck_check', $check);
            }
        }
    }
}
