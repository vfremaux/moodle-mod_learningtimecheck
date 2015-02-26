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

if (!defined('MOODLE_INTERNAL')) {
    die('You cannot access this script this way');
}

/**
 * Stores all the functions for manipulating a learningtimecheck
 *
 * @author   David Smith <moodle@davosmith.co.uk>
 * @package  mod/learningtimecheck
 */

define("LEARNINGTIMECHECK_TEXT_INPUT_WIDTH", 45);
define("LEARNINGTIMECHECK_OPTIONAL_NO", 0);
define("LEARNINGTIMECHECK_OPTIONAL_YES", 1);
define("LEARNINGTIMECHECK_OPTIONAL_HEADING", 2);
//define("LEARNINGTIMECHECK_OPTIONAL_DISABLED", 3);  // Removed as new 'hidden' field added
//define("LEARNINGTIMECHECK_OPTIONAL_HEADING_DISABLED", 4);

define("LEARNINGTIMECHECK_HIDDEN_NO", 0);
define("LEARNINGTIMECHECK_HIDDEN_MANUAL", 1);
define("LEARNINGTIMECHECK_HIDDEN_BYMODULE", 2);

define ('LEARNINGTIMECHECK_DECLARATIVE_NO', 0);
define ('LEARNINGTIMECHECK_DECLARATIVE_STUDENTS', 1);
define ('LEARNINGTIMECHECK_DECLARATIVE_TEACHERS', 2);
define ('LEARNINGTIMECHECK_DECLARATIVE_BOTH', 3);

