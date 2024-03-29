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

/**
 * Define all the backup steps that will be used by the backup_forum_activity_task
 */

/**
 * Define the complete learningtimecheck structure for backup, with file and id annotations
 */
class backup_learningtimecheck_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated

        $learningtimecheck = new backup_nested_element('learningtimecheck', array('id'), array(
            'name', 'intro', 'introformat', 'timecreated', 'timemodified', 'useritemsallowed',
            'teacheredit', 'duedatesoncalendar', 'teachercomments', 'maxgrade',
            'autopopulate', 'autoupdate', 'completionpercent', 'emailoncomplete', 'lockteachermarks', 'lockstudentinput',
            'declaredoverridepolicy', 'usetimecounterpart', 'lastcompiledtime'));

        $items = new backup_nested_element('items');

        $item = new backup_nested_element('item', array('id'),
                                          array('userid', 'displaytext', 'position', 'indent',
                                                'itemoptional', 'credittime', 'teachercredittime',
                                                'teachercredittimeperuser', 'enablecredit', 'isdeclarative',
                                                'eventid', 'colour', 'moduleid', 'hidden', 'groupingid'));

        $checks = new backup_nested_element('checks');

        $check = new backup_nested_element('check', array('id'), array(
            'userid', 'usertimestamp', 'declaredtime', 'teacherid', 'teachermark', 'teacherdeclaredtime', 'teachertimestamp'));

        $comments = new backup_nested_element('comments');

        $comment = new backup_nested_element('comment', array('id'), array(
            'userid', 'commentby', 'text'));

        // Build the tree
        $learningtimecheck->add_child($items);
        $items->add_child($item);

        $item->add_child($checks);
        $checks->add_child($check);

        $item->add_child($comments);
        $comments->add_child($comment);

        // Define sources
        $learningtimecheck->set_source_table('learningtimecheck', array('id' => backup::VAR_ACTIVITYID));

        if ($userinfo) {
            $item->set_source_table('learningtimecheck_item', array('learningtimecheck' => backup::VAR_PARENTID));
            $check->set_source_table('learningtimecheck_check', array('item' => backup::VAR_PARENTID));
            $comment->set_source_table('learningtimecheck_comment', array('itemid' => backup::VAR_PARENTID));
        } else {
            $item->set_source_sql('SELECT * FROM {learningtimecheck_item} WHERE userid = 0 AND learningtimecheck = ?', array(backup::VAR_PARENTID));
        }

        // Define id annotations
        $item->annotate_ids('user', 'userid');
        $item->annotate_ids('course_modules', 'moduleid');
        $check->annotate_ids('user', 'userid');
        $check->annotate_ids('user', 'teacherid');
        $comment->annotate_ids('user', 'userid');
        $comment->annotate_ids('user', 'commentby');

        // Define file annotations

        $learningtimecheck->annotate_files('mod_learningtimecheck', 'intro', null); // This file area hasn't itemid

        // Return the root element (forum), wrapped into standard activity structure
        return $this->prepare_activity_structure($learningtimecheck);
    }
}
