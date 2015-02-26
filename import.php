<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/importexportfields.php');
require_once($CFG->libdir.'/formslib.php');

define('STATE_WAITSTART', 0);
define('STATE_INQUOTES', 1);
define('STATE_ESCAPE', 2);
define('STATE_NORMAL', 3);

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

$url = new moodle_url('/mod/learningtimecheck/import.php', array('id' => $cm->id));
$PAGE->set_url($url);
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
if (!has_capability('mod/learningtimecheck:edit', $context)) {
    error('You do not have permission to import items to this learningtimecheck');
}

$returl = new moodle_url('/mod/learningtimecheck/edit.php', array('id' => $cm->id));

class learningtimecheck_import_form extends moodleform {
    function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'formheading', get_string('import', 'learningtimecheck'));

        $mform->addElement('filepicker', 'importfile', get_string('importfile', 'learningtimecheck'), null, array('accepted_types'=>array('*.csv')));

        $this->add_action_buttons(true, get_string('import', 'learningtimecheck'));
    }
}

function cleanrow($separator, $row) {
    // Convert and $separator inside quotes into [!SEPARATOR!] (to skip it during the 'explode')
    $state = STATE_WAITSTART;
    $chars = str_split($row);
    $cleanrow = '';
    $quotes = '"';
    foreach ($chars as $char) {
        switch ($state) {
        case STATE_WAITSTART:
            if ($char == ' ' || $char == ',') { } // Still in STATE_WAITSTART
            else if ($char == '"') { $quotes = '"'; $state = STATE_INQUOTES; }
            else if ($char == "'") { $quotes = "'"; $state = STATE_INQUOTES; }
            else { $state = STATE_NORMAL; }
            break;
        case STATE_INQUOTES:
            if ($char == $quotes) { $state = STATE_NORMAL; } // End of quotes
            else if ($char == '\\') { $state = STATE_ESCAPE; continue 2; }  // Possible escaped quotes skip (for now)
            else if ($char == $separator) { $cleanrow .= '[!SEPARATOR!]'; continue 2; } // Replace $separator and continue loop
            break;
        case STATE_ESCAPE:
            // Retain escape char, unless escaping a quote character
            if ($char != $quotes) { $cleanrow .= '\\'; }
            $state = STATE_INQUOTES;
            break;
        default:
            if ($char == ',') { $state = STATE_WAITSTART; }
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

            /// Check for Macintosh OS line returns (ie file on one line), and fix
            if (ereg("\r", $filearray[0]) AND !ereg("\n", $filearray[0])) {
                $filearray = explode("\r", $filearray[0]);
            }

            $skipheading = true;
            $ok = true;
            $position = $DB->count_records('learningtimecheck_item', array('learningtimecheck' => $learningtimecheck->id, 'userid' => 0)) + 1;

            foreach ($filearray as $row) {
                if ($skipheading) {
                    $skipheading = false;
                    continue;
                }

                // Separator defined in importexportfields.php (currently ',')
                // Split $row into array $item, by $separator, but ignore $separator when it occurs within ""
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

                // $fields defined in importexportfields.php
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

                    case 'duetime':
                        $newitem->duetime = intval($itemfield);
                        if ($newitem->itemoptional < 0) {
                            $newitem->itemoptional = 0;
                        }
                        break;

                    case 'colour':
                        $allowedcolours = array('red', 'orange', 'green', 'purple', 'black');
                        $itemfield = trim(strtolower($itemfield));
                        if (!in_array($itemfield, $allowedcolours)) {
                            $itemfield = 'black';
                        }
                        $newitem->colour = $itemfield;
                        break;
                    }

                    $itemfield = next($item);
                }

                if ($newitem->displaytext) { // Don't insert items without any text in them
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

