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
 * @author Valery Fremaux
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/report/learningtimecheck/lib.php');

// Valid even if format page not installed and not included.
use \format\page\course_page;

/**
 * Stores all the functions for manipulating a learningtimecheck
 */
define("LTC_TEXT_INPUT_WIDTH", 45);
define("LTC_OPTIONAL_NO", 0);
define("LTC_OPTIONAL_YES", 1);
define("LTC_OPTIONAL_HEADING", 2);

define("LTC_HIDDEN_NO", 0);
define("LTC_HIDDEN_MANUAL", 1);
define("LTC_HIDDEN_BYMODULE", 2);

define ('LTC_DECLARATIVE_NO', 0);
define ('LTC_DECLARATIVE_STUDENTS', 1);
define ('LTC_DECLARATIVE_TEACHERS', 2);
define ('LTC_DECLARATIVE_BOTH', 3);

define('LTC_HPAGE_SIZE', 20);

if (!function_exists('debug_trace')) {
    @include_once($CFG->dirroot.'/local/advancedperfs/debugtools.php');
    if (!function_exists('debug_trace')) {
        function debug_trace($msg, $tracelevel = 0, $label = '', $backtracelevel = 1) {
            // Fake this function if not existing in the target moodle environment.
            assert(1);
        }
        define('TRACE_ERRORS', 1); // Errors should be always traced when trace is on.
        define('TRACE_NOTICE', 3); // Notices are important notices in normal execution.
        define('TRACE_DEBUG', 5); // Debug are debug time notices that should be burried in debug_fine level when debug is ok.
        define('TRACE_DATA', 8); // Data level is when requiring to see data structures content.
        define('TRACE_DEBUG_FINE', 10); // Debug fine are control points we want to keep when code is refactored and debug needs to be reactivated.
    }
}

class learningtimecheck_class {
    public $cm;
    public $course;
    public $learningtimecheck;
    public $strlearningtimechecks;
    public $strlearningtimecheck;
    public $context;
    public $userid;
    public $items;
    public $ignoreditems;
    public $useritems;
    public $useredit;
    public $additemafter;
    public $groupings;
    public $counters = array();

    /**
     *
     * @param array $cmid
     * @param array $userid
     * @param array $learningtimecheck
     * @param array $cm
     * @param array $course
     * @param array $updateusers an array of users ids to update. Unrelated to $userid.
     */
    public function __construct($cmid = 'staticonly', $userid = 0, $learningtimecheck = null, $cm = null, $course = null, $updateusers = []) {
        global $COURSE, $DB, $CFG;

        if ($cmid == 'staticonly') {
            // Use static functions only !
            return;
        }

        $this->userid = $userid;
        $this->ignoreditems = array();

        if ($cm) {
            $this->cm = $cm;
        } else if (!$this->cm = get_coursemodule_from_id('learningtimecheck', $cmid)) {
            print_error('invalidcoursemodule');
        }

        $this->context = context_module::instance($this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else if (! $this->course = $DB->get_record('course', array('id' => $this->cm->course) )) {
            print_error('coursemisconf');
        }

        if ($learningtimecheck) {
            $this->learningtimecheck = $learningtimecheck;
        } else if (! $this->learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $this->cm->instance) )) {
            print_error('errorbadinstance', 'learningtimecheck', '', $this->cm->instance);
        }

        if (isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly && $this->learningtimecheck->autopopulate && $userid) {
            $this->groupings = self::get_user_groupings($userid, $this->course->id);
        } else {
            $this->groupings = false;
        }

        $this->strlearningtimecheck = get_string('modulename', 'learningtimecheck');
        $this->strlearningtimechecks = get_string('modulenameplural', 'learningtimecheck');
        $this->pagetitle = strip_tags($this->course->shortname.': '.$this->strlearningtimecheck.': '.format_string($this->learningtimecheck->name, true));

        $this->get_items();

