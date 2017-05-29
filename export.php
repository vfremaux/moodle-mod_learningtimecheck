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
 * Exports the item list of a Learningtimecheck instance as a CSV file.
 * regardless to real users results or marking.
 *
 * @package mod_learningtimecheck
 * @category mod
 * @author  David Smith <moodle@davosmith.co.uk> as checklist
 * @author Valery Fremaux
 * @version Moodle 2.7
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
require('../../config.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/importexportfields.php');

$id = required_param('id', PARAM_INT); // Course module id.

if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
    print_error('invalidcoursemodule');
}
if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error('coursemisconf');
}
if (!$learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $cm->instance))) {
    print_error('badlearningtimecheckid', 'learningtimecheck');
}

$url = new moodle_url('/mod/learningtimecheck/export.php', array('id' => $cm->id));
$PAGE->set_url($url);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
if (!has_capability('mod/learningtimecheck:edit', $context)) {
    print_error('errornoeditcapability', 'learningtimecheck');
}

$select = "learningtimecheck = ? AND userid = 0";
$items = $DB->get_records_select('learningtimecheck_item', $select, array($learningtimecheck->id), 'position');
if (!$items) {
    print_error('noitems', 'learningtimecheck');
}

if (strpos($CFG->wwwroot, 'https://') === 0) {
    @header('Cache-Control: max-age=10');
    @header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
    @header('Pragma: ');
} else {
    // Normal http - prevent caching at all cost.
    @header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
    @header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
    @header('Pragma: no-cache');
}

$strlearningtimecheck = get_string('learningtimecheck', 'learningtimecheck');

header('Content-Type: application/download');
$downloadfilename = clean_filename("{$course->shortname} $strlearningtimecheck {$learningtimecheck->name}");
header("Content-Disposition: attachment; filename=\"$downloadfilename.csv\"\n");

// Output the headings.
echo implode($separator, $fields)."\n";

foreach ($items as $item) {
    $output = array();
    foreach ($fields as $field => $title) {
        $output[] = $item->$field;
    }
    echo implode($separator, $output)."\n";
}

