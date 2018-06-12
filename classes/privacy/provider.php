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
 * Data provider.
 *
 * @package    mod_learningtimecheck
 * @copyright  2018 Valery Fremaux
 * @author     Valery Fremaux <valery.fremaux@mylearningfactory.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_learningtimecheck\privacy;
defined('MOODLE_INTERNAL') || die();

use context;
use context_helper;
use context_module;
use moodle_recordset;
use stdClass;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Data provider class.
 *
 * @package    mod_chat
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {

        $collection->add_database_table('learningtimecheck_check', [
            'userid' => 'privacy:metadata:check:userid',
            'usertimestamp' => 'privacy:metadata:check:usertimestamp',
            'declaredtime' => 'privacy:metadata:check:declaredtime',
            'teacherid' => 'privacy:metadata:check:teacherid',
            'teachermark' => 'privacy:metadata:check:teachermark',
            'teachertimestamp' => 'privacy:metadata:check:teachertimestamp',
            'teacherdeclaredtime' => 'privacy:metadata:check:teacherdeclaredtime',
        ], 'privacy:metadata:checks');

        $collection->add_database_table('learningtimecheck_comment', [
            'userid' => 'privacy:metadata:comment:userid',
            'itemid' => 'privacy:metadata:comment:itemid',
            'commentby' => 'privacy:metadata:comment:commentby',
            'text' => 'privacy:metadata:comment:text',
        ], 'privacy:metadata:comment');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        // Add contexts for user as student.
        $sql = "
            SELECT DISTINCT ctx.id
              FROM {learningtimecheck} ltc
              JOIN {modules} m
                ON m.name = :learningtimecheck
              JOIN {course_modules} cm
                ON cm.instance = ltc.id
               AND cm.module = m.id
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :modulelevel
              JOIN {learningtimecheck_item} ltci
                ON ltci.learningtimecheck = ltc.id
              JOIN {learningtimecheck_check} ltcc
                ON ltcc.item = ltci.id
             WHERE ltcc.userid = :userid";

        $params = [
            'learningtimecheck' => 'learningtimecheck',
            'modulelevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        // Add contexts for user as teacher.
        $sql = "
            SELECT DISTINCT ctx.id
              FROM {learningtimecheck} ltc
              JOIN {modules} m
                ON m.name = :learningtimecheck
              JOIN {course_modules} cm
                ON cm.instance = ltc.id
               AND cm.module = m.id
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :modulelevel
              JOIN {learningtimecheck_item} ltci
                ON ltci.learningtimecheck = ltc.id
              JOIN {learningtimecheck_check} ltcc
                ON ltcc.item = ltci.id
             WHERE ltcc.teacherid = :userid";

        $params = [
            'learningtimecheck' => 'learningtimecheck',
            'modulelevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        // Add contexts for user as teacher by comments.
        $sql = "
            SELECT DISTINCT ctx.id
              FROM {learningtimecheck} ltc
              JOIN {modules} m
                ON m.name = :learningtimecheck
              JOIN {course_modules} cm
                ON cm.instance = ltc.id
               AND cm.module = m.id
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :modulelevel
              JOIN {learningtimecheck_item} ltci
                ON ltci.learningtimecheck = ltc.id
              JOIN {learningtimecheck_comment} ltcc
                ON ltcc.itemid = ltci.id
             WHERE ltcc.commentby = :userid";

        $params = [
            'learningtimecheck' => 'learningtimecheck',
            'modulelevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $userid = $user->id;
        $ltcids = array_reduce($contextlist->get_contexts(), function($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $ltcid = $DB->get_field('course_module', 'instance', array('id' => $context->instanceid));
                $carry[] = $ltcid;
            }
            return $carry;
        }, []);
        if (empty($ltcids)) {
            return;
        }

        // Now export data.

    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('learningtimecheck', $context->instanceid);
        if (!$cm) {
            return;
        }

        $ltcid = $cm->instance;
    $items = $DB->get_records('learningtimecheck_item', ['learningtimecheckid' => $ltcid]);
        $itemlist = array_keys($items);
        if (!empty($itemlist)) {
            // TODO to be checked.
            $DB->delete_records_list('learningtimecheck_check', 'item', $itemlist);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $ltcids = array_reduce($contextlist->get_contexts(), function($carry, $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $ltcid = $DB->get_field('course_module', 'instance', ['id' => $context->instanceid]);
                $carry[] = $ltcid;
            }
            return $carry;
        }, []);
        if (empty($ltcids)) {
            return;
        }

        foreach ($ltcids as $ltcid) {
            $items = $DB->get_records('learnigntimecheck_item', ['learningtimecheckid' => $ltcid]);
            $itemids = array_keys($items);
            if (!empty($itemids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
                $sql = "itemid $insql AND userid = :userid";
                $params = array_merge($inparams, ['userid' => $userid]);

                // Delete checks if student.
                $DB->delete_records_select('learningtimecheck_check', $sql, $params);
                $DB->delete_records_select('learningtimecheck_comment', $sql, $params);

                // Delete teacher marks and data if teacher, but DO NOT delete the check record.
                $sql = "UPDATE
                    {learningtimecheck_check} ltcc
                    SET
                        teacherid = 0,
                        teachermark = 0,
                        teacherdeclaredtime = 0,
                        teachertimestamp = 0
                    WHERE
                        itemid $insql AND
                        teacherid = :userid
                ";
                $DB->execute($sql, $params);
            }
        }
    }
}