        if ($this->learningtimecheck->autopopulate) {
            if ($this->course->id == $COURSE->id) {
                /*
                 * We must be very carefull here, because we may call
                 * the constructor from another course context.
                 * Some inner calls may make a mistake and mess the item list.
                 */
                $this->update_items_from_course($updateusers);
            }
        }
    }

    /**
     * PHP overloading magic to make the $PAGE->course syntax work by redirecting
     * it to the corresponding $PAGE->magic_get_course() method if there is one, and
     * throwing an exception if not.
     *
     * @param string $name property name
     * @return mixed
     */
    public function __get($name) {
        if (property_exists($this->learningtimecheck, $name)) {
            return $this->learningtimecheck->$name;
        } else {
            throw new coding_exception('Unknown property ' . $name . ' in learningtimecheck instance.');
        }
    }

    /**
     * Get an array of the items in a learningtimecheck
     *
     */
    public function get_items_from_db($showoptionals = true, $hideheadings = true) {
        global $DB;

        $params = ['learningtimecheckid' => $this->id];
        $sql = '
            SELECT
                li.*
            FROM
                {learningtimecheck_item} li
            LEFT JOIN
                {course_modules} cm
            ON
                li.moduleid = cm.id
            WHERE
                learningtimecheck = :learningtimecheckid AND
                (cm.deletioninprogress IS NULL OR cm.deletioninprogress = 0)
        ';

        $optionalclause = '';
        $optionals[] = ' itemoptional = 0 ';
        if ($showoptionals) {
            $optionals[] = ' itemoptional = 1 ';
        }

        if (!$hideheadings) {
            $optionals[] = ' itemoptional = 2 ';
        }
        $optionalclause = ' AND ('.implode(' OR ', $optionals).')';

        $orderby = " ORDER BY
                li.position
        ";

        $items = $DB->get_records_sql($sql.$optionalclause.$orderby, $params);
        return $items;
    }

    /**
     * Get an array of the items in a learningtimecheck
     *
     */
    public function get_items() {
        static $modnames = array();
        global $DB, $CFG, $COURSE;

        $modinfo = get_fast_modinfo($COURSE, $this->userid);

        $this->counters['optionals'] = 0;
        $this->counters['mandatories'] = 0;
        $this->counters['optionalschecked'] = 0;
        $this->counters['mandatorieschecked'] = 0;
        $this->counters['optionalcredittime'] = 0;
        $this->counters['mandatorycredittime'] = 0;
        $this->counters['optionalacquiredtime'] = 0;
        $this->counters['mandatoryacquiredtime'] = 0;

        // Load all shared learningtimecheck items.
        $sql = 'learningtimecheck = ? ';
        $sql .= ' AND userid = 0';
        $params = array('learningtimecheck' => $this->learningtimecheck->id, 'userid' => 0);
        $this->items = $DB->get_records('learningtimecheck_item', $params, 'position');
        // Makes sure all items are numbered sequentially, starting at 1.
        $this->update_item_positions(); // Update before filtering or positions will be messed.

        // Get user's grouping/groups info.
        $usergroups[0] = [];
        if (!empty($this->userid)) {
            $usergroups = groups_get_user_groups($COURSE->id, $this->userid);
        }

        /*
         * Experimental : Filter out module bound items the user should not see
         * filtered out modules are moved to an ignored list for other process filtering
         * we just store cmid reference (auto_populate inhibition)
         */
        foreach ($this->items as $iid => $item) {

            if ($item->itemoptional == LTC_OPTIONAL_HEADING) {
                continue;
            }

            if (!$item->moduleid) {
                continue;
            }

            try {
                $cm = $modinfo->get_cm($item->moduleid);
            } catch (Exception $e) {
                // Deleted course modules.
                // TODO : Cleanup the item list accordingly.
                if (!$cm = $DB->get_record('course_modules', array('id' => $item->moduleid))) {
                    // Safety.
                    continue;
                }
                $cm->uservisible = $cm->visible;
                if (!in_array($cm->id, $modnames)) {
                    // Cache names for performance.
                    $mname = $DB->get_field('modules', 'name', array('id' => $cm->module));
                    $modnames[$cm->id] = format_string($DB->get_field($mname, 'name', array('id' => $cm->instance)));
                }
                $cm->modname = $modnames[$cm->id];
            }

            if ($DB->get_field('course_modules', 'deletioninprogress', ['id' => $item->moduleid])) {
                unset($this->items[$iid]);
                continue;
            }

            if ($item->itemoptional == LTC_OPTIONAL_YES) {
                $this->counters['optionals']++;
            } else {
                $this->counters['mandatories']++;
            }

            if (!empty($item->credittime)) {
                if ($item->itemoptional == LTC_OPTIONAL_YES) {
                    $this->counters['optionalcredittime'] += $item->credittime;
                } else {
                    $this->counters['mandatorycredittime'] += $item->credittime;
                }
            }

            if (!$cm->visible) {
                $this->ignoreditems[$iid] = $this->items[$iid]->moduleid;
                unset($this->items[$iid]);
            }

            if (!$cm->groupingid) {
                // We check the user is in one of the groupings group or has grouping 0.
                if (!isset($usergroups[0]) && !array_key_exists($cm->groupingid, $usergroups)) {
                    echo "discard $item->id by grouping <br/>";
                    $this->ignoreditems[$iid] = $this->items[$iid]->moduleid;
                    unset($this->items[$iid]);
                }
            }

            /*
             * Why NOT check for availability ?
             *
             * Because availability is mostly temporary situation, but the course module will have to be done
             * at one moment.
             */

            if ($this->course->format == 'page') {
                require_once($CFG->dirroot.'/course/format/page/xlib.php');
                // If paged, check the module is on a visible page.
                if (!page_module_is_visible($cm, false)) {
                    if (array_key_exists($iid, $this->items)) {
                        $this->ignoreditems[$iid] = $this->items[$iid]->moduleid;
                        unset($this->items[$iid]);
                    }
                }
            }

            $modurl = new moodle_url('/mod/'.$cm->modname.'/view.php', array('id' => $cm->id));
            $item->modulelink = $modurl;
        }

        // Load student's own learningtimecheck items.
        /*
        if ($this->userid && $this->canaddown()) {
            $sql = 'learningtimecheck = ? ';
            $sql .= ' AND userid = ? ';//.$this->userid;
            $params = array('learningtimecheck' => $this->learningtimecheck->id, 'userid' => $this->userid);
            $this->useritems = $DB->get_records('learningtimecheck_item', $params, 'position, id');
        } else {
            $this->useritems = false;
        }
        */

        // Load the currently checked-off items.
        if ($this->userid) {
            $sql = '
                SELECT
                    i.id,
                    i.enablecredit,
                    c.usertimestamp,
                    c.declaredtime,
                    c.teacherdeclaredtime,
                    c.teachermark,
                    c.teachertimestamp,
                    c.teacherid
                FROM
                    {learningtimecheck_item} i
                LEFT JOIN
                    {learningtimecheck_check} c ';
            $sql .= 'ON (i.id = c.item AND c.userid = ?) WHERE i.learningtimecheck = ? ';

            $checks = $DB->get_records_sql($sql, array($this->userid, $this->learningtimecheck->id));

            foreach ($checks as $check) {
                $id = $check->id;

                if (isset($this->items[$id])) {
                    $this->items[$id]->checked = $check->usertimestamp > 0;
                    $this->items[$id]->teachermark = $check->teachermark;
                    $this->items[$id]->declaredtime = $check->declaredtime;
                    $this->items[$id]->teacherdeclaredtime = $check->teacherdeclaredtime;
                    $this->items[$id]->usertimestamp = $check->usertimestamp;
                    $this->items[$id]->teachertimestamp = $check->teachertimestamp;
                    $this->items[$id]->teacherid = $check->teacherid;
                    $this->items[$id]->enablecredit = $check->enablecredit;

                    // Calculate checked counters
                    if ($this->learningtimecheck->teacheredit == LTC_MARKING_STUDENT || $this->learningtimecheck->teacheredit == LTC_MARKING_EITHER) {
                        if (!empty($this->items[$id]->checked)) {
                            if ($this->items[$id]->itemoptional == LTC_OPTIONAL_YES) {
                                $this->counters['optionalschecked']++;
                            } else {
                                $this->counters['mandatorieschecked']++;
                            }

                            if (!empty($this->items[$id]->credittime)) {
                                if ($this->items[$id]->itemoptional == LTC_OPTIONAL_YES) {
                                    $this->counters['optionalacquiredtime'] += $item->credittime;
                                } else {
                                    $this->counters['mandatoryacquiredtime'] += $item->credittime;
                                }
                            }
                        }
                    } else {
                        // Teacher must have marked.
                        if ($this->items[$id]->teachermark == LTC_TEACHERMARK_YES) {
                            if ($this->items[$id]->itemoptional == LTC_OPTIONAL_YES) {
                                $this->counters['optionalschecked']++;
                            } else {
                                $this->counters['mandatorieschecked']++;
                            }
                        }

                        if (!empty($this->items[$id]->credittime)) {
                            if ($this->items[$id]->itemoptional == LTC_OPTIONAL_YES) {
                                $this->counters['optionalacquiredtime'] += $item->credittime;
                            } else {
                                $this->counters['mandatoryacquiredtime'] += $item->credittime;
                            }
                        }
                    }

                } else if ($this->useritems && isset($this->useritems[$id])) {
                    $this->useritems[$id]->checked = $check->usertimestamp > 0;
                    $this->useritems[$id]->usertimestamp = $check->usertimestamp;
                    // User items never have a teacher mark to go with them.
                }
            }
        }

        return $this->items;
    }

    /**
     * Get all check/marks information for a user in the current learningtimecheck.
     * @param int $userid the user ID
     * @param int $hpage if null, get all checks available. If not null, a page number to get a slice of results.
     * @param string $orderby if 'position', get them by sortorder, you may use 'usertimestamp' or 'teachertimestamp'
     */
    public function get_checks($userorid, $hpage = null, $orderby = 'position') {
        global $DB;

        if (!in_array($orderby, array('position', 'usertimestamp', 'teachertimestamp'))) {
            $orderby = 'i.position';
        }

        if ($orderby == 'position') {
            $orderby = 'i.position';
        } else if ($orderby != 'i.position') {
            $orderby = 'c.'.$orderby;
        }

        $sql = "
            SELECT
                i.id,
                i.moduleid,
                i.displaytext,
                i.itemoptional,
                i.credittime,
                i.hidden,
                c.userid,
                c.usertimestamp,
                c.declaredtime,
                c.teachermark,
                c.teacherid,
                c.teacherdeclaredtime,
                c.teachertimestamp
            FROM
                {learningtimecheck_item} i
            LEFT JOIN
                {learningtimecheck_check} c
            ON
                i.id = c.item AND
                c.userid = ?
            WHERE
                i.learningtimecheck = ? AND
                i.userid = 0
            ORDER BY
                {$orderby}
        ";

        if (is_object($userorid)) {
            $userid = $userorid->id;
        } else {
            $userid = $userorid;
        }

        if (is_null($hpage)) {
            // Get all available checks in all pages.
            $checks = $DB->get_records_sql($sql, array($userid, $this->learningtimecheck->id));
        } else {
            $offset = $hpage * LTC_HPAGE_SIZE;
            $limit = LTC_HPAGE_SIZE;
            $checks = $DB->get_records_sql($sql, array($userid, $this->learningtimecheck->id), $offset, $limit);
        }

        return $checks;
    }

    /**
     * Get all check/marks information for a user in the current learningtimecheck.
     * @param int $userid the user ID
     * @param int $hpage if null, get all checks available. If not null, a page number to get a slice of results.
     * @param string $orderby if 'position', get them by sortorder, you may use 'usertimestamp' or 'teachertimestamp'
     */
    public function get_checks_for_items($itemlist, $userorid, $orderby = 'position') {
        global $DB;

        $params = [$userorid, $this->learningtimecheck->id];

        $insql = '';
        if ($itemlist) {
            list($insql, $inparams) = $DB->get_in_or_equal($itemlist);
            foreach ($inparams as $p) {
                $params[] = $p;
            }
        }

        if (!in_array($orderby, array('position', 'usertimestamp', 'teachertimestamp'))) {
            $orderby = 'i.position';
        }

        if ($orderby == 'position') {
            $orderby = 'i.position';
        } else if ($orderby != 'i.position') {
            $orderby = 'c.'.$orderby;
        }

        $sql = "
            SELECT
                i.id,
                i.moduleid,
                i.displaytext,
                i.itemoptional,
                i.credittime,
                i.hidden,
                c.userid,
                c.usertimestamp,
                c.declaredtime,
                c.teachermark,
                c.teacherid,
                c.teacherdeclaredtime,
                c.teachertimestamp
            FROM
                {learningtimecheck_item} i
            LEFT JOIN
                {learningtimecheck_check} c
            ON
                i.id = c.item AND
                c.userid = ?
            WHERE
                i.learningtimecheck = ? AND
                i.userid = 0 AND
                i.id $insql
            ORDER BY
                {$orderby}
        ";

        if (is_object($userorid)) {
            $userid = $userorid->id;
        } else {
            $userid = $userorid;
        }

        $checks = $DB->get_records_sql($sql, $params);

        return $checks;
    }

    /**
     * Redraw this function
     * Loop through all activities / resources in course and check they
     * are in the current learningtimecheck (in the right order)
     * @param array $userlist
     */
    public function update_items_from_course($userlist = []) {
        global $DB, $CFG;
        static $reloaded = false;

        if ($reloaded) {
            return;
        }

        $reloaded = true;

        $mods = get_fast_modinfo($this->course);

        // Clean lost modules out clean them in whole course.
        $sql = "
            SELECT DISTINCT
                ltci.id,
                ltci.moduleid
            FROM
                {learningtimecheck_item} ltci
            LEFT JOIN
                {course_modules} cm
            ON
                ltci.moduleid = cm.id
            WHERE
                ltci.learningtimecheck = ? AND
                ltci.itemoptional <> ".LTC_OPTIONAL_HEADING." AND
                ((ltci.moduleid != 0 AND cm.id IS NULL) OR cm.deletioninprogress = 1)
        ";
        $lostcms = $DB->get_records_sql($sql, array($this->learningtimecheck->id));
        if (!empty($lostcms)) {
            foreach ($lostcms as $lti) {
                unset($this->items[$lti->id]);
                $params = array('id' => $lti->id, 'learningtimecheck' => $this->learningtimecheck->id);
                $DB->delete_records('learningtimecheck_item', $params);
                $DB->delete_records('learningtimecheck_check', array('item' => $lti->id));
            }
        }

        // Renumber all items in sequence.
        $this->fix_positions();

        // Now scan for new.
        if ($this->course->format != 'page') {
            $importsection = -1;
            if ($this->learningtimecheck->autopopulate == LTC_AUTOPOPULATE_SECTION) {
                foreach ($mods->get_sections() as $num => $section) {
                    if (in_array($this->cm->id, $section)) {
                        $importsection = $num;
                        break;
                    }
                }
            }
        } else {
            if (!$pageid = optional_param('page', 0, PARAM_INT)) {
                /*
                 * Do not try to update anything while current page is not
                 * strictly defined. This might be less responsive,
                 * but much safer
                 */
                $page = course_page::get_current_page($this->course->id, false);
                $importsection = $page->section;
            } else {
                $importsection = $DB->get_field('format_page', 'section', array('id' => $pageid));
            }
        }

        $nextpos = 1;
        $section = 1;
        $changes = false;
        reset($this->items);

        $groupmembersonly = isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly;

        $numsections = 1;
        if ($CFG->version >= 2012120300) {
            $courseformat = course_get_format($this->course);
            $opts = $courseformat->get_format_options();
            if (isset($opts['numsections'])) {
                $numsections = $opts['numsections'];
            }
        } else {
            $numsections = $this->course->numsections;
        }

        $sections = $mods->get_sections();
        ksort($sections);

        $keeped = array();
        $keepedindent = array();
        $currentindent = 0;

        // Filter out sections and keep only interesting ones.
        if ($this->learningtimecheck->autopopulate == LTC_AUTOPOPULATE_SECTION) {
            $keeped[$importsection] = $sections[$importsection];
            $keepedindent[$importsection] = 1;
        } else if ($this->learningtimecheck->autopopulate == LTC_AUTOPOPULATE_CURRENT_PAGE) {
            $keeped[$importsection] = $sections[$importsection];
            $importedpage = course_page::get_by_section($importsection);
            $keepedindent[$importsection] = $importedpage->get_page_depth();
        } else if ($this->learningtimecheck->autopopulate == LTC_AUTOPOPULATE_CURRENT_PAGE_AND_SUBS) {
            $importedpage = course_page::get_by_section($importsection);
            if (empty($importedpage)) {
                return;
            }
            $keeped[$importsection] = $sections[$importsection];
            $keepedindent[$importsection] = $importedpage->get_page_depth();
            $children = course_page::get_all_pages($this->course->id, 'flat', true, $importedpage->id);
            if ($children) {
                foreach ($children as $child) {
                    // Empty sections may not appear in sections.
                    if (array_key_exists($child->section, $sections)) {
                        $keeped[$child->section] = $sections[$child->section];
                        $keepedindent[$child->id] = $child->get_page_depth();
                    }
                }
            }
        } else if ($this->learningtimecheck->autopopulate == LTC_AUTOPOPULATE_CURRENT_TOP_PAGE) {
            $importedpage = course_page::get_by_section($importsection);
            $toppage = $importedpage->get_top_parent();
            $keeped[$toppage->section] = $sections[$toppage->section];
            $keepedindent[$toppage->id] = $toppage->get_page_depth();
            $children = course_page::get_all_pages($this->course->id, 'flat', true, $toppage->id);
            if ($children) {
                foreach ($children as $child) {
                    // Empty sections may not appear in sections.
                    if (array_key_exists($child->section, $sections)) {
                        $keeped[$child->section] = $sections[$child->section];
                        $keepedindent[$child->id] = $child->get_page_depth();
                    }
                }
            }
        } else {
            // Scann all course.
            if ($this->course->format != 'page') {
                $keeped = $sections;
            } else {
                $keeped = array();
                $keepedindent = array();
                course_page::get_sections($this->course->id, $keeped, $keepedindent);
            }
        }

        $config = get_config('learningtimecheck');

        foreach ($keeped as $sid => $section) {

            // We need ignore all non published modules when in page format.
            if (($this->course->format == 'page') && ($sid == 0)) {
                continue;
            }

            if ($CFG->version >= 2012120300) {
                $sectionname = $courseformat->get_section_name($sid);
            } else {
                $sectionname = get_string('section').' '.$sid;
            }

            $sqlparams = array('learningtimecheck' => $this->learningtimecheck->id,
                               'moduleid' => $sid,
                               'itemoptional' => LTC_OPTIONAL_HEADING);
            if ($headingitem = $DB->get_record('learningtimecheck_item', $sqlparams, '*', IGNORE_MULTIPLE)) {
                $headingitemid = $headingitem->id;
            }

            if (!$headingitem) {
                // If section name does not exist, create it.
                if (empty($sectionname)) {
                    $sectionname = 'Section';
                }
                $headingitemid = $this->additem($sectionname, 0, 0, $nextpos, $sid, LTC_OPTIONAL_HEADING);
            } else {
                $headingitem->displaytext = $sectionname;
                $headingitem->position = $nextpos;
                $DB->update_record('learningtimecheck_item', $headingitem);
            }

            $existingitems[$headingitemid] = true;

            // Increment for next coming modules.
            $nextpos++;
            $params = array('learningtimecheck' => $this->learningtimecheck->id, 'position' => $nextpos);
            while ($DB->get_field('learningtimecheck_item', 'moduleid', $params)) {
                $nextpos++;
                $params = array('learningtimecheck' => $this->learningtimecheck->id, 'position' => $nextpos);
            }

            foreach ($section as $cmid) {

                // Do not include this learningtimecheck (self) in the list of modules.
                if ($this->cm->id == $cmid) {
                    continue;
                }

                // Discard all label type modules.
                try {
                    // if (preg_match('/label$/', $mods->get_cm($cmid)->modname)) {
                    if ($mods->get_cm($cmid)->modname == 'label') {
                        continue;
                    }

                    /*
                     * Special case for customlabels : need check if they have some completion enabled,
                     * otherwise they will be considered as simple labels.
                     */
                    $cm = $mods->get_cm($cmid);
                    if ($cm->modname == 'customlabel') {
                        $instance = $DB->get_record('customlabel', array('id' => $cm->instance));
                        if (empty($instance->completion1enabled) &&
                                empty($instance->completion2enabled) &&
                                        empty($instance->completion3enabled)) {
                            continue;
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }

                // Do not trace certificates neither.
                if ($mods->get_cm($cmid)->modname == 'certificate') {
                    continue;
                }

                $sqlparams = array($this->learningtimecheck->id, $cmid, LTC_OPTIONAL_HEADING);

                $select = "
                    learningtimecheck = ? AND
                    moduleid = ? AND
                    itemoptional <> ?
                ";
                $cmitem = $DB->get_record_select('learningtimecheck_item', $select, $sqlparams);

                $modname = $mods->get_cm($cmid)->name;

                if ($cmitem) {
                    $existingitems[$cmitem->id] = true;

                    $cmitem->position = $nextpos;
                    $cmitem->displaytext = $modname;
                    if (($cmitem->hidden == LTC_HIDDEN_BYMODULE) && $mods->get_cm($cmid)->visible) {
                        // Course module was hidden and now is not.
                        $cmitem->hidden = LTC_HIDDEN_NO;
                    } else if (($cmitem->hidden == LTC_HIDDEN_NO) && !$mods->get_cm($cmid)->visible) {
                        // Course module is now hidden.
                        $cmitem->hidden = LTC_HIDDEN_BYMODULE;
                    }

                    $groupingid = $mods->get_cm($cmid)->groupingid;
                    if ($groupmembersonly && $groupingid && $mods->get_cm($cmid)->groupmembersonly) {
                        if ($cmitem->groupingid != $groupingid) {
                            $cmitem->groupingid = $groupingid;
                        }
                    } else {
                        if (@$cmitem->groupingid) {
                            $cmitem->groupingid = 0;
                        }
                    }

                    $DB->update_record('learningtimecheck_item', $cmitem);
                } else {
                    // This is a new module that appeared in the meanwhile.
                    $hidden = $mods->get_cm($cmid)->visible ? LTC_HIDDEN_NO : LTC_HIDDEN_BYMODULE;
                    $mandat = 0 + !@$config->initiallymandatory;
                    $itemid = $this->additem($modname, 0, @$keepedindent[$sid] + 1, $nextpos, $cmid, $mandat, $hidden);
                    $changes = true;
                    $existingitems[$itemid] = true;
                    $grouping = ($groupmembersonly && $mods->get_cm($cmid)->groupmembersonly) ? $mods->get_cm($cmid)->groupingid : 0;
                    $DB->set_field('learningtimecheck_item', 'groupingid', $grouping, array('id' => $itemid));
                }
                $nextpos++;
                $params = array('learningtimecheck' => $this->learningtimecheck->id, 'position' => $nextpos);
                while ($DB->get_field('learningtimecheck_item', 'moduleid', $params)) {
                    $nextpos++;
                    $params = array('learningtimecheck' => $this->learningtimecheck->id, 'position' => $nextpos);
                }
            }
        }

        // Delete any items that are related to activities / resources that have been deleted.
        $existingids = implode("','", array_keys($existingitems));
        $select = "
            id NOT IN ('$existingids') AND
            learningtimecheck = ?
        ";
        if ($baditems = $DB->get_records_select('learningtimecheck_item', $select, array($this->learningtimecheck->id))) {
            foreach ($baditems as $item) {
                $DB->delete_records('learningtimecheck_comment', array('itemid' => $item->id));
                $DB->delete_records('learningtimecheck_check', array('item' => $item->id));
                $DB->delete_records('learningtimecheck_item', array('id' => $item->id));
            }
        }

        $this->get_items();
        $this->update_all_autoupdate_checks($userlist);

        $eventparams = array(
            'objectid' => $this->learningtimecheck->id,
            'context' => $this->context,
        );
        $event = mod_learningtimecheck\event\items_updated::create($eventparams);
        $event->trigger();
    }

    public function removeauto() {
        if ($this->learningtimecheck->autopopulate) {
            // Still automatically populating the learningtimecheck, so don't remove the items.
            return;
        }

        if (!$this->canedit()) {
            return;
        }

        if ($this->items) {
            foreach ($this->items as $item) {
                if ($item->moduleid) {
                    $this->deleteitem($item->id);
                }
            }
        }
    }

    /**
     * Fix all positions for a learningtimecheck instance
     * @param int $learningtimecheckid
     */
    public function fix_positions() {
        global $DB;

        // Fix positions for all items.
        $position = 1;
        $params = array('learningtimecheck' => $this->learningtimecheck->id);
        if ($allitems = $DB->get_records('learningtimecheck_item', $params, 'position', 'id, position')) {
            foreach ($allitems as $item) {
                $item->position = $position;
                $DB->update_record('learningtimecheck_item', $item);
            }
        }
    }

    /**
     * Check all items are numbered sequentially from 1
     * then, move any items between $start and $end
     * the number of places indicated by $move
     * this is used for making "holes" in position sequence
     * to insert new items.
     *
     * @param $move (optional) - how far to offset the current positions
     * @oaram $start (optional) - where to start offsetting positions
     * @param $end (optional) - where to stop offsetting positions
     */
    public function update_item_positions($move=0, $start = 1, $end = false) {
        global $DB;

        $pos = 1;

        if (!$this->items) {
            return;
        }

        foreach ($this->items as $item) {
            if ($pos == $start) {
                $pos += $move;
                $start = -1;
            }
            if ($item->position != $pos) {
                $oldpos = $item->position;
                $item->position = $pos;
                $upditem = new stdClass;
                $upditem->id = $item->id;
                $upditem->position = $pos;
                $DB->update_record('learningtimecheck_item', $upditem);
                if ($oldpos == $end) {
                    break;
                }
            }
            $pos++;
        }
    }

    public function get_item_at_position($position) {
        if (!$this->items) {
            return false;
        }
        foreach ($this->items as $item) {
            if ($item->position == $position) {
                return $item;
            }
        }
        return false;
    }

    /**
     * checks if the current users is allowed to change is own marks and data.
     */
    public function canupdateown() {
        global $USER;

        return ($this->userid &&
                ($this->userid == $USER->id)) &&
                        has_capability('mod/learningtimecheck:updateown', $this->context);
    }

    public function canaddown() {
        global $USER;

        return $this->learningtimecheck->useritemsallowed &&
                (!$this->userid || ($this->userid == $USER->id)) &&
                        has_capability('mod/learningtimecheck:updateown', $this->context);
    }

    public function canpreview() {
        return has_capability('mod/learningtimecheck:preview', $this->context);
    }

    public function canedit() {
        return has_capability('mod/learningtimecheck:edit', $this->context);
    }

    public function caneditother() {
        return has_capability('mod/learningtimecheck:updateother', $this->context);
    }

    public function canviewreports() {
        return has_capability('mod/learningtimecheck:viewreports', $this->context) ||
                has_capability('mod/learningtimecheck:viewmenteereports', $this->context);
    }

    public function canviewcoursecalibrationreport() {
        return has_capability('mod/learningtimecheck:viewcoursecalibrationreport', $this->context) &&
                $this->learningtimecheck->usetimecounterpart;
    }

    public function canviewtutorboard() {
        return has_capability('mod/learningtimecheck:viewtutorboard', $this->context) &&
                $this->learningtimecheck->usetimecounterpart;
    }

    public static function only_view_mentee_reports($context) {
        return has_capability('mod/learningtimecheck:viewmenteereports', $context) &&
                !has_capability('mod/learningtimecheck:viewreports', $context);
    }

    /**
     * Test if the current user is a mentor of the passed in user id.
     */
    public static function is_mentor($userid) {
        global $USER, $DB;

        $sql = 'SELECT c.instanceid
                  FROM {role_assignments} ra
                  JOIN {context} c ON ra.contextid = c.id
                 WHERE c.contextlevel = '.CONTEXT_USER.'
                   AND ra.userid = ?
                   AND c.instanceid = ?';
        return $DB->record_exists_sql($sql, array($USER->id, $userid));
    }

    /**
     * Takes a list of userids and returns only those that the current user
     * is a mentor for (ones where the current user is assigned a role in their
     * user context)
     */
    public static function filter_mentee_users(&$users) {
        global $DB, $USER;

        $userids = array_keys($users);

        list($usql, $uparams) = $DB->get_in_or_equal($userids);
        $sql = '
            SELECT
                c.instanceid
            FROM
                {role_assignments} ra
            JOIN
                {context} c ON ra.contextid = c.id
            WHERE
                c.contextlevel = '.CONTEXT_USER.' AND
                ra.userid = ? AND
                c.instanceid '.$usql;
        $params = array_merge(array($USER->id), $uparams);
        if ($tokeep = $DB->get_fieldset_sql($sql, $params)) {
            $tokeepids = array_keys($tokeep);
            foreach ($users as $uid => $foo) {
                if (!in_array($uid, $tokeepids)) {
                    unset($users[$uid]);
                }
            }
        }
    }

    public function user_complete() {
        global $PAGE;

        $renderer = $PAGE->get_renderer('learningtimecheck');
        $renderer->set_instance($this);
        $renderer->view_items(false, true);
    }

    public function get_teachermark($itemid) {
        global $OUTPUT;

        if (!isset($this->items[$itemid])) {
            return array('', '');
        }
        switch ($this->items[$itemid]->teachermark) {
            case LTC_TEACHERMARK_YES:
                $str = get_string('teachermarkyes', 'learningtimecheck');
                return array($OUTPUT->image_url('tick_box', 'learningtimecheck'), $str);

            case LTC_TEACHERMARK_NO:
                $str = get_string('teachermarkno', 'learningtimecheck');
                return array($OUTPUT->image_url('cross_box', 'learningtimecheck'), $str);

            default:
                $str = get_string('teachermarkundecided', 'learningtimecheck');
                return array($OUTPUT->image_url('empty_box', 'learningtimecheck'), $str);
        }
    }

    public function view_import_export() {
        $importurl = new moodle_url('/mod/learningtimecheck/import.php', array('id' => $this->cm->id));
        $exporturl = new moodle_url('/mod/learningtimecheck/export.php', array('id' => $this->cm->id));

        $importstr = get_string('import', 'learningtimecheck');
        $exportstr = get_string('export', 'learningtimecheck');

        echo "<div class='learningtimecheckimportexport'>";
        echo "<a href='$importurl'>$importstr</a>&nbsp;&nbsp;&nbsp;<a href='$exporturl'>$exportstr</a>";
        echo "</div>";
    }

    public function get_total_time($ltcid) {
        global $DB;

        $params = ['learningtimecheckid' => $ltcid];
        return $DB->get_field_select('learningtimecheck_item', 'SUM(credittime)', " learningtimecheckid = ? ", $params);
    }

    public function get_accessory_time($ltcid) {
        global $DB;

        $params = ['learningtimecheckid' => $ltcid];
        return $DB->get_field_select('learningtimecheck_item', 'SUM(credittime)', " learningtimecheck = ? AND itemoptional = ".LTC_OPTIONAL_YES, $params);
    }

    public function get_mandatory_time($ltcid) {
        global $DB;

        $params = ['learningtimecheckid' => $ltcid];
        return $DB->get_field_select('learningtimecheck_item', 'SUM(credittime)', " learningtimecheck = ? AND itemoptional = ".LTC_OPTIONAL_NO, $params);
    }

    public function get_acquired_time($sqlconds, $params) {
        global $DB;

        $sql = "
            SELECT
                SUM(credittime)
            FROM
                {learningtimecheck_item} li,
                {learningtimecheck_check} lc
            WHERE
                lc.item = li.id AND
                li.learningtimecheck = :learningtimecheckid
        ";

        $params['learningtimecheckid'] = $this->learningtimecheck->id;

        if (!empty($sqlconds)) {
            $sql .= " AND $sqlconds ";
        }

        return $DB->get_field_sql($sql, $params);
    }

    /**
     * get total count in instance of items, validated items, total time and acquired time against
     * marking rules.
     * @param int $userid examined user ID
     * @param object $reportsettings some settings comming from reporting requirements (f.e. workingtime)
     * @param object $useroptions some options from the user configuration
     * @return a four component array of counts and times (in minutes)
     */
    public function get_items_for_user(&$user, $reportsettings = null, $useroptions = null) {

        $totalitems = 0;
        $totaloptionalitems = 0;

        $allchecks = $this->get_checks($user->id);

        $mandatories = array(
            'items' => 0,
            'time' => 0,
            'credittime' => 0,
            'ticked' => 0,
            'tickedtime' => 0,
            'tickedcredittime' => 0,
            'percentcomplete' => 0,
            'percenttimecomplete' => 0,
            'percentcredittimecomplete' => 0,
            'timeleft' => 0,
            'percenttimeleft' => 1,
            'firstcheckid' => 0,
            'lastcheckid' => 0);

        $optionals = array(
            'items' => 0,
            'time' => 0,
            'credittime' => 0,
            'ticked' => 0,
            'tickedtime' => 0,
            'tickedcredittime' => 0,
            'percentcomplete' => 0,
            'percenttimecomplete' => 0,
            'percentcredittimecomplete' => 0,
            'timeleft' => 0,
            'percenttimeleft' => 1,
            'firstcheckid' => 0,
            'lastcheckid' => 0);

        $firstevent = array('optionals' => 0, 'mandatories' => 0);
        $lastevent = array('optionals' => 0, 'mandatories' => 0);

        $discards = array();

        foreach ($allchecks as $checkitem) {

            // Item is hidden administratively.
            if ($checkitem->hidden) {
                $discards[] = $checkitem->id." because hidden";
                continue;
            }

            // Not "my" item.
            if (($checkitem->userid && ($checkitem->userid != $user->id))) {
                $discards[] = $checkitem->id." because user bound and not owner";
                continue;
            }

            // No headings.
            if ($checkitem->itemoptional == LTC_OPTIONAL_HEADING) {
                $discards[] = $checkitem->id." because heading";
                continue;
            }

            $checktime = $this->get_report_time($checkitem);

            // Absolute pedagogic requirement.
            if ($checkitem->itemoptional == LTC_OPTIONAL_YES) {
                $optionals['items']++;
                $optionals['time'] += $checktime;
                $optionals['credittime'] += $checkitem->credittime;
            } else {
                $mandatories['items']++;
                $mandatories['time'] += $checktime;
                $mandatories['credittime'] += $checkitem->credittime;
            }

            $checkitem->course = $this->course;

            if (!report_learningtimecheck::meet_report_conditions($checkitem, $reportsettings, $useroptions,
                                                                 $user, $idnumbernotused)) {
                $discards[] = $checkitem->id." because outside report conditions";
                continue;
            }

            $discards[] = $checkitem->id." OK";
            if ($checkitem->itemoptional == LTC_OPTIONAL_YES) {
                if ($this->is_checked($checkitem)) {
                    $optionals['ticked']++;
                    $optionals['tickedtime'] += $checktime;
                    $optionals['tickedcredittime'] += $checkitem->credittime;
                    if ($checkitem->usertimestamp > $lastevent['optionals']) {
                        $lastevent['optionals'] = $checkitem->usertimestamp;
                        $optionals['lastcheckid'] = $checkitem->id;
                    }
                    if (($checkitem->usertimestamp < $firstevent['optionals']) || !$firstevent['optionals']) {
                        $firstevent['optionals'] = $checkitem->usertimestamp;
                        $optionals['firstcheckid'] = $checkitem->id;
                    }
                }
            } else {
                if ($this->is_checked($checkitem)) {
                    $mandatories['ticked']++;
                    $mandatories['tickedtime'] += $checktime;
                    $mandatories['tickedcredittime'] += $checkitem->credittime;
                    if ($checkitem->usertimestamp > $lastevent['mandatories']) {
                        $lastevent['mandatories'] = $checkitem->usertimestamp;
                        $mandatories['lastcheckid'] = $checkitem->id;
                    }
                    if ($checkitem->usertimestamp < $firstevent['mandatories'] || !$firstevent['mandatories']) {
                        $firstevent['mandatories'] = $checkitem->usertimestamp;
                        $mandatories['firstcheckid'] = $checkitem->id;
                    }
                }
            }
        }

        $syscontext = context_system::instance();

        if (optional_param('debug', false, PARAM_BOOL)) {
            echo '<pre>';
            echo "For USERID $user->id in LTC {$this->learningtimecheck->id} in course {$this->course->id}\n\n";
            echo implode("\n", $discards);
            echo '</pre>';
        }

        if ($mandatories['items']) {
            $mandatories['percentcomplete'] = ($mandatories['items']) ? $mandatories['ticked'] / $mandatories['items'] : 0;
            $mandatories['percenttimecomplete'] = ($mandatories['time']) ? $mandatories['tickedtime'] / $mandatories['time'] : 0;
            $mandatories['percentcredittimecomplete'] = ($mandatories['credittime']) ? $mandatories['tickedcredittime'] / $mandatories['credittime'] : 0;
            $mandatories['timeleft'] = ($mandatories['time']) - $mandatories['tickedtime'];
            $mandatories['percenttimeleft'] = ($mandatories['time']) ? $mandatories['timeleft'] / $mandatories['time'] : 0;
        }

        if ($optionals['items']) {
            $optionals['percentcomplete'] = ($optionals['items']) ? $optionals['ticked'] / $optionals['items'] : 0;
            $optionals['percenttimecomplete'] = ($optionals['time']) ? $optionals['tickedtime'] / $optionals['time'] : 0;
            $optionals['percentcredittimecomplete'] = ($optionals['credittime']) ? $optionals['tickedcredittime'] / $optionals['credittime'] : 0;
            $optionals['timeleft'] = ($optionals['time']) - $optionals['tickedtime'];
            $optionals['percenttimeleft'] = ($optionals['time']) ? $optionals['timeleft'] / $optionals['time'] : 0;
        }
        return array('mandatory' => $mandatories, 'optional' => $optionals);
    }

    public function get_report_time($check) {
        switch ($this->learningtimecheck->declaredoverridepolicy) {

            case LTC_OVERRIDE_CREDIT : {
                return $check->credittime;
            }

            case LTC_OVERRIDE_DECLAREDOVERCREDITIFHIGHER : {
                if ($check->declaredtime > 0 && $check->declaredtime > $check->credittime) {
                    return $check->declaredtime;
                }
                return $check->credittime;
            }

            case LTC_OVERRIDE_DECLAREDCAPEDBYCREDIT : {
                if ($check->declaredtime > 0 && $check->declaredtime < $check->credittime) {
                    return $check->declaredtime;
                }
                return $check->credittime;
            }

            case LTC_OVERRIDE_DECLARED : {
                return $check->declaredtime;
            }

        }
    }

    /**
     * Checks if a checkitem is considered as checked and validated
     */
    public function is_checked($itemcheck) {
        if ($this->learningtimecheck->teacheredit == LTC_MARKING_STUDENT) {
            if ($itemcheck->usertimestamp) {
                return true;
            }
        } else if ($this->learningtimecheck->teacheredit == LTC_MARKING_EITHER) {
            if (!empty($itemcheck->usertimestamp) || !empty($itemcheck->teachertimestamp)) {
                return true;
            }
        } else if ($this->learningtimecheck->teacheredit == LTC_MARKING_BOTH) {
            if (!empty($itemcheck->usertimestamp) && !empty($itemcheck->teachertimestamp)) {
                return true;
            }
        } else {
            if ($itemcheck->teachertimestamp) {
                return true;
            }
        }
        return false;
    }

    public function apply_to_all($fieldname, $value) {
        global $DB;

        $sql = "
            UPDATE
                {learningtimecheck_item}
            SET
                $fieldname = '$value'
            WHERE
                learningtimecheck = ?
        ";

        $DB->execute($sql, array($this->learningtimecheck->id), false);
        redirect(new moodle_url('/mod/learningtimecheck/edit.php', array('id' => $this->cm->id)));
    }

    public static function get_report_settings() {
        global $SESSION;

        if (!isset($SESSION->learningtimecheck_report) || !is_object($SESSION->learningtimecheck_report)) {

            $settings = report_learningtimecheck::get_user_options();
            $settings['startrange'] = 0;
            $settings['endrange'] = time();
            $settings['showoptional'] = true;
            $settings['showprogressbars'] = false;
            $settings['showcompletiondates'] = false;
            $SESSION->learningtimecheck_report = (object) $settings;
        }

        $SESSION->learningtimecheck_report->sortby = optional_param('sortby', 'lastasc', PARAM_TEXT);

        // We want changes to settings to be explicit.
        return clone $SESSION->learningtimecheck_report;
    }

    public function set_report_settings($settings) {
        global $SESSION, $CFG;

        $currsettings = self::get_report_settings();
        foreach ($currsettings as $key => $currval) {
            if (isset($settings->$key)) {
                // Only set values if they already exist.
                $currsettings->$key = $settings->$key;
            }
        }
        if ($CFG->debug == DEBUG_DEVELOPER) {
            // Show dev error if attempting to set non-existent setting.
            foreach ($settings as $key => $val) {
                if (!isset($currsettings->$key)) {
                    debugging("Attempting to set invalid setting '$key'", DEBUG_DEVELOPER);
                }
            }
        }

        $SESSION->learningtimecheck_report = $currsettings;
    }

    /**
     * Add item is a high level item addition function that will care for correct position handling of the other items.
     * Items
     * @param string $displaytext the item title
     * @param int $userid If set, the item is a user item (students). Teachers only can add plain items (userid = 0).
     * @param int $indent deprecated
     * @param int $position insert this item at this position pushing all other items up
     * @param int $moduleid the related module id when autopopulated
     * @param int $optional is the item optional ? the item accepts also a special LTC_OPTIONAL_HEADING
     * case for section headings
     * @param boolean $hidden is the item used for checklist ? this allows disabling some autpopulated items that should
     * not be used at all
     */
    public function additem($displaytext, $userid = 0, $indent = 0, $position = false, $moduleid = 0,
                            $optional = LTC_OPTIONAL_NO, $hidden = LTC_HIDDEN_NO) {
        global $DB;

        $displaytext = trim($displaytext);
        if ($displaytext == '') {
            return false;
        }

        if ($userid) {
            if (!$this->canaddown()) {
                return false;
            }
        } else {
            if (!$moduleid && !$this->canedit()) {
                // Moduleid entries are added automatically, if the activity exists; ignore canedit check.
                return false;
            }
        }

        $item = new stdClass;
        $item->learningtimecheck = $this->learningtimecheck->id;
        $item->displaytext = $displaytext;
        if ($position) {
            $item->position = $position;
        } else {
            $item->position = count($this->items) + 1;
        }
        $item->indent = $indent;
        $item->userid = $userid;
        $item->itemoptional = $optional;
        $item->hidden = $hidden;
        $item->eventid = 0;
        $item->moduleid = $moduleid;
        $item->checked = false;

        $item->id = $DB->insert_record('learningtimecheck_item', $item);
        if ($item->id) {
            if ($userid) {
                $this->useritems[$item->id] = $item;
                $this->useritems[$item->id]->checked = false;
                if ($position) {
                    uasort($this->useritems, 'learningtimecheck_itemcompare');
                }
            } else {
                if ($position) {
                    $this->additemafter = $item->id;
                    $this->update_item_positions(1, $position);
                }
                $this->items[$item->id] = $item;
                $this->items[$item->id]->checked = false;
                $this->items[$item->id]->teachermark = LTC_TEACHERMARK_UNDECIDED;
                uasort($this->items, 'learningtimecheck_itemcompare');
            }
        }

        return $item->id;
    }

    public function updateitemtext($itemid, $displaytext) {
        global $DB;

        $displaytext = trim($displaytext);
        if ($displaytext == '') {
            return;
        }

        if (isset($this->items[$itemid])) {
            if ($this->canedit()) {
                $this->items[$itemid]->displaytext = $displaytext;
                $upditem = new stdClass;
                $upditem->id = $itemid;
                $upditem->displaytext = $displaytext;
                $DB->update_record('learningtimecheck_item', $upditem);
            }
        } else if (isset($this->useritems[$itemid])) {
            if ($this->canaddown()) {
                $this->useritems[$itemid]->displaytext = $displaytext;
                $upditem = new stdClass;
                $upditem->id = $itemid;
                $upditem->displaytext = $displaytext;
                $DB->update_record('learningtimecheck_item', $upditem);
            }
        }
    }

    public function toggledisableitem($itemid) {
        global $DB;

        if (isset($this->items[$itemid])) {
            if (!$this->canedit()) {
                return;
            }

            $item = $this->items[$itemid];
            if ($item->hidden == LTC_HIDDEN_NO) {
                $item->hidden = LTC_HIDDEN_MANUAL;
            } else if ($item->hidden == LTC_HIDDEN_MANUAL) {
                $item->hidden = LTC_HIDDEN_NO;
            }

            $upditem = new stdClass;
            $upditem->id = $itemid;
            $upditem->hidden = $item->hidden;
            $DB->update_record('learningtimecheck_item', $upditem);

            // If the item is a section heading, then show/hide all items in that section.
            if ($item->itemoptional == LTC_OPTIONAL_HEADING) {
                if ($item->hidden) {
                    foreach ($this->items as $it) {
                        if ($it->position <= $item->position) {
                            continue;
                        }
                        if ($it->itemoptional == LTC_OPTIONAL_HEADING) {
                            break;
                        }
                        if (!$it->moduleid) {
                            continue;
                        }
                        if ($it->hidden == LTC_HIDDEN_NO) {
                            $it->hidden = LTC_HIDDEN_MANUAL;
                            $upditem = new stdClass;
                            $upditem->id = $it->id;
                            $upditem->hidden = $it->hidden;
                            $DB->update_record('learningtimecheck_item', $upditem);
                        }
                    }

                } else {

                    foreach ($this->items as $it) {
                        if ($it->position <= $item->position) {
                            continue;
                        }
                        if ($it->itemoptional == LTC_OPTIONAL_HEADING) {
                            break;
                        }
                        if (!$it->moduleid) {
                            continue;
                        }
                        if ($it->hidden == LTC_HIDDEN_MANUAL) {
                            $it->hidden = LTC_HIDDEN_NO;
                            $upditem = new stdClass;
                            $upditem->id = $it->id;
                            $upditem->hidden = $it->hidden;
                            $DB->update_record('learningtimecheck_item', $upditem);
                        }
                    }
                }
            }
            learningtimecheck_update_grades($this->learningtimecheck);
        }

        $eventparams = array(
            'objectid' => $this->learningtimecheck->id,
            'context' => $this->context,
        );
        $event = mod_learningtimecheck\event\items_updated::create($eventparams);
        $event->trigger();
    }

    public function deleteitem($itemid, $forcedelete = false) {
        global $DB;

        if (isset($this->items[$itemid])) {
            if (!$forcedelete && !$this->canedit()) {
                return;
            }
            unset($this->items[$itemid]);
        } else if (isset($this->useritems[$itemid])) {
            if (!$this->canaddown()) {
                return;
            }
            unset($this->useritems[$itemid]);
        } else {
            // Item for deletion is not currently available.
            return;
        }

        $DB->delete_records('learningtimecheck_item', array('id' => $itemid));
        $DB->delete_records('learningtimecheck_check', array('item' => $itemid));
        $DB->delete_records('learningtimecheck_comments', array('itemid' => $itemid));

        $this->update_item_positions();

        $eventparams = array(
            'objectid' => $this->learningtimecheck->id,
            'context' => $this->context,
        );
        $event = mod_learningtimecheck\event\items_updated::create($eventparams);
        $event->trigger();
    }

    public function moveitemto($itemid, $newposition, $forceupdate=false) {
        global $DB;

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                if ($this->canupdateown()) {
                    $this->useritems[$itemid]->position = $newposition;
                    $upditem = new stdClass;
                    $upditem->id = $itemid;
                    $upditem->position = $newposition;
                    $DB->update_record('learningtimecheck_item', $upditem);
                }
            }
            return;
        }

        if (!$forceupdate && !$this->canedit()) {
            return;
        }

        $itemcount = count($this->items);
        if ($newposition < 1) {
            $newposition = 1;
        } else if ($newposition > $itemcount) {
            $newposition = $itemcount;
        }

        $oldposition = $this->items[$itemid]->position;
        if ($oldposition == $newposition) {
            return;
        }

        if ($newposition < $oldposition) {
            $this->update_item_positions(1, $newposition, $oldposition); // Move items down.
        } else {
            $this->update_item_positions(-1, $oldposition, $newposition); // Move items up (including this one).
        }

        $this->items[$itemid]->position = $newposition; // Move item to new position.
        uasort($this->items, 'learningtimecheck_itemcompare'); // Sort the array by position.
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->position = $newposition;
        $DB->update_record('learningtimecheck_item', $upditem); // Update the database.
    }

    public function moveitemup($itemid) {
        // TODO If indented, only allow move if suitable space for 'reparenting'.

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                $this->moveitemto($itemid, $this->useritems[$itemid]->position - 1);
            }
            return;
        }
        $this->moveitemto($itemid, $this->items[$itemid]->position - 1);
    }

    public function moveitemdown($itemid) {
        // TODO If indented, only allow move if suitable space for 'reparenting'.

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                $this->moveitemto($itemid, $this->useritems[$itemid]->position + 1);
            }
            return;
        }
        $this->moveitemto($itemid, $this->items[$itemid]->position + 1);
    }


    public function makeoptional($itemid, $optional, $heading = false) {
        global $DB;

        if ($item = $DB->get_record('learningtimecheck_item', array('id' => $itemid))) {

            if ($heading) {
                $optional = LTC_OPTIONAL_HEADING;
            } else if ($optional) {
                $optional = LTC_OPTIONAL_YES;
            } else {
                $optional = LTC_OPTIONAL_NO;
            }

            $item->itemoptional = $optional;
            $DB->update_record('learningtimecheck_item', $item);

            // renovate cache
            if (isset($this->items) && array_key_exists($itemid, $this->items)) {
                $this->items[$itemid] = $item;
            }
        }

        $eventparams = array(
            'objectid' => $this->learningtimecheck->id,
            'context' => $this->context,
        );
        $event = mod_learningtimecheck\event\items_updated::create($eventparams);
        $event->trigger();
    }

    public function hideitem($itemid) {
        global $DB;

        $item = $DB->get_record('learningtimecheck_item', array('id' => $itemid));

        $item->hidden = 1;
        $DB->update_record('learningtimecheck_item', $item);

        // renovate cache
        if (isset($this->items[$itemid])) {
            $this->items = $item;
        }

        $eventparams = array(
            'objectid' => $this->learningtimecheck->id,
            'context' => $this->context,
        );
        $event = mod_learningtimecheck\event\items_updated::create($eventparams);
        $event->trigger();
    }

    public function showitem($itemid) {
        global $DB;

        $this->items[$itemid]->hidden = 0;
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->hidden = 0;
        $DB->update_record('learningtimecheck_item', $upditem);

        $eventparams = array(
            'objectid' => $this->learningtimecheck->id,
            'context' => $this->context,
        );
        $event = mod_learningtimecheck\event\items_updated::create($eventparams);
        $event->trigger();
    }

    public function ajaxupdatechecks($changechecks) {
        // Convert array of itemid=>true/false, into array of all 'checked' itemids.

        $newchecks = array();
        foreach ($this->items as $item) {
            if (array_key_exists($item->id, $changechecks)) {
                if ($changechecks[$item->id]) {
                    // Include in array if new status is true.
                    $newchecks[] = $item->id;
                }
            } else {
                // If no new status, include in array if checked.
                if ($item->checked) {
                    $newchecks[] = $item->id;
                }
            }
        }
        if ($this->useritems) {
            foreach ($this->useritems as $item) {
                if (array_key_exists($item->id, $changechecks)) {
                    if ($changechecks[$item->id]) {
                        // Include in array if new status is true.
                        $newchecks[] = $item->id;
                    }
                } else {
                    // If no new status, include in array if checked.
                    if ($item->checked) {
                        $newchecks[] = $item->id;
                    }
                }
            }
        }

        $this->updatechecks($newchecks);
    }

    /**
     *
     *
     */
    public function updatechecks($newchecks) {
        global $DB, $COURSE;

        $completion = new completion_info($COURSE);

        if (!is_array($newchecks)) {
            // Something has gone wrong, so update nothing.
            return;
        }

        // Trigger checks updated event.
        $context = context_module::instance($this->cm->id);
        $params = array(
            'context' => $context,
            'objectid' => $this->cm->instance
        );
        $event = \mod_learningtimecheck\event\course_module_checks_updated::create($params);
        $event->add_record_snapshot('course', $this->course);
        $event->trigger();

        $updategrades = false;
        $declaredtimes = optional_param_array('declaredtime', '', PARAM_INT);
        if ($this->items) {
            foreach ($this->items as &$item) {

                // Declarative time may concern autoupdated items.
                $select = '
                    item = ? AND
                    userid = ?
                ';
                $check = $DB->get_record_select('learningtimecheck_check', $select, array($item->id, $this->userid));
                if ((($item->isdeclarative == LTC_DECLARATIVE_STUDENTS) ||
                        ($item->isdeclarative == LTC_DECLARATIVE_BOTH))) {
                    if (!$check) {
                        $check = new stdClass;
                        $check->item = $item->id;
                        $check->userid = $this->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = LTC_TEACHERMARK_UNDECIDED;
                        $check->declaredtime = $declaredtimes[$item->id];

                        // Modify in memory item.
                        $item->declaredtime = $declaredtimes[$item->id];

                        $check->id = $DB->insert_record('learningtimecheck_check', $check);
                        $completion->update_state($this->cm, COMPLETION_UNKNOWN, $this->userid);
                    } else {
                        if (is_array($declaredtimes)) {
                            if (array_key_exists($item->id, $declaredtimes)) {
                                $check->declaredtime = $declaredtimes[$item->id];

                                // Modify in memory item.
                                $item->declaredtime = $declaredtimes[$item->id];
                                $DB->update_record('learningtimecheck_check', $check);
                                $completion->update_state($this->cm, COMPLETION_UNKNOWN, $this->userid);
                            }
                        }
                    }
                }

                if (($this->learningtimecheck->autoupdate == LTC_AUTOUPDATE_YES) && ($item->moduleid)) {
                    continue; // Shouldn't get updated anyway, but just in case...
                }

                $newval = in_array($item->id, $newchecks);

                if ($newval != @$item->checked || !empty($check->declaredtime)) {
                    $updategrades = true;
                    $item->checked = $newval;

                    $check = $DB->get_record('learningtimecheck_check', array('item' => $item->id, 'userid' => $this->userid) );
                    if ($check) {
                        if ($newval || !empty($check->declaredtime)) {
                            // If the item has been newly checked or given time, register user timestamp.
                            $check->usertimestamp = time();
                        } else {
                            // Item has been unchecked, or declaredtime erased. Revert to undeclared state.
                            $check->usertimestamp = 0;
                        }
                        $DB->update_record('learningtimecheck_check', $check);
                        $completion->update_state($this->cm, COMPLETION_UNKNOWN, $this->userid);

                    } else {
                        $check = new stdClass;
                        $check->item = $item->id;
                        $check->userid = $this->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = LTC_TEACHERMARK_UNDECIDED;

                        $check->id = $DB->insert_record('learningtimecheck_check', $check);
                        $completion->update_state($this->cm, COMPLETION_UNKNOWN, $this->userid);
                    }
                }
            }
        }

        // This should be removed. There are no more user defined items in learningtimecheck.
        if ($this->useritems) {
            foreach ($this->useritems as $item) {
                $newval = in_array($item->id, $newchecks);

                if ($newval != $item->checked) {
                    $item->checked = $newval;

                    $check = $DB->get_record('learningtimecheck_check', array('item' => $item->id, 'userid' => $this->userid));
                    if ($check) {
                        if ($newval) {
                            $check->usertimestamp = time();
                        } else {
                            $check->usertimestamp = 0;
                        }
                        $DB->update_record('learningtimecheck_check', $check);
                        $completion->update_state($this->cm, COMPLETION_UNKNOWN, $this->userid);
                    } else {
                        $check = new stdClass;
                        $check->item = $item->id;
                        $check->userid = $this->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = LTC_TEACHERMARK_UNDECIDED;

                        $check->id = $DB->insert_record('learningtimecheck_check', $check);
                        $completion->update_state($this->cm, COMPLETION_UNKNOWN, $this->userid);
                    }
                }
            }
        }
    }

    public function updateteachermarks() {
        global $USER, $DB;

        $teacherdeclaredtimes = optional_param_array('teacherdeclaredtime', array(), PARAM_INT);
        $teacherdeclaredtimesperuser = optional_param_array('teacherdeclaredtimeperuser', array(), PARAM_INT);
        $teachercomments = optional_param_array('teachercomment', array(), PARAM_TEXT);

        if (empty($teacherdeclaredtimes) && empty($teacherdeclaredtimesperuser) && empty($teachercomments)) {
            // Something has gone wrong, so update nothing.
            return;
        }

        $updategrades = false;

        // Process global tutoring time.
        foreach ($teacherdeclaredtimes as $itemid => $newval) {

            if (array_key_exists($itemid, $this->items)) {
                // Should always exit.
                $this->items[$itemid]->teacherdeclaredtime = $newval;
            }

            $needsupdategrades = true;
            $params = ['userid' => $USER->id, 'item' => $itemid];
            $oldcheck = $DB->get_record('learningtimecheck_check', $params);
            if ($oldcheck) {
                $oldcheck->teacherdeclaredtime = $newval;
                $DB->update_record('learningtimecheck_check', $oldcheck);
            } else {
                $newcheck = new StdClass;
                $newcheck->item = $itemid;
                $newcheck->userid = $USER->id;
                $newcheck->usertimestamp = 0;
                $newcheck->teachertimestamp = time();
                $newcheck->teacherid = $USER->id;
                $newcheck->teacherdeclaredtime = $newval;
                $newcheck->id = $DB->insert_record('learningtimecheck_check', $newcheck);
            }
        }

        $studentid = required_param('studentid', PARAM_INT);

        // Process user assigned tutoring time.
        foreach ($teacherdeclaredtimesperuser as $itemid => $newval) {

            if (array_key_exists($itemid, $this->items)) {
                // Should always exit.
                $this->items[$itemid]->teacherdeclaredtime = $newval;
            }

            $needsupdategrades = true;
            $params = ['userid' => $USER->id, 'item' => $itemid];
            $oldcheck = $DB->get_record('learningtimecheck_check', $params);
            if ($oldcheck) {
                $oldcheck->teacherdeclaredtime = $newval;
                $oldcheck->studentid = $studentid;
                $DB->update_record('learningtimecheck_check', $oldcheck);
            } else {
                $newcheck = new StdClass;
                $newcheck->item = $itemid;
                $newcheck->userid = $studentid;
                $newcheck->usertimestamp = 0;
                $newcheck->teachertimestamp = time();
                $newcheck->teacherid = $USER->id;
                $newcheck->teacherdeclaredtime = $newval;
                $newcheck->id = $DB->insert_record('learningtimecheck_check', $newcheck);
            }
        }

        if ($this->learningtimecheck->teacheredit != LTC_MARKING_STUDENT) {
            // Do not process any teacher mark when student marking only.

            if (!$this->userid || !$student = $DB->get_record('user', array('id' => $this->userid))) {
                print_error('erronosuchuser', 'learningtimecheck');
            }
            $info = $this->learningtimecheck->name.' ('.fullname($student, true).')';

            // Trigger teacher marks updated event.
            $context = context_module::instance($this->cm->id);
            $params = array(
                'context' => $context,
                'objectid' => $this->cm->instance
            );
            $event = \mod_learningtimecheck\event\course_module_teachermarks_updated::create($params);
            $event->add_record_snapshot('course', $this->course);
            $event->trigger();

            $teachermarklocked = $this->learningtimecheck->lockteachermarks &&
                    !has_capability('mod/learningtimecheck:updatelocked', $this->context);

            if ($updategrades) {
                learningtimecheck_update_grades($this->learningtimecheck, $this->userid);
            }
        }

        // Process all comments.
        if ($this->learningtimecheck->teachercomments && !empty($teachercomments)) {

            list($isql, $iparams) = $DB->get_in_or_equal(array_keys($this->items));
            $select = "
                userid = ? AND
                itemid $isql
            ";
            $params = array_merge(array($this->userid), $iparams);
            $commentsunsorted = $DB->get_records_select('learningtimecheck_comment', $select, $params);
            $comments = array();
            foreach ($commentsunsorted as $comment) {
                $comments[$comment->itemid] = $comment;
            }
            foreach ($newcomments as $itemid => $newcomment) {
                $newcomment = trim($newcomment);
                if ($newcomment == '') {
                    if (array_key_exists($itemid, $comments)) {
                        $DB->delete_records('learningtimecheck_comment', array('id' => $comments[$itemid]->id) );
                        unset($comments[$itemid]); // Should never be needed, but just in case...
                    }
                } else {
                    if (array_key_exists($itemid, $comments)) {
                        if ($comments[$itemid]->text != $newcomment) {
                            $updatecomment = new stdClass;
                            $updatecomment->id = $comments[$itemid]->id;
                            $updatecomment->userid = $this->userid;
                            $updatecomment->itemid = $itemid;
                            $updatecomment->commentby = $USER->id;
                            $updatecomment->text = $newcomment;

                            $DB->update_record('learningtimecheck_comment', $updatecomment);
                        }
                    } else {
                        $addcomment = new stdClass;
                        $addcomment->itemid = $itemid;
                        $addcomment->userid = $this->userid;
                        $addcomment->commentby = $USER->id;
                        $addcomment->text = $newcomment;

                        $DB->insert_record('learningtimecheck_comment', $addcomment);
                    }
                }
            }
        }

    }

    public function updateallteachermarks() {
        global $DB, $USER;

        if ($this->learningtimecheck->teacheredit == LTC_MARKING_STUDENT) {
            // Student only lists do not have teacher marks to update.
            return;
        }

        $userids = optional_param_array('userids', array(), PARAM_INT);
        if (!is_array($userids)) {
            // Something has gone wrong, so update nothing.
            return;
        }

        $userchecks = array();
        foreach ($userids as $userid) {
            $checkdata = optional_param_array('items_'.$userid, array(), PARAM_INT);
            if (!is_array($checkdata)) {
                continue;
            }
            foreach ($checkdata as $itemid => $val) {
                if ($val != LTC_TEACHERMARK_NO &&
                    $val != LTC_TEACHERMARK_YES &&
                    $val != LTC_TEACHERMARK_UNDECIDED) {
                    continue; // Invalid value.
                }
                if (!$itemid) {
                    continue;
                }
                if (!array_key_exists($itemid, $this->items)) {
                    continue; // Item is not part of this learningtimecheck.
                }
                if (!array_key_exists($userid, $userchecks)) {
                    $userchecks[$userid] = array();
                }
                $userchecks[$userid][$itemid] = $val;
            }
        }

        if (empty($userchecks)) {
            return;
        }

        $teachermarklocked = $this->learningtimecheck->lockteachermarks &&
                !has_capability('mod/learningtimecheck:updatelocked', $this->context);

        foreach ($userchecks as $userid => $items) {
            list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));
            $params = array_merge(array($userid), $iparams);
            $select = "userid = ? AND item $isql";
            $currentchecks = $DB->get_records_select('learningtimecheck_check', $select, $params, '', 'item, id, teachermark');
            $updategrades = false;
            foreach ($items as $itemid => $val) {
                if (!array_key_exists($itemid, $currentchecks)) {
                    if ($val == LTC_TEACHERMARK_UNDECIDED) {
                        continue; // Do not create an entry for blank marks.
                    }

                    // No entry for this item - need to create it.
                    $newcheck = new stdClass;
                    $newcheck->item = $itemid;
                    $newcheck->userid = $userid;
                    $newcheck->teachermark = $val;
                    $newcheck->teachertimestamp = time();
                    $newcheck->declaredtime = 0;
                    $newcheck->usertimestamp = 0;
                    $newcheck->teacherid = $USER->id;

                    $DB->insert_record('learningtimecheck_check', $newcheck);
                    $updategrades = true;

                } else if ($currentchecks[$itemid]->teachermark != $val) {
                    if ($teachermarklocked && $currentchecks[$itemid]->teachermark == LTC_TEACHERMARK_YES) {
                        continue;
                    }

                    $updcheck = new stdClass;
                    $updcheck->id = $currentchecks[$itemid]->id;
                    $updcheck->teachermark = $val;
                    $updcheck->teachertimestamp = time();
                    $updcheck->teacherid = $USER->id;

                    $DB->update_record('learningtimecheck_check', $updcheck);
                    $updategrades = true;
                }
            }
            if ($updategrades) {
                learningtimecheck_update_grades($this->learningtimecheck, $userid);
            }
        }
    }

    public function update_complete_scores() {
        global $DB;

        $newitems = optional_param_array('items', false, PARAM_INT);
        $newscores = optional_param_array('complete_score', false, PARAM_INT);
        $newcredittimes = optional_param_array('credittime', false, PARAM_INT);

        $newoptionals = optional_param_array('optional', false, PARAM_INT);
        if (empty($newoptionals)) {
            $newoptionals = array();
        }

        $newteachercredittimeperusers = optional_param_array('teachercredittimeperuser', false, PARAM_INT);
        $newteachercredittimes = optional_param_array('teachercredittime', false, PARAM_INT);
        $newenablecredits = optional_param_array('enablecredit', false, PARAM_INT);
        $newisdeclaratives = optional_param_array('isdeclarative', false, PARAM_INT);

        if ((!$newoptionals ||
                !$newitems ||
                        !$newscores ||
                                !is_array($newscores)) &&
                                        (!$newcredittimes) &&
                                                (!$newenablecredits) &&
                                                        (!$newisdeclaratives) &&
                                                                (!$newteachercredittimes) &&
                                                                        (!$newteachercredittimeperusers)) {
            // Perf trap.
            return;
        }

        $changed = false;
        foreach ($newitems as $itemid) {
            $newscore = 0 + @$newscore[$itemid];
            $newcredittime = 0 + @$newcredittimes[$itemid];
            $newoptional = in_array($itemid, $newoptionals);
            $newteachercredittime = 0 + @$newteachercredittimes[$itemid];
            $newteachercredittimeperuser = 0 + @$newteachercredittimeperusers[$itemid];
            $newisdeclarative = 0 + @$newisdeclaratives[$itemid];
            $newenablecredit = 0 + @$newenablecredits[$itemid];

            if ($upditem = $DB->get_record('learningtimecheck_item', array('id' => $itemid))) {
                $upditem->complete_score = $newscore;
                $upditem->credittime = $newcredittime;
                if ($upditem->itemoptional != LTC_OPTIONAL_HEADING) {
                    $upditem->itemoptional = ($newoptional) ? LTC_OPTIONAL_NO : LTC_OPTIONAL_YES;
                } else {
                    unset($upditem->itemoptional);
                }
                $upditem->enablecredit = $newenablecredit;
                $upditem->isdeclarative = $newisdeclarative;
                $upditem->teachercredittime = $newteachercredittime;
                $upditem->teachercredittimeperuser = $newteachercredittimeperuser;
                $DB->update_record('learningtimecheck_item', $upditem);
            }

            if (isset($this->items[$itemid])) {
                $item =& $this->items[$itemid];
                $item->complete_score = $newscore;
                $item->credittime = $newcredittime;
                if ($this->items[$itemid]->itemoptional != LTC_OPTIONAL_HEADING) {
                    $item->itemoptional = ($newoptional) ? LTC_OPTIONAL_NO : LTC_OPTIONAL_YES;
                }
                $item->enablecredit = $newenablecredit;
                $item->isdeclarative = $newisdeclarative;
                $item->teachercredittime = $newteachercredittime;
                $item->teachercredittimeperuser = $newteachercredittimeperuser;
            }
        }

        $this->update_all_autoupdate_checks();

        $eventparams = array(
            'objectid' => $this->learningtimecheck->id,
            'context' => $this->context,
        );
        $event = mod_learningtimecheck\event\items_updated::create($eventparams);
        $event->trigger();
    }


    /**
     * Allows a learningtimecheck instance bound refresh (not optimized for cron)
     * for an interactive cleanup.
     * @param array $userlist allow restrict update to an interesting subset of users. f.e. the displayed one.
     */
    public function update_all_autoupdate_checks($userlist = []) {
        global $DB;

        $now = time();

        $users = array();
        $cap = 'mod/learningtimecheck:updateown';
        if ($this->userid) {
            $userids = $this->userid;
            $users = $DB->get_records('user', array('id' => $userids));
        } else if (!empty($userlist)) {
            $userids = implode(',', array_keys($userlist));
            $users = $DB->get_records_list('user', 'id', array_keys($userlist));
        } else {
            // $users = get_users_by_capability($this->context, $cap, 'u.id, u.username', '', '', '', '', '', false);
            // Restrict to active users only.
            $users = get_enrolled_users($this->context, $cap, 0, 'u.*', null, 0, 0, true);
            if (!$users) {
                return;
            }
            $userids = implode(',', array_keys($users));
        }

        // Get a list of all the learningtimecheck items with a module linked to them (ignoring headings).
        $sql = "
            SELECT DISTINCT
                cm.id AS cmid,
                m.name AS mod_name,
                cm.course AS course,
                i.id AS itemid,
                l.lastcompiledtime AS lastcompiled,
                cm.completion AS completion
            FROM
                {modules} m,
                {course_modules} cm,
                {learningtimecheck_item} i,
                {learningtimecheck} l
            WHERE
                m.id = cm.module AND
                cm.id = i.moduleid AND
                cm.deletioninprogress = 0 AND
                l.id = i.learningtimecheck AND
                i.moduleid > 0 AND
                i.learningtimecheck = ? AND
                i.itemoptional != 2
        ";

        $completion = new completion_info($this->course);
        $usingcompletion = $completion->is_enabled();

        $reportconfig = get_config('report_learningtimecheck');
        $context = context_module::instance($this->cm->id);

        $items = $DB->get_records_sql($sql, array($this->learningtimecheck->id));

        if ($items) {

            // Prepare uid list
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($users));

            foreach ($items as $itemid => $item) {
                if ($usingcompletion && $item->completion) {
                    // $fakecm = new stdClass;
                    // $fakecm->id = $item->cmid;

                    $select = " userid $insql AND coursemoduleid = ? ";
                    $uinparams = $inparams;
                    $uinparams[] = $item->cmid;
                    $compstates = $DB->get_records_select('course_modules_completion', $select, $uinparams, 'userid', 'userid, completionstate');

                    foreach ($users as $user) {
                        if (array_key_exists($user->id, $compstates)) {
                            $cstate = $compstates[$user->id]->completionstate;
                        } else {
                            $cstate = 0;
                        }
                        if ($cstate == COMPLETION_COMPLETE ||
                                $cstate == COMPLETION_COMPLETE_PASS) {
                            $params = array('item' => $item->itemid, 'userid' => $user->id);
                            $check = $DB->get_record('learningtimecheck_check', $params);
                            if ($check) {
                                if ($check->usertimestamp) {
                                    continue;
                                }
                                if (report_learningtimecheck::is_valid($check, $reportconfig, $context)) {
                                    $check->usertimestamp = (empty($compdata->timemodified)) ? time() : $compdata->timemodified;
                                    $DB->update_record('learningtimecheck_check', $check);
                                }
                            } else {
                                $check = new stdClass;
                                $check->item = $item->itemid;
                                $check->userid = $user->id;
                                $check->usertimestamp = (empty($compdata->timemodified)) ? time() : $compdata->timemodified;
                                $check->teachertimestamp = 0;
                                $check->teachermark = LTC_TEACHERMARK_UNDECIDED;

                                if (report_learningtimecheck::is_valid($check, $reportconfig, $context)) {
                                    $check->id = $DB->insert_record('learningtimecheck_check', $check);
                                }
                            }
                        }
                    }

                    continue;
                }

                // For each item that has no completion try resolve by log.
                $logaction = '';
                $logaction2 = false;

                switch($item->mod_name) {
                    case 'survey':
                        $logaction = 'submit';
                        break;
                    case 'quiz':
                        $logaction = 'close attempt';
                        break;
                    case 'forum':
                        $logaction = 'add post';
                        $logaction2 = 'add discussion';
                        break;
                    case 'resource':
                        $logaction = 'view';
                        break;
                    case 'hotpot':
                        $logaction = 'submit';
                        break;
                    case 'wiki':
                        $logaction = 'edit';
                        break;
                    case 'learningtimecheck':
                        $logaction = 'complete';
                        break;
                    case 'choice':
                        $logaction = 'choose';
                        break;
                    case 'lams':
                        $logaction = 'view';
                        break;
                    case 'scorm':
                        $logaction = 'view';
                        break;
                    case 'assignment':
                        $logaction = 'upload';
                        break;
                    case 'journal':
                        $logaction = 'add entry';
                        break;
                    case 'lesson':
                        $logaction = 'end';
                        break;
                    case 'realtimequiz':
                        $logaction = 'submit';
                        break;
                    case 'workshop':
                        $logaction = 'submit';
                        break;
                    case 'glossary':
                        $logaction = 'add entry';
                        break;
                    case 'data':
                        $logaction = 'add';
                        break;
                    case 'chat':
                        $logaction = 'talk';
                        break;
                    case 'page':
                        $logaction = 'view';
                        break;
                    case 'url':
                        $logaction = 'url';
                        break;
                    case 'feedback':
                        $logaction = 'submit';
                        break;
                    default:
                        continue 2;
                        break;
                }

                $logmanager = get_log_manager();
                $readers = $logmanager->get_readers(self::get_reader_source());
                $reader = reset($readers);

                if (empty($reader)) {
                    continue; // No log reader found.
                }

                $logupdate = 0;
                $totalcount = 0;

                if ($reader instanceof \logstore_standard\log\store) {
                    $courseparm = 'courseid';
                    $select = "timecreated >= ? AND courseid = {$item->course} AND objectid > 0 AND component = 'mod_{$item->mod_name}' ";
                    $logentries = $DB->get_records_select('logstore_standard_log', $select, array($item->lastcompiled));
                } else if ($reader instanceof \logstore_legacy\log\store) {
                    $params = array($item->lastcompiled, $logaction);
                    if (!empty($logaction2)) {
                        $action2clause = ' OR l.action = ? ';
                        $params[] = $logaction2;
                    }
                    $logentries = get_logs("l.time >= ? AND (l.action = ? $action2clause) AND cmid > 0", $params, 'l.time ASC', '', '', $totalcount);
                } else {
                    continue;
                }

                if (!$logentries) {
                    // Mark the compiletime.
                    $DB->set_field('learningtimecheck', 'lastcompiledtime', $now, array('id' => $this->learningtimecheck->id));
                    continue;
                }

                foreach ($logentries as $entry) {
                    $check = $DB->get_record('learningtimecheck_check', array('item' => $item->itemid, 'userid' => $entry->userid));
                    if ($check) {
                        if ($check->usertimestamp) {
                            continue;
                        }
                        $check->usertimestamp = 0 + @$entry->time;
                        if (report_learningtimecheck::is_valid($check, $reportconfig, $context)) {
                            $DB->update_record('learningtimecheck_check', $check);
                        }
                    } else {
                        $check = new stdClass;
                        $check->item = $item->itemid;
                        $check->userid = $entry->userid;
                        $check->usertimestamp = 0 + @$entry->time;
                        $check->teachertimestamp = 0;
                        $check->teachermark = LTC_TEACHERMARK_UNDECIDED;

                        if (report_learningtimecheck::is_valid($check, $reportconfig, $context)) {
                            $check->id = $DB->insert_record('learningtimecheck_check', $check);
                        }
                    }
                }

                // Mark the compiletime.
                $DB->set_field('learningtimecheck', 'lastcompiledtime', $now, array('id' => $this->learningtimecheck->id));
            }
        } else {
            if (function_exists('debug_trace')) {
                debug_trace("No items to track in LTC {$this->learningtimecheck->id}");
            }
        }
    }

    /**
     * Update the userid to point to the next user to view
     */
    public function getnextuserid() {
        global $DB;

        $activegroup = groups_get_activity_group($this->cm, true);
        $settings = self::get_report_settings();
        switch ($settings->sortby) {
            case 'firstdesc':
                $orderby = 'ORDER BY u.firstname DESC';
                break;

            case 'lastasc':
                $orderby = 'ORDER BY u.lastname';
                break;

            case 'lastdesc':
                $orderby = 'ORDER BY u.lastname DESC';
                break;

            default:
                $orderby = 'ORDER BY u.firstname';
                break;
        }

        $ausers = false;
        if ($users = get_users_by_capability($this->context, 'mod/learningtimecheck:updateown', 'u.id', '', '', '', $activegroup, '', false)) {
            $users = array_keys($users);
            if ($this->only_view_mentee_reports()) {
                $users = $this->filter_mentee_users($users);
            }
            if (!empty($users)) {
                list($usql, $uparams) = $DB->get_in_or_equal($users);
                $ausers = $DB->get_records_sql('SELECT u.id FROM {user} u WHERE u.id '.$usql.$orderby, $uparams);
            }
        }

        $stoponnext = false;
        foreach ($ausers as $user) {
            if ($stoponnext) {
                $this->userid = $user->id;
                return;
            }
            if ($user->id == $this->userid) {
                $stoponnext = true;
            }
        }
        $this->userid = false;
    }

    public static function print_user_progressbar($learningtimecheckid, $userid, $width = '300px', $showpercent = true,
                                                  $return = false, $hidecomplete = false) {
        global $OUTPUT;

        list($ticked, $total) = self::get_user_progress($learningtimecheckid, $userid);
        if (!$total) {
            return '';
        }
        if ($hidecomplete && ($ticked == $total)) {
            return '';
        }
        $percent = round($ticked * 100 / $total);

        // TODO - fix this now that styles.css is included.
        $output = '<div class="ltc_progress_outer" style="width: '.$width.';" >';
        $style = 'width:'.$percent.'%; background-image: url('.$OUTPUT->image_url('progress', 'learningtimecheck').');';
        $output .= '<div class="ltc_progress_inner" title="'.$percent.'%" style="'.$style.'" >&nbsp;</div>';
        $output .= '</div>';
        if ($showpercent) {
            $output .= '<span class="ltc_progress_percent">&nbsp;'.sprintf('%0d%%', $percent).'</span>';
        }
        $output .= '<br style="clear:both;" />';
        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }

    /**
     * Get the progress ratio of a user
     * @param int $learningtimecheckid
     * @param int $userid
     * @param int $mandatory
     *
     */
    public static function get_user_progress($learningtimecheckid, $userid, $mandatory = LTC_OPTIONAL_NO) {
        global $DB, $CFG;

        $userid = intval($userid); // Just to be on the safe side...

        $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $learningtimecheckid) );
        if (!$learningtimecheck) {
            return array(false, false);
        }
        $groupingssel = '';
        if (isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly && $learningtimecheck->autopopulate) {
            $groupings = self::get_user_groupings($userid, $learningtimecheck->course);
            $groupings[] = 0;
            $groupingssel = ' AND groupingid IN ('.implode(',', $groupings).') ';
        }
        $select = '
            learningtimecheck = ? AND
            userid = 0 AND
            itemoptional = '.$mandatory.' AND
            hidden = '.LTC_HIDDEN_NO.$groupingssel;
        $items = $DB->get_records_select('learningtimecheck_item', $select, array($learningtimecheck->id), '', 'id');
        if (empty($items)) {
            return array(false, false);
        }
        $total = count($items);
        list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));
        $params = array_merge(array($userid), $iparams);

        $sql = "userid = ? AND item $isql AND ";
        if ($learningtimecheck->teacheredit == LTC_MARKING_STUDENT) {
            $sql .= 'usertimestamp > 0';
        } else {
            $sql .= 'teachermark = '.LTC_TEACHERMARK_YES;
        }
        $ticked = $DB->count_records_select('learningtimecheck_check', $sql, $params);

        return array($ticked, $total);
    }

    public static function get_user_groupings($userid, $courseid) {
        global $DB;

        $sql = "
            SELECT DISTINCT
                gg.groupingid
            FROM
                ({groups} g
            JOIN
                {groups_members} gm
            ON
                g.id = gm.groupid)
            JOIN
                {groupings_groups} gg
            ON
                gg.groupid = g.id
            WHERE
                gm.userid = ? AND
                g.courseid = ?
        ";
        $groupings = $DB->get_records_sql($sql, array($userid, $courseid));
        if (!empty($groupings)) {
            return array_keys($groupings);
        }
        return array();
    }

    /**
     * returs options for logical combination of report rules
     */
    public static function get_logop_options() {
        $options = array();

        $options[''] = '';
        $options['and'] = get_string('and', 'learningtimecheck');
        $options['or'] = get_string('or', 'learningtimecheck');
        $options['xor'] = get_string('xor', 'learningtimecheck');

        return $options;
    }

    /**
     * returs options for logical combination of report rules
     */
    public static function get_rule_options($contexttype = 'course') {
        global $CFG, $DB;

        $options = array();

        if ($contexttype == 'course') {
            $options['courseenroltime'] = get_string('courseenroltime', 'learningtimecheck');
            $options['firstcheckaquired'] = get_string('firstcheckaquired', 'learningtimecheck');
            $options['checkcomplete'] = get_string('checkcomplete', 'learningtimecheck');
            $options['coursestarted'] = get_string('coursestarted', 'learningtimecheck');
            $options['coursecompleted'] = get_string('coursecompleted', 'learningtimecheck');
            $options['lastcoursetrack'] = get_string('lastcoursetrack', 'learningtimecheck');
            if ($DB->get_record('modules', array('name' => 'certificate'))) {
                $options['onecertificateissued'] = get_string('onecertificateissued', 'learningtimecheck');
                $options['allcertificatesissued'] = get_string('allcertificatesissued', 'learningtimecheck');
            }
        } else if ($contexttype == 'user') {
            $options['usercreationdate'] = get_string('usercreationdate', 'learningtimecheck');
            $options['sitefirstevent'] = get_string('sitefirstevent', 'learningtimecheck');
            $options['sitelastevent'] = get_string('sitelastevent', 'learningtimecheck');
            $options['firstcoursestarted'] = get_string('firstcoursestarted', 'learningtimecheck');
            $options['firstcoursecompleted'] = get_string('firstcoursecompleted', 'learningtimecheck');
        } else if ($contexttype == 'cohort') {
            $options['usercreationdate'] = get_string('usercreationdate', 'learningtimecheck');
            $options['sitefirstevent'] = get_string('sitefirstevent', 'learningtimecheck');
            $options['sitelastevent'] = get_string('sitelastevent', 'learningtimecheck');
            $options['firstcoursestarted'] = get_string('firstcoursestarted', 'learningtimecheck');
            $options['firstcoursecompleted'] = get_string('firstcoursecompleted', 'learningtimecheck');
            $options['usercohortaddition'] = get_string('usercohortaddition', 'learningtimecheck');
        }

        // May add programmed customized events for a particular implementation.
        if (file_exists($CFG->dirroot.'/local/learningtimecheckcustomlib.php')) {
            include_once($CFG->dirroot.'/local/learningtimecheckcustomlib.php');
            learningtimecheck_extend_rules($options, $contexttype);
        }

        return $options;
    }

    /**
     * returns options for comparison in report rules
     */
    public static function get_ruleop_options() {

        $options = array();

        $options['gt'] = '>';
        $options['gte'] = '>=';
        $options['lt'] = '<';
        $options['lte'] = '<=';
        $options['eq'] = '=';
        $options['neq'] = '<>';

        return $options;
    }

    public static function get_reader_source() {
        return '\core\log\sql_select_reader';
    }

    /**
     * Calculates all counters.
     */
    public function calculate() {

        $teacherdrivenprogress = (($this->learningtimecheck->teacheredit != LTC_MARKING_STUDENT) &&
            ($this->learningtimecheck->teacheredit != LTC_MARKING_EITHER));

        $result['requireditems'] = 0;
        $result['requiredcompleteitems'] = 0;

        $result['requiredtime'] = 0;
        $result['requiredcompletetime'] = 0;

        $result['optionalitems'] = 0;
        $result['optionalcompleteitems'] = 0;

        $result['optionaltime'] = 0;
        $result['optionalcompletetime'] = 0;

        $result['allitems'] = 0;
        $result['alltime'] = 0;

        $result['allcompleteitems'] = 0;
        $result['allcompletetime'] = 0;

        if (empty($this->items)) {
            return $result;
        }

        $checkgroupings = $this->learningtimecheck->autopopulate && ($this->groupings !== false);

        foreach ($this->items as $item) {

            $reporttime = $this->get_report_time($item);

            if (($item->itemoptional == LTC_OPTIONAL_HEADING)||($item->hidden)) {
                // Eliminate all headings.
                continue;
            }

            if ($checkgroupings && !empty($item->groupingid)) {
                if (!in_array($item->groupingid, $this->groupings)) {
                    // Current user is not a member of this item's grouping.
                    continue;
                }
            }

            if ($item->itemoptional == LTC_OPTIONAL_NO) {
                if ($teacherdrivenprogress) {
                    if ($item->teachermark == LTC_TEACHERMARK_YES) {
                        $result['requiredcompleteitems']++;
                        $result['requiredcompletetime'] += $reporttime;
                        $result['allcompleteitems']++;
                        $result['allcompletetime'] += $reporttime;
                    }
                } else if ($item->checked) {
                    $result['requiredcompleteitems']++;
                    $result['requiredcompletetime'] += $reporttime;
                    $result['allcompleteitems']++;
                    $result['allcompletetime'] += $reporttime;
                }
                $result['requireditems']++;
                $result['requiredtime'] += $reporttime;
            } else {
                if ($teacherdrivenprogress) {
                    if ($item->teachermark == LTC_TEACHERMARK_YES) {
                        $result['allcompleteitems']++;
                        $result['allcompletetime'] += $reporttime;
                        $result['optionalcompleteitems']++;
                        $result['optionalcompletetime'] += $reporttime;
                    }
                } else if ($item->checked) {
                    $result['allcompleteitems']++;
                    $result['allcompletetime'] += $reporttime;
                    $result['optionalcompleteitems']++;
                    $result['optionalcompletetime'] += $reporttime;
                }
                $result['optionalitems']++;
                $result['optionaltime'] += $reporttime;
            }
            $result['allitems']++;
            $result['alltime'] += $reporttime;
        }

        // Process additional user items. Check for DEPRECATION.
        /*
        if (!empty($this->useritems)) {
            if (!$teacherdrivenprogress) {
                foreach ($this->useritems as $item) {
                    if ($item->checked) {
                        $result['allcompleteitems']++;
                        $result['allcompletetime'] += $reporttime;
                        // User items are always optional (user items may be deprecated).
                        $result['optionalcompleteitems']++;
                        $result['optionalcompletetime'] += $reporttime;
                        $result['allitems']++;
                        $result['alltime'] += $reporttime;
                    }
                    $result['allitems']++;
                    $result['alltime'] += $reporttime;
                }
            }
        }
        */

        return $result;
    }
}

