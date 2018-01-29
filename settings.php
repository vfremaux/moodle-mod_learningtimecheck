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

/**
 * Global settings for the learningtimecheck
 */

require_once($CFG->dirroot.'/mod/learningtimecheck/lib.php');
require_once $CFG->dirroot.'/mod/learningtimecheck/adminlib.php';

use \mod\learningtimecheck\admin_setting_configdatetime;

if ($ADMIN->fulltree) {
    $key = 'learningtimecheck/initiallymandatory';
    $label = get_string('configinitiallymandatory', 'mod_learningtimecheck');
    $desc = get_string('configinitiallymandatory_desc', 'mod_learningtimecheck');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));

    $key = 'learningtimecheck/initialcredittimeon';
    $label = get_string('configinitialcredittimeon', 'mod_learningtimecheck');
    $desc = get_string('configinitialcredittimeon_desc', 'mod_learningtimecheck');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));

    $key = 'learningtimecheck/couplecredittomandatoryoption';
    $label = get_string('configcouplecredittomandatoryoption', 'mod_learningtimecheck');
    $desc = get_string('configcouplecredittomandatoryoption_desc', 'mod_learningtimecheck');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));

    $autopopulateoptions = array (LTC_AUTOPOPULATE_NO => get_string('no'),
                                  LTC_AUTOPOPULATE_COURSE => get_string('importfromcourse', 'learningtimecheck'));

    $key = 'learningtimecheck/initialautocapture';
    $label = get_string('configinitialautocapture', 'mod_learningtimecheck');
    $desc = get_string('configinitialautocapture_desc', 'mod_learningtimecheck');
    $settings->add(new admin_setting_configselect($key, $label, $desc, LTC_AUTOPOPULATE_COURSE, $autopopulateoptions));

    $key = 'learningtimecheck/lastcompiled';
    $label = get_string('configlastcompiled', 'learningtimecheck');
    $desc = get_string('configlastcompiled_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configdatetime($key, $label, $desc, ''));

    $settings->add(new admin_setting_heading('h0', get_string('configmy', 'learningtimecheck'), ''));

    $key = 'learningtimecheck/showmymoodle';
    $label = get_string('configshowmymoodle', 'mod_learningtimecheck');
    $desc = get_string('configshowmymoodle_desc', 'mod_learningtimecheck');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));

    $key = 'learningtimecheck/showcompletemymoodle';
    $label = get_string('configshowcompletemymoodle', 'mod_learningtimecheck');
    $desc = get_string('configshowcompletemymoodle_desc', 'mod_learningtimecheck');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));

    $settings->add(new admin_setting_heading('h1', get_string('configcsvformat', 'learningtimecheck'), ''));

    $csvfieldseparatoroptions = array (',' => ',',
                                  ';' => ';',
                                  ':' => ':',
                                  'TAB' => 'Tab');
    $key = 'learningtimecheck/csvfieldseparator';
    $label = get_string('configcsvfieldseparator', 'mod_learningtimecheck');
    $desc = get_string('configcsvfieldseparator_desc', 'mod_learningtimecheck');
    $settings->add(new admin_setting_configselect($key, $label, $desc, ';', $csvfieldseparatoroptions));

    $csvlineseparatoroptions = array ('LF' => 'LF',
                                  'CR' => 'CR',
                                  'CRLF' => 'CRLF');
    $key = 'learningtimecheck/csvlineseparator';
    $label = get_string('configcsvlineseparator', 'mod_learningtimecheck');
    $desc = get_string('configcsvlineseparator_desc', 'mod_learningtimecheck');
    $settings->add(new admin_setting_configselect($key, $label, $desc, '\n',$csvlineseparatoroptions));
 
    $csvencodingoptions = array ('ISO-8859-1' => 'ISO-8859-1',
                                  'UTF-8' => 'UTF-8',
                                  'HTML' => 'HTML - Debugging');
    $key = 'learningtimecheck/csvencoding';
    $label = get_string('configcsvencoding', 'mod_learningtimecheck');
    $desc = get_string('configcsvencoding_desc', 'mod_learningtimecheck');
    $settings->add(new admin_setting_configselect($key, $label, $desc, 'UTF-8', $csvencodingoptions));

    if (is_dir($CFG->dirroot.'/blocks/use_stats')) {
        $settings->add(new admin_setting_heading('h2', get_string('configusestatscoupling', 'learningtimecheck'), ''));

        $key = 'learningtimecheck/integrateusestats';
        $label = get_string('configintegrateusestats', 'mod_learningtimecheck');
        $desc = get_string('configintegrateusestats_desc', 'mod_learningtimecheck');
        $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));

        $key = 'learningtimecheck/allowoverrideusestats';
        $label = get_string('configallowoverrideusestats', 'mod_learningtimecheck');
        $desc = get_string('configallowoverrideusestats_desc', 'mod_learningtimecheck');
        $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));

        $key = 'learningtimecheck/strictcredits';
        $label = get_string('configstrictcredits', 'mod_learningtimecheck');
        $desc = get_string('configstrictcredits_desc', 'mod_learningtimecheck');
        $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));
    }
}