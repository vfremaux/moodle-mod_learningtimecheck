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

/*
 * Global settings for the learningtimecheck
 */

require_once($CFG->dirroot.'/mod/learningtimecheck/lib.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/adminlib.php');

use \mod\learningtimecheck\admin_setting_configdatetime;

if ($ADMIN->fulltree) {
    $key = 'learningtimecheck/initiallymandatory';
    $label = get_string('configinitiallymandatory', 'learningtimecheck');
    $desc = get_string('configinitiallymandatory_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));

    $key = 'learningtimecheck/initialcredittimeon';
    $label = get_string('configinitialcredittimeon', 'learningtimecheck');
    $desc = get_string('configinitialcredittimeon_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));

    $key = 'learningtimecheck/couplecredittomandatoryoption';
    $label = get_string('configcouplecredittomandatoryoption', 'learningtimecheck');
    $desc = get_string('configcouplecredittomandatoryoption_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));

    $autopopulateoptions = array (LTC_AUTOPOPULATE_NO => get_string('no'),
                                  LTC_AUTOPOPULATE_COURSE => get_string('importfromcourse', 'learningtimecheck'));

    $key = 'learningtimecheck/initialautocapture';
    $label = get_string('configinitialautocapture', 'learningtimecheck');
    $desc = get_string('configinitialautocapture_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configselect($key, $label, $desc, LTC_AUTOPOPULATE_COURSE, $autopopulateoptions));

    $overrideoptions = array(
        LTC_OVERRIDE_CREDIT => get_string('credit', 'learningtimecheck'),
        LTC_OVERRIDE_DECLAREDOVERCREDITIFHIGHER => get_string('declaredovercreditifhigher', 'learningtimecheck'),
        LTC_OVERRIDE_DECLAREDCAPEDBYCREDIT => get_string('declaredcapedbycredit', 'learningtimecheck'),
        LTC_OVERRIDE_DECLARED => get_string('declared', 'learningtimecheck')
    );
    $key = 'learningtimecheck/declaredoverridepolicy';
    $label = get_string('configinitialdeclaredoverridepolicy', 'learningtimecheck');
    $desc = get_string('configinitialdeclaredoverridepolicy_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configselect($key, $label, $desc, LTC_OVERRIDE_CREDIT, $overrideoptions));

    $key = 'learningtimecheck/lastcompiled';
    $label = get_string('configlastcompiled', 'learningtimecheck');
    $desc = get_string('configlastcompiled_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configdatetime($key, $label, $desc, ''));

    $settings->add(new admin_setting_heading('h0', get_string('configmy', 'learningtimecheck'), ''));

    $key = 'learningtimecheck/showmymoodle';
    $label = get_string('configshowmymoodle', 'learningtimecheck');
    $desc = get_string('configshowmymoodle_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));

    $key = 'learningtimecheck/showcompletemymoodle';
    $label = get_string('configshowcompletemymoodle', 'learningtimecheck');
    $desc = get_string('configshowcompletemymoodle_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 1));

    $settings->add(new admin_setting_heading('h1', get_string('configcsvformat', 'learningtimecheck'), ''));

    $csvfieldseparatoroptions = array (',' => ',',
                                  ';' => ';',
                                  ':' => ':',
                                  'TAB' => 'Tab');
    $key = 'learningtimecheck/csvfieldseparator';
    $label = get_string('configcsvfieldseparator', 'learningtimecheck');
    $desc = get_string('configcsvfieldseparator_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configselect($key, $label, $desc, ';', $csvfieldseparatoroptions));

    $csvlineseparatoroptions = array ('LF' => 'LF',
                                  'CR' => 'CR',
                                  'CRLF' => 'CRLF');
    $key = 'learningtimecheck/csvlineseparator';
    $label = get_string('configcsvlineseparator', 'learningtimecheck');
    $desc = get_string('configcsvlineseparator_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configselect($key, $label, $desc, '\n', $csvlineseparatoroptions));

    $csvencodingoptions = array ('ISO-8859-1' => 'ISO-8859-1',
                                  'UTF-8' => 'UTF-8',
                                  'HTML' => 'HTML - Debugging');
    $key = 'learningtimecheck/csvencoding';
    $label = get_string('configcsvencoding', 'learningtimecheck');
    $desc = get_string('configcsvencoding_desc', 'learningtimecheck');
    $settings->add(new admin_setting_configselect($key, $label, $desc, 'UTF-8', $csvencodingoptions));

    if (is_dir($CFG->dirroot.'/blocks/use_stats')) {
        $settings->add(new admin_setting_heading('h2', get_string('configusestatscoupling', 'learningtimecheck'), ''));

        $key = 'learningtimecheck/integrateusestats';
        $label = get_string('configintegrateusestats', 'learningtimecheck');
        $desc = get_string('configintegrateusestats_desc', 'learningtimecheck');
        $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));

        $key = 'learningtimecheck/allowoverrideusestats';
        $label = get_string('configallowoverrideusestats', 'learningtimecheck');
        $desc = get_string('configallowoverrideusestats_desc', 'learningtimecheck');
        $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));

        $options = array(
            0 => get_string('applynever', 'learningtimecheck'),
            1 => get_string('applyifcredithigher', 'learningtimecheck'),
            2 => get_string('applyifcreditlower', 'learningtimecheck'),
            3 => get_string('applyalways', 'learningtimecheck'),
        );
        $key = 'learningtimecheck/strictcredits';
        $label = get_string('configstrictcredits', 'learningtimecheck');
        $desc = get_string('configstrictcredits_desc', 'learningtimecheck');
        $settings->add(new admin_setting_configselect($key, $label, $desc, 0, $options));
    }

    if (learningtimecheck_supports_feature('emulate/community') == 'pro') {
        include_once($CFG->dirroot.'/mod/learningtimecheck/pro/prolib.php');
        $promanager = mod_learningtimecheck\pro_manager::instance();
        $promanager->add_settings($ADMIN, $settings);
    } else {
        $label = get_string('plugindist', 'learningtimecheck');
        $desc = get_string('plugindist_desc', 'learningtimecheck');
        $settings->add(new admin_setting_heading('plugindisthdr', $label, $desc));
    }
}