function learningtimecheck_itemcompare($item1, $item2) {
    if ($item1->position < $item2->position) {
        return -1;
    } else if ($item1->position > $item2->position) {
        return 1;
    }
    if ($item1->id < $item2->id) {
        return -1;
    } else if ($item1->id > $item2->id) {
        return 1;
    }
    return 0;
}

/**
 * brings back all the learningtimechecks user has tracks in
 * @param int $userid
 * @param string $arrangemode
 * @param int $courseid If couseid is null or 0, will get all the checks of the user in the whole moodle
 */
function learningtimecheck_get_my_checks($userid, $arrangemode = 'flat', $courseid = 0) {
    global $DB;

    $courseclause = ($courseid) ? " AND lt.course = $courseid " : '';

    $sql = "
        SELECT DISTINCT
            lt.id,
            lt.id
        FROM
            {learningtimecheck} lt,
            {learningtimecheck_item} lti,
            {learningtimecheck_check} ltc
        WHERE
            lt.id = lti.learningtimecheck AND
            lti.id = ltc.item AND
            ltc.userid = ?
            {$courseclause}
    ";
    $allchecks = $DB->get_records_sql($sql, array($userid));

    $results = array();
    foreach ($allchecks as $c) {
        if ($cm = get_coursemodule_from_instance('learningtimecheck', $c->id)) {
            $results[$c->id] = new learningtimecheck_class($cm->id, $userid, null, $cm);
        }
    }

    if ($arrangemode == 'flat') {
        return $results;
    }

    // Else is 'by course'.

    $bycourse = array();
    foreach ($results as $cid => $check) {
        $bycourse[$check->learningtimecheck->course][$cid] = $check;
    }
    return $bycourse;
}

