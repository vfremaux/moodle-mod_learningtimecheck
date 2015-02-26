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

require_once($CFG->dirroot . '/mod/learningtimecheck/backup/moodle2/backup_learningtimecheck_stepslib.php'); // Because it exists (must)
require_once($CFG->dirroot . '/mod/learningtimecheck/backup/moodle2/backup_learningtimecheck_settingslib.php'); // Because it exists (optional)

/**
 * forum backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_learningtimecheck_activity_task extends backup_activity_task {

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
        // Learningtimecheck only has one structure step.
        $this->add_step(new backup_learningtimecheck_activity_structure_step('learningtimecheck structure', 'learningtimecheck.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        // I don't think there is anything needed here (but I could be wrong)
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of learningtimechecks
        $search="/(".$base."\/mod\/learningtimecheck\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@LEARNINGTIMECHECKINDEX*$2@$', $content);

        // Link to learningtimecheck view by moduleid
        $search="/(".$base."\/mod\/learningtimecheck\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@LEARNINGTIMECHECKVIEWBYID*$2@$', $content);

        // Link to learningtimecheck view by id
        $search="/(".$base."\/mod\/learningtimecheck\/view.php\?learningtimecheck\=)([0-9]+)/";
        $content= preg_replace($search, '$@LEARNINGTIMECHECKVIEWBYINSTANCE*$2@$', $content);

        // Link to learningtimecheck report by moduleid
        $search="/(".$base."\/mod\/learningtimecheck\/report.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@LEARNINGTIMECHECKREPORTBYID*$2@$', $content);

        // Link to learningtimecheck report by id
        $search="/(".$base."\/mod\/learningtimecheck\/report.php\?learningtimecheck\=)([0-9]+)/";
        $content= preg_replace($search, '$@LEARNINGTIMECHECKREPORTBYINSTANCE*$2@$', $content);

        // Link to learningtimecheck edit by moduleid
        $search="/(".$base."\/mod\/learningtimecheck\/edit.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@LEARNINGTIMECHECKEDITBYID*$2@$', $content);

        // Link to learningtimecheck edit by id
        $search="/(".$base."\/mod\/learningtimecheck\/edit.php\?learningtimecheck\=)([0-9]+)/";
        $content= preg_replace($search, '$@LEARNINGTIMECHECKEDITBYINSTANCE*$2@$', $content);

        return $content;
    }
}