define('LEARNINGTIMECHECK_HPAGE_SIZE', 11);

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

    function __construct($cmid='staticonly', $userid=0, $learningtimecheck=null, $cm=null, $course=null) {
        global $COURSE, $DB, $CFG;

        if ($cmid == 'staticonly') {
            // Use static functions only !
            return;
        }

        $this->userid = $userid;
        $this->ignoreditems = array();

        if ($cm) {
            $this->cm = $cm;
        } elseif (!$this->cm = get_coursemodule_from_id('learningtimecheck', $cmid)) {
            print_error('invalidcoursemodule');
        }

        $this->context = context_module::instance($this->cm->id);

        if ($course) {
            $this->course = $course;
        } elseif ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else if (! $this->course = $DB->get_record('course', array('id' => $this->cm->course) )) {
            print_error('coursemisconf');
        }

        if ($learningtimecheck) {
            $this->learningtimecheck = $learningtimecheck;
        } elseif (! $this->learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $this->cm->instance) )) {
            error('learningtimecheck ID was incorrect');
        }
        
        if (!$this->learningtimecheck) {
            print_error('errorbadinstance', 'learningtimecheck', '', $cmid);
        }

        if (isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly && $this->learningtimecheck->autopopulate && $userid) {
            $this->groupings = self::get_user_groupings($userid, $this->course->id);
        } else {
            $this->groupings = false;
        }

        $this->strlearningtimecheck = get_string('modulename', 'learningtimecheck');
        $this->strlearningtimechecks = get_string('modulenameplural', 'learningtimecheck');
        $this->pagetitle = strip_tags($this->course->shortname.': '.$this->strlearningtimecheck.': '.format_string($this->learningtimecheck->name,true));

        $this->get_items();

        if ($this->learningtimecheck->autopopulate) {
            $this->update_items_from_course();
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
    function get_items() {
        global $DB, $CFG, $COURSE;

        // Load all shared learningtimecheck items
        $sql = 'learningtimecheck = ? ';
        $sql .= ' AND userid = 0';
        $this->items = $DB->get_records('learningtimecheck_item', array('learningtimecheck' => $this->learningtimecheck->id, 'userid' => 0), 'position');
        // Makes sure all items are numbered sequentially, starting at 1
        $this->update_item_positions(); // update before filtering or positions will be messed

        // Experimental : Filter out module bound items the user should not see
        // filtered out modules are moved to an ignored list for other process filtering
        // we just store cmid reference (auto_populate inhibition)
        foreach ($this->items as $iid => $item) {

            if (!$item->moduleid) continue;

            if ($item->itemoptional == LEARNINGTIMECHECK_OPTIONAL_HEADING) continue;

            $cm = $DB->get_record('course_modules', array('id' => $item->moduleid));
            if (!$cm) {
                // Deleted course modules. 
                // TODO : Cleanup the item list accordingly.
                continue;
            }

            if (!$cm->visible) {
                $this->ignoreditems[$iid] = $this->items[$iid]->moduleid;
                unset($this->items[$iid]);
            }

            // check agains group constraints
            if (!groups_course_module_visible($cm, $userid = null)) {
                $this->ignoreditems[$iid] = $this->items[$iid]->moduleid;
                unset($this->items[$iid]);
            }

            if ($COURSE->format == 'page') {
                // if paged, check the module is on a visible page
                if (!course_page::is_module_visible($cm, false)) {
                    $this->ignoreditems[$iid] = $this->items[$iid]->moduleid;
                    unset($this->items[$iid]);
                }
            }
        }

        // Load student's own learningtimecheck items
        if ($this->userid && $this->canaddown()) {
            $sql = 'learningtimecheck = ? ';//.$this->learningtimecheck->id;
            $sql .= ' AND userid = ? ';//.$this->userid;
            $this->useritems = $DB->get_records('learningtimecheck_item', array('learningtimecheck' => $this->learningtimecheck->id, 'userid' => $this->userid), 'position, id');
        } else {
            $this->useritems = false;
        }

        // Load the currently checked-off items
        if ($this->userid) { // && ($this->canupdateown() || $this->canviewreports() )) {
            $sql = '
                SELECT 
                    i.id, 
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
                } else if ($this->useritems && isset($this->useritems[$id])) {
                    $this->useritems[$id]->checked = $check->usertimestamp > 0;
                    $this->useritems[$id]->usertimestamp = $check->usertimestamp;
                    // User items never have a teacher mark to go with them
                }
            }
        }
    }

    function get_checks($userid, $hpage) {
        global $DB;
        
        $sql = "
            SELECT 
                i.id,
                i.moduleid,
                i.displaytext,
                i.itemoptional,
                i.credittime,
                i.hidden,
                c.usertimestamp,
                c.declaredtime,
                c.teachermark,
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
                i.userid=0
            ORDER BY
                i.position
        ";
        $checks = $DB->get_records_sql($sql, array($userid, $this->learningtimecheck->id), $hpage * LEARNINGTIMECHECK_HPAGE_SIZE, LEARNINGTIMECHECK_HPAGE_SIZE);

        return $checks;
    }

    /**
     * Redraw this function
     * Loop through all activities / resources in course and check they
     * are in the current learningtimecheck (in the right order)
     *
     */
    function update_items_from_course() {
        global $DB, $CFG, $COURSE;
        static $RELOADED = false;

        if ($RELOADED) {
            return;
        }

        $RELOADED = true;

        $mods = get_fast_modinfo($this->course);

        // Clean lost modules out clean them in whole course.
        $sql = "
            SELECT DISTINCT
                ltci.moduleid,
                ltci.id
            FROM
                {learningtimecheck_item} ltci
            LEFT JOIN
                {course_modules} cm
            ON
                ltci.moduleid = cm.id
            WHERE
                ltci.learningtimecheck = ? AND
                ltci.itemoptional <> ".LEARNINGTIMECHECK_OPTIONAL_HEADING." AND
                cm.id IS NULL
        ";
        $lostcms = $DB->get_records_sql($sql, array($this->learningtimecheck->id));
        if (!empty($lostcms)) {
            foreach ($lostcms as $lti) {
                unset($this->items[$lti->id]);
                $DB->delete_records('learningtimecheck_item', array('id' => $lti->id, 'learningtimecheck' => $this->learningtimecheck->id));
                $DB->delete_records('learningtimecheck_check', array('item' => $lti->id));
            }
        }

        // Renumber all items in sequence.
        $this->fix_positions();

        // Now scan for new.
        if ($COURSE->format != 'page') {
            $importsection = -1;
            if ($this->learningtimecheck->autopopulate == LEARNINGTIMECHECK_AUTOPOPULATE_SECTION) {
                foreach ($mods->get_sections() as $num => $section) {
                    if (in_array($this->cm->id, $section)) {
                        $importsection = $num;
                        break;
                    }
                }
            }
        } else {
            if (!$pageid = optional_param('page', 0, PARAM_INT)) {
                // Do not try to update anything while current page is not 
                // strictly defined. This might be less responsive, 
                // but much safer 
                // return;
                $page = course_page::get_current_page($COURSE->id, false);
                $importsection = $page->id;
            } else {
                $importsection = $pageid;
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
        if ($this->learningtimecheck->autopopulate == LEARNINGTIMECHECK_AUTOPOPULATE_SECTION) {
            $keeped[$importsection] = $sections[$importsection];
            $keepedindent[$importsection] = 1;
        } elseif ($this->learningtimecheck->autopopulate == LEARNINGTIMECHECK_AUTOPOPULATE_CURRENT_PAGE) {
            $keeped[$importsection] = $sections[$importsection];
            $importedpage = course_page::get_by_section($importsection);
            $keepedindent[$importsection] = $importedpage->get_page_depth();
        } elseif ($this->learningtimecheck->autopopulate == LEARNINGTIMECHECK_AUTOPOPULATE_CURRENT_PAGE_AND_SUBS) {
            $importedpage = course_page::get($importsection);
            if (empty($importedpage)) {
                return;
            }
            $keeped[$importsection] = $sections[$importsection];
            $keepedindent[$importsection] = $importedpage->get_page_depth();
            $children = course_page::get_all_pages($COURSE->id, 'flat', true, $fromparent = $importedpage->id);
            if ($children) {
                foreach ($children as $child) {
                    $keeped[$child->id] = $sections[$child->id];
                    $keepedindent[$child->id] = $child->get_page_depth();
                }
            }
        } elseif ($this->learningtimecheck->autopopulate == LEARNINGTIMECHECK_AUTOPOPULATE_CURRENT_TOP_PAGE) {
            $importedpage = course_page::get($importsection);
            $toppage = $importedpage->get_toplevel_parent();
            $keeped[$importedpage->id] = $sections[$importedpage->id];
            $keepedindent[$importedpage->id] = $toppage->get_page_depth();
            $children = course_page::get_all_pages($COURSE->id, 'flat', true, $fromparent = $importedpage->id);
            if ($children) {
                foreach ($children as $child) {
                    $keeped[$child->id] = $sections[$child->id];
                    $keepedindent[$child->id] = $child->get_page_depth();
                }
            }
        } else {
            $keeped = $sections;
        }

        foreach ($keeped as $sid => $section) {

            // We need ignore all non published modules when in page format.
            if (($COURSE->format == 'page') && ($sid == 0)) {
                continue;
            }

            if ($CFG->version >= 2012120300) {
                $sectionname = $courseformat->get_section_name($sid);
            } else {
                $sectionname = get_string('section').' '.$sid;
            }

            $sqlparams = array('learningtimecheck' => $this->learningtimecheck->id, 'moduleid' => $sid, 'itemoptional' => LEARNINGTIMECHECK_OPTIONAL_HEADING);
            if ($headingitem = $DB->get_record('learningtimecheck_item', $sqlparams)) {
                $headingitemid = $headingitem->id;
            }

            if (!$headingitem) {
                // If section name does not exist, create it.
                if (empty($sectionname)) {
                    $sectionname = 'Section';
                }
                $headingitemid = $this->additem($sectionname, 0, 0, $nextpos, false, $sid, LEARNINGTIMECHECK_OPTIONAL_HEADING);
            } else {
                if ($headingitem->displaytext != $sectionname) {
                    $headingitem->displaytext = $sectionname;
                }
                $headingitem->position = $nextpos;
                $DB->update_record('learningtimecheck_item', $headingitem);
                // echo "updating section at $nextpos $sid $sectionname <br/>";
            }

            $existingitems[$headingitemid] = true;

            // Increment for next coming modules.
            $nextpos++;
            while ($DB->get_field('learningtimecheck_item', 'moduleid', array('learningtimecheck' => $this->learningtimecheck->id, 'position' => $nextpos))) {
                $nextpos++;
            }

            foreach ($section as $cmid) {

                // Do not include this learningtimecheck (self) in the list of modules.
                if ($this->cm->id == $cmid) {
                    continue;
                }

                // Discard all label type modules.
                if (preg_match('/label$/', $mods->get_cm($cmid)->modname)) {
                    continue;
                }

                $sqlparams = array($this->learningtimecheck->id, $cmid, LEARNINGTIMECHECK_OPTIONAL_HEADING);

                $cmitem = $DB->get_record_select('learningtimecheck_item', " learningtimecheck = ? AND moduleid = ? AND itemoptional <> ? ", $sqlparams);

                $modname = $mods->get_cm($cmid)->name;

                if ($cmitem) {
                    $existingitems[$cmitem->id] = true;

                    $cmitem->position = $nextpos;
                    if ($cmitem->displaytext != $modname) {
                        $this->updateitemtext($cmitem->id, $modname);
                    }
                    if (($cmitem->hidden == LEARNINGTIMECHECK_HIDDEN_BYMODULE) && $mods->get_cm($cmid)->visible) {
                        // Course module was hidden and now is not.
                        $cmitem->hidden = LEARNINGTIMECHECK_HIDDEN_NO;
                    } elseif (($cmitem->hidden == LEARNINGTIMECHECK_HIDDEN_NO) && !$mods->get_cm($cmid)->visible) {
                        // Course module is now hidden.
                        $cmitem->hidden = LEARNINGTIMECHECK_HIDDEN_BYMODULE;
                    }

                    $groupingid = $mods->get_cm($cmid)->groupingid;
                    if ($groupmembersonly && $groupingid && $mods->get_cm($cmid)->groupmembersonly) {
                        if ($cmitem->grouping != $groupingid) {
                            $cmitem->grouping = $groupingid;
                        }
                    } else {
                        if (@$cmitem->grouping) {
                            $cmitem->grouping = 0;
                        }
                    }

                    $DB->update_record('learningtimecheck_item', $cmitem);
                    // echo "updating module at $nextpos $cmid $modname <br/>";
                } else {
                    // This is a new module that appeared in the meanwhile
                    $hidden = $mods->get_cm($cmid)->visible ? LEARNINGTIMECHECK_HIDDEN_NO : LEARNINGTIMECHECK_HIDDEN_BYMODULE;
                    $itemid = $this->additem($modname, 0, @$keepedindent[$sid] + 1, $nextpos, false, $cmid, LEARNINGTIMECHECK_OPTIONAL_NO, $hidden);
                    $changes = true;
                    $existingitems[$itemid] = true;
                    $DB->set_field('learningtimecheck_item', 'grouping', ($groupmembersonly && $mods->get_cm($cmid)->groupmembersonly) ? $mods->get_cm($cmid)->groupingid : 0, array('id' => $itemid));
                    // echo "creating module at $nextpos $cmid $modname <br/>";
                }
                $nextpos++;
                while ($DB->get_field('learningtimecheck_item', 'moduleid', array('learningtimecheck' => $this->learningtimecheck->id, 'position' => $nextpos))) {
                    $nextpos++;
                }
            }
        }

        // Delete any items that are related to activities / resources that have been deleted
        $existingids = implode("','", array_keys($existingitems));
        if ($baditems = $DB->get_records_select('learningtimecheck_item', " id NOT IN ('$existingids') AND learningtimecheck = ? ", array($this->learningtimecheck->id))) {
            foreach ($baditems as $item) {
                $DB->delete_records('learningtimecheck_comment', array('itemid' => $item->id));
                $DB->delete_records('learningtimecheck_check', array('item' => $item->id));
                $DB->delete_records('learningtimecheck_item', array('id' => $item->id));
            }
        }

        $this->get_items();
        $this->update_all_autoupdate_checks();
    }

    function removeauto() {
        if ($this->learningtimecheck->autopopulate) {
            return; // Still automatically populating the learningtimecheck, so don't remove the items
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
     function fix_positions() {
        global $DB;

        // Fix positions for all items.
        $position = 1;
        if ($allitems = $DB->get_records('learningtimecheck_item', array('learningtimecheck' => $this->learningtimecheck->id), 'position', 'id, position')) {
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
    function update_item_positions($move=0, $start=1, $end=false) {
        global $DB;

        $pos = 1;

        if (!$this->items) {
            return;
        }

        foreach($this->items as $item) {
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

    function get_item_at_position($position) {
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

    function canupdateown() {
        global $USER;

        return ($this->userid && ($this->userid != $USER->id)) && has_capability('mod/learningtimecheck:updateown', $this->context);
    }

    function canaddown() {
        global $USER;

        return $this->learningtimecheck->useritemsallowed && (!$this->userid || ($this->userid == $USER->id)) && has_capability('mod/learningtimecheck:updateown', $this->context);
    }

    function canpreview() {
        return has_capability('mod/learningtimecheck:preview', $this->context);
    }

    function canedit() {
        return has_capability('mod/learningtimecheck:edit', $this->context);
    }

    function caneditother() {
        return has_capability('mod/learningtimecheck:updateother', $this->context);
    }

    function canviewreports() {
        return has_capability('mod/learningtimecheck:viewreports', $this->context) || has_capability('mod/learningtimecheck:viewmenteereports', $this->context);
    }

    function canviewcoursecalibrationreport() {
        return has_capability('mod/learningtimecheck:viewcoursecalibrationreport', $this->context) && $this->learningtimecheck->usetimecounterpart;
    }

    function canviewtutorboard() {
        return has_capability('mod/learningtimecheck:viewtutorboard', $this->context) && $this->learningtimecheck->usetimecounterpart;
    }

    function only_view_mentee_reports() {
        return has_capability('mod/learningtimecheck:viewmenteereports', $this->context) && !has_capability('mod/learningtimecheck:viewreports', $this->context);
    }

    // Test if the current user is a mentor of the passed in user id
    static function is_mentor($userid) {
        global $USER, $DB;

        $sql = 'SELECT c.instanceid
                  FROM {role_assignments} ra
                  JOIN {context} c ON ra.contextid = c.id
                 WHERE c.contextlevel = '.CONTEXT_USER.'
                   AND ra.userid = ?
                   AND c.instanceid = ?';
        return $DB->record_exists_sql($sql, array($USER->id, $userid));
    }

    // Takes a list of userids and returns only those that the current user
    // is a mentor for (ones where the current user is assigned a role in their
    // user context)
    static function filter_mentee_users(&$users) {
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
        if ($tokeep = $DB->get_fieldset_sql($sql, $params)){
            $tokeepids = array_keys($tokeep);
            foreach ($users as $uid => $foo) {
                if (!in_array($uid, $tokeepids)) {
                    unset($users[$uid]);
                }
            }
        }
    }

    function user_complete() {
        global $PAGE;
        
        $renderer = $PAGE->get_renderer('learningtimecheck');
        $renderer->set_instance($this);
        $renderer->view_items(false, true);
    }

    function get_teachermark($itemid) {
        global $OUTPUT;

        if (!isset($this->items[$itemid])) {
            return array('','');
        }
        switch ($this->items[$itemid]->teachermark) {
        case LEARNINGTIMECHECK_TEACHERMARK_YES:
            return array($OUTPUT->pix_url('tick_box','learningtimecheck'),get_string('teachermarkyes','learningtimecheck'));

        case LEARNINGTIMECHECK_TEACHERMARK_NO:
            return array($OUTPUT->pix_url('cross_box','learningtimecheck'),get_string('teachermarkno','learningtimecheck'));

        default:
            return array($OUTPUT->pix_url('empty_box','learningtimecheck'),get_string('teachermarkundecided','learningtimecheck'));
        }
    }

    function view_import_export() {
        $importurl = new moodle_url('/mod/learningtimecheck/import.php', array('id' => $this->cm->id));
        $exporturl = new moodle_url('/mod/learningtimecheck/export.php', array('id' => $this->cm->id));

        $importstr = get_string('import', 'learningtimecheck');
        $exportstr = get_string('export', 'learningtimecheck');

        echo "<div class='learningtimecheckimportexport'>";
        echo "<a href='$importurl'>$importstr</a>&nbsp;&nbsp;&nbsp;<a href='$exporturl'>$exportstr</a>";
        echo "</div>";
    }


    function get_total_time($isql, $iparams){
        global $DB;

        return $DB->get_field_select('learningtimecheck_item', 'SUM(credittime)', " id $isql ", $iparams);
    }

    function get_acquired_time($sqlconds, $params){
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
        
        if (!empty($sqlconds)){
            $sql .= " AND $sqlconds ";
        }
        
        return $DB->get_field_sql($sql, $params);
    }

    /**
     * get total count in instance of items, validated items, total time and acquired time against
     * marking rules. 
     * @param int $userid examined user ID
     * @param object $reportsettings some settings comming from reporting requirements (f.e. showoptional)
     * @return a four component array of counts and times (in minutes)
     */
    function get_items_for_user($userid) {
        global $DB, $COURSE;

        $totalitems = 0;
        $totaloptionalitems = 0;

        if (empty($this->items)) {
            return(array(0, 0, 0, 0));
        }

        $itemstocount = array();
        $optionalitemstocount = array();
        foreach ($this->items as $item) {

            // Item is hidden administratively.
            if ($item->hidden){
                continue;
            }

            // No headings.
             if($item->itemoptional == LEARNINGTIMECHECK_OPTIONAL_HEADING){
                 continue;
             }

            // Not "my" item.
            if (($item->userid && $item->userid != $userid)){
                continue;
            }

            // Course module not in user's scope.
            if ($item->moduleid) {
                $cm = $DB->get_record('course_modules', array('id' => $item->moduleid));
                if ($cm){
                    if (!$cm->visible) {
                        continue; // quicker
                    }
                    if (!groups_course_module_visible($cm, $userid)) {
                        continue;
                    }
                }
            } else {
                continue; // No module
            }

            if ($COURSE->format == 'page') {
                // if paged, check the module is on a visible page.
                if (!course_page::is_module_visible($cm, false)) {
                    continue;
                }
            }

            if ($item->itemoptional == LEARNINGTIMECHECK_OPTIONAL_YES) {
                $totaloptionalitems++;
                $optionalitemstocount[] = $item->id;
            } else {
                $totalitems++;
                $itemstocount[] = $item->id;
            }

        }

        if ($totalitems) {
            $mandatories = $this->_get_tick_info($itemstocount, $userid);
            $mandatories['items'] = $totalitems;
            $mandatories['percentcomplete'] = ($totalitems) ? $mandatories['ticked'] / $mandatories['items'] : 0;
            $mandatories['percenttimecomplete'] = ($mandatories['time']) ? $mandatories['tickedtime'] / $mandatories['time'] : 0;
            $mandatories['timeleft'] = ($mandatories['time']) - $mandatories['tickedtime'];
            $mandatories['percenttimeleft'] = ($mandatories['time']) ? $mandatories['timeleft'] / $mandatories['time'] : 0;
        } else {
            $mandatories = array(
                'items' => 0,
                'time' => 0,
                'ticked' => 0,
                'tickedtime' => 0,
                'percentcomplete' => 0,
                'percenttimecomplete' => 0,
                'timeleft' => 0,
                'percenttimeleft' => 0);
        }

        if ($totaloptionalitems) {
            $optionals = $this->_get_tick_info($optionalitemstocount, $userid);
            $optionals['items'] = $totaloptionalitems;
            $optionals['percentcomplete'] = ($optionals['items']) ? $optionals['ticked'] / $optionals['items'] : 0;
            $optionals['percenttimecomplete'] = ($optionals['time']) ? $optionals['tickedtime'] / $optionals['time'] : 0 ;
            $optionals['timeleft'] = ($optionals['time']) - $optionals['tickedtime'];
            $optionals['percenttimeleft'] = ($optionals['time']) ? $optionals['timeleft'] / $optionals['time'] : 0;
        } else {
            $optionals = array(
                'items' => 0,
                'time' => 0,
                'ticked' => 0,
                'tickedtime' => 0,
                'percentcomplete' => 0,
                'percenttimecomplete' => 0,
                'timeleft' => 0,
                'percenttimeleft' => 1);
        }

        return array('mandatory' => $mandatories, 'optional' => $optionals);
    }


    private function _get_tick_info($itemlist, $userid) {
        global $DB;

        if (empty($itemlist)) return array('time' => 0, 'ticked' => 0, 'tickedtime' => 0);

        list($isql, $iparams) = $DB->get_in_or_equal($itemlist, SQL_PARAMS_NAMED);
        $totaltime = 0 + $this->get_total_time($isql, $iparams);
        if ($this->learningtimecheck->teacheredit == LEARNINGTIMECHECK_MARKING_STUDENT) {
            $sqlconds = ' usertimestamp > 0 AND item '.$isql.' AND lc.userid = :user ';
            $sqlconds2 = ' usertimestamp > 0 AND item '.$isql.' AND userid = :user ';
        } else {
            $sqlconds = ' teachermark = '.LEARNINGTIMECHECK_TEACHERMARK_YES.' AND item '.$isql.' AND lc.userid = :user ';
            $sqlconds2 = ' teachermark = '.LEARNINGTIMECHECK_TEACHERMARK_YES.' AND item '.$isql.' AND userid = :user ';
        }

        $iparams['user'] = $userid;
        $tickeditems = 0 + $DB->count_records_select('learningtimecheck_check', $sqlconds2, $iparams);
        $tickedtimes = 0 + $this->get_acquired_time($sqlconds, $iparams);

        return array('time' => $totaltime, 'ticked' => $tickeditems, 'tickedtime' => $tickedtimes);
    }

    function apply_to_all($fieldname, $value) {
        global $DB, $CFG;

        $sql = "
            UPDATE 
                {learningtimecheck_item}
            SET
                $fieldname = '$value'
            WHERE
                learningtimecheck = ?
        ";

        $DB->execute($sql, array($this->learningtimecheck->id), false);
        redirect($CFG->wwwroot.'/mod/learningtimecheck/edit.php?id='.$this->cm->id);
    }

    function get_report_settings() {
        global $SESSION;

        if (!isset($SESSION->learningtimecheck_report)) {
            $settings = new stdClass;
            $settings->showcompletiondates = false;
            $settings->showoptional = true;
            $settings->showprogressbars = false;
            $settings->showheaders = false;
            $settings->sortby = 'firstasc';
            $SESSION->learningtimecheck_report = $settings;
        }
        return clone $SESSION->learningtimecheck_report; // We want changes to settings to be explicit
    }

    function set_report_settings($settings) {
        global $SESSION, $CFG;

        $currsettings = $this->get_report_settings();
        foreach ($currsettings as $key => $currval) {
            if (isset($settings->$key)) {
                $currsettings->$key = $settings->$key; // Only set values if they already exist
            }
        }
        if ($CFG->debug == DEBUG_DEVELOPER) { // Show dev error if attempting to set non-existent setting
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
     * @param int $duetime If the imtem is manual and not related to a course module, then a due date can be recorded locally
     * @param int $moduleid the related module id when autopopulated
     * @param int $optional is the item optional ? the item accepts also a special LEARNINGTIMECHECK_OPTIONAL_HEADING case for section headings
     * @param boolean $hidden is the item used for checklist ? this allows disabling some autpopulated items that should not be used at all
     */
    function additem($displaytext, $userid = 0, $indent = 0, $position = false, $duetime = false, $moduleid = 0, $optional = LEARNINGTIMECHECK_OPTIONAL_NO, $hidden = LEARNINGTIMECHECK_HIDDEN_NO) {
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
                // $moduleid entries are added automatically, if the activity exists; ignore canedit check
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
        $item->duetime = 0;
        if ($duetime) {
            $item->duetime = make_timestamp($duetime['year'], $duetime['month'], $duetime['day']);
        }
        $item->eventid = 0;
        $item->colour = 'black';
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
                $this->items[$item->id]->teachermark = LEARNINGTIMECHECK_TEACHERMARK_UNDECIDED;
                uasort($this->items, 'learningtimecheck_itemcompare');
                if ($this->learningtimecheck->duedatesoncalendar) {
                    $this->setevent($item->id, true);
                }
            }
        }

        return $item->id;
    }

    function setevent($itemid, $add) {
        global $DB;

        $item = $this->items[$itemid];
        $update = false;

        if  ((!$add) || ($item->duetime == 0)) {  // Remove the event (if any)
            if (!$item->eventid) {
                return; // No event to remove
            }

            delete_event($item->eventid);
            $this->items[$itemid]->eventid = 0;
            $update = true;

        } else {  // Add/update event
            $event = new stdClass;
            $event->name = $item->displaytext;
            $event->description = get_string('calendardescription', 'learningtimecheck', $this->learningtimecheck->name);
            $event->courseid = $this->course->id;
            $event->modulename = 'learningtimecheck';
            $event->instance = $this->learningtimecheck->id;
            $event->eventtype = 'due';
            $event->timestart = $item->duetime;

            if ($item->eventid) {
                $event->id = $item->eventid;
                update_event($event);
            } else {
                $this->items[$itemid]->eventid = add_event($event);
                $update = true;
            }
        }

        if ($update) { // Event added or removed
            $upditem = new stdClass;
            $upditem->id = $itemid;
            $upditem->eventid = $this->items[$itemid]->eventid;
            $DB->update_record('learningtimecheck_item', $upditem);
        }
    }

    function setallevents() {
        if (!$this->items) {
            return;
        }

        $add = $this->learningtimecheck->duedatesoncalendar;
        foreach ($this->items as $key => $value) {
            $this->setevent($key, $add);
        }
    }

    function updateitemtext($itemid, $displaytext, $duetime = false) {
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

                $upditem->duetime = 0;
                if ($duetime) {
                    $upditem->duetime = make_timestamp($duetime['year'], $duetime['month'], $duetime['day']);
                }
                $this->items[$itemid]->duetime = $upditem->duetime;

                $DB->update_record('learningtimecheck_item', $upditem);

                if ($this->learningtimecheck->duedatesoncalendar) {
                    $this->setevent($itemid, true);
                }
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

    function toggledisableitem($itemid) {
        global $DB;

        if (isset($this->items[$itemid])) {
            if (!$this->canedit()) {
                return;
            }

            $item = $this->items[$itemid];
            if ($item->hidden == LEARNINGTIMECHECK_HIDDEN_NO) {
                $item->hidden = LEARNINGTIMECHECK_HIDDEN_MANUAL;
            } else if ($item->hidden == LEARNINGTIMECHECK_HIDDEN_MANUAL) {
                $item->hidden = LEARNINGTIMECHECK_HIDDEN_NO;
            }

            $upditem = new stdClass;
            $upditem->id = $itemid;
            $upditem->hidden = $item->hidden;
            $DB->update_record('learningtimecheck_item', $upditem);

            // If the item is a section heading, then show/hide all items in that section
            if ($item->itemoptional == LEARNINGTIMECHECK_OPTIONAL_HEADING) {
                if ($item->hidden) {
                    foreach ($this->items as $it) {
                        if ($it->position <= $item->position) {
                            continue;
                        }
                        if ($it->itemoptional == LEARNINGTIMECHECK_OPTIONAL_HEADING) {
                            break;
                        }
                        if (!$it->moduleid) {
                            continue;
                        }
                        if ($it->hidden == LEARNINGTIMECHECK_HIDDEN_NO) {
                            $it->hidden = LEARNINGTIMECHECK_HIDDEN_MANUAL;
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
                        if ($it->itemoptional == LEARNINGTIMECHECK_OPTIONAL_HEADING) {
                            break;
                        }
                        if (!$it->moduleid) {
                            continue;
                        }
                        if ($it->hidden == LEARNINGTIMECHECK_HIDDEN_MANUAL) {
                            $it->hidden = LEARNINGTIMECHECK_HIDDEN_NO;
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
    }

    function deleteitem($itemid, $forcedelete = false) {
        global $DB;

        if (isset($this->items[$itemid])) {
            if (!$forcedelete && !$this->canedit()) {
                return;
            }
            $this->setevent($itemid, false); // Remove any calendar events
            unset($this->items[$itemid]);
        } else if (isset($this->useritems[$itemid])) {
            if (!$this->canaddown()) {
                return;
            }
            unset($this->useritems[$itemid]);
        } else {
            // Item for deletion is not currently available
            return;
        }

        $DB->delete_records('learningtimecheck_item', array('id' => $itemid));
        $DB->delete_records('learningtimecheck_check', array('item' => $itemid));
        $DB->delete_records('learningtimecheck_comments', array('itemid' => $itemid));

        $this->update_item_positions();
    }

    function moveitemto($itemid, $newposition, $forceupdate=false) {
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
            $this->update_item_positions(1, $newposition, $oldposition); // Move items down
        } else {
            $this->update_item_positions(-1, $oldposition, $newposition); // Move items up (including this one)
        }

        $this->items[$itemid]->position = $newposition; // Move item to new position
        uasort($this->items, 'learningtimecheck_itemcompare'); // Sort the array by position
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->position = $newposition;
        $DB->update_record('learningtimecheck_item', $upditem); // Update the database
    }

    function moveitemup($itemid) {
        // TODO If indented, only allow move if suitable space for 'reparenting'

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                $this->moveitemto($itemid, $this->useritems[$itemid]->position - 1);
            }
            return;
        }
        $this->moveitemto($itemid, $this->items[$itemid]->position - 1);
    }

    function moveitemdown($itemid) {
        // TODO If indented, only allow move if suitable space for 'reparenting'

        if (!isset($this->items[$itemid])) {
            if (isset($this->useritems[$itemid])) {
                $this->moveitemto($itemid, $this->useritems[$itemid]->position + 1);
            }
            return;
        }
        $this->moveitemto($itemid, $this->items[$itemid]->position + 1);
    }


    function makeoptional($itemid, $optional, $heading=false) {
        global $DB;

        $item = $DB->get_record('learningtimecheck_item', array('id' => $itemid));

        if ($heading) {
            $optional = LEARNINGTIMECHECK_OPTIONAL_HEADING;
        } else if ($optional) {
            $optional = LEARNINGTIMECHECK_OPTIONAL_YES;
        } else {
            $optional = LEARNINGTIMECHECK_OPTIONAL_NO;
        }

        $item->itemoptional = $optional;
        $DB->update_record('learningtimecheck_item', $item);

        // renovate cache
        if (isset($this->items[$itemid])) {
            $this->items[$itemid] = $item;
        }
    }

    function hideitem($itemid) {
        global $DB;

        $item = $DB->get_record('learningtimecheck_item', array('id' => $itemid));

        $item->hidden = 1;
        $DB->update_record('learningtimecheck_item', $item);

        // renovate cache
        if (isset($this->items[$itemid])) {
            $this->items = $item;
        }
    }

    function showitem($itemid) {
        global $DB;
        
        $this->items[$itemid]->hidden = 0;
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->hidden = 0;
        $DB->update_record('learningtimecheck_item', $upditem);
    }

    function ajaxupdatechecks($changechecks) {
        // Convert array of itemid=>true/false, into array of all 'checked' itemids

        $newchecks = array();
        foreach ($this->items as $item) {
            if (array_key_exists($item->id, $changechecks)) {
                if ($changechecks[$item->id]) {
                    // Include in array if new status is true
                    $newchecks[] = $item->id;
                }
            } else {
                // If no new status, include in array if checked
                if ($item->checked) {
                    $newchecks[] = $item->id;
                }
            }
        }
        if ($this->useritems) {
            foreach ($this->useritems as $item) {
                if (array_key_exists($item->id, $changechecks)) {
                    if ($changechecks[$item->id]) {
                        // Include in array if new status is true
                        $newchecks[] = $item->id;
                    }
                } else {
                    // If no new status, include in array if checked
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
    function updatechecks($newchecks) {
        global $DB;

        if (!is_array($newchecks)) {
            // Something has gone wrong, so update nothing.
            return;
        }

        print_object($newchecks);
        add_to_log($this->course->id, 'learningtimecheck', 'update checks', "report.php?id={$this->cm->id}&studentid={$this->userid}", $this->learningtimecheck->name, $this->cm->id);

        $updategrades = false;
        $declaredtimes = optional_param_array('declaredtime', '', PARAM_INT);
        if ($this->items) {
            foreach ($this->items as $item) {

                // Declarative time may concern autoupdated items.
                $check = $DB->get_record_select('learningtimecheck_check', 'item = ? AND userid = ? ', array($item->id, $this->userid));
                if ((($item->isdeclarative == LEARNINGTIMECHECK_DECLARATIVE_STUDENTS) || ($item->isdeclarative == LEARNINGTIMECHECK_DECLARATIVE_BOTH)) && $item->checked) {
                    if (is_array($declaredtimes)) {
                        if (array_key_exists($item->id, $declaredtimes)) {
                            $check->declaredtime = $declaredtimes[$item->id];
                            $item->declaredtime = $declaredtimes[$item->id];
                            $DB->update_record('learningtimecheck_check', $check);
                        }
                    }
                }

                if (($this->learningtimecheck->autoupdate == LEARNINGTIMECHECK_AUTOUPDATE_YES) && ($item->moduleid)) {
                    continue; // Shouldn't get updated anyway, but just in case...
                }

                $newval = in_array($item->id, $newchecks);

                if ($newval != $item->checked) {
                    $updategrades = true;
                    $item->checked = $newval;

                    $check = $DB->get_record('learningtimecheck_check', array('item' => $item->id, 'userid' => $this->userid) );
                    if ($check) {
                        if ($newval) {
                            $check->usertimestamp = time();
                        } else {
                            $check->usertimestamp = 0;
                        }

                        $DB->update_record('learningtimecheck_check', $check);

                    } else {
                        $check = new stdClass;
                        $check->item = $item->id;
                        $check->userid = $this->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = LEARNINGTIMECHECK_TEACHERMARK_UNDECIDED;

                        $check->id = $DB->insert_record('learningtimecheck_check', $check);
                    }
                }
            }
        }
        if ($updategrades) {
            learningtimecheck_update_grades($this->learningtimecheck, $this->userid);
        }

        if ($this->useritems) {
            foreach ($this->useritems as $item) {
                $newval = in_array($item->id, $newchecks);

                if ($newval != $item->checked) {
                    $item->checked = $newval;

                    $check = $DB->get_record('learningtimecheck_check', array('item' => $item->id, 'userid' => $this->userid) );
                    if ($check) {
                        if ($newval) {
                            $check->usertimestamp = time();
                        } else {
                            $check->usertimestamp = 0;
                        }
                        $DB->update_record('learningtimecheck_check', $check);

                    } else {
                        $check = new stdClass;
                        $check->item = $item->id;
                        $check->userid = $this->userid;
                        $check->usertimestamp = time();
                        $check->teachertimestamp = 0;
                        $check->teachermark = LEARNINGTIMECHECK_TEACHERMARK_UNDECIDED;

                        $check->id = $DB->insert_record('learningtimecheck_check', $check);
                    }
                }
            }
        }
    }

    function updateteachermarks() {
        global $USER, $DB, $CFG;

        $newchecks = optional_param_array('items', array(), PARAM_TEXT);
        if (!is_array($newchecks)) {
            // Something has gone wrong, so update nothing.
            return;
        }

        $updategrades = false;
        if ($this->learningtimecheck->teacheredit != LEARNINGTIMECHECK_MARKING_STUDENT) {
            if (!$student = $DB->get_record('user', array('id' => $this->userid))) {
                print_error('erronosuchuser', 'learningtimecheck');
            }
            $info = $this->learningtimecheck->name.' ('.fullname($student, true).')';
            add_to_log($this->course->id, 'learningtimecheck', 'update checks', "report.php?id={$this->cm->id}&studentid={$this->userid}", $info, $this->cm->id);

            $teachermarklocked = $this->learningtimecheck->lockteachermarks && !has_capability('mod/learningtimecheck:updatelocked', $this->context);
            $teacherdeclaredtimesperuser = optional_param_array('teacherdeclaredtimeperuser', '', PARAM_INT);

            foreach ($newchecks as $itemid => $newval) {
                if (isset($this->items[$itemid])) {
                    $item = $this->items[$itemid];

                    if ($newval != $item->teachermark || $teacherdeclaredtimesperuser != $item->teacherdeclaredtime) {
                        $updategrades = true;

                        $newcheck = new stdClass;
                        $newcheck->teachertimestamp = time();
                        $newcheck->teacherdeclaredtime = $teacherdeclaredtimesperuser[$itemid];
                        if (!$teachermarklocked || $item->teachermark != LEARNINGTIMECHECK_TEACHERMARK_YES) {
                            $newcheck->teachermark = $newval;
                        } else {
                            $newcheck->teachermark = true;
                        }
                        $newcheck->teacherid = $USER->id;

                        $item->teachermark = $newcheck->teachermark;
                        $item->teachertimestamp = $newcheck->teachertimestamp;
                        $item->teacherdeclaredtime = $teacherdeclaredtimesperuser[$itemid];
                        $item->teacherid = $newcheck->teacherid;

                        $oldcheck = $DB->get_record('learningtimecheck_check', array('item' => $item->id, 'userid' => $this->userid) );
                        if ($oldcheck) {
                            $newcheck->id = $oldcheck->id;
                            $DB->update_record('learningtimecheck_check', $newcheck);
                        } else {
                            $newcheck->item = $itemid;
                            $newcheck->userid = $this->userid;
                            $newcheck->id = $DB->insert_record('learningtimecheck_check', $newcheck);
                        }
                    }
                }
            }
            if ($updategrades) {
                learningtimecheck_update_grades($this->learningtimecheck, $this->userid);
            }
        }

        $newcomments = optional_param_array('teachercomment', false, PARAM_TEXT);
        if (!$this->learningtimecheck->teachercomments || !$newcomments || !is_array($newcomments)) {
            return;
        }

        list($isql, $iparams) = $DB->get_in_or_equal(array_keys($this->items));
        $commentsunsorted = $DB->get_records_select('learningtimecheck_comment',"userid = ? AND itemid $isql", array_merge(array($this->userid), $iparams));
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

                        $DB->update_record('learningtimecheck_comment',$updatecomment);
                    }
                } else {
                    $addcomment = new stdClass;
                    $addcomment->itemid = $itemid;
                    $addcomment->userid = $this->userid;
                    $addcomment->commentby = $USER->id;
                    $addcomment->text = $newcomment;

                    $DB->insert_record('learningtimecheck_comment',$addcomment);
                }
            }
        }
    }

    function updateallteachermarks() {
        global $DB, $CFG, $USER;

        if ($this->learningtimecheck->teacheredit == LEARNINGTIMECHECK_MARKING_STUDENT) {
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
                if ($val != LEARNINGTIMECHECK_TEACHERMARK_NO && $val != LEARNINGTIMECHECK_TEACHERMARK_YES && $val != LEARNINGTIMECHECK_TEACHERMARK_UNDECIDED) {
                    continue; // Invalid value
                }
                if (!$itemid) {
                    continue;
                }
                if (!array_key_exists($itemid, $this->items)) {
                    continue; // Item is not part of this learningtimecheck
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

        $teachermarklocked = $this->learningtimecheck->lockteachermarks && !has_capability('mod/learningtimecheck:updatelocked', $this->context);

        foreach ($userchecks as $userid => $items) {
            list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));
            $params = array_merge(array($userid), $iparams);
            $currentchecks = $DB->get_records_select('learningtimecheck_check', "userid = ? AND item $isql", $params, '', 'item, id, teachermark');
            $updategrades = false;
            foreach ($items as $itemid => $val) {
                if (!array_key_exists($itemid, $currentchecks)) {
                    if ($val == LEARNINGTIMECHECK_TEACHERMARK_UNDECIDED) {
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

                } elseif ($currentchecks[$itemid]->teachermark != $val) {
                    if ($teachermarklocked && $currentchecks[$itemid]->teachermark == LEARNINGTIMECHECK_TEACHERMARK_YES) {
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

    function update_complete_scores() {
        global $DB;

        if (!$this->learningtimecheck->autopopulate || !$this->learningtimecheck->autoupdate) {
            return;
        }

        $newitems = optional_param_array('items', false, PARAM_INT);
        $newscores = optional_param_array('complete_score', false, PARAM_INT);
        $newcredittimes = optional_param_array('credittime', false, PARAM_INT);
        $newteachercredittimeperuser = optional_param_array('teachercredittimeperuser', false, PARAM_INT);
        $newteachercredittimes = optional_param_array('teachercredittime', false, PARAM_INT);
        $newenablecredits = optional_param_array('enablecredit', false, PARAM_INT);
        $newisdeclaratives = optional_param_array('isdeclarative', false, PARAM_INT);

        if ((!$newitems || !$newscores || !is_array($newscores)) && (!$newcredittimes) && (!$newenablecredits) && (!$newisdeclaratives) && (!$newteachercredittimes) && (!$newteachercredittimeperuser)) {
            // perf trap
            return;
        }

        $changed = false;
        foreach ($newitems as $itemid) {
            $newscore = 0 + @$newscore[$itemid];
            $newcredittime = 0 + @$newcredittimes[$itemid];
            $newteachercredittime = 0 + @$newteachercredittimes[$itemid];
            $newteachercredittimeperuser = 0 + @$newteachercredittimeperuser[$itemid];
            $newisdeclarative = 0 + @$newisdeclaratives[$itemid];
            $newenablecredit = 0 + @$newenablecredits[$itemid];

            if ($upditem = $DB->get_record('learningtimecheck_item', array('id' => $itemid))) {
                $upditem->complete_score = $newscore;
                $upditem->credittime = $newcredittime;
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
                $item->enablecredit = $newenablecredit;
                $item->isdeclarative = $newisdeclarative;
                $item->teachercredittime = $newteachercredittime;
                $item->teachercredittimeperuser = $newteachercredittimeperuser;
            }

            $this->update_all_autoupdate_checks();
        }
    }


    function update_all_autoupdate_checks() {
        global $DB;

        if (!$this->learningtimecheck->autoupdate) {
            return;
        }

        $users = get_users_by_capability($this->context, 'mod/learningtimecheck:updateown', 'u.id', '', '', '', '', '', false);
        if (!$users) {
            return;
        }
        $userids = implode(',',array_keys($users));

        // Get a list of all the learningtimecheck items with a module linked to them (ignoring headings)
        $sql = "
            SELECT DISTINCT
                cm.id AS cmid, 
                m.name AS mod_name, 
                i.id AS itemid, 
                cm.completion AS completion
            FROM 
                {modules} m, 
                {course_modules} cm, 
                {learningtimecheck_item} i
            WHERE 
                m.id = cm.module AND 
                cm.id = i.moduleid AND 
                i.moduleid > 0 AND 
                i.learningtimecheck = ? AND 
                i.itemoptional != 2
        ";

        $completion = new completion_info($this->course);
        $using_completion = $completion->is_enabled();

        $items = $DB->get_records_sql($sql, array($this->learningtimecheck->id));
        foreach ($items as $item) {
            if ($using_completion && $item->completion) {
                $fakecm = new stdClass;
                $fakecm->id = $item->cmid;

                foreach ($users as $user) {
                    $comp_data = $completion->get_data($fakecm, false, $user->id);
                    if ($comp_data->completionstate == COMPLETION_COMPLETE || $comp_data->completionstate == COMPLETION_COMPLETE_PASS) {
                        $check = $DB->get_record('learningtimecheck_check', array('item' => $item->itemid, 'userid' => $user->id));
                        if ($check) {
                            if ($check->usertimestamp) {
                                continue;
                            }
                            $check->usertimestamp = time();
                            $DB->update_record('learningtimecheck_check', $check);
                        } else {
                            $check = new stdClass;
                            $check->item = $item->itemid;
                            $check->userid = $user->id;
                            $check->usertimestamp = time();
                            $check->teachertimestamp = 0;
                            $check->teachermark = LEARNINGTIMECHECK_TEACHERMARK_UNDECIDED;

                            $check->id = $DB->insert_record('learningtimecheck_check', $check);
                        }
                    }
                }

                continue;
            }

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
            case 'feedback':
                $logaction = 'submit';
                break;
            default:
                continue 2;
                break;
            }

            $sql = 'SELECT DISTINCT userid ';
            $sql .= "FROM {log} ";
            $sql .= "WHERE cmid = ? AND (action = ?";
            if ($logaction2) {
                $sql .= ' OR action = ?';
            }
            $sql .= ") AND userid IN ($userids)";
            $log_entries = $DB->get_records_sql($sql, array($item->cmid, $logaction, $logaction2));

            if (!$log_entries) {
                continue;
            }

            foreach ($log_entries as $entry) {
                //echo "User: {$entry->userid} has completed '{$item->mod_name}' with cmid {$item->cmid}, so updating learningtimecheck item {$item->itemid}<br />\n";

                $check = $DB->get_record('learningtimecheck_check', array('item' => $item->itemid, 'userid' => $entry->userid));
                if ($check) {
                    if ($check->usertimestamp) {
                        continue;
                    }
                    $check->usertimestamp = time();
                    $DB->update_record('learningtimecheck_check', $check);
                } else {
                    $check = new stdClass;
                    $check->item = $item->itemid;
                    $check->userid = $entry->userid;
                    $check->usertimestamp = time();
                    $check->teachertimestamp = 0;
                    $check->teachermark = LEARNINGTIMECHECK_TEACHERMARK_UNDECIDED;

                    $check->id = $DB->insert_record('learningtimecheck_check', $check);
                }
            }

            // Always update the grades
            learningtimecheck_update_grades($this->learningtimecheck);
        }
    }

    // Update the userid to point to the next user to view
    function getnextuserid() {
        global $DB;

        $activegroup = groups_get_activity_group($this->cm, true);
        $settings = $this->get_report_settings();
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

    static function print_user_progressbar($learningtimecheckid, $userid, $width='300px', $showpercent=true, $return=false, $hidecomplete=false) {
        global $OUTPUT;

        list($ticked, $total) = learningtimecheck_class::get_user_progress($learningtimecheckid, $userid);
        if (!$total) {
            return '';
        }
        if ($hidecomplete && ($ticked == $total)) {
            return '';
        }
        $percent = $ticked * 100 / $total;

        // TODO - fix this now that styles.css is included
        $output = '<div class="learningtimecheck_progress_outer" style="width: '.$width.';" >';
        $output .= '<div class="learningtimecheck_progress_inner" style="width:'.$percent.'%; background-image: url('.$OUTPUT->pix_url('progress','learningtimecheck').');" >&nbsp;</div>';
        $output .= '</div>';
        if ($showpercent) {
            $output .= '<span class="learningtimecheck_progress_percent">&nbsp;'.sprintf('%0d%%', $percent).'</span>';
        }
        $output .= '<br style="clear:both;" />';
        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }

    static function get_user_progress($learningtimecheckid, $userid) {
        global $DB, $CFG;

        $userid = intval($userid); // Just to be on the safe side...

        $learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $learningtimecheckid) );
        if (!$learningtimecheck) {
            return array(false, false);
        }
        $groupings_sel = '';
        if (isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly && $learningtimecheck->autopopulate) {
            $groupings = self::get_user_groupings($userid, $learningtimecheck->course);
            $groupings[] = 0;
            $groupings_sel = ' AND grouping IN ('.implode(',',$groupings).') ';
        }
        $items = $DB->get_records_select('learningtimecheck_item', 'learningtimecheck = ? AND userid = 0 AND itemoptional = '.LEARNINGTIMECHECK_OPTIONAL_NO.' AND hidden = '.LEARNINGTIMECHECK_HIDDEN_NO.$groupings_sel, array($learningtimecheck->id), '', 'id');
        if (empty($items)) {
            return array(false, false);
        }
        $total = count($items);
        list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));
        $params = array_merge(array($userid), $iparams);

        $sql = "userid = ? AND item $isql AND ";
        if ($learningtimecheck->teacheredit == LEARNINGTIMECHECK_MARKING_STUDENT) {
            $sql .= 'usertimestamp > 0';
        } else {
            $sql .= 'teachermark = '.LEARNINGTIMECHECK_TEACHERMARK_YES;
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
    static function get_logop_options() {
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
    static function get_rule_options($contexttype = 'course') {
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
        } elseif ($contexttype == 'user') {
            $options['usercreationdate'] = get_string('usercreationdate', 'learningtimecheck');
            $options['sitefirstevent'] = get_string('sitefirstevent', 'learningtimecheck');
            $options['sitelastevent'] = get_string('sitelastevent', 'learningtimecheck');
            $options['firstcoursestarted'] = get_string('firstcoursestarted', 'learningtimecheck');
            $options['firstcoursecompleted'] = get_string('firstcoursecompleted', 'learningtimecheck');
        } elseif ($contexttype == 'cohort') {
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
    static function get_ruleop_options() {

        $options = array();

        $options['gt'] = '>';
        $options['gte'] = '>=';
        $options['lt'] = '<';
        $options['lte'] = '<=';
        $options['eq'] = '=';
        $options['neq'] = '<>';

        return $options;
    }

}

function learningtimecheck_itemcompare($item1, $item2) {
    if ($item1->position < $item2->position) {
        return -1;
    } elseif ($item1->position > $item2->position) {
        return 1;
    }
    if ($item1->id < $item2->id) {
        return -1;
    } elseif ($item1->id > $item2->id) {
        return 1;
    }
    return 0;
}

/**
* brings back all the learningtimechecks user has tracks in
*
*/
function learningtimecheck_get_my_checks($userid, $arrangemode = 'flat', $courseid = 0) {
    global $DB;

    $courseclause = ($courseid) ? " AND lt.course = $courseid " : '' ;

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
            $results[$c->id] = new learningtimecheck_class($cm->id, 0);
        }
    }

    if ($arrangemode == 'flat') {
        return $results;
    }

    // else is 'by course'

    $bycourse = array();
    foreach ($results as $cid => $check) {
        $bycourse[$check->learningtimecheck->course][$cid] = $check;
    }
    return $bycourse;
}


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
    if ($thickness == 'thin'){
        $timearray['6'] = '4 '.$minutesstr;
        $timearray['7'] = '4 '.$minutesstr;
        $timearray['8'] = '4 '.$minutesstr;
        $timearray['9'] = '4 '.$minutesstr;
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
    }
    
    return $timearray;
}

function learningtimecheck_get_teacher_mark_options(){

    $options = array();
    $options[LEARNINGTIMECHECK_TEACHERMARK_UNDECIDED] = '';
    $options[LEARNINGTIMECHECK_TEACHERMARK_YES] = get_string('yes');
    $options[LEARNINGTIMECHECK_TEACHERMARK_NO] = get_string('no');
    
    return $options;
}

function learningtimecheck_add_paged_params() {
    global $COURSE;
    if ($COURSE->format == 'page') {
        if ($pageid = optional_param('page', 0, PARAM_INT)) {
            echo "<input type=\"hidden\" name=\"page\" value=\"$pageid\" />";
        }
    }
}

/**
 * Counts all evaluable items forgetting headings
 *
 *
 */
function learningtimecheck_count_total_items($courseid = 0, $userid = 0, $showhidden = false) {
    global $DB, $COURSE, $USER;

    $courseclause = ($courseid) ? ' l.course =  $courseid ' : '';

    if ($userid && !$courseid) {
        $courses = get_my_courses($userid);
        if ($courses) {
            $idlist = implode("','", array_keys($courses));
        }
        $courseclause = " l.course IN ('$list') ";
    }

    $module = $DB->get_record('modules', array('name' => 'learningtimecheck'));

    $showhiddenclause = ($showhidden) ? ' AND li.hidden = 0 ' : '';

    $sql = "
        SELECT 
            li.*
        FROM
            {learningtimecheck_item} li,
            {learningtimecheck} l,
            {course_modules} cm
        WHERE
            l.id = li.learningtimecheck AND
            cm.instance = l.id AND
            cm.module = ? AND
            cm.visible = 1 AND
            l.course = ? AND
            li.itemoptional <> ".LEARNINGTIMECHECK_OPTIONAL_HEADING."
            $showhiddenclause
    ";

    $itemscount = 0;
    $optionalitemscount = 0;
    $time = 0;
    $optionaltime = 0;

    if ($items = $DB->get_records_sql($sql, array($module->id, $courseid))) {

        foreach($items as $item) {
            if ($item->itemoptional == LEARNINGTIMECHECK_OPTIONAL_NO) {
                $itemscount++;
                $time += $item->credittime;
            } else {
                $optionalitemscount++;
                $optionaltime += $item->credittime;
            }
        }
    } else {
        return array(0,0,0);
    }

    return array('count' => $itemscount, 'time' => $time, 'optionalcount' => $optionalitemscount, 'optionaltime' => $optionaltime);
}

function learningtimecheck_item_get_colour($item, $what = 'current') {
    switch ($item->colour) {
    case 'red':
        $itemcolour = 'itemred';
        $nexticon = 'colour_orange';
        break;
    case 'orange':
        $itemcolour = 'itemorange';
        $nexticon = 'colour_green';
        break;
    case 'green':
        $itemcolour = 'itemgreen';
        $nexticon = 'colour_purple';
        break;
    case 'purple':
        $itemcolour = 'itempurple';
        $nexticon = 'colour_black';
        break;
    default:
        $itemcolour = 'itemblack';
        $nexticon = 'colour_red';
    }

    if ($what == 'next') return $nexticon;
    return $itemcolour;
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

