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
require_once($CFG->dirroot.'/report/learningtimecheck/lib.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$learningtimecheckid = optional_param('learningtimecheck', 0, PARAM_INT);  // learningtimecheck instance ID

$PAGE->requires->css('/mod/learningtimecheck/css/icons.css');
$PAGE->requires->js('/mod/learningtimecheck/teacherupdatechecks.js');
$PAGE->requires->js_call_amd('mod_learningtimecheck/report', 'init');
$PAGE->requires->js('/mod/learningtimecheck/js/jquery.easyui.min.js');
$PAGE->requires->js('/mod/learningtimecheck/js/locale/easyui-lang-'.current_language().'.js');
$PAGE->requires->css('/mod/learningtimecheck/css/default/easyui.css');

if ($id) {
    if (!$cm = get_coursemodule_from_id('learningtimecheck', $id)) {
        print_error('invalidcoursemodule');
    }

    if (!$course = $DB->get_record('course', array('id' => $cm->course) )) {
        print_error('coursemisconf');
    }

    if (!$learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $cm->instance) )) {
        print_error('badlearningtimecheckid', 'learningtimecheck');
    }
} else if ($learningtimecheckid) {
    if (! $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $learningtimecheckid) )) {
        print_error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $learningtimecheck->course) )) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheck->id, $course->id)) {
        print_error('Course Module ID was incorrect');
    }
} else {
    print_error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$PAGE->set_title($course->fullname);
$PAGE->set_heading(format_string($learningtimecheck->name));

$context = context_module::instance($cm->id);

$userid = optional_param('studentid', 0, PARAM_INT);
$action = optional_param('what', false, PARAM_TEXT);

if (has_capability('mod/learningtimecheck:viewreports', $context)) {
    // Teachers should rather default to the progress report in current case
    $view = optional_param('view', 'report', PARAM_TEXT);
} else {
    $view = optional_param('view', 'view', PARAM_TEXT);
}

$url = new moodle_url('/mod/learningtimecheck/view.php', array('id' => $id, 'learningtimecheck' => $learningtimecheckid, 'view' => $view));
$PAGE->set_url($url);

// Resolve view controllers.
if ($view == 'preview') {

    $userid = $USER->id;
    $chk = new learningtimecheck_class($cm->id, $userid, $learningtimecheck, $cm, $course);

    if ($action) {
        include($CFG->dirroot.'/mod/learningtimecheck/view.controller.php');
    }

} else if ($view == 'view' or empty($view)) {

    $view = 'view';

    // Process submits nbuttons.
    if (!empty($_POST['viewnext'])) {
        $action = 'viewnext';
    }

    if (!has_capability('mod/learningtimecheck:updateother', $context)) {
        $userid = $USER->id;
    }

    $chk = new learningtimecheck_class($cm->id, $userid, $learningtimecheck, $cm, $course);

    if ($action) {
        include($CFG->dirroot.'/mod/learningtimecheck/view.controller.php');
    }
} else if ($view == 'report') {
    $studentid = optional_param('studentid', false, PARAM_INT);
    if ($studentid && has_capability('mod/learningtimecheck:viewmenteereports', $context) && !has_capability('mod/learningtimecheck:viewreports', $context)) {
        // Check i am a mentor of this student.
        if (!learningtimecheck::is_mentor($studentid)) {
            $studentid = false;
        }
    } else if (!has_capability('mod/learningtimecheck:updateother', $context)) {
        $studentid = false;
    }

    $userid = $studentid;

    // Get for which users.
    $reportsettings = learningtimecheck_class::get_report_settings();

    // Getting users for report.
    switch ($reportsettings->sortby) {
        case 'firstdesc':
            $orderby = 'u.firstname DESC';
            break;

        case 'lastasc':
            $orderby = 'u.lastname';
            break;

        case 'lastdesc':
            $orderby = 'u.lastname DESC';
            break;

        default:
            $orderby = 'u.firstname';
            break;
    }

    $page = optional_param('page', 0, PARAM_INT);
    $perpage = optional_param('perpage', 20, PARAM_INT);
    $totalusers = 0;
    $forusers = learningtimecheck_get_report_users($cm, $page, $perpage, $orderby, $totalusers);

    $chk = new learningtimecheck_class($cm->id, $userid, $learningtimecheck, $cm, $course, $forusers);

    if (!empty($action)) {
        include($CFG->dirroot.'/mod/learningtimecheck/report.controller.php');
    }
}

// Redirect to itemlist edition if empty learningtimecheck and have edtion capabilities.
if ((!$chk->items) && $chk->canedit()) {
    redirect(new moodle_url('/mod/learningtimecheck/edit.php', array('id' => $cm->id)) );
}

// Check and cache once the first access time to get it faster. This will accelearate progressively
// all reports.
if (!has_capability('mod/learningtimecheck:viewreports', $context, $userid)) {
    report_learningtimecheck::get_first_course_log($userid, $course->id);
}

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('learningtimecheck');
$renderer->set_instance($chk);
$renderer->view_tabs($view);

// Trigger module viewed event.
$eventparams = array(
    'objectid' => $learningtimecheck->id,
    'context' => $context,
);

$event = \mod_learningtimecheck\event\course_module_viewed::create($eventparams);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('learningtimecheck', $learningtimecheck);
$event->trigger();

switch ($view) {
    case 'view':
        $seeother = ($USER->id != $userid);
        if (!$seeother) {
            echo $OUTPUT->heading(get_string('myprogress', 'learningtimecheck'));
        }
        echo $renderer->view_items($seeother, true);
        break;

    case 'preview':
        echo $OUTPUT->heading(get_string('listpreview', 'learningtimecheck'));
        echo $renderer->view_items(false, false);
        break;

    case 'report':
        echo $OUTPUT->heading(get_string('report', 'learningtimecheck'));
        $renderer->view_report($forusers, $totalusers);
        break;
}

// End of page.
echo '<center>';
if ($course->format != 'singleactivity') {
    if ($course->id > SITEID) {
        echo $OUTPUT->single_button(new moodle_url('/course/view.php', array('id' => $course->id)), get_string('backtocourse', 'learningtimecheck'));
    } else {
        echo $OUTPUT->single_button($CFG->wwwroot, get_string('backtosite', 'learningtimecheck'));
    }
}
echo '</center>';

if ($course->format == 'page') {
    require_once $CFG->dirroot.'/course/format/page/xlib.php';
    // No "backtocourse" print as was already printed in page
    page_print_page_format_navigation($cm->id, false);
}

echo $OUTPUT->footer();
