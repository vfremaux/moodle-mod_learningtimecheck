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
 * Recalculates all LTC checks from a given date
 *
 * @package    mod_learningtimecheck
 * @copyright  2020 onwards Valery Fremaux (http://www.activeprolearn.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

global $CLI_VMOODLE_PRECHECK;

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);
$CLI_VMOODLE_PRECHECK = true; // Force first config to be minimal.

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');

if (!isset($CFG->dirroot)) {
    die ('$CFG->dirroot must be explicitely defined in moodle config.php for this script to be used');
}

require_once($CFG->dirroot.'/lib/clilib.php'); // Cli only functions.

$help =
    "Reset all states of learningtime check activities and recalculate.

Options:
   -H, --host                Host to play on.
   -c, --course              Process in a course. All courses if not given
   -m, --moduleinstance      Process a single course module (course is optional in this case).
   -f, --fromdate            forces the last compiled starting date.
   -h, --help                Print out this help.

Example:
\$ sudo -u www-data /usr/bin/php mod/learningtimecheck/cli/reset_ltc_states.php --course=106
\$ sudo -u www-data /usr/bin/php mod/learningtimecheck/cli/reset_ltc_states.php --moduleinstance=3233
";

list($options, $unrecognized) = cli_get_params(
    array(
        'host' => false,
        'course' => false,
        'moduleinstance' => false,
        'fromdate' => false,
        'help'    => false,
    ),
    array(
        'h' => 'help',
        'c' => 'course',
        'm' => 'moduleinstance',
        'f' => 'fromdate',
        'H' => 'host',
    )
);

if ($options['help']) {
    echo $help;
    exit(0);
}

if (!empty($options['host'])) {
    // Arms the vmoodle switching.
    echo('Arming for '.$options['host']."\n"); // Mtrace not yet available.
    define('CLI_VMOODLE_OVERRIDE', $options['host']);
}

// Replay full config whenever. If vmoodle switch is armed, will switch now config.

if (!defined('MOODLE_INTERNAL')) {
    // If we are still in precheck, this means this is NOT a VMoodle install and full setup has already run.
    // Otherwise we only have a tiny config at this location, sso run full config again forcing playing host if required.
    require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php'); // Global moodle config file.
}
echo('Config check : playing for '.$CFG->wwwroot."\n");

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/lib.php');
require_once($CFG->dirroot.'/mod/learningtimecheck/locallib.php');

if (!empty($options['moduleinstance'])) {
    $ltcid = $options['moduleinstance'];
} else {
    if (!empty($options['course'])) {
        $courseid = $options['course'];
    }
}

$ltcids = array();
if (!empty($ltcid)) {
    echo "Processing by module instance\n";
    $ltcids[] = $ltcid;
} else {

    echo "Processing by course\n";

    $courseclause = '';

    if (!empty($courseid)) {
        $courseclause = " AND course = ?";
        $params[] = $courseid;
    }

    $sql = "
        SELECT
            ltc.id
        FROM
            {learningtimecheck} ltc
        WHERE
            1 = 1
            {$courseclause}
        ORDER BY
            ltc.course
    ";

    $ltcidrecs = $DB->get_records_sql($sql, $params);
    if ($ltcidrecs) {
        foreach ($ltcidrecs as $ltcidrec) {
            $ltcids[] = $ltcidrec->id;
        }
    }
}

$cnt = count($ltcids);
echo "found $cnt learningtimechecks to process\n\n";

if (!empty($ltcids)) {
    echo "Start resetting learningtimecheck states\n";
    foreach ($ltcids as $ltcid) {
        $cm = get_coursemodule_from_instance('learningtimecheck', $ltcid);
        $learningtimecheck = $DB->get_record('learningtimecheck', ['id' => $ltcid]);
        $learningtimecheck->lastcompiledtime = 0;
        $course = $DB->get_record('course', ['id' => $learningtimecheck->course]);
        $chk = new learningtimecheck_class($cm->id, 0, $learningtimecheck, $cm, $course);
        echo "\tresetting states for LTC $ltcid in course {$course->id}\n";
        $chk->update_all_autoupdate_checks();
    }
    echo "done.\n";
}

exit(0);
