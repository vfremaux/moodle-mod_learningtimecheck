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

class daily_ltccompletion_indicator extends zabbix_indicator {

    static $submodes = 'dailystudentmarks,dailystudentmandatorymarks,dailydistinctmodulesmarked,dailyfullcompletions,dailyteachermarks';

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

            case 'dailystudentmarks': {

                $sql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {learningtimecheck_check} ltcc
                    WHERE
                        ltcc.usertimestamp >= ?
                ";

                $horizon = time() - DAYSECS;
                $modulescomp = 0 + $DB->count_records_sql($sql, [$horizon]);

                $this->value->$submode = $modulescomp;
                break;
            }

            case 'dailystudentmandatorymarks': {

                $sql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {learningtimecheck_check} ltcc,
                        {learningtimecheck_item} ltci
                    WHERE
                        ltcc.item = ltci.id AND
                        ltci.itemoptional = 0 AND
                        ltcc.usertimestamp >= ?
                ";

                $horizon = time() - DAYSECS;
                $modulescomp = 0 + $DB->count_records_sql($sql, [$horizon]);

                $this->value->$submode = $modulescomp;
                break;
            }

            case 'dailydistinctmodulesmarked': {

                $sql = "
                    SELECT
                        COUNT(DISINCT moduleid)
                    FROM
                        {learningtimecheck_check} ltcc,
                        {learningtimecheck_item} ltci
                    WHERE
                        ltcc.item = ltci.id AND
                        ltcc.usertimestamp >= ?
                ";

                $horizon = time() - DAYSECS;
                $modulescomp = 0 + $DB->count_records_sql($sql, [$horizon]);

                $this->value->$submode = $modulescomp;
                break;
            }

            case 'dailyteachermarks': {
                $sql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {learningtimecheck_check} ltcc
                    WHERE
                        ltcc.teachertimestamp >= ?
                ";

                $horizon = time() - DAYSECS;
                $modulescomp = 0 + $DB->count_records_sql($sql, [$horizon]);

                $this->value->$submode = $modulescomp;
                break;
            }

            case 'dailyfullcompletions': {
                $horizon = time() - DAYSECS;

                $sql = "
                    SELECT
                        CONCAT(ltcc.userid, '_', ltcc.learningtimecheck) as pkey,
                        SUM(1) AS useritemstocomplete,
                        SUM(CASE WHEN ltcc.usertimestamp IS NULL THEN 0 ELSE 1 END) AS usercompleted
                    FROM
                        {learningtimecheck_check} ltcc
                    LEFT JOIN
                        {learningtimecheck_item} ltci
                    ON
                        ltcc.item = ltci.id AND
                        ltci.itemoptional = 0
                    WHERE
                        ltcc.usertimestamp >= ? OR ltcc.usertimestamp IS NULL
                    GROUP BY
                        ltcc.userid, ltcc.learningtimecheck
                ";

                $usercompletions = $DB->get_records_sql($sql, [$horizon]);
                $dailyfullcompletions = 0;
                if (!empty($usercompletions)) {
                    foreach ($usercompletions as $uc) {
                        if ($uc->useritemstocomplete == $uc->usercomplete) {
                            $dailyfullcompletions++;
                        }
                    }
                }

                $this->value->$submode = $dailyfullcompletions;
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