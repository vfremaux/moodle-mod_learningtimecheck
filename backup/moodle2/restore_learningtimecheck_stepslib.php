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

class restore_learningtimecheck_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('learningtimecheck', '/activity/learningtimecheck');
        $paths[] = new restore_path_element('learningtimecheck_item', '/activity/learningtimecheck/items/item');
        if ($userinfo) {
            $paths[] = new restore_path_element('learningtimecheck_check', '/activity/learningtimecheck/items/item/checks/check');
            $paths[] = new restore_path_element('learningtimecheck_comment', '/activity/learningtimecheck/items/item/comments/comment');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_learningtimecheck($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newid = $DB->insert_record('learningtimecheck', $data);
        $this->set_mapping('learningtimecheck', $oldid, $newid);
        $this->apply_activity_instance($newid);
    }

    protected function process_learningtimecheck_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->learningtimecheck = $this->get_new_parentid('learningtimecheck');
        if ($data->userid > 0) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }
        if ($data->groupingid > 0) {
            $data->groupingid = $this->get_mappingid('groupings', $data->groupingid);
        }
        // Update to new data structure, where 'hidden' status is stored in separate field
        if ($data->itemoptional == 3) {
            $data->itemoptional = 0;
            $data->hidden = 1;
        } else if ($data->itemoptional == 4) {
            $data->itemoptional = 2;
            $data->hidden = 1;
        }

        // Process the moduleids in the 'after_restore' function - after all the other activities have been restored.

        $newid = $DB->insert_record('learningtimecheck_item', $data);
        $this->set_mapping('learningtimecheck_item', $oldid, $newid);
    }

    protected function process_learningtimecheck_check($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->item = $this->get_new_parentid('learningtimecheck_item');
        if ($data->usertimestamp > 0) {
            $data->usertimestamp = $this->apply_date_offset($data->usertimestamp);
        }
        if ($data->teachertimestamp > 0) {
            $data->teachertimestamp = $this->apply_date_offset($data->teachertimestamp);
        }
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->teacherid) {
            $data->teacherid = $this->get_mappingid('user', $data->teacherid);
        }

        $newid = $DB->insert_record('learningtimecheck_check', $data);
        $this->set_mapping('learningtimecheck_check', $oldid, $newid);
    }

    protected function process_learningtimecheck_comment($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->itemid = $this->get_new_parentid('learningtimecheck_item');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->commentby > 0) {
            $data->commentby = $this->get_mappingid('user', $data->commentby);
        }

        $newid = $DB->insert_record('learningtimecheck_comment', $data);
        $this->set_mapping('learningtimecheck_comment', $oldid, $newid);
    }

    protected function after_execute() {

        // Add learningtimecheck related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_learningtimecheck', 'intro', null);

        /*
         * Do NOT Trye to remap CMs here. this is done in task.
         */
    }
}
