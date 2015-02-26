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
 * @author  2012, Valery Fremaux <valery.fremaux@gmail.com>
 * @package mod_learningtimecheck
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('learningtimecheck/showmymoodle',
                                                    get_string('configshowmymoodle', 'mod_learningtimecheck'),
                                                    get_string('configshowmymoodledesc', 'mod_learningtimecheck'), 1));
    $settings->add(new admin_setting_configcheckbox('learningtimecheck/showcompletemymoodle',
                                                    get_string('configshowcompletemymoodle', 'mod_learningtimecheck'),
                                                    get_string('configshowcompletemymoodledesc', 'mod_learningtimecheck'), 1));

    $csvfieldseparatoroptions = array (',' => ',',
                                  ';' => ';',
                                  ':' => ':',
                                  'TAB' => 'Tab');
    $settings->add(new admin_setting_configselect('learningtimecheck/csvfieldseparator',
                                                    get_string('configcsvfieldseparator', 'mod_learningtimecheck'),
                                                    get_string('configcsvfieldseparatordesc', 'mod_learningtimecheck'), ';', $csvfieldseparatoroptions));

    $csvlineseparatoroptions = array ('LF' => 'LF',
                                  'CR' => 'CR',
                                  'CRLF' => 'CRLF',
                                  );
   $settings->add(new admin_setting_configselect('learningtimecheck/csvlineseparator',
                                                    get_string('configcsvlineseparator', 'mod_learningtimecheck'),
                                                    get_string('configcsvlineseparatordesc', 'mod_learningtimecheck'), '\n',$csvlineseparatoroptions));
 
    $csvencodingoptions = array ('ISO-8859-1' => 'ISO-8859-1',
                                  'UTF-8' => 'UTF-8',
                                  'HTML' => 'HTML - Debugging',
                                  );
   $settings->add(new admin_setting_configselect('learningtimecheck/csvencoding',
                                                    get_string('configcsvencoding', 'mod_learningtimecheck'),
                                                    get_string('configcsvencodingdesc', 'mod_learningtimecheck'), 'UTF-8', $csvencodingoptions));
}