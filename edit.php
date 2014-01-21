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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

global $DB;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$learningtimecheckid  = optional_param('learningtimecheck', 0, PARAM_INT);  // learningtimecheck instance ID

$url = new moodle_url('/mod/learningtimecheck/view.php');
if ($id) {
    if (! $cm = get_coursemodule_from_id('learningtimecheck', $id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course) )) {
        error('Course is misconfigured');
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
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('learningtimecheck', $learningtimecheck->id, $course->id)) {
        error('Course Module ID was incorrect');
    }
    $url->param('learningtimecheck', $learningtimecheckid);

} else {
    error('You must specify a course_module ID or an instance ID');
}

$PAGE->set_url($url);
require_login($course, true, $cm);

if ($chk = new learningtimecheck_class($cm->id, 0, $learningtimecheck, $cm, $course)) {
    $chk->edit();
}
