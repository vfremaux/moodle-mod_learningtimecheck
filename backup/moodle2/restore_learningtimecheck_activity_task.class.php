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

require_once($CFG->dirroot . '/mod/learningtimecheck/backup/moodle2/restore_learningtimecheck_stepslib.php'); // Because it exists (must)

/**
 * learningtimecheck restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_learningtimecheck_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_learningtimecheck_activity_structure_step('learningtimecheck_structure', 'learningtimecheck.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('learningtimecheck', array('intro'), 'learningtimecheck');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of learningtimechecks in course
        $rules[] = new restore_decode_rule('LEARNINGTIMECHECKINDEX', '/mod/learningtimecheck/index.php?id=$1', 'course');
        // learningtimecheck by cm->id and instance->id
        $rules[] = new restore_decode_rule('LEARNINGTIMECHECKVIEWBYID', '/mod/learningtimecheck/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('LEARNINGTIMECHECKVIEWBYINSTANCE', '/mod/learningtimecheck/view.php?learningtimecheck=$1', 'learningtimecheck');
        // learningtimecheck report by cm->id and instance->id
        $rules[] = new restore_decode_rule('LEARNINGTIMECHECKREPORTBYID', '/mod/learningtimecheck/report.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('LEARNINGTIMECHECKREPORTBYINSTANCE', '/mod/learningtimecheck/report.php?learningtimecheck=$1', 'learningtimecheck');
        // learningtimecheck edit by cm->id and instance->id
        $rules[] = new restore_decode_rule('LEARNINGTIMECHECKEDITBYID', '/mod/learningtimecheck/edit.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('LEARNINGTIMECHECKEDITBYINSTANCE', '/mod/learningtimecheck/edit.php?learningtimecheck=$1', 'learningtimecheck');

        return $rules;
    }

    public function after_restore() {
        global $DB;

        // Find all the items that have a 'moduleid' but are not headings and match them up to the newly-restored activities.
        $items = $DB->get_records_select('learningtimecheck_item', 'learningtimecheck = ? AND moduleid > 0 AND itemoptional <> 2', array($this->get_activityid()));

        foreach ($items as $item) {
            $moduleid = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'course_module', $item->moduleid);
            if ($moduleid) {
                // Match up the moduleid to the restored activity module.
                $DB->set_field('learningtimecheck_item', 'moduleid', $moduleid->newitemid, array('id' => $item->id));
            } else {
                // Does not match up to a restored activity module => delete the item + associated user data.
                $DB->delete_records('learningtimecheck_check', array('item' => $item->id));
                $DB->delete_records('learningtimecheck_comment', array('itemid' => $item->id));
                $DB->delete_records('learningtimecheck_item', array('id' => $item->id));
            }
        }
    }
}
