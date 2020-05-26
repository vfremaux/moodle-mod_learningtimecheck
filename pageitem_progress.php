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
 *
 * implements a hook for the page_module block to add
 * the link allowing editing the expertnote for experts
 */
defined('MOODLE_INTERNAL') || die();

function learningtimecheck_progress_set_instance(&$block, $chooseview = false) {
    global $USER, $CFG, $COURSE, $PAGE, $OUTPUT;

    $str = '';

    // Transfer content from title to content.
    $block->title = '';

    $context = context_module::instance($block->cm->id);
    $userid = $USER->id;
    $chk = new learningtimecheck_class($block->cm->id, $userid, $block->moduleinstance, $block->cm, $block->course);

    if (has_capability('mod/learningtimecheck:updateother', $context) && !$chooseview) {
        // Get standard module link and icon.
        include_once($CFG->dirroot.'/course/format/page/plugins/page_item_default.php');
        page_item_default_set_instance($block);
    } else {
        $renderer = $PAGE->get_renderer('learningtimecheck');
        $renderer->set_instance($chk);
        $checkinfo = $chk->get_items_for_user($USER, array());
        $percentcomplete = 0 + $checkinfo['mandatory']['percentcomplete'] * 100;
        $str .= '<div style="text-align:center;margin-left:auto;margin-right:auto">';
        $str .= $renderer->progressbar_thin($percentcomplete);
        $str .= '</div>';

        if ($block->moduleinstance->usetimecounterpart) {
            $completeviewstr = get_string('fullviewdeclare', 'learningtimecheck');
        } else {
            $completeviewstr = get_string('fullview', 'learningtimecheck');
        }
        $page = \format\page\course_page::get_current_page($COURSE->id, false);
        $str .= '<center>';
        $buttonurl = new moodle_url('/mod/learningtimecheck/view.php', array('id' => $block->cm->id, 'page' => $page->id));
        $str .= $OUTPUT->single_button($buttonurl, $completeviewstr);
        $str .= '</center>';
        $str .= '<br/>';
    }

    $block->content->text = $str;
    return true;
}