/**
 * Provides the option list of credit times
 */
function learningtimecheck_get_credit_times($thickness = 'coarse') {
    $minutesstr = get_string('minutes');
    $hourstr = get_string('hour');
    $hoursstr = get_string('hours');
    $timearray['0'] = get_string('disabled', 'learningtimecheck');
    $timearray['1'] = '1 '.$minutesstr;
    $timearray['2'] = '2 '.$minutesstr;
    $timearray['3'] = '3 '.$minutesstr;
    $timearray['4'] = '4 '.$minutesstr;
    $timearray['5'] = '5 '.$minutesstr;
    if ($thickness == 'thin') {
        $timearray['6'] = '6 '.$minutesstr;
        $timearray['7'] = '7 '.$minutesstr;
        $timearray['8'] = '8 '.$minutesstr;
        $timearray['9'] = '9 '.$minutesstr;
    }
    $timearray['10'] = '10 '.$minutesstr;
    $timearray['15'] = '15 '.$minutesstr;
    $timearray['20'] = '20 '.$minutesstr;
    $timearray['25'] = '25 '.$minutesstr;
    $timearray['30'] = '30 '.$minutesstr;
    $timearray['35'] = '35 '.$minutesstr;
    $timearray['40'] = '40 '.$minutesstr;
    $timearray['45'] = '45 '.$minutesstr;
    $timearray['50'] = '50 '.$minutesstr;
    $timearray['55'] = '55 '.$minutesstr;
    $timearray['60'] = '1 '.$hourstr;
    if ($thickness == 'coarse') {
        $timearray['65'] = '1 '.$hourstr.' 05 '.$minutesstr;
        $timearray['70'] = '1 '.$hourstr.' 10 '.$minutesstr;
        $timearray['75'] = '1 '.$hourstr.' 15 '.$minutesstr;
        $timearray['80'] = '1 '.$hourstr.' 20 '.$minutesstr;
        $timearray['90'] = '1 '.$hourstr.' 30 '.$minutesstr;
        $timearray['100'] = '1 '.$hourstr.' 40 '.$minutesstr;
        $timearray['110'] = '1 '.$hourstr.' 50 '.$minutesstr;
        $timearray['120'] = '2 '.$hoursstr;
        $timearray['150'] = '2 '.$hoursstr.' 30 '.$minutesstr;
        $timearray['180'] = '3 '.$hoursstr;
        $timearray['210'] = '3 '.$hoursstr.' 30 '.$minutesstr;
        $timearray['240'] = '4 '.$hoursstr;
        $timearray['270'] = '4 '.$hoursstr.' 30 '.$minutesstr;
        $timearray['300'] = '5 '.$hoursstr;
        $timearray['330'] = '5 '.$hoursstr.' 30 '.$minutesstr;
        $timearray['360'] = '6 '.$hoursstr;
        $timearray['390'] = '6 '.$hoursstr.' 30 '.$minutesstr;
        $timearray['420'] = '7 '.$hoursstr;
        $timearray['450'] = '7 '.$hoursstr.' 30 '.$minutesstr;
        $timearray['480'] = '8 '.$hoursstr;
        $timearray['540'] = '9 '.$hoursstr;
        $timearray['600'] = '10 '.$hoursstr;
    }

    return $timearray;
}

