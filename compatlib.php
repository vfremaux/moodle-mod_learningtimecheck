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
 * Cross version compatibility functions.
 * @package mod_learningtimecheck
 * @category mod
 * @author Valery Fremaux
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
namespace mod_learningtimecheck;

defined('MOODLE_INTERNAL') || die();

class compat {

    public static function init_page($cm, $instance) {
        global $PAGE, $CFG;

        if ($CFG->branch >= 400) {
	        $PAGE->set_cm($cm);
	        $PAGE->set_activity_record($instance);
	        $PAGE->set_pagelayout('incourse');
	     }
    }

    public static function get_logreader_name() {
        return '\core\log\sql_reader';
    }

    public static function get_user_fields($prefix = 'u') {

        global $CFG;

        if ($CFG->branch < 400) {
            return $prefix.'.id,'.get_all_user_name_fields(true, $prefix);
        } else {
            if (!empty($prefix)) {
                $prefix = $prefix.'.';
            }
            $fields = $prefix.'id';
            $morefields = \core_user\fields::for_name()->with_userpic()->excluding('id')->get_required_fields();
            foreach ($morefields as &$f) {
                $f = $prefix.$f;
            }
            $fields .= ','.implode(',', $morefields);
            return $fields;
        }
    }
}