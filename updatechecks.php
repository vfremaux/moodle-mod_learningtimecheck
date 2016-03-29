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
 * Used by AJAX calls to update the learningtimecheck marks
 *
 * @author  David Smith <moodle@davosmith.co.uk>
 * @package mod/learningtimecheck
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/lib.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$learningtimecheckid  = optional_param('learningtimecheck', 0, PARAM_INT);  // learningtimecheck instance ID
$items = optional_param_array('items', false, PARAM_INT);

$url = new moodle_url('/mod/learningtimecheck/view.php');
if ($id) {
    if (! $cm = get_coursemodule_from_id('learningtimecheck', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record('course', array('id' => $cm->course) )) {
        print_error('coursemisconf');
    }
    if (! $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $cm->instance) )) {
        print_error('Course module is incorrect');
    }
    $url->param('id', $id);

} else if ($learningtimecheckid) {
    if (! $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $learningtimecheckid) )) {
        print_error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $learningtimecheck->course) )) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheck->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('learningtimecheck', $learningtimecheckid);

} else {
    error('You must specify a course_module ID or an instance ID');
}

$PAGE->set_url($url);

// Security.

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
$userid = $USER->id;
require_capability('mod/learningtimecheck:updateown', $context);

if (!confirm_sesskey()) {
    echo 'Error: invalid sesskey';
    die();
}
if (!$items || !is_array($items)) {
    echo 'Error: invalid (or missing) items list';
    die();
}
if (!empty($items)) {
    $chk = new learningtimecheck_class($cm->id, $userid, $learningtimecheck, $cm, $course);
    $chk->ajaxupdatechecks($items);
}

echo 'OK';