function learningtimecheck_get_teacher_mark_options() {

    $options = array();
    $options[LTC_TEACHERMARK_UNDECIDED] = '';
    $options[LTC_TEACHERMARK_YES] = get_string('yes');
    $options[LTC_TEACHERMARK_NO] = get_string('no');

    return $options;
}

function learningtimecheck_add_paged_params() {
    global $COURSE;

    if ($COURSE->format == 'page') {
        if ($pageid = optional_param('page', 0, PARAM_INT)) {
            echo '<input type="hidden" name="page" value="'.$pageid.'" />';
        }
    }
}

/**
 * Counts all evaluable items forgetting headings
 */
function learningtimecheck_count_total_items($courseid = 0, $userid = 0, $hidehidden = false) {
    global $DB;

    $courseclause = ($courseid) ? ' l.course =  $courseid ' : '';

    if ($userid && !$courseid) {
        $courses = get_my_courses($userid);
        if ($courses) {
            $idlist = implode("','", array_keys($courses));
        }
        $courseclause = " l.course IN ('$list') ";
    }

    $module = $DB->get_record('modules', array('name' => 'learningtimecheck'));

    $showhiddenclause = ($hidehidden) ? ' AND li.hidden = 0 ' : '';

    $sql = "
        SELECT
            li.*
        FROM
            {learningtimecheck_item} li,
            {learningtimecheck} l,
            {course_modules} cm
        WHERE
            l.id = li.learningtimecheck AND
            cm.id = li.moduleid AND
            (cm.deletioninprogress IS NULL or cm.deletioninprogress = 0) AND
            l.course = ? AND
            li.itemoptional <> ".LTC_OPTIONAL_HEADING."
            $showhiddenclause
    ";

    $itemscount = 0;
    $optionalitemscount = 0;
    $time = 0;
    $optionaltime = 0;
    $params = array($courseid);
    if ($items = $DB->get_records_sql($sql, $params)) {
        foreach ($items as $item) {
            if ($item->itemoptional == LTC_OPTIONAL_NO) {
                $itemscount++;
                $time += $item->credittime;
            } else {
                $optionalitemscount++;
                $optionaltime += $item->credittime;
            }
        }
    } else {
        return array('count' => 0, 'time' => 0, 'optionalcount' => 0, 'optionaltime' => 0);
    }

    return array('count' => $itemscount, 'time' => $time, 'optionalcount' => $optionalitemscount, 'optionaltime' => $optionaltime);
}

