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

defined('MOODLE_INTERNAL') || die();

/**
 * @package mod_learningtimecheck
 * @category mod
 * @author  David Smith <moodle@davosmith.co.uk> as checklist
 * @author Valery Fremaux
 * @version Moodle 2.7
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

$chk->useredit = optional_param('useredit', false, PARAM_BOOL);

if (!confirm_sesskey()) {
    error('Invalid sesskey');
}

$itemid = optional_param('itemid', 0, PARAM_INT);

switch ($action) {
    case 'updatechecks':
        $newchecks = optional_param_array('items', array(), PARAM_INT);
        $chk->updatechecks($newchecks);
        break;

    case 'teacherupdatechecks':
        $newchecks = optional_param_array('items', array(), PARAM_INT);
        $jumpnext = false;
        $jumpprev = false;

        if (optional_param('viewprev', '', PARAM_TEXT)) {
            // Do not save but direct jump to next.
            $prevuser = learningtimecheck_get_prev_user($chk, $context, required_param('studentid', PARAM_INT), 'u.lastname, u.firstname');
            $params = array('id' => $id, 'view' => 'view', 'studentid' => $prevuser->id, 'sesskey' => sesskey());
            redirect(new moodle_url('/mod/learningtimecheck/view.php', $params));
        }

        if (optional_param('viewnext', '', PARAM_TEXT)) {
            $jumpnext = true;
        } else {
            $chk->updateteachermarks();
            if (optional_param('savenext', '', PARAM_TEXT)) {
                $jumpnext = true;
            }
        }

        if ($jumpnext) {
            // Do not save but direct jump to next.
            $nextuser = learningtimecheck_get_next_user($chk, $context, required_param('studentid', PARAM_INT), 'u.lastname, u.firstname');
            $params = array('id' => $id, 'view' => 'view', 'studentid' => $nextuser->id, 'sesskey' => sesskey());
            redirect(new moodle_url('/mod/learningtimecheck/view.php', $params));
        }
        break;

    case 'startadditem':
        $chk->additemafter = $itemid;
        break;

    case 'edititem':
        if ($chk->useritems && isset($chk->useritems[$itemid])) {
            $chk->useritems[$itemid]->editme = true;
        }
        break;

    case 'additem':
        $displaytext = optional_param('displaytext', '', PARAM_TEXT);
        $displaytext .= "\n".optional_param('displaytextnote', '', PARAM_TEXT);
        $position = optional_param('position', false, PARAM_INT);
        $chk->additem($displaytext, $chk->userid, 0, $position);
        $item = $chk->get_item_at_position($position);
        if ($item) {
            $chk->additemafter = $item->id;
        }
        break;

    case 'deleteitem':
        $chk->deleteitem($itemid);
        break;

    case 'updateitem':
        $displaytext = optional_param('displaytext', '', PARAM_TEXT);
        $displaytext .= "\n".optional_param('displaytextnote', '', PARAM_TEXT);
        $chk->updateitemtext($itemid, $displaytext);
        break;

    case 'viewnext':
    case 'seeknext':
        $nextuser = learningtimecheck_get_next_user($chk, $context, required_param('studentid', PARAM_INT), 'u.lastname, u.firstname');
        $params = array('id' => $id, 'view' => 'view', 'studentid' => $nextuser->id, 'sesskey' => sesskey());
        redirect(new moodle_url('/mod/learningtimecheck/view.php', $params));

    default:
        print_error('Invalid action - "'.s($action).'"');
}

if ($action != 'updatechecks') {
    $chk->useredit = true;
}

