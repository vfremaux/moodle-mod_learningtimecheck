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
 * This page lists all the instances of learningtimecheck in a particular course
 *
 * @author  David Smith <moodle@davosmith.co.uk>
 * @package mod/learningtimecheck
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

global $DB;

$id = required_param('id', PARAM_INT);   // course

if (! $course = $DB->get_record('course', array('id' => $id) )) {
    error('Course ID is incorrect');
}

$PAGE->set_url('/mod/learningtimecheck/index.php', array('id'=>$course->id));
require_course_login($course);
$PAGE->set_pagelayout('incourse');

add_to_log($course->id, 'learningtimecheck', 'view all', "index.php?id=$course->id", '');

/// Get all required stringsnewmodule

$strlearningtimechecks = get_string('modulenameplural', 'learningtimecheck');
$strlearningtimecheck  = get_string('modulename', 'learningtimecheck');


/// Print the header

$PAGE->navbar->add($strlearningtimechecks);
$PAGE->set_title($strlearningtimechecks);
echo $OUTPUT->header();

/// Get all the appropriate data

if (! $learningtimechecks = get_all_instances_in_course('learningtimecheck', $course)) {
    notice('There are no instances of learningtimecheck', "../../course/view.php?id=$course->id");
    die;
}

/// Print the list of instances (your module will probably extend this)

$timenow  = time();
$strname  = get_string('name');
$strweek  = get_string('week');
$strtopic = get_string('topic');
$strprogress = get_string('progress', 'learningtimecheck');

$table = new html_table();

if ($course->format == 'weeks') {
    $table->head  = array ($strweek, $strname);
    $table->align = array ('center', 'left', 'left');
} else if ($course->format == 'topics') {
    $table->head  = array ($strtopic, $strname);
    $table->align = array ('center', 'left', 'left');
} else {
    $table->head  = array ($strname);
    $table->align = array ('left', 'left');
}

$context = context_course::instance($course->id);
$canupdateown = has_capability('mod/learningtimecheck:updateown', $context);
if ($canupdateown) {
    $table->head[] = $strprogress;
}

foreach ($learningtimechecks as $learningtimecheck) {
    if (!$learningtimecheck->visible) {
        //Show dimmed if the mod is hidden
        $link = '<a class="dimmed" href="view.php?id='.$learningtimecheck->coursemodule.'">'.format_string($learningtimecheck->name).'</a>';
    } else {
        //Show normal if the mod is visible
        $link = '<a href="view.php?id='.$learningtimecheck->coursemodule.'">'.format_string($learningtimecheck->name).'</a>';
    }


    if ($course->format == 'weeks' or $course->format == 'topics') {
        $row = array ($learningtimecheck->section, $link);
    } else {
        $row = array ($link);
    }

    if ($canupdateown) {
        $row[] = learningtimecheck_class::print_user_progressbar($learningtimecheck->id, $USER->id, '300px', true, true);
    }

    $table->data[] = $row;
}

echo $OUTPUT->heading($strlearningtimechecks);
echo html_writer::table($table);

/// Finish the page

echo $OUTPUT->footer();
