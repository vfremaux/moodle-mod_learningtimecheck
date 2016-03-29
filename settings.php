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
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

/**
 * Global settings for the learningtimecheck
 */

require_once($CFG->dirroot.'/mod/learningtimecheck/lib.php');
require_once $CFG->dirroot.'/mod/learningtimecheck/adminlib.php';

use \mod\learningtimecheck\admin_setting_configdatetime;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('learningtimecheck/initiallymandatory',
                                                    get_string('configinitiallymandatory', 'mod_learningtimecheck'),
                                                    get_string('configinitiallymandatory_desc', 'mod_learningtimecheck'), 1));

    $settings->add(new admin_setting_configcheckbox('learningtimecheck/initialcredittimeon',
                                                    get_string('configinitialcredittimeon', 'mod_learningtimecheck'),
                                                    get_string('configinitialcredittimeon_desc', 'mod_learningtimecheck'), 1));

    $autopopulateoptions = array (LEARNINGTIMECHECK_AUTOPOPULATE_NO => get_string('no'),
                                  LEARNINGTIMECHECK_AUTOPOPULATE_COURSE => get_string('importfromcourse', 'learningtimecheck'));

    $settings->add(new admin_setting_configselect('learningtimecheck/initialautocapture',
                                                    get_string('configinitialautocapture', 'mod_learningtimecheck'),
                                                    get_string('configinitialautocapture_desc', 'mod_learningtimecheck'), LEARNINGTIMECHECK_AUTOPOPULATE_COURSE, $autopopulateoptions));

    $autoupdateoptions = array (LEARNINGTIMECHECK_AUTOUPDATE_CRON_NO => get_string('no'),
                                  LEARNINGTIMECHECK_AUTOUPDATE_CRON_YES => get_string('yes'));

    $settings->add(new admin_setting_configselect('learningtimecheck/autoupdateusecron',
                                                    get_string('configautoupdateusecron', 'mod_learningtimecheck'),
                                                    get_string('configautoupdateusecron_desc', 'mod_learningtimecheck'), LEARNINGTIMECHECK_AUTOUPDATE_CRON_YES, $autoupdateoptions));

    $settings->add(new admin_setting_configcheckbox('learningtimecheck/showmymoodle',
                                                    get_string('configshowmymoodle', 'mod_learningtimecheck'),
                                                    get_string('configshowmymoodle_desc', 'mod_learningtimecheck'), 1));

    $settings->add(new admin_setting_configcheckbox('learningtimecheck/showcompletemymoodle',
                                                    get_string('configshowcompletemymoodle', 'mod_learningtimecheck'),
                                                    get_string('configshowcompletemymoodle_desc', 'mod_learningtimecheck'), 1));

    $csvfieldseparatoroptions = array (',' => ',',
                                  ';' => ';',
                                  ':' => ':',
                                  'TAB' => 'Tab');
    $settings->add(new admin_setting_configselect('learningtimecheck/csvfieldseparator',
                                                    get_string('configcsvfieldseparator', 'mod_learningtimecheck'),
                                                    get_string('configcsvfieldseparator_desc', 'mod_learningtimecheck'), ';', $csvfieldseparatoroptions));

    $csvlineseparatoroptions = array ('LF' => 'LF',
                                  'CR' => 'CR',
                                  'CRLF' => 'CRLF',
                                  );
   $settings->add(new admin_setting_configselect('learningtimecheck/csvlineseparator',
                                                    get_string('configcsvlineseparator', 'mod_learningtimecheck'),
                                                    get_string('configcsvlineseparator_desc', 'mod_learningtimecheck'), '\n',$csvlineseparatoroptions));
 
    $csvencodingoptions = array ('ISO-8859-1' => 'ISO-8859-1',
                                  'UTF-8' => 'UTF-8',
                                  'HTML' => 'HTML - Debugging',
                                  );

   $settings->add(new admin_setting_configselect('learningtimecheck/csvencoding',
                                                    get_string('configcsvencoding', 'mod_learningtimecheck'),
                                                    get_string('configcsvencoding_desc', 'mod_learningtimecheck'), 'UTF-8', $csvencodingoptions));

   $settings->add(new admin_setting_configcheckbox('learningtimecheck/couplecredittomandatoryoption',
                                                    get_string('configcouplecredittomandatoryoption', 'mod_learningtimecheck'),
                                                    get_string('configcouplecredittomandatoryoption_desc', 'mod_learningtimecheck'), 0));

    $settings->add(new admin_setting_configdatetime('learningtimecheck/lastcompiled', get_string('configlastcompiled', 'learningtimecheck'),
                       get_string('configlastcompiled_desc', 'learningtimecheck'), ''));

   $settings->add(new admin_setting_configcheckbox('learningtimecheck/integrateusestats',
                                                    get_string('configintegrateusestats', 'mod_learningtimecheck'),
                                                    get_string('configintegrateusestats_desc', 'mod_learningtimecheck'), 0));
}