function learningtimecheck_format_time($time, $unit = 'min') {

    if ($unit == 'sec') {
        $time /= 60;
    }

    $mins = $time % 60;
    $hours = floor($time / 60);

    if ($hours && !$mins) {
        return $hours .' '.get_string('hours');
    }
    if ($hours && $mins) {
        return $hours .' '.get_string('hours').' '.$mins.' '.get_string('mins');
    }
    if (!$hours && $mins) {
        return $mins.' '.get_string('mins');
    }
}

/**
 * Searches the lowest check time in this learningtimecheck
 * @param object a Learningtimecheck database record
 */
function learningtimecheck_get_lowest_track_time($learningtimecheckrec) {
    global $DB;

    $sql = "
        SELECT
            MIN(usertimestamp)
        FROM
            {learningtimecheck_check} ltc,
            {learningtimecheck_item} lti
        WHERE
            ltc.item = lti.id AND
            lti.learningtimecheck = ?
    ";

    return $DB->get_field_sql($sql, array($learningtimecheckrec->id));
}

/**
 * Fetches next user id in enrolled iusers or group membders
 */
function learningtimecheck_get_next_user($ltc, $context, $userid, $orderby) {

    $modinfo = get_fast_modinfo($ltc->course);
    $cm = $modinfo->get_cm($context->instanceid);
    $activegroup = groups_get_activity_group($cm);

    $cap = 'mod/learningtimecheck:updateown';
    // M4.
    $fields = \core_user\fields::for_name()->with_userpic()->excluding('id')->get_required_fields();
    $fields = 'u.id,'.implode(',', $fields);
    if ($fullusers = get_users_by_capability($context, $cap, $fields, $orderby, '', '', $activegroup, '', false)) {
        learningtimecheck_apply_rules($fullusers);
        learningtimecheck_apply_namefilters($fullusers);
        if ($ltc->only_view_mentee_reports($context)) {
            // Filter to only show reports for users who this user mentors (ie they have been assigned to them in a context).
            $ltc->filter_mentee_users($fullusers);
        }
    }

    $found = false;
    foreach ($fullusers as $user) {
        if ($found) {
            return $user;
        }
        if ($user->id == $userid) {
            $found = true;
        }
    }
    return array_shift($fullusers);
}

