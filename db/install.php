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

function xmldb_learningtimecheck_install() {
    global $CFG;

    // Initialise global compilation horizon date at one day before installation if never installed before.
    $config = get_config('learningtimecheck');
    if (empty($config->lastcompiled)) {
        set_config('lastcompiled', time() - HOURSECS, 'learningtimecheck');
    }

    // Register zabbix indicators if installed.
    // Note will only work with report_zabbix "pro" version.
    // This call is only a wrapper.
    if (is_dir($CFG->dirroot.'/report/zabbix')) {
        include_once($CFG->dirroot.'/report/zabbix/xlib.php');
        report_zabbix_register_plugin('local', 'vmoodle');
    }

}
