<?php

require('../../../config.php');
require($CFG->dirroot.'/mod/learningtimecheck/locallib.php');

require_login();

$url = new moodle_url('/mod/learningtimecheck/ajax/services.php');
$PAGE->set_url($url);

$action = required_param('what', PARAM_TEXT);
$id = required_param('id', PARAM_INT); // The course module id

if (! $cm = $DB->get_record('course_modules', array('id' => $id))) {
    print_error('Course Module ID was incorrect');
}

if (! $course = $DB->get_record('course', array('id' => $cm->course) )) {
    print_error('coursemisconf');
}

if (! $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $cm->instance) )) {
    print_error('Course module is incorrect');
}

$editurl = new moodle_url('/mod/learningtimecheck/edit.php', array('id' => $id, 'sesskey' => sesskey()));
$reporturl = new moodle_url('/mod/learningtimecheck/view.php', array('id' => $id, 'view' => 'report', 'sesskey' => sesskey()));

$context = context_module::instance($id);
$PAGE->set_context($context);

$renderer = $PAGE->get_renderer('mod_learningtimecheck');
if (!$chk = new learningtimecheck_class($cm->id, 0, $learningtimecheck, $cm, $course)) {
    print_error('Bad module');
}
$renderer->set_instance($chk);

if ($action == 'getadditemform') {
    $itemid = required_param('itemid', PARAM_INT);
    $item = $DB->get_record('learningtimecheck_item', array('id' => $itemid));
    echo '<div class="learningtimecheck-newitem">';
    echo $renderer->edit_item_form($editurl, $item);
    echo '</div>';
}
if ($action == 'geteditmeform') {
    echo $renderer->edit_me_form($item, $thispage);
    $focusitem = 'updateitembox';
    echo $renderer->cancel_item_form($thispage);

    $addatend = false;
}
if ($action == 'getfilterruleform') {
    echo $renderer->filter_rule_form($reporturl);
}