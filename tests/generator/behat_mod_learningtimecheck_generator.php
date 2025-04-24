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
 * Behat data generator for mod_learningtimecheck.
 *
 * @package   mod_learningtimecheck
 * @copyright 2022 Noel De Martin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_learningtimecheck_generator extends behat_generator_base {

    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'entries' => [
                'singular' => 'entry',
                'datagenerator' => 'entry',
                'required' => ['database'],
                'switchids' => ['database' => 'databaseid', 'user' => 'userid'],
            ],
            'fields' => [
                'singular' => 'field',
                'datagenerator' => 'field',
                'required' => ['database', 'type', 'name'],
                'switchids' => ['database' => 'databaseid'],
            ],
            'templates' => [
                'singular' => 'template',
                'datagenerator' => 'template',
                'required' => ['database', 'name'],
                'switchids' => ['database' => 'databaseid'],
            ],
            'presets' => [
                'singular' => 'preset',
                'datagenerator' => 'preset',
                'required' => ['database', 'name'],
                'switchids' => ['database' => 'databaseid', 'user' => 'userid'],
            ],
        ];
    }

    /**
     * Get the database id using an activity idnumber.
     *
     * @param string $idnumber
     * @return int The database id
     */
    protected function get_database_id(string $idnumber): int {
        $cm = $this->get_cm_by_activity_name('learningtimecheck', $idnumber);

        return $cm->instance;
    }

    /**
     * Add an item.
     *
     * @param array $data Item data.
     */
    public function process_check(array $data): void {
        global $DB;

        $ltcinstance = $DB->get_record('learningtimecheck', ['id' => $data['learningtimecheckid']], '*', MUST_EXIST);

        unset($data['learningtimecheckid']);
        $userid = 0;
        if (array_key_exists('userid', $data)) {
            $userid = $data['userid'];
            unset($data['userid']);
        }

        $data = array_reduce(array_keys($data), function ($checks, $itemid) use ($data, $ltcinstance) {
            global $DB;

            $checks = $DB->get_record('learningtimecheck_item', ['itemid' => $itemid, 'learningtimecheckid' => $ltcinstance->id], 'id', MUST_EXIST);

            $checks[$data->id] = $data[$itemid];

            return $checks;
        }, []);

        $this->get_data_generator()->create_item($ltcinstance, $data, 0, [], null, $userid);
    }

    /**
     * Add a field.
     *
     * @param array $data Field data.
     */
    public function process_item(array $data): void {
        global $DB;

        $ltcinstance = $DB->get_record('learningtimecheck', ['id' => $data['learningtimecheckid']], '*', MUST_EXIST);

        unset($data['learningtimecheckid']);

        $this->get_data_generator()->create_item((object) $data, $ltcinstance);
    }

    /**
     * Get the module data generator.
     *
     * @return mod_data_generator Database data generator.
     */
    protected function get_data_generator(): mod_data_generator {
        return $this->componentdatagenerator;
    }

}
