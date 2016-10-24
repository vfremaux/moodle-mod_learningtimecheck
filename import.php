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
require_once($CFG->dirroot.'/mod/learningtimecheck/importexportfields.php');
require_once($CFG->libdir.'/formslib.php');

define('STATE_WAITSTART', 0);
define('STATE_INQUOTES', 1);
define('STATE_ESCAPE', 2);
define('STATE_NORMAL', 3);

$id = required_param('id', PARAM_INT); // Course module id.

if (! $cm = get_coursemodule_from_id('learningtimecheck', $id)) {
    print_error('invalidcoursemodule');
}
if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error('coursemisconf');
}

if (! $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $cm->instance))) {
    print_error('Course module is incorrect');
}

$url = new moodle_url('/mod/learningtimecheck/import.php', array('id' => $cm->id));
$PAGE->set_url($url);

// Security.

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/learningtimecheck:edit', $context);

$returl = new moodle_url('/mod/learningtimecheck/edit.php', array('id' => $cm->id));

class learningtimecheck_import_form extends moodleform {
    function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'formheading', get_string('import', 'learningtimecheck'));

        $label = get_string('importfile', 'learningtimecheck');
        $mform->addElement('filepicker', 'importfile', $label, null, array('accepted_types' => array('*.csv')));

        $this->add_action_buttons(true, get_string('import', 'learningtimecheck'));
    }
}

function cleanrow($separator, $row) {
    // Convert and $separator inside quotes into [!SEPARATOR!] (to skip it during the 'explode').
    $state = STATE_WAITSTART;
    $chars = str_split($row);
    $cleanrow = '';
    $quotes = '"';

    foreach ($chars as $char) {
        switch ($state) {
            case STATE_WAITSTART:
                if ($char == ' ' || $char == ',') {
                    // Still in STATE_WAITSTART.
                    assert(true);
                } else if ($char == '"') {
                    $quotes = '"'; $state = STATE_INQUOTES;
                } else if ($char == "'") {
                    $quotes = "'"; $state = STATE_INQUOTES;
                } else {
                    $state = STATE_NORMAL;
                }
                break;
            case STATE_INQUOTES:
                if ($char == $quotes) {
                    // End of quotes.
                    $state = STATE_NORMAL;
                } else if ($char == '\\') {
                    // Possible escaped quotes skip (for now).
                    $state = STATE_ESCAPE;
                    continue 2;
                } else if ($char == $separator) {
                    // Replace $separator and continue loop.
                    $cleanrow .= '[!SEPARATOR!]';
                    continue 2;
                }
                break;
            case STATE_ESCAPE:
                // Retain escape char, unless escaping a quote character.
                if ($char != $quotes) {
                    $cleanrow .= '\\';
                }
                $state = STATE_INQUOTES;
                break;
            default:
                if ($char == ',') {
                    $state = STATE_WAITSTART;
                }
                break;
        }
        $cleanrow .= $char;
    }

    return $cleanrow;
}

$form = new learningtimecheck_import_form();
$defaults = new stdClass;
$defaults->id = $cm->id;

$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect($returl);
}

$errormsg = '';
if ($data = $form->get_data()) {
    $filename = $form->save_temp_file('importfile');

    if (!file_exists($filename)) {
        $errormsg = "Something went wrong with the file upload";
    } else {
        if (is_readable($filename)) {
            $filearray = file($filename);
            unlink($filename);

            // Check for Macintosh OS line returns (ie file on one line), and fix.
            if (preg_match("!\r!", $filearray[0]) && !preg_match("!\n!", $filearray[0])) {
                $filearray = explode("\r", $filearray[0]);
            }

            $skipheading = true;
            $ok = true;
            $params = array('learningtimecheck' => $learningtimecheck->id, 'userid' => 0);
            $position = $DB->count_records('learningtimecheck_item', $params) + 1;

            foreach ($filearray as $row) {
                if ($skipheading) {
                    $skipheading = false;
                    continue;
                }

                /*
                 * Separator defined in importexportfields.php (currently ',')
                 * Split $row into array $item, by $separator, but ignore $separator when it occurs within ""
                 */
                $row = cleanrow($separator, $row);
                $item = explode($separator, $row);

                if (count($item) != count($fields)) {
                    $errormsg = "Row has incorrect number of columns in it:<br />$row";
                    $ok = false;
                    break;
                }

                $itemfield = reset($item);
                $newitem = new stdClass;
                $newitem->learningtimecheck = $learningtimecheck->id;
                $newitem->position = $position++;
                $newitem->userid = 0;

                foreach ($fields as $field => $fieldtext) {
                    $itemfield = trim($itemfield);
                    if (substr($itemfield, 0, 1) == '"' && substr($itemfield, -1) == '"') {
                        $itemfield = substr($itemfield, 1, -1);
                    }
                    $itemfield = trim($itemfield);
                    $itemfield = str_replace('[!SEPARATOR!]', $separator, $itemfield);
                    switch ($field) {
                        case 'displaytext':
                            $newitem->displaytext = trim($itemfield);
                            break;

                        case 'indent':
                            $newitem->indent = intval($itemfield);
                            if ($newitem->indent < 0) {
                                $newitem->indent = 0;
                            } else if ($newitem->indent > 10) {
                                $newitem->indent = 10;
                            }
                            break;

                        case 'itemoptional':
                            $newitem->itemoptional = intval($itemfield);
                            if ($newitem->itemoptional < 0 || $newitem->itemoptional > 2) {
                                $newitem->itemoptional = 0;
                            }
                            break;
                    }

                    $itemfield = next($item);
                }

                if ($newitem->displaytext) {
                    // Don't insert items without any text in them.
                    if (!$DB->insert_record('learningtimecheck_item', $newitem)) {
                        $ok = false;
                        $errormsg = 'Unable to insert DB record for item';
                        break;
                    }
                }
            }

            if ($ok) {
                redirect($returl);
            }

        } else {
            $errormsg = "Something went wrong with the file upload";
        }
    }
}

$strlearningtimecheck = get_string('modulename', 'learningtimecheck');
$pagetitle = strip_tags($course->shortname.': '.$strlearningtimecheck.': '.format_string($learningtimecheck->name, true));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

if ($errormsg) {
    echo '<p class="error">'.$errormsg.'</p>';
}

$form->display();

echo $OUTPUT->footer();
