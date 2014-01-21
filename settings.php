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
 * Global settings for the learningtimecheck
 *
 * @author  2012, Davo Smith <moodle@davosmith.co.uk>
 * @package mod_learningtimecheck
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('learningtimecheck/showmymoodle',
                                                    get_string('showmymoodle', 'mod_learningtimecheck'),
                                                    get_string('configshowmymoodle', 'mod_learningtimecheck'), 1));
    $settings->add(new admin_setting_configcheckbox('learningtimecheck/showcompletemymoodle',
                                                    get_string('showcompletemymoodle', 'mod_learningtimecheck'),
                                                    get_string('configshowcompletemymoodle', 'mod_learningtimecheck'), 1));
}