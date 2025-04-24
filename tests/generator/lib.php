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
 * Data generator class for mod_data.
 *
 * @package    mod_learningtimecheck
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_data\manager;
use mod_data\preset;

defined('MOODLE_INTERNAL') || die();


/**
 * Data generator class for mod_data.
 *
 * @package    mod_learningtimecheck
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_learningtimecheck_generator extends testing_module_generator {

    /**
     * @var int keep track of how many ltc records have been created.
     */
    protected $ltcrecordcount = 0;

    /**
     * @var int keep track of how many ltc records have been created.
     */
    protected $ltcitemcount = 0;

    /**
     * @var int keep track of how many ltc records have been created.
     */
    protected $positions = [];

    /**
     * To be called from ltc reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->ltcrecordcount = 0;
        $this->ltcitemcount = 0;
        $this->ltcitemcount = 0;

        parent::reset();
    }

    /**
     * Creates a mod_learningtimecheck instance
     *
     * @param array $record
     * @param array $options
     * @return StdClass
     */
    public function create_instance($record = null, array $options = null) {
        // Note, the parent class does not type $record to cast to array and then to object.
        $record = (object) (array) $record;

        if (!isset($record->maxgrade)) {
            $record->maxgrade = 100;
        }

        $this->ltcrecordcount++;

        return parent::create_instance((array) $record, $options);
    }

    /**
     * Creates an item for a mod_learningtimecheck instance.
     *
     * @param StdClass $record
     * @param mod_learningtimecheck $ltc
     * @return stdClass
     */
    public function create_item(stdClass $record = null, $ltc = null) {

        $record = (array) $record;

        $this->ltcitemcount++;

        if (!isset($ltc->id)) {
            throw new coding_exception('learningtimecheck must be present in phpunit_util::create_item() $ltc');
        } else {
            $record['learningtimecheck'] = $ltc->id;
        }

        if (!isset($record['itemoptional'])) {
            throw new coding_exception('itemoptional must be present in phpunit_util::create_item() $record');
        }

        if (!isset($record['displaytext'])) {
            $record['displaytext'] = "LTC Item - " . $this->ltcitemcount;
        }

        if (!array_key_exists($ltc->id, $this->positions)) {
            $this->positions[$ltc->id] = 0;
        }

        if (!isset($record['position'])) {
            $record['position'] = ++$this->positions[$ltc->id];
        }

        if (!isset($record['indent'])) {
            $record['indent'] = 0;
        }

        $record = (object) $record;

        $record->id = $DB->insert_record('learningtimecheck_item', $record);

        return $record;
    }

    /**
     * Creates a user check for a mod_learningtimecheck instance.
     *
     * @param StdClass $record
     * @param int $itemid
     * @return stdClass
     */
    public function create_check(stdClass $record = null, $itemid = 0) {

        $record = (array) $record;

        $this->ltccheckcount++;

        if (empty($itemid)) {
            throw new coding_exception('itemid must be present in phpunit_util::create_check() $itemid');
        } else {
            $record['item'] = $itemid;
        }

        if (!isset($record['itemoptional'])) {
            throw new coding_exception('itemoptional must be present in phpunit_util::create_item() $record');
        }

        if (!isset($record['displaytext'])) {
            $record['displaytext'] = "LTC Item - " . $this->ltcitemcount;
        }

        if (!array_key_exists($ltc->id, $this->positions)) {
            $this->positions[$ltc->id] = 0;
        }

        if (!isset($record['position'])) {
            $record['position'] = ++$this->positions[$ltc->id];
        }

        if (!isset($record['indent'])) {
            $record['indent'] = 0;
        }

        $record = (object) $record;

        $record->id = $DB->insert_record('learningtimecheck_check', $record);

        return $record;
    }
}
