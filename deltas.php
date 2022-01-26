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
 * @version Moodle 2.7
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
require('../../config.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/lib.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/locallib.php');
require_once($CFG->dirroot.'/blocks/use_stats/xlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$learningtimecheckid  = optional_param('l', 0, PARAM_INT);  // Learningtimecheck instance ID.
$page = optional_param('page', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

$url = new moodle_url('/mod/learningtimecheck/deltas.php', array('id' => $id, 'l' => $learningtimecheckid));

if ($id) {
    if (!$cm = get_coursemodule_from_id('learningtimecheck', $id)) {
        error('Course Module ID was incorrect');
    }

    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }

    if (!$learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $cm->instance))) {
        error('Course module is incorrect');
    }

    $url->param('id', $id);

} else if ($learningtimecheckid) {
    if (!$learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $learningtimecheckid))) {
        print_error('Course module is incorrect');
    }
    if (!$course = $DB->get_record('course', array('id' => $learningtimecheck->course))) {
        print_error('coursemisconf');
    }
    if (!$cm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheck->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

    $url->param('learningtimecheck', $learningtimecheckid);

} else {
    error('You must specify a course_module ID or an instance ID');
}

$context = context_module::instance($cm->id);

$PAGE->set_url($url);
$PAGE->navbar->add(get_string('deltas', 'learningtimecheck'));
$PAGE->requires->js_call_amd('mod_learningtimecheck/deltas', 'init');

// Security.

require_login($course, true, $cm);

$allusers = get_enrolled_users($context, '', 0, 'u.id');

if (!$userid) {
    $userid = array_keys($allusers)[0];
}

$chk = new learningtimecheck_class($cm->id, $userid, $learningtimecheck, $cm, $course);
$timeaggregate = block_use_stats_get_user_course_time($course->id, $userid);

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('learningtimecheck');
$renderer->set_instance($chk);

echo $OUTPUT->heading(format_string($learningtimecheck->name), 2);

echo $renderer->delta_cms($userid, $timeaggregate, $allusers);

$buttonurl = new moodle_url('/mod/learningtimecheck/view.php', array('id' => $cm->id));
echo $OUTPUT->single_button($buttonurl, get_string('back', 'learningtimecheck'));

if ($course->format == 'page') {
    require_once($CFG->dirroot.'/course/format/page/xlib.php');
    page_print_page_format_navigation($cm);
}
echo $OUTPUT->footer();
