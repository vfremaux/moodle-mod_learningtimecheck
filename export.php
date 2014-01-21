<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/importexportfields.php');
$id = required_param('id', PARAM_INT); // course module id

if (! $cm = get_coursemodule_from_id('learningtimecheck', $id)) {
    error('Course Module ID was incorrect');
}

if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    error('Course is misconfigured');
}

if (! $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $cm->instance))) {
    error('Course module is incorrect');
}

$url = new moodle_url('/mod/learningtimecheck/export.php', array('id' => $cm->id));
$PAGE->set_url($url);
require_login($course, true, $cm);

if ($CFG->version < 2011120100) {
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
} else {
    $context = context_module::instance($cm->id);
}
if (!has_capability('mod/learningtimecheck:edit', $context)) {
    error('You do not have permission to export items from this learningtimecheck');
}

$items = $DB->get_records_select('learningtimecheck_item', "learningtimecheck = ? AND userid = 0", array($learningtimecheck->id), 'position');
if (!$items) {
    error(get_string('noitems', 'learningtimecheck'));
}

if (strpos($CFG->wwwroot, 'https://') === 0) { //https sites - watch out for IE! KB812935 and KB316431
    @header('Cache-Control: max-age=10');
    @header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
    @header('Pragma: ');
} else { //normal http - prevent caching at all cost
    @header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
    @header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
    @header('Pragma: no-cache');
}

$strlearningtimecheck = get_string('learningtimecheck', 'learningtimecheck');

header("Content-Type: application/download\n");
$downloadfilename = clean_filename("{$course->shortname} $strlearningtimecheck {$learningtimecheck->name}");
header("Content-Disposition: attachment; filename=\"$downloadfilename.csv\"");

// Output the headings
echo implode($separator, $fields)."\n";

foreach ($items as $item) {
    $output = array();
    foreach ($fields as $field => $title) {
        $output[] = $item->$field;
    }
    echo implode($separator, $output)."\n";
}

exit;
