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

require_once($CFG->dirroot.'/mod/learningtimecheck/locallib.php');

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

if (interface_exists('\core_privacy\local\request\userlist')) {
    interface my_userlist extends \core_privacy\local\request\userlist{}
} else {
    interface my_userlist {};
}

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
    \core_privacy\local\request\plugin\provider,
    my_userlist {

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

        foreach ($contextlist->get_contexts() as $ctx) {

            $ltc = self::export_learningtimecheck($ctx, $user);

            $sql = "
                SELECT
                    ltcc.id as checkid,
                    ltcc.userid as userid,
                    ltcc.usertimestamp as markedon,
                    ltcc.declaredtime as userid,
                    ltcc.teacherid as assessedby,
                    ltcc.teachermark as assessmark,
                    ltcc.teacherdeclaredtime as coachingtime,
                    ltcc.teachertimestamp as assessedon,
                    ltci.itemoptional,
                    ltci.credittime,
                    ltcc.teachertimestamp as assessedon,
                    m.name as modname,
                    cm.instanceid
                FROM
                    {learningtimecheck_check} ltcc,
                    {learningtimecheck_item} ltci,
                    {course_modules} cm, ". /* as check related referred module, not LTC module itself */ "
                    {modules} m
                WHERE
                    ltcc.item = ltci.id AND
                    ltci.moduleid = cm.id AND
                    cm.module = m.id AND
                    ltcc.userid = :userid AND
                    ltci.learningtimecheck = :ltcid
            ";

            $userchecks = $DB->get_records_sql($sql, ['userid' => $user->id, 'ltcid' => $ltc->id]);
            if ($userchecks) {
                foreach ($userchecks as $check) {
                    self::export_learningtimecheck_check($ctx, $user, $check);
                }
            }

            $sql = "
                SELECT
                    ltcm.text as comment,
                    ltcm.commentby as commentedby,
                    ltci.id as itemid,
                    ltci.displaytext as itemname,
                    ltcm.userid as userid,
                    m.name as modname,
                    cm.instanceid
                FROM
                    {learningtimecheck_item} ltci,
                    {learningtimecheck_comment} ltcm,
                    {course_modules} cm, ". /* as item related referred module, not LTC module itself */ "
                    {modules} m
                WHERE
                    ltcm.itemid = ltci.id AND
                    ltcm.userid = :userid
                    ltci.moduleid = cm.id AND
                    cm.module = m.id AND
                    ltci.learningtimecheck = :ltcid
            ";

            $usercommentonme = $DB->get_records_sql($sql, ['userid' => $user->id, 'ltcid' => $ltc->id]);
            if ($usercommentonme) {
                foreach ($usercommentonme as $comment) {
                    self::export_learningtimecheck_comment($ctx, $user, $comment);
                }
            }

            // Teacher role.

            $sql = "
                SELECT
                    ltcc.id as checkid,
                    ltcc.userid as userid,
                    ltcc.usertimestamp as markedon,
                    ltcc.declaredtime as userid,
                    ltcc.teacherid as assessedby,
                    ltcc.teachermark as assessmark,
                    ltcc.teacherdeclaredtime as coachingtime,
                    ltcc.teachertimestamp as assessedon,
                    ltci.itemoptional,
                    ltci.credittime,
                    ltcc.teachertimestamp as assessedon,
                    m.name as modname,
                    cm.instanceid
                FROM
                    {learningtimecheck_check} ltcc,
                    {learningtimecheck_item} ltci,
                    {course_modules} cm, ". /* as check related referred module, not LTC module itself */ "
                    {modules} m
                WHERE
                    ltcc.item = ltci.id AND
                    ltci.moduleid = cm.id AND
                    cm.module = m.id AND
                    ltcc.teacherid = :userid AND
                    ltci.learningtimecheck = :ltcid
            ";

            $userassessments = $DB->get_records_sql($sql, ['userid' => $user->id, 'ltcid' => $ltc->id]);
            if ($userassessments) {
                foreach ($userassessments as $check) {
                    self::export_learningtimecheck_check($ctx, $user, $check);
                }
            }

            $sql = "
                SELECT
                    ltcm.text as comment,
                    ltcm.commentby as commentedby,
                    ltcm.userid as commenton,
                    ltci.id as itemid,
                    ltci.displaytext as itemname,
                    m.name as modname,
                    cm.instanceid
                FROM
                    {learningtimecheck_item} ltci,
                    {learningtimecheck_comment} ltcm,
                    {course_modules} cm, ". /* as item related referred module, not LTC module itself */ "
                    {modules} m
                WHERE
                    ltcm.itemid = ltci.id AND
                    ltcm.commentby = :userid
                    ltci.moduleid = cm.id AND
                    cm.module = m.id AND
                    ltci.learningtimecheck = :ltcid
            ";

            $usercommentonothers = $DB->get_records_sql($sql, ['userid' => $user->id, 'ltcid' => $ltc->id]);
            if ($usercommentonothers) {
                foreach ($usercommentonothers as $comment) {
                    self::export_check_comment($ctx, $user, $comment);
                }
            }
        }
    }

    protected static function export_check_records($context, $user, $recordobj) {
        global $DB;

        if (!$recordobj) {
            return;
        }

        $recordobj->userid = transform::user($user->id);
        $recordobj->declaredtime = self::transform_duration($recordobj->declaredtime);
        $recordobj->markedon = transform::datetime($recordobj->markedon);
        $recordobj->teacherid = transform::user($recordobj->teacherid);
        $recordobj->coachingtime = self::transform_duration($recordobj->coachingtime);
        $recordobj->assessedon = transform::datetime($recordobj->assessedon);
        $recordobj->itemoptional = self::transform_optional($recordobj->itemoptional);

        $recordobj->name = $DB->get_field($recordobj->modname, 'name', ['id' => $recordobj->instanceid]);

        // Data about the record.
        writer::with_context($context)->export_data([$recordobj->id], (object)$recordobj);
    }

    protected static function export_check_comments($context, $user, $recordobj) {
        global $DB;

        if (!$recordobj) {
            return;
        }

        $recordobj->userid = transform::user($recordobj->userid);
        $recordobj->commenton = transform::user($recordobj->commenton);

        $data->name = $DB->get_field($data->modname, 'name', ['id' => $data->instanceid]);

        // Data about the record.
        writer::with_context($context)->export_data([$recordobj->id], (object)$data);
    }

    protected static function transform_duration($duration) {
        return $duration;
    }

    protected static function transform_optional($itemoptional) {
        return transform::yesno($itemoptional == LTC_OPTIONAL_YES);
    }

    protected static function export_learningtimecheck($context, $user) {
        global $DB;

        if (!$context) {
            return;
        }

        $contextdata = helper::get_context_data($context, $user);
        writer::with_context($context)->export_data([], $contextdata);

        $sql = "
            SELECT
                cm.id,
                ".self::get_fields()."
            FROM
                {context} ctx,
                {course_modules} cm,
                {modules} m,
                {learningtimecheck} ltc
            WHERE
                cm.module = m.id AND
                m.name = 'learningtimecheck' AND
                cm.instance = ltc.id AND
                ctx.contextlevel = ? AND
                ctx.instanceid = cm.id AND
                ctx.id = ?
        ";

        $ltc = $DB->get_record_sql($sql, [CONTEXT_MODULE, $context->id]);
        $ltc->emailoncomplete = transform::yesno($ltc->emailoncomplete);
        $ltc->lockteachermarks = transform::yesno($ltc->lockteachermarks);
        $ltc->usetimecounterparts = transform::yesno($ltc->usetimecounterparts);

        writer::with_context($context)->export_data([], $ltc);

        return $ltc;
    }

    protected static function get_fields() {
        return " ltc.name, ltc.intro, ltc.maxgrade, ltc.emailoncomplete, ltc.lockteachermarks, ltc.usetimecounterpart ";
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

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $params = [
            'instanceid'    => $context->instanceid,
            'modulename'    => 'learningtimecheck',
        ];

        // Users owning items.
        $sql = "SELECT ltci.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {learningtimecheck} ltc ON ltc.id = cm.instance
                  JOIN {learningtimecheck_item} ltci ON ltci.learningtimecheck = ltc.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Students having marks.
        $sql = "SELECT ltcc.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {learningtimecheck} ltc ON ltc.id = cm.instance
                  JOIN {learningtimecheck_item} ltci ON ltci.learningtimecheck = ltc.id
                  JOIN {learningtimecheck_check} ltcc ON ltcc.itemid = ltci.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Teachers owning marks.
        $sql = "SELECT ltcc.teacherid as userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {learningtimecheck} ltc ON ltc.id = cm.instance
                  JOIN {learningtimecheck_item} ltci ON ltci.learningtimecheck = ltc.id
                  JOIN {learningtimecheck_check} ltcc ON ltcc.itemid = ltci.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Users being commented on.
        $sql = "SELECT ltcc.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {learningtimecheck} ltc ON ltc.id = cm.instance
                  JOIN {learningtimecheck_item} ltci ON ltci.learningtimecheck = ltc.id
                  JOIN {learningtimecheck_comment} ltcc ON ltcc.itemid = ltci.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Users having commented.
        $sql = "SELECT ltcc.commentby as userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                  JOIN {learningtimecheck} ltc ON ltc.id = cm.instance
                  JOIN {learningtimecheck_item} ltci ON ltci.learningtimecheck = ltc.id
                  JOIN {learningtimecheck_comment} ltcc ON ltcc.itemid = ltci.id
                 WHERE cm.id = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        $learningtimecheck = $DB->get_record('learningtimecheck', ['id' => $cm->instance]);

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['learningtimecheckid' => $learningtimecheck->id], $userinparams);

        // get item ids from users to be deleted.
        $deleteditems = $DB->get_records_select('learningtimecheck_item', "learningtimecheck = :learningtimecheckid AND userid {$userinsql}", $params, 'id', 'id,id');
        list($iteminsql, $iteminparams) = $DB->get_in_or_equal(array_keys($deleteditems), SQL_PARAMS_NAMED);

        $DB->delete_records_select('learningtimecheck_item', "learningtimecheck = :learningtimecheckid AND userid {$userinsql}", $params);

        // Delete all checks and comments for those items
        $DB->delete_records_select('learningtimecheck_check', "itemid ($iteminsql)", $iteminparams);
        $DB->delete_records_select('learningtimecheck_comment', "itemid ($iteminsql)", $iteminparams);

        // Get non owned items.
        $params = ['learningtimecheckid' => $learningtimecheck->id];
        $unowneditems = $DB->get_records_select('learningtimecheck_item', "learningtimecheck = :learningtimecheckid AND userid = 0", $params, 'id', 'id,id');
        list($iteminsql, $iteminparams) = $DB->get_in_or_equal(array_keys($unowneditems), SQL_PARAMS_NAMED);

        // Delete data in those items for those users.
        $params = array_merge($iteminparams, $userinparams);
        $DB->delete_records_select('learningtimecheck_check', "itemid ($iteminsql) AND userid {$userinsql}", $params);
        $DB->delete_records_select('learningtimecheck_comment', "itemid ($iteminsql) AND userid {$userinsql}", $params);
        $DB->delete_records_select('learningtimecheck_comment', "itemid ($iteminsql) AND commentby {$userinsql}", $params);

        // TOOD : examine what to do with teacher marks...should they be reset, changing checking results ? my opinion is "no".
        // Marking is historical, not related to ownership.
    }
}
