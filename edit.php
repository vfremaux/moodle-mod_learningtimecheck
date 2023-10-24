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

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or.
$learningtimecheckid  = optional_param('learningtimecheck', 0, PARAM_INT);  // Learningtimecheck instance ID.

$url = new moodle_url('/mod/learningtimecheck/edit.php', ['id' => $id]);
if ($id) {
    if (!$cm = get_coursemodule_from_id('learningtimecheck', $id)) {
        print_error('Course Module ID was incorrect');
    }

    if (!$course = $DB->get_record('course', array('id' => $cm->course) )) {
        print_error('coursemisconf');
    }

    if (!$learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $cm->instance) )) {
        print_error('Course module is incorrect');
    }
    $url->param('id', $id);

} else if ($learningtimecheckid) {
    if (!$learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $learningtimecheckid) )) {
        print_error('Course module is incorrect');
    }
    if (!$course = $DB->get_record('course', array('id' => $learningtimecheck->course) )) {
        print_error('coursemisconf');
    }
    if (!$cm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheck->id, $course->id)) {
        print_error('Course Module ID was incorrect');
    }
    $url->param('learningtimecheck', $learningtimecheckid);

} else {
    print_error('You must specify a course_module ID or an instance ID');
}

$context = context_module::instance($cm->id);

$PAGE->set_url($url);
require_login($course, true, $cm);

$PAGE->requires->js('/mod/learningtimecheck/js/jquery.ltcedit.js');

if (!$chk = new learningtimecheck_class($cm->id, 0, $learningtimecheck, $cm, $course)) {
    print_error('Bad module');
}

if (!$chk->canedit()) {
    redirect(new moodle_url('/mod/learningtimecheck/view.php', array('id' => $cm->id)));
}

$context = context_module::instance($cm->id);

// Trigger module viewed event.
$eventparams = array(
    'objectid' => $learningtimecheck->id,
    'context' => $context,
);

$event = \mod_learningtimecheck\event\course_module_edited::create($eventparams);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('learningtimecheck', $learningtimecheck);
$event->trigger();

require($CFG->dirroot.'/mod/learningtimecheck/edit.controller.php');

if ($learningtimecheck->autopopulate) {
    // Needs to be done again, just in case the edit actions have changed something.
    $chk->update_items_from_course();
}

$PAGE->set_title(format_string($learningtimecheck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('edit', 'learningtimecheck'));
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('jqplot', 'local_vflibs');

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('learningtimecheck');
$renderer->set_instance($chk);
$renderer->view_tabs('edit');

echo $OUTPUT->heading(get_string('editchecks', 'learningtimecheck'));

$chk->view_import_export();

$renderer->view_edit_items();

// Print a little information only if trainingsession reports are installed.
if (!empty($learningtimecheck->usetimecounterpart)) {
    if (is_dir($CFG->dirroot.'/report/trainingsessions') &&
        has_capability('mod/learningtimecheck:forceintrainingsessions', $context, $USER->id, false)) {
        echo $OUTPUT->box(get_string('enablecredit_desc', 'learningtimecheck'));
    }
}

if ($course->format == 'page') {
    require_once($CFG->dirroot.'/course/format/page/xlib.php');
    page_print_page_format_navigation($cm);
}

echo $OUTPUT->footer();
