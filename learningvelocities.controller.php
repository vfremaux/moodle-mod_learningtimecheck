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
defined('MOODLE_INTERNAL') || die();

if ($action == 'refreshchecks') {
    require_sesskey();

    $DB->set_field('learningtimecheck', 'lastcompiledtime', 0, array('id' => $learningtimecheck->id));
    // Reset also "in memory" records.
    $chk->learningtimecheck->lastcompiledtime = 0;
    $learningtimecheck->lastcompiledtime = 0;
    $items = $DB->get_records_menu('learningtimecheck_item', array('learningtimecheck' => $learningtimecheck->id), 'id,id');
    if ($items) {
        $itemlist = array_keys($items);
        $DB->delete_records_list('learningtimecheck_check', 'item', $itemlist);
        $chk->update_all_autoupdate_checks();
        $result = get_string('checksrefreshed', 'learningtimecheck');
    }
}