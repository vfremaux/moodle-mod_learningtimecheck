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
 * This page prints a list of all student's results
 *
 * @author  David Smith <moodle@davosmith.co.uk>
 * @package mod/learningtimecheck
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

global $DB;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$learningtimecheckid  = optional_param('learningtimecheck', 0, PARAM_INT);  // learningtimecheck instance ID
$studentid = optional_param('studentid', false, PARAM_INT);
$action = optional_param('what', '', PARAM_TEXT);

$url = new moodle_url('/mod/learningtimecheck/report.php');

if ($id) {
    if (! $cm = get_coursemodule_from_id('learningtimecheck', $id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course) )) {
        print_error('coursemisconf');
    }

    if (! $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $cm->instance) )) {
        error('Course module is incorrect');
    }

    $url->param('id', $id);

} else if ($learningtimecheckid) {
    if (! $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $learningtimecheckid) )) {
        error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $learningtimecheck->course) )) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheck->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

    $url->param('learningtimecheck', $learningtimecheckid);

} else {
    error('You must specify a course_module ID or an instance ID');
}

$url->param('studentid', $studentid);
$PAGE->set_url($url);

require_login($course, true, $cm);

$chk = new learningtimecheck_class($cm->id, $studentid, $learningtimecheck, $cm, $course);

if ((!$chk->items) && $chk->canedit()) {
    redirect(new moodle_url('/mod/learningtimecheck/edit.php', array('id' => $cm->id)) );
}

if (!$chk->canviewreports()) {
    redirect(new moodle_url('/mod/learningtimecheck/view.php', array('id' => $cm->id)) );
}

// Call controller.
if ($action) {
    include($CFG->dirroot.'/mod/learningtimecheck/report.controller.php');
}

if ($studentid && $chk->only_view_mentee_reports()) {
    // Check this user is a mentee of the logged in user.
    if (!$this->is_mentor($this->userid)) {
        $this->userid = false;
    }
} elseif (!$chk->caneditother()) {
    $studentid = false;
}

$chk->view_header();

echo $OUTPUT->heading(format_string($learningtimecheck->name));

$renderer = $PAGE->get_renderer('learningtimecheck');
$renderer->set_instance($chk);

$renderer->view_tabs('report');

if ($studentid) {
    $renderer->view_items(true);
} else {
    add_to_log($course->id, 'learningtimecheck', 'report', 'report.php?id='.$cm->id, $learningtimecheck->name, $cm->id);
    $renderer->view_report();
}

if (learningtimecheck_course_is_page_formatted()) {
    require_once $CFG->dirroot.'/course/format/page/xlib.php';
    page_print_page_format_navigation($cm);
}

$chk->view_footer();