/**
 * Fetches next user id in enrolled iusers or group membders
 */
function learningtimecheck_get_prev_user($ltc, $context, $userid, $orderby) {

    $modinfo = get_fast_modinfo($ltc->course);
    $cm = $modinfo->get_cm($context->instanceid);
    $activegroup = groups_get_activity_group($cm);

    $cap = 'mod/learningtimecheck:updateown';
    // M4.
    $fields = \core_user\fields::for_name()->with_userpic()->excluding('id')->get_required_fields();
    $fields = 'u.id,'.implode(',', $fields);
    if ($fullusers = get_users_by_capability($context, $cap, $fields, $orderby, '', '', $activegroup, '', false)) {
        learningtimecheck_apply_rules($fullusers);
        learningtimecheck_apply_namefilters($fullusers);
        if ($ltc->only_view_mentee_reports($context)) {
            // Filter to only show reports for users who this user mentors (ie they have been assigned to them in a context).
            $ltc->filter_mentee_users($fullusers);
        }
    }

    $found = false;
    $prev = 0;
    foreach ($fullusers as $user) {
        if ($user->id == $userid) {
            if ($prev) {
                return $fullusers[$prev];
            }
            return $fullusers[$user->id];
            // Keep on same if unique.
        }
        $prev = $user->id;
    }
    return array_shift($fullusers);
}

