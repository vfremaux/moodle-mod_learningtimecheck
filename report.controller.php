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
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
defined('MOODLE_INTERNAL') || die();

$settings = learningtimecheck_class::get_report_settings();

if ($sortby = optional_param('sortby', false, PARAM_TEXT)) {
    $settings->sortby = $sortby;
    $chk->set_report_settings($settings);
}

$savenext = optional_param('savenext', false, PARAM_TEXT);
$viewnext = optional_param('viewnext', false, PARAM_TEXT);

if (!confirm_sesskey()) {
    print_error('invalidsesskey');
}

switch ($action) {
    case 'showprogressbars':
        $settings->showprogressbars = true;
        break;

    case 'hideprogressbars':
        $settings->showprogressbars = false;
        break;

    case 'showoptional':
        $settings->showoptional = true;
        break;

    case 'hideoptional':
        $settings->showoptional = false;
        break;

    case 'collapseheaders':
        $settings->showheaders = false;
        break;

    case 'expandheaders':
        $settings->showheaders = true;
        break;

    case 'updatechecks':
        if ($chk->caneditother() && !$viewnext) {
            $chk->updateteachermarks();
        }
        break;

    case 'updateallchecks':
        if ($chk->caneditother()) {
            $chk->updateallteachermarks();
        }
        break;

    case 'toggledates':
        $settings->showcompletiondates = !$settings->showcompletiondates;
        break;

    case 'newfilterrule':
        $rule = new StdClass;
        $rule->rule = required_param('rule', PARAM_TEXT);
        $rule->ruleop = required_param('ruleop', PARAM_TEXT);
        $rule->logop = optional_param('logop', '', PARAM_TEXT);
        $rule->datetime = required_param('datetime', PARAM_TEXT);

        if (!$rule->datetime) {
            redirect($url.'&view='.$view.'&filtererror=errornodate');
        }

        $rules = @$SESSION->learningtimecheck->filterrules;

        if ($rules) {
            // Check for having th logop.
            if (empty($rule->logop)) {
                redirect($url.'&view='.$view.'&filtererror=errornologop');
            }

            $rule->id = count($rules) + 1;
        } else {
            $rule->id = 1;
            if (!isset($SESSION->learningtimecheck)) {
                $SESSION->learningtimecheck = new Stdclass;
            }
            $SESSION->learningtimecheck->filterrules = array();
        }
        $SESSION->learningtimecheck->filterrules[$rule->id] = $rule;
        redirect($url.'&view='.$view);
        break;

    case 'deleterule':
        $ruleid = required_param('ruleid', PARAM_INT);
        if ($rules = @$SESSION->learningtimecheck->filterrules) {
            unset($rules[$ruleid]);
            if (!empty($rules)) {
                $i = 1;
                $updatedrules = array();
                foreach ($rules as $r) {
                    $r->id = $i;
                    $updatedrules[$i] = $r;
                    $i++;
                }
                $SESSION->learningtimecheck->filterrules = $updatedrules;
            } else {
                unset($SESSION->learningtimecheck->filterrules);
            }
        }
        break;
}

$chk->set_report_settings($settings);

if ($viewnext || $savenext) {
    $chk->getnextuserid();
    $chk->get_items();
}
