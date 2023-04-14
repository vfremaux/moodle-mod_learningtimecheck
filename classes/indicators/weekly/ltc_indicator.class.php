<?php
// This file is part of Moodle - http://moodle.org/
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
 * @author Valery Fremaux valery.fremaux@gmail.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package report_zabbix
 * @category report
 */
namespace report_zabbix\indicators;

use moodle_exception;
use coding_exception;
use StdClass;

require_once($CFG->dirroot.'/report/zabbix/classes/indicator.class.php');

class ltc_indicator extends zabbix_indicator {

    static $submodes = 'instances,overallcreditedtime,overalldeclaredtime,overallteacherdeclaredtime,overalloptionals,overallmandatories';

    public function __construct() {
        parent::__construct();
        $this->key = 'moodle.ltc';
    }

    /**
     * Return all available submodes
     * return array of strings
     */
    public function get_submodes() {
        return explode(',', self::$submodes);
    }

    /**
     * the function that contains the logic to acquire the indicator instant value.
     * @param string $submode to target an aquisition to an explicit submode, elsewhere 
     */
    public function acquire_submode($submode) {
        global $DB;

        if(!isset($this->value)) {
            $this->value = new Stdclass;
        }

        if (is_null($submode)) {
            $submode = $this->submode;
        }

        switch ($submode) {

            case 'instances': {

                $sql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {learningtimecheck} ltc,
                        {course_modules} cm,
                        {modules} m
                    WHERE
                        ltc.id = cm.instanceid AND
                        cm.module = m.id AND
                        m.name = 'learningtimecheck'
                ";

                $all = 0 + $DB->count_records_sql($sql, []);

                $this->value->$submode = $all;
                break;
            }

            case 'overallcreditedtime': {

                $sql = "
                    SELECT
                        SUM(credittime) as ct
                    FROM
                        {learningtimecheck_checks} ltcc,
                        {learningtimecheck_items} ltci
                    WHERE
                        ltcc.item = ltci.id
                ";

                $ct = 0 + $DB->get_field_sql($sql, 'ct', []);

                $this->value->$submode = $ct / HOURSECS;
                break;
            }

            case 'overalldeclaredtime': {

                $sql = "
                    SELECT
                        SUM(declaredtime) as dt
                    FROM
                        {learningtimecheck_checks} ltcc,
                        {learningtimecheck_items} ltci
                    WHERE
                        ltcc.item = ltci.id
                ";

                $dt = 0 + $DB->get_field_sql($sql, 'dt', []);

                $this->value->$submode = $dt / HOURSECS;
                break;
            }

            case 'overallteacherdeclaredtime': {

                $sql = "
                    SELECT
                        SUM(declaredtime) as tdt
                    FROM
                        {learningtimecheck_checks} ltcc,
                        {learningtimecheck_items} ltci
                    WHERE
                        ltcc.item = ltci.id
                ";

                $tdt = 0 + $DB->get_field_sql($sql, 'tdt', []);

                $this->value->$submode = $tdt / HOURSECS;
                break;
            }

            case 'overalloptionals': {

                $sql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {learningtimecheck_checks} ltcc,
                        {learningtimecheck_items} ltci,
                        {course_modules} cm,
                    WHERE
                        ltcc.item = ltci.id AND
                        ltci.itemoptional = 0 AND
                        ltci.moduleid = cm.id AND
                        (cm.deletioninprogress IS NULL || cm.deletioninprogress = 0)
                ";

                $opts = 0 + $DB->count_records_sql($sql, []);

                $this->value->$submode = $opts;
                break;
            }

            case 'overallmandatories': {

                $sql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {learningtimecheck_checks} ltcc,
                        {learningtimecheck_items} ltci,
                        {course_modules} cm,
                    WHERE
                        ltcc.item = ltci.id AND
                        ltci.itemoptional = 1 AND
                        ltci.moduleid = cm.id AND
                        (cm.deletioninprogress IS NULL || cm.deletioninprogress = 0)
                ";

                $mand = 0 + $DB->count_records_sql($sql, []);

                $this->value->$submode = $mand;
                break;
            }

            default: {
                if ($CFG->debug == DEBUG_DEVELOPER) {
                    throw new coding_exception("Indicator has a submode that is not handled in aquire_submode().");
                }
            }
        }
    }
}