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

require('../../../config.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/locallib.php');

// Security.

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