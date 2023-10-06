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
require_once($CFG->dirroot.'/local/vflibs/jqplotlib.php');

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('jqplot', 'local_vflibs');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or.
$learningtimecheckid  = optional_param('l', 0, PARAM_INT);  // Learningtimecheck instance ID.
$scale = optional_param('scale', 'days', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$action = optional_param('what', '', PARAM_ALPHA);

$url = new moodle_url('/mod/learningtimecheck/learningvelocities.php', array('id' => $id, 'l' => $learningtimecheckid));

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

$PAGE->set_cm($cm);
$PAGE->set_activity_record($learningtimecheck);
$PAGE->set_url($url);
$PAGE->navbar->add(get_string('learningvelocities', 'learningtimecheck'));

// Security.

require_login($course, true, $cm);
require_capability('mod/learningtimecheck:updateother', $context);

$chk = new learningtimecheck_class($cm->id, 0, $learningtimecheck, $cm, $course);

if ($action) {
    include($CFG->dirroot.'/mod/learningtimecheck/learningvelocities.controller.php');
}

$perpage = 30;

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($learningtimecheck->name));

if (!empty($result)) {
    echo $OUTPUT->notification($result);
}
echo "Dernier calcul : ".userdate($learningtimecheck->lastcompiledtime);

$renderer = $PAGE->get_renderer('learningtimecheck');
$renderer->set_instance($chk);

$allusers = get_enrolled_users($context, '', 0, 'u.id');
// M4.
$fields = \core_user\fields::for_name()->excluding('id')->get_required_fields();
$users = get_enrolled_users($context, '', 0, 'u.*', 'u.id'.implode(',', $fields), $page, $perpage);

$lowesttime = learningtimecheck_get_lowest_track_time($learningtimecheck);

echo $OUTPUT->paging_bar(count($allusers), $page, $perpage, $url);

echo $renderer->learning_curves($users, $scale, $lowesttime);

echo $OUTPUT->paging_bar(count($allusers), $page, $perpage, $url);

$params = array('id' => $cm->id, 'what' => 'refreshchecks', 'sesskey' => sesskey());
$buttonurl = new moodle_url('/mod/learningtimecheck/learningvelocities.php', $params);
echo $OUTPUT->single_button($buttonurl, get_string('refresh', 'learningtimecheck'));

$buttonurl = new moodle_url('/mod/learningtimecheck/view.php', array('id' => $cm->id));
echo $OUTPUT->single_button($buttonurl, get_string('back', 'learningtimecheck'));

if ($course->format == 'page') {
    require_once($CFG->dirroot.'/course/format/page/xlib.php');
    page_print_page_format_navigation($cm);
}

echo $OUTPUT->footer();