/**
 * Fetch all required users for a report screen
 */
function learningtimecheck_get_report_users($cm, $page, $perpage, $orderby, &$totalusers) {
    global $DB;

    $context = context_module::instance($cm->id);
    $activegroup = groups_get_activity_group($cm);
    // M4.
    $fields = \core_user\fields::for_name()->with_userpic()->excluding('id')->get_required_fields();

    $ausers = false;
    $cap = 'mod/learningtimecheck:updateown';
    if ($fullusers = get_users_by_capability($context, $cap, 'u.id,'.implode(',', $fields), $orderby, '', '', $activegroup, '', false)) {
        learningtimecheck_apply_rules($fullusers);
        learningtimecheck_apply_namefilters($fullusers);
        if (learningtimecheck_class::only_view_mentee_reports($context)) {
            // Filter to only show reports for users who this user mentors (ie they have been assigned to them in a context).
            learningtimecheck_class::filter_mentee_users($fullusers);
        }
    }
    $users = array_keys($fullusers);
    $totalusers = count($fullusers);

    if (!empty($users)) {
        $users = array_slice($users, $page * $perpage, $perpage);

        // Get back users from DB. Can this be optimized ?
        list($usql, $uparams) = $DB->get_in_or_equal($users);

        $sql = "
            SELECT
                u.id,
                ".implode(',', $fields)."
            FROM
                {user} u
            WHERE
                u.id {$usql}
            ORDER BY {$orderby}
        ";
        $ausers = $DB->get_records_sql($sql, $uparams);
    }

    return $ausers;
}