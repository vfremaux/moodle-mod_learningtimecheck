<?php

/**
 * @package mod-learningtimecheck
 * @author valery fremaux
 *
 * This board summarizes the tutor's coaching activity within the course
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$learningtimecheck  = optional_param('learningtimecheck', 0, PARAM_INT);  // learningtimecheck instance ID

$params = array();
if ($id) {
    $params['id'] = $id;
} else {
    $params['learningtimecheck'] = $learningtimecheckid;
}
$PAGE->set_url('/mod/learningtimecheck/coursecalibrationreport.php', $params);

if ($id) {
    if (! $cm = get_coursemodule_from_id('learningtimecheck', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }
    if (! $learningtimecheck = $DB->get_record("learningtimecheck", array("id" => $cm->instance))) {
        print_error('invalidlearningtimecheckid', 'learningtimecheck');
    }
    // move require_course_login here to use forced language for course
    // fix for MDL-6926
    require_course_login($course, true, $cm);
    $strforums = get_string("modulenameplural", "learningtimecheck");
    $strforum = get_string("modulename", "learningtimecheck");
} else if ($f) {

    if (! $learningtimecheck = $DB->get_record("learningtimecheck", array("id" => $learningtimecheckid))) {
        print_error('invalidlearningtimecheckid', 'learningtimecheck');
    }
    if (! $course = $DB->get_record("course", array("id" => $learningtimecheck->course))) {
        print_error('coursemisconf');
    }

    if (!$cm = get_coursemodule_from_instance("learningtimecheck", $chcklist->id, $course->id)) {
        print_error('missingparameter');
    }
    // move require_course_login here to use forced language for course
    // fix for MDL-6926
    require_course_login($course, true, $cm);
    $strlearningtimechecks = get_string("modulenameplural", "learningtimecheck");
    $strlearningtimecheck = get_string("modulename", "learningtimecheck");
} else {
    print_error('missingparameter');
}

$context = context_course::instance($course->id);
require_capability('mod/learningtimecheck:viewtutorboard', $context);

$chk = new learningtimecheck_class($cm->id, $USER->id, $learningtimecheck, $cm, $course);

if (!$chk->canviewtutorboard()) {
    redirect(new moodle_url('/mod/learningtimecheck/view.php', array('id' => $cm->id)));
}

$PAGE->set_title($course->fullname);
$PAGE->set_heading(format_string($learningtimecheck->name));
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'learningtimecheck'));

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('learningtimecheck');
$renderer->set_instance($chk);

$renderer->view_tabs('tutorboard');

echo $OUTPUT->heading(get_string('tutorboard', 'learningtimecheck'));

$renderer->view_tutorboard($cm);

echo $OUTPUT->footer();
