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
 * Stores all the functions for manipulating a learningtimecheck
 *
 * @author   David Smith <moodle@davosmith.co.uk>
 * @package  mod/learningtimecheck
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

define("learningtimecheck_TEXT_INPUT_WIDTH", 45);
define("learningtimecheck_OPTIONAL_NO", 0);
define("learningtimecheck_OPTIONAL_YES", 1);
define("learningtimecheck_OPTIONAL_HEADING", 2);
//define("learningtimecheck_OPTIONAL_DISABLED", 3);  // Removed as new 'hidden' field added
//define("learningtimecheck_OPTIONAL_HEADING_DISABLED", 4);

define("learningtimecheck_HIDDEN_NO", 0);
define("learningtimecheck_HIDDEN_MANUAL", 1);
define("learningtimecheck_HIDDEN_BYMODULE", 2);

class learningtimecheck_class {
    var $cm;
    var $course;
    var $learningtimecheck;
    var $strlearningtimechecks;
    var $strlearningtimecheck;
    var $context;
    var $userid;
    var $items;
    var $useritems;
    var $useredit;
    var $additemafter;
    var $editdates;
    var $groupings;

    function learningtimecheck_class($cmid='staticonly', $userid=0, $learningtimecheck=null, $cm=null, $course=null) {
        global $COURSE, $DB, $CFG;

        if ($cmid == 'staticonly') {
            //use static functions only!
            return;
        }

        $this->userid = $userid;

        if ($cm) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('learningtimecheck', $cmid)) {
            error('Course Module ID was incorrect');
        }

        if ($CFG->version < 2011120100) {
            $this->context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
        } else {
            $this->context = context_module::instance($this->cm->id);
        }

        if ($course) {
            $this->course = $course;
        } else if ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else if (! $this->course = $DB->get_record('course', array('id' => $this->cm->course) )) {
            error('Course is misconfigured');
        }

        if ($learningtimecheck) {
            $this->learningtimecheck = $learningtimecheck;
        } else if (! $this->learningtimecheck = $DB->get_record('learningtimecheck', array('id' => $this->cm->instance) )) {
            error('learningtimecheck ID was incorrect');
        }

        if (isset($CFG->enablegroupmembersonly) && $CFG->enablegroupmembersonly && $learningtimecheck->autopopulate && $userid) {
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
     * Get an array of the items in a learningtimecheck
     *
     */
    function get_items() {
        global $DB;

        // Load all shared learningtimecheck items
        $sql = 'learningtimecheck = ? ';
        $sql .= ' AND userid = 0';
        $this->items = $DB->get_records('learningtimecheck_item', array('learningtimecheck' => $this->learningtimecheck->id, 'userid' => 0), 'position');

        // Makes sure all items are numbered sequentially, starting at 1
        $this->update_item_positions();

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

    /**
     * Loop through all activities / resources in course and check they
     * are in the current learningtimecheck (in the right order)
     *
     */
    function update_items_from_course() {
        global $DB, $CFG;

        $mods = get_fast_modinfo($this->course);

		if (!learningtimecheck_course_is_page_formatted()){
	        $importsection = -1;
	        if ($this->learningtimecheck->autopopulate == learningtimecheck_AUTOPOPULATE_SECTION) {
	            foreach ($mods->get_sections() as $num => $section) {
	                if (in_array($this->cm->id, $section)) {
	                    $importsection = $num;
	                    break;
	                }
	            }
	        }
		} else {
	    	require_once($CFG->dirroot.'/course/format/page/lib.php');
	    	require_once($CFG->dirroot.'/course/format/page/xlib.php');
	    	if (!$pageid = optional_param('page', 0, PARAM_INT)){
	    		// Do not try to update anything while current page is not 
	    		// strictly defined. This might be less responsive, 
	    		// but much safer 
	    		return;
		    	// $page = page_get_current_page($COURSE->id, false);
	            // $importsection = $page->id;
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
        while ($section <=  $numsections) {
            if (!array_key_exists($section, $sections)) {
                $section++;
                continue;
            }

            if ($importsection > 0 && $importsection != $section) {
                $section++; // Only importing the section with the learningtimecheck in it
                continue;
            }

            $sectionheading = 0;
            while (list($itemid, $item) = each($this->items)) {
                // Search from current position
                if (($item->moduleid == $section) && ($item->itemoptional == learningtimecheck_OPTIONAL_HEADING)) {
                    $sectionheading = $itemid;
                    break;
                }
            }

            if (!$sectionheading) {
                // Search again from the start
                foreach ($this->items as $item) {
                    if (($item->moduleid == $section) && ($item->itemoptional == learningtimecheck_OPTIONAL_HEADING)) {
                        $sectionheading = $itemid;
                        break;
                    }
                }
                reset($this->items);
            }

            if ($CFG->version >= 2012120300) {
                $sectionname = $courseformat->get_section_name($section);
            } else {
                $sectionname = get_string('section').' '.$section;
            }
            if (!$sectionheading) {
                //echo 'adding section '.$section.'<br/>';
                $sectionheading = $this->additem($sectionname, 0, 0, false, false, $section, learningtimecheck_OPTIONAL_HEADING);
                reset($this->items);
            } else {
                if ($this->items[$sectionheading]->displaytext != $sectionname) {
                    $this->updateitemtext($sectionheading, $sectionname);
                }
            }
            $this->items[$sectionheading]->stillexists = true;

            if ($this->items[$sectionheading]->position < $nextpos) {
                $this->moveitemto($sectionheading, $nextpos, true);
                reset($this->items);
            }
            $nextpos = $this->items[$sectionheading]->position + 1;

            foreach($sections[$section] as $cmid) {
                if ($this->cm->id == $cmid) {
                    continue; // Do not include this learningtimecheck in the list of modules
                }
                if ($mods->get_cm($cmid)->modname == 'label') {
                    continue; // Ignore any labels
                }

                $foundit = false;
                while(list($itemid, $item) = each($this->items)) {
                    // Search list from current position (will usually be the next item)
                    if (($item->moduleid == $cmid) && ($item->itemoptional != learningtimecheck_OPTIONAL_HEADING)) {
                        $foundit = $item;
                        break;
                    }
                    if (($item->moduleid == 0) && ($item->position == $nextpos)) {
                        // Skip any items that are not linked to modules
                        $nextpos++;
                    }
                }
                if (!$foundit) {
                    // Search list again from the start (just in case)
                    foreach($this->items as $item) {
                        if (($item->moduleid == $cmid) && ($item->itemoptional != learningtimecheck_OPTIONAL_HEADING)) {
                            $foundit = $item;
                            break;
                        }
                    }
                    reset($this->items);
                }
                $modname = $mods->get_cm($cmid)->name;
                if ($foundit) {
                    $item->stillexists = true;
                    if ($item->position != $nextpos) {
                        //echo 'reposition '.$item->displaytext.' => '.$nextpos.'<br/>';
                        $this->moveitemto($item->id, $nextpos, true);
                        reset($this->items);
                    }
                    if ($item->displaytext != $modname) {
                        $this->updateitemtext($item->id, $modname);
                    }
                    if (($item->hidden == learningtimecheck_HIDDEN_BYMODULE) && $mods->get_cm($cmid)->visible) {
                        // Course module was hidden and now is not
                        $item->hidden = learningtimecheck_HIDDEN_NO;
                        $upd = new stdClass;
                        $upd->id = $item->id;
                        $upd->hidden = $item->hidden;
                        $DB->update_record('learningtimecheck_item', $upd);
                        $changes = true;

                    } else if (($item->hidden == learningtimecheck_HIDDEN_NO) && !$mods->get_cm($cmid)->visible) {
                        // Course module is now hidden
                        $item->hidden = learningtimecheck_HIDDEN_BYMODULE;
                        $upd = new stdClass;
                        $upd->id = $item->id;
                        $upd->hidden = $item->hidden;
                        $DB->update_record('learningtimecheck_item', $upd);
                        $changes = true;
                    }

                    $groupingid = $mods->get_cm($cmid)->groupingid;
                    if ($groupmembersonly && $groupingid && $mods->get_cm($cmid)->groupmembersonly) {
                        if ($item->grouping != $groupingid) {
                            $item->grouping = $groupingid;
                            $upd = new stdClass;
                            $upd->id = $item->id;
                            $upd->grouping = $groupingid;
                            $DB->update_record('learningtimecheck_item', $upd);
                            $changes = true;
                        }
                    } else {
                        if ($item->grouping) {
                            $item->grouping = 0;
                            $upd = new stdClass;
                            $upd->id = $item->id;
                            $upd->grouping = 0;
                            $DB->update_record('learningtimecheck_item', $upd);
                            $changes = true;
                        }
                    }
                } else {
                    //echo '+++adding item '.$name.' at '.$nextpos.'<br/>';
                    $hidden = $mods->get_cm($cmid)->visible ? learningtimecheck_HIDDEN_NO : learningtimecheck_HIDDEN_BYMODULE;
                    $itemid = $this->additem($modname, 0, 0, $nextpos, false, $cmid, learningtimecheck_OPTIONAL_NO, $hidden);
                    $changes = true;
                    reset($this->items);
                    $this->items[$itemid]->stillexists = true;
                    $this->items[$itemid]->grouping = ($groupmembersonly && $mods->get_cm($cmid)->groupmembersonly) ? $mods->get_cm($cmid)->groupingid : 0;
                    $item = $this->items[$itemid];
                }
                $item->modulelink = new moodle_url('/mod/'.$mods->get_cm($cmid)->modname.'/view.php', array('id' => $cmid));
                $nextpos++;
            }

            $section++;
        }

        // Delete any items that are related to activities / resources that have been deleted
        if ($this->items) {
            foreach($this->items as $item) {
                if ($item->moduleid && !isset($item->stillexists)) {
                    //echo '---deleting item '.$item->displaytext.'<br/>';
                    $this->deleteitem($item->id, true);
                    $changes = true;
                }
            }
        }

        if ($changes) {
            $this->update_all_autoupdate_checks();
        }
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
     * Check all items are numbered sequentially from 1
     * then, move any items between $start and $end
     * the number of places indicated by $move
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
        return (!$this->userid || ($this->userid == $USER->id)) && has_capability('mod/learningtimecheck:updateown', $this->context);
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
        return has_capability('mod/learningtimecheck:viewcoursecalibrationreport', $this->context);
    }

    function canviewtutorboard() {
        return has_capability('mod/learningtimecheck:viewtutorboard', $this->context);
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
    static function filter_mentee_users($userids) {
        global $DB, $USER;

        list($usql, $uparams) = $DB->get_in_or_equal($userids);
        $sql = 'SELECT c.instanceid
                  FROM {role_assignments} ra
                  JOIN {context} c ON ra.contextid = c.id
                 WHERE c.contextlevel = '.CONTEXT_USER.'
                   AND ra.userid = ?
                   AND c.instanceid '.$usql;
        $params = array_merge(array($USER->id), $uparams);
        return $DB->get_fieldset_sql($sql, $params);
    }

    function view() {
        global $OUTPUT;

        if ((!$this->items) && $this->canedit()) {
            redirect(new moodle_url('/mod/learningtimecheck/edit.php', array('id' => $this->cm->id)) );
        }

        if ($this->canupdateown()) {
            $currenttab = 'view';
        } else if ($this->canpreview()) {
            $currenttab = 'preview';
        } else {
            if ($this->canviewreports()) { // No editing, but can view reports
                redirect(new moodle_url('/mod/learningtimecheck/report.php', array('id' => $this->cm->id)));
            } else {
                $this->view_header();

                echo $OUTPUT->heading(format_string($this->learningtimecheck->name));
                echo $OUTPUT->confirm('<p>' . get_string('guestsno', 'learningtimecheck') . "</p>\n\n<p>" .
                                      get_string('liketologin') . "</p>\n", get_login_url(), get_referer(false));
                echo $OUTPUT->footer();
                die;
            }
            $currenttab = '';
        }

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->learningtimecheck->name));

        $this->view_tabs($currenttab);

        add_to_log($this->course->id, 'learningtimecheck', 'view', "view.php?id={$this->cm->id}", $this->learningtimecheck->name, $this->cm->id);

        if ($this->canupdateown()) {
            $this->process_view_actions();
        }

        $this->view_items();

        $this->view_footer();
    }


    function edit() {
        global $OUTPUT;

        if (!$this->canedit()) {
            redirect(new moodle_url('/mod/learningtimecheck/view.php', array('id' => $this->cm->id)) );
        }

        add_to_log($this->course->id, "learningtimecheck", "edit", "edit.php?id={$this->cm->id}", $this->learningtimecheck->name, $this->cm->id);

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->learningtimecheck->name));

        $this->view_tabs('edit');
        
        $this->process_edit_actions();

        if ($this->learningtimecheck->autopopulate) {
            // Needs to be done again, just in case the edit actions have changed something
            $this->update_items_from_course();
        }

        $this->view_import_export();

        $this->view_edit_items();

        $this->view_footer();
    }

    function report() {
        global $OUTPUT;

        if ((!$this->items) && $this->canedit()) {
            redirect(new moodle_url('/mod/learningtimecheck/edit.php', array('id' => $this->cm->id)) );
        }

        if (!$this->canviewreports()) {
            redirect(new moodle_url('/mod/learningtimecheck/view.php', array('id' => $this->cm->id)) );
        }

        if ($this->userid && $this->only_view_mentee_reports()) {
            // Check this user is a mentee of the logged in user
            if (!$this->is_mentor($this->userid)) {
                $this->userid = false;
            }

        } else if (!$this->caneditother()) {
            $this->userid = false;
        }

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->learningtimecheck->name));

        $this->view_tabs('report');

        $this->process_report_actions();

        if ($this->userid) {
            $this->view_items(true);
        } else {
            add_to_log($this->course->id, "learningtimecheck", "report", "report.php?id={$this->cm->id}", $this->learningtimecheck->name, $this->cm->id);
            $this->view_report();
        }

        $this->view_footer();
    }

    function user_complete() {
        $this->view_items(false, true);
    }

    function view_header() {
        global $PAGE, $OUTPUT;

        $PAGE->set_title($this->pagetitle);
        $PAGE->set_heading($this->course->fullname);

        echo $OUTPUT->header();
    }

    function view_tabs($currenttab) {
    	global $CFG;
    	
        $tabs = array();
        $row = array();
        $inactive = array();
        $activated = array();
        
        $pageattr = '';
        if (learningtimecheck_course_is_page_formatted()){
        	if ($pageid = optional_param('page', 0, PARAM_INT)){
        		$pageattr = 'page='.$pageid;
        	}
        }

        if ($this->canupdateown()) {
            $row[] = new tabobject('view', new moodle_url('/mod/learningtimecheck/view.php', array('id' => $this->cm->id)), get_string('view', 'learningtimecheck'));
        } else if ($this->canpreview()) {
            $row[] = new tabobject('preview', new moodle_url('/mod/learningtimecheck/view.php', array('id' => $this->cm->id)), get_string('preview', 'learningtimecheck'));
        }
        if ($this->canviewreports()) {
            $row[] = new tabobject('report', new moodle_url('/mod/learningtimecheck/report.php', array('id' => $this->cm->id)), get_string('report', 'learningtimecheck'));
        }
        if ($this->canviewcoursecalibrationreport()) {
            $row[] = new tabobject('calibrationreport', "$CFG->wwwroot/mod/learningtimecheck/coursecalibrationreport.php?id={$this->cm->id}{$pageattr}", get_string('coursecalibrationreport', 'learningtimecheck'));
        }
        if ($this->canviewtutorboard()) {
            $row[] = new tabobject('tutorboard', "$CFG->wwwroot/mod/learningtimecheck/coursetutorboard.php?id={$this->cm->id}{$pageattr}", get_string('tutorboard', 'learningtimecheck'));
        }
        if ($this->canedit()) {
            $row[] = new tabobject('edit', new moodle_url('/mod/learningtimecheck/edit.php', array('id' => $this->cm->id)), get_string('edit', 'learningtimecheck'));
        }

        if (count($row) == 1) {
            // No tabs for students
        } else {
            $tabs[] = $row;
        }

        if ($currenttab == 'report') {
            $activated[] = 'report';
        }

        if ($currenttab == 'calibrationreport') {
            $activated[] = 'calibrationreport';
        }

        if ($currenttab == 'edit') {
            $activated[] = 'edit';

            if (!$this->items) {
                $inactive = array('view', 'report', 'preview');
            }
        }

        if ($currenttab == 'preview') {
            $activated[] = 'preview';
        }

        print_tabs($tabs, $currenttab, $inactive, $activated);
    }

    function view_progressbar() {
        global $OUTPUT;

        if (empty($this->items)) {
            return;
        }

        $teacherprogress = ($this->learningtimecheck->teacheredit != learningtimecheck_MARKING_STUDENT);

        $totalitems = 0;
        $requireditems = 0;
        $completeitems = 0;
        $allcompleteitems = 0;
        $checkgroupings = $this->learningtimecheck->autopopulate && ($this->groupings !== false);
        foreach ($this->items as $item) {
            if (($item->itemoptional == learningtimecheck_OPTIONAL_HEADING)||($item->hidden)) {
                continue;
            }
            if ($checkgroupings && !empty($item->grouping)) {
                if (!in_array($item->grouping, $this->groupings)) {
                    continue; // Current user is not a member of this item's grouping
                }
            }
            if ($item->itemoptional == learningtimecheck_OPTIONAL_NO) {
                $requireditems++;
                if ($teacherprogress) {
                    if ($item->teachermark == learningtimecheck_TEACHERMARK_YES) {
                        $completeitems++;
                        $allcompleteitems++;
                    }
                } else if ($item->checked) {
                    $completeitems++;
                    $allcompleteitems++;
                }
            } else if ($teacherprogress) {
                if ($item->teachermark == learningtimecheck_TEACHERMARK_YES) {
                    $allcompleteitems++;
                }
            } else if ($item->checked) {
                $allcompleteitems++;
            }
            $totalitems++;
        }
        if (!$teacherprogress) {
            if ($this->useritems) {
                foreach ($this->useritems as $item) {
                    if ($item->checked) {
                        $allcompleteitems++;
                    }
                    $totalitems++;
                }
            }
        }
        if ($totalitems == 0) {
            return;
        }

        $allpercentcomplete = ($allcompleteitems * 100) / $totalitems;

        if ($requireditems > 0 && $totalitems > $requireditems) {
            $percentcomplete = ($completeitems * 100) / $requireditems;
            echo '<div style="display:block; float:left; width:150px;" class="learningtimecheck_progress_heading">';
            echo get_string('percentcomplete','learningtimecheck').':&nbsp;';
            echo '</div>';
            echo '<span id="learningtimecheckprogressrequired">';
            echo '<div class="learningtimecheck_progress_outer">';
            echo '<div class="learningtimecheck_progress_inner" style="width:'.$percentcomplete.'%; background-image: url('.$OUTPUT->pix_url('progress','learningtimecheck').');" >&nbsp;</div>';
            echo '<div class="learningtimecheck_progress_anim" style="width:'.$percentcomplete.'%; background-image: url('.$OUTPUT->pix_url('progress-fade', 'learningtimecheck').');" >&nbsp;</div>';
            echo '</div>';
            echo '<span class="learningtimecheck_progress_percent">&nbsp;'.sprintf('%0d',$percentcomplete).'% </span>';
            echo '</span>';
            echo '<br style="clear:both"/>';
        }

        echo '<div style="display:block; float:left; width:150px;" class="learningtimecheck_progress_heading">';
        echo get_string('percentcompleteall','learningtimecheck').':&nbsp;';
        echo '</div>';
        echo '<span id="learningtimecheckprogressall">';
        echo '<div class="learningtimecheck_progress_outer">';
        echo '<div class="learningtimecheck_progress_inner" style="width:'.$allpercentcomplete.'%; background-image: url('.$OUTPUT->pix_url('progress','learningtimecheck').');" >&nbsp;</div>';
        echo '<div class="learningtimecheck_progress_anim" style="width:'.$allpercentcomplete.'%; background-image: url('.$OUTPUT->pix_url('progress-fade', 'learningtimecheck').');" >&nbsp;</div>';
        echo '</div>';
        echo '<span class="learningtimecheck_progress_percent">&nbsp;'.sprintf('%0d',$allpercentcomplete).'% </span>';
        echo '</span>';
        echo '<br style="clear:both"/>';
    }

    function get_teachermark($itemid) {
        global $OUTPUT;

        if (!isset($this->items[$itemid])) {
            return array('','');
        }
        switch ($this->items[$itemid]->teachermark) {
        case learningtimecheck_TEACHERMARK_YES:
            return array($OUTPUT->pix_url('tick_box','learningtimecheck'),get_string('teachermarkyes','learningtimecheck'));

        case learningtimecheck_TEACHERMARK_NO:
            return array($OUTPUT->pix_url('cross_box','learningtimecheck'),get_string('teachermarkno','learningtimecheck'));

        default:
            return array($OUTPUT->pix_url('empty_box','learningtimecheck'),get_string('teachermarkundecided','learningtimecheck'));
        }
    }

    function view_items($viewother = false, $userreport = false) {
        global $DB, $OUTPUT, $PAGE, $CFG;

        echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter learningtimecheckbox');

        echo html_writer::tag('div', '&nbsp;', array('id' => 'learningtimecheckspinner'));

        $comments = $this->learningtimecheck->teachercomments;
        $editcomments = false;
        $thispage = new moodle_url('/mod/learningtimecheck/view.php', array('id' => $this->cm->id) );

        $teachermarklocked = false;
        $showcompletiondates = false;
        if ($viewother) {
            if ($comments) {
                $editcomments = optional_param('editcomments', false, PARAM_BOOL);
            }
            $thispage = new moodle_url('/mod/learningtimecheck/report.php', array('id' => $this->cm->id, 'studentid' => $this->userid) );

            if (!$student = $DB->get_record('user', array('id' => $this->userid) )) {
                error('No such user!');
            }

            $info = $this->learningtimecheck->name.' ('.fullname($student, true).')';
            add_to_log($this->course->id, "learningtimecheck", "report", "report.php?id={$this->cm->id}&studentid={$this->userid}", $info, $this->cm->id);

            echo '<h2>'.get_string('learningtimecheckfor','learningtimecheck').' '.fullname($student, true).'</h2>';
            echo '&nbsp;';
            echo '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
            echo html_writer::input_hidden_params($thispage, array('studentid'));
            echo '<input type="submit" name="viewall" value="'.get_string('viewall','learningtimecheck').'" />';
            echo '</form>';

            if (!$editcomments) {
                echo '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
                echo html_writer::input_hidden_params($thispage);
                echo '<input type="hidden" name="editcomments" value="on" />';
                echo ' <input type="submit" name="viewall" value="'.get_string('addcomments','learningtimecheck').'" />';
                echo '</form>';
            }
            echo '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
            echo html_writer::input_hidden_params($thispage);
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="hidden" name="action" value="toggledates" />';
            echo ' <input type="submit" name="toggledates" value="'.get_string('toggledates','learningtimecheck').'" />';
            echo '</form>';

            $teachermarklocked = $this->learningtimecheck->lockteachermarks && !has_capability('mod/learningtimecheck:updatelocked', $this->context);

            $reportsettings = $this->get_report_settings();
            $showcompletiondates = $reportsettings->showcompletiondates;

            $strteacherdate = get_string('teacherdate', 'mod_learningtimecheck');
            $struserdate = get_string('userdate', 'mod_learningtimecheck');
            $strteachername = get_string('teacherid', 'mod_learningtimecheck');

            if ($showcompletiondates) {
                $teacherids = array();
                foreach ($this->items as $item) {
                    if ($item->teacherid) {
                        $teacherids[$item->teacherid] = $item->teacherid;
                    }
                }
                $teachers = $DB->get_records_list('user', 'id', $teacherids, '', 'id, firstname, lastname');
                foreach ($this->items as $item) {
                    if (isset($teachers[$item->teacherid])) {
                        $item->teachername = fullname($teachers[$item->teacherid]);
                    } else {
                        $item->teachername = false;
                    }
                }
            }
        }

        $intro = file_rewrite_pluginfile_urls($this->learningtimecheck->intro, 'pluginfile.php', $this->context->id, 'mod_learningtimecheck', 'intro', null);
        $opts = array('trusted' => $CFG->enabletrusttext);
        echo format_text($intro, $this->learningtimecheck->introformat, $opts);
        echo '<br/>';

        $showteachermark = false;
        $showcheckbox = true;
        if ($this->canupdateown() || $viewother || $userreport) {
            $this->view_progressbar();
            $showteachermark = ($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_TEACHER) || ($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_BOTH);
            $showcheckbox = ($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_STUDENT) || ($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_BOTH);
            $teachermarklocked = $teachermarklocked && $showteachermark; // Make sure this is OFF, if not showing teacher marks
        }
        
        $overrideauto = ($this->learningtimecheck->autoupdate != learningtimecheck_AUTOUPDATE_YES);
        $checkgroupings = $this->learningtimecheck->autopopulate && ($this->groupings !== false);

        if (!$this->items) {
            print_string('noitems','learningtimecheck');
        } else {
            $focusitem = false;
            $updateform = ($showcheckbox && $this->canupdateown() && !$viewother && !$userreport) || ($viewother && ($showteachermark || $editcomments));
            $addown = $this->canaddown() && $this->useredit;
            if ($updateform) {
                if ($this->canaddown() && !$viewother) {
                    echo '<form style="display:inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage);
                    if ($addown) {
                        $thispage->param('useredit','on'); // Switch on for any other forms on this page (but off if this form submitted)
                        echo '<input type="submit" name="submit" value="'.get_string('addownitems-stop','learningtimecheck').'" />';
                    } else {
                        echo '<input type="hidden" name="useredit" value="on" />';
                        echo '<input type="submit" name="submit" value="'.get_string('addownitems','learningtimecheck').'" />';
                    }
                    echo '</form>';
                }

                if (!$viewother) {
                    // Load the Javascript required to send changes back to the server (without clicking 'save')
                    if ($CFG->version < 2012120300) { // < Moodle 2.4
                        $jsmodule = array(
                            'name' => 'mod_learningtimecheck',
                            'fullpath' => new moodle_url('/mod/learningtimecheck/updatechecks.js')
                        );
                        $PAGE->requires->yui2_lib('dom');
                        $PAGE->requires->yui2_lib('event');
                        $PAGE->requires->yui2_lib('connection');
                        $PAGE->requires->yui2_lib('animation');
                    } else {
                        $jsmodule = array(
                            'name' => 'mod_learningtimecheck',
                            'fullpath' => new moodle_url('/mod/learningtimecheck/updatechecks24.js')
                        );
                    }
                    $updatechecksurl = new moodle_url('/mod/learningtimecheck/updatechecks.php');
                    $updateprogress = $showteachermark ? 0 : 1; // Progress bars should only be updated with 'student only' learningtimechecks
                    $PAGE->requires->js_init_call('M.mod_learningtimecheck.init', array($updatechecksurl->out(), sesskey(), $this->cm->id, $updateprogress), true, $jsmodule);
                }

                echo '<form action="'.$thispage->out_omit_querystring().'" method="post">';
                echo html_writer::input_hidden_params($thispage);
            	echo learningtimecheck_add_paged_params();
                echo '<input type="hidden" name="action" value="updatechecks" />';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            }

            if ($this->useritems) {
                reset($this->useritems);
            }

            if ($comments) {
                list($isql, $iparams) = $DB->get_in_or_equal(array_keys($this->items));
                $params = array_merge(array($this->userid), $iparams);
                $commentsunsorted = $DB->get_records_select('learningtimecheck_comment',"userid = ? AND itemid $isql", $params);
                $commentuserids = array();
                $commentusers = array();
                if (!empty($commentsunsorted)) {
                    $comments = array();
                    foreach ($commentsunsorted as $comment) {
                        $comments[$comment->itemid] = $comment;
                        if ($comment->commentby) {
                            $commentuserids[] = $comment->commentby;
                        }
                    }
                    if (!empty($commentuserids)) {
                        list($csql, $cparams) = $DB->get_in_or_equal(array_unique($commentuserids, SORT_NUMERIC));
                        $commentusers = $DB->get_records_select('user', 'id '.$csql, $cparams);
                    }
                } else {
                    $comments = false;
                }
            }

            if ($teachermarklocked) {
                echo '<p style="learningtimecheckwarning">'.get_string('lockteachermarkswarning', 'learningtimecheck').'</p>';
            }

            echo '<ol class="learningtimecheck" id="learningtimecheckouter">';
            $currindent = 0;
            foreach ($this->items as $item) {
            	
                if ($item->hidden) {
                    continue;
                }

                if ($checkgroupings && !empty($item->grouping)) {
                    if (!in_array($item->grouping, $this->groupings)) {
                        continue; // Current user is not a member of this item's grouping, so skip
                    }
                }

                while ($item->indent > $currindent) {
                    $currindent++;
                    echo '<ol class="learningtimecheck">';
                }
                while ($item->indent < $currindent) {
                    $currindent--;
                    echo '</ol>';
                }
                $itemname = '"item'.$item->id.'"';
                $checked = (($updateform || $viewother || $userreport) && $item->checked) ? ' checked="checked" ' : '';
                if ($viewother || $userreport) {
                    $checked .= ' disabled="disabled" ';
                } else if (!$overrideauto && $item->moduleid) {
                    $checked .= ' disabled="disabled" ';
                }
                switch ($item->colour) {
                case 'red':
                    $itemcolour = 'itemred';
                    break;
                case 'orange':
                    $itemcolour = 'itemorange';
                    break;
                case 'green':
                    $itemcolour = 'itemgreen';
                    break;
                case 'purple':
                    $itemcolour = 'itempurple';
                    break;
                default:
                    $itemcolour = 'itemblack';
                }

                if ($item->itemoptional == learningtimecheck_OPTIONAL_HEADING) {
                    $optional = ' class="itemheading '.$itemcolour.'" ';
                    $spacerimg = $OUTPUT->pix_url('check_spacer','learningtimecheck');
                } else if ($item->itemoptional == learningtimecheck_OPTIONAL_YES) {
                    $optional = ' class="itemoptional '.$itemcolour.'" ';
                    $checkclass = ' itemoptional';
                } else {
                    $optional = ' class="'.$itemcolour.'" ';
                    $checkclass = '';
                }

                echo '<li>';
                if ($showteachermark) {
                    if ($item->itemoptional == learningtimecheck_OPTIONAL_HEADING) {
                        //echo '<img src="'.$spacerimg.'" alt="" title="" />';
                    } else {
                        if ($viewother) {
                            $disabled = ($teachermarklocked && $item->teachermark == learningtimecheck_TEACHERMARK_YES) ? 'disabled="disabled" ' : '';

                            $selu = ($item->teachermark == learningtimecheck_TEACHERMARK_UNDECIDED) ? 'selected="selected" ' : '';
                            $sely = ($item->teachermark == learningtimecheck_TEACHERMARK_YES) ? 'selected="selected" ' : '';
                            $seln = ($item->teachermark == learningtimecheck_TEACHERMARK_NO) ? 'selected="selected" ' : '';

                            echo '<select name="items['.$item->id.']" '.$disabled.'>';
                            echo '<option value="'.learningtimecheck_TEACHERMARK_UNDECIDED.'" '.$selu.'></option>';
                            echo '<option value="'.learningtimecheck_TEACHERMARK_YES.'" '.$sely.'>'.get_string('yes').'</option>';
                            echo '<option value="'.learningtimecheck_TEACHERMARK_NO.'" '.$seln.'>'.get_string('no').'</option>';
                            echo '</select>';
                        } else {
                            list($imgsrc, $titletext) = $this->get_teachermark($item->id);
                            echo '<img src="'.$imgsrc.'" alt="'.$titletext.'" title="'.$titletext.'" />';
                        }
                    }
                }
                if ($showcheckbox) {
                    if ($item->itemoptional == learningtimecheck_OPTIONAL_HEADING) {
                        //echo '<img src="'.$spacerimg.'" alt="" title="" />';
                    } else {
                        echo '<input class="learningtimecheckitem'.$checkclass.'" type="checkbox" name="items[]" id='.$itemname.$checked.' value="'.$item->id.'" />';
                    }
                }
                echo '<label for='.$itemname.$optional.'>'.s($item->displaytext).'</label>';
                
				$context = context_module::instance($this->cm->id);
				if (@$item->isdeclarative > 0){
					if ($USER->id != $this->userid && has_capability('mod/learningtimecheck:updateother', $context) && ($item->isdeclarative > learningtimecheck_DECLARATIVE_STUDENTS)){
						$declaredtimedisabled = ($item->teachermark == learningtimecheck_TEACHERMARK_UNDECIDED);
						$teachermarkinitialtime = ($item->teachermark == learningtimecheck_TEACHERMARK_UNDECIDED) ? 0 : $item->teacherdeclaredtime;
						echo '<br/>'.get_string('teachertimetodeclare', 'learningtimecheck');
						echo html_writer::select(learningtimecheck_get_credit_times(), "teacherdeclaredtime[$item->id]", $teachermarkinitialtime, '', array('onchange' => 'learningtimecheck_updatechecks_show()', 'id' => 'declaredtime'.$item->id));

						if (@$item->declaredtime){
							echo ' '.get_string('studenthasdeclared', 'learningtimecheck', $item->declaredtime);
						}
					}
					
					if (($USER->id == $this->userid) && (($item->isdeclarative == learningtimecheck_DECLARATIVE_STUDENTS) || ($item->isdeclarative == learningtimecheck_DECLARATIVE_BOTH))){
						if (has_capability('mod/learningtimecheck:updateother', $context)){
						} else {
							$declaredtimedisabled = (!$item->checked);
							echo '<br/>'.get_string('timetodeclare', 'learningtimecheck');
							echo html_writer::select(learningtimecheck_get_credit_times(), "declaredtime[$item->id]", $item->declaredtime, '', array('onchenge' => 'learningtimecheck_updatechecks_show()', 'id' => 'declaredtime'.$item->id));
						}
					}
				}

                if (isset($item->modulelink)) {
                    echo '&nbsp;<a href="'.$item->modulelink.'"><img src="'.$OUTPUT->pix_url('follow_link','learningtimecheck').'" alt="'.get_string('linktomodule','learningtimecheck').'" /></a>';
                }

                if ($addown) {
                    echo '&nbsp;<a href="'.$thispage->out(true, array('itemid'=>$item->id, 'sesskey'=>sesskey(), 'action'=>'startadditem') ).'">';
                    $title = '"'.get_string('additemalt','learningtimecheck').'"';
                    echo '<img src="'.$OUTPUT->pix_url('add','learningtimecheck').'" alt='.$title.' title='.$title.' /></a>';
                }

                if ($item->duetime) {
                    if ($item->duetime > time()) {
                        echo '<span class="learningtimecheck-itemdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                    } else {
                        echo '<span class="learningtimecheck-itemoverdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                    }
                }

                if ($showcompletiondates) {
                    if ($item->itemoptional != learningtimecheck_OPTIONAL_HEADING) {
                        if ($showteachermark && $item->teachermark != learningtimecheck_TEACHERMARK_UNDECIDED && $item->teachertimestamp) {
                            if ($item->teachername) {
                                echo '<span class="itemteachername" title="'.$strteachername.'">'.$item->teachername.'</span>';
                            }
                            echo '<span class="itemteacherdate" title="'.$strteacherdate.'">'.userdate($item->teachertimestamp, get_string('strftimedatetimeshort')).'</span>';
                        }
                        if ($showcheckbox && $item->checked && $item->usertimestamp) {
                            echo '<span class="itemuserdate" title="'.$struserdate.'">'.userdate($item->usertimestamp, get_string('strftimedatetimeshort')).'</span>';
                        }
                    }
                }

                $foundcomment = false;
                if ($comments) {
                    if (array_key_exists($item->id, $comments)) {
                        $comment =  $comments[$item->id];
                        $foundcomment = true;
                        echo ' <span class="teachercomment">&nbsp;';
                        if ($comment->commentby) {
                            $userurl = new moodle_url('/user/view.php', array('id'=>$comment->commentby, 'course'=>$this->course->id) );
                            echo '<a href="'.$userurl.'">'.fullname($commentusers[$comment->commentby]).'</a>: ';
                        }
                        if ($editcomments) {
                            $outid = '';
                            if (!$focusitem) {
                                $focusitem = 'firstcomment';
                                $outid = ' id="firstcomment" ';
                            }
                            echo '<input type="text" name="teachercomment['.$item->id.']" value="'.s($comment->text).'" '.$outid.'/>';
                        } else {
                            echo s($comment->text);
                        }
                        echo '&nbsp;</span>';
                    }
                }
                if (!$foundcomment && $editcomments) {
                    echo '&nbsp;<input type="text" name="teachercomment['.$item->id.']" />';
                }

                echo '</li>';

                // Output any user-added items
                if ($this->useritems) {
                    $useritem = current($this->useritems);

                    if ($useritem && ($useritem->position == $item->position)) {
                        $thisitemurl = clone $thispage;
                        $thisitemurl->param('action', 'updateitem');
                        $thisitemurl->param('sesskey', sesskey());

                        echo '<ol class="learningtimecheck">';
                        while ($useritem && ($useritem->position == $item->position)) {
                            $itemname = '"item'.$useritem->id.'"';
                            $checked = ($updateform && $useritem->checked) ? ' checked="checked" ' : '';
                            if (isset($useritem->editme)) {
                                $itemtext = explode("\n", $useritem->displaytext, 2);
                                $itemtext[] = '';
                                $text = $itemtext[0];
                                $note = $itemtext[1];
                                $thisitemurl->param('itemid', $useritem->id);

                                echo '<li>';
                                echo '<div style="float: left;">';
                                if ($showcheckbox) {
                                    echo '<input class="learningtimecheckitem itemoptional" type="checkbox" name="items[]" id='.$itemname.$checked.' disabled="disabled" value="'.$useritem->id.'" />';
                                }
                                echo '<form style="display:inline" action="'.$thisitemurl->out_omit_querystring().'" method="post">';
                                echo html_writer::input_hidden_params($thisitemurl);
            					echo learningtimecheck_add_paged_params();
                                echo '<input type="text" size="'.learningtimecheck_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.s($text).'" id="updateitembox" />';
                                echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','learningtimecheck').'" />';
                                echo '<br />';
                                echo '<textarea name="displaytextnote" rows="3" cols="25">'.s($note).'</textarea>';
                                echo '</form>';
                                echo '</div>';

                                echo '<form style="display:inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
                                echo html_writer::input_hidden_params($thispage);
            					echo learningtimecheck_add_paged_params();
                                echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','learningtimecheck').'" />';
                                echo '</form>';
                                echo '<br style="clear: both;" />';
                                echo '</li>';

                                $focusitem = 'updateitembox';
                            } else {
                                echo '<li>';
                                if ($showcheckbox) {
                                    echo '<input class="learningtimecheckitem itemoptional" type="checkbox" name="items[]" id='.$itemname.$checked.' value="'.$useritem->id.'" />';
                                }
                                $splittext = explode("\n",s($useritem->displaytext),2);
                                $splittext[] = '';
                                $text = $splittext[0];
                                $note = str_replace("\n",'<br />',$splittext[1]);
                                echo '<label class="useritem" for='.$itemname.'>'.$text.'</label>';

                                if ($addown) {
                                    $baseurl = $thispage.'&amp;itemid='.$useritem->id.'&amp;sesskey='.sesskey().'&amp;action=';
                                    echo '&nbsp;<a href="'.$baseurl.'edititem">';
                                    $title = '"'.get_string('edititem','learningtimecheck').'"';
                                    echo '<img src="'.$OUTPUT->pix_url('/t/edit').'" alt='.$title.' title='.$title.' /></a>';

                                    echo '&nbsp;<a href="'.$baseurl.'deleteitem" class="deleteicon">';
                                    $title = '"'.get_string('deleteitem','learningtimecheck').'"';
                                    echo '<img src="'.$OUTPUT->pix_url('remove','learningtimecheck').'" alt='.$title.' title='.$title.' /></a>';
                                }
                                if ($note != '') {
                                    echo '<div class="note">'.$note.'</div>';
                                }

                                echo '</li>';
                            }
                            $useritem = next($this->useritems);
                        }
                        echo '</ol>';
                    }
                }

                if ($addown && ($item->id == $this->additemafter)) {
                    $thisitemurl = clone $thispage;
                    $thisitemurl->param('action', 'additem');
                    $thisitemurl->param('position', $item->position);
                    $thisitemurl->param('sesskey', sesskey());

                    echo '<ol class="learningtimecheck"><li>';
                    echo '<div style="float: left;">';
                    echo '<form action="'.$thispage->out_omit_querystring().'" method="post">';
                    echo html_writer::input_hidden_params($thisitemurl);
            		echo learningtimecheck_add_paged_params();
                    if ($showcheckbox) {
                        echo '<input type="checkbox" disabled="disabled" />';
                    }
                    echo '<input type="text" size="'.learningtimecheck_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
                    echo '<input type="submit" name="additem" value="'.get_string('additem','learningtimecheck').'" />';
                    echo '<br />';
                    echo '<textarea name="displaytextnote" rows="3" cols="25"></textarea>';
                    echo '</form>';
                    echo '</div>';

                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage);
   					echo learningtimecheck_add_paged_params();
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','learningtimecheck').'" />';
                    echo '</form>';
                    echo '<br style="clear: both;" />';
                    echo '</li></ol>';

                    if (!$focusitem) {
                        $focusitem = 'additembox';
                    }
                }
            }
            echo '</ol>';

            if ($updateform) {
                echo '<input id="learningtimechecksavechecks" type="submit" name="submit" value="'.get_string('savechecks','learningtimecheck').'" />';
                if ($viewother) {
                    echo '&nbsp;<input type="submit" name="save" value="'.get_string('savechecks', 'mod_learningtimecheck').'" />';
                    echo '&nbsp;<input type="submit" name="savenext" value="'.get_string('saveandnext').'" />';
                    echo '&nbsp;<input type="submit" name="viewnext" value="'.get_string('next').'" />';
                }
                echo '</form>';
            }

            if ($focusitem) {
                echo '<script type="text/javascript">document.getElementById("'.$focusitem.'").focus();</script>';
            }

            if ($addown) {
                echo '<script type="text/javascript">';
                echo 'function confirmdelete(url) {';
                echo 'if (confirm("'.get_string('confirmdeleteitem','learningtimecheck').'")) { window.location = url; } ';
                echo '} ';
                echo 'var links = document.getElementById("learningtimecheckouter").getElementsByTagName("a"); ';
                echo 'for (var i in links) { ';
                echo 'if (links[i].className == "deleteicon") { ';
                echo 'var url = links[i].href;';
                echo 'links[i].href = "#";';
                echo 'links[i].onclick = new Function( "confirmdelete(\'"+url+"\')" ) ';
                echo '}} ';
                echo '</script>';
            }
        }

        echo $OUTPUT->box_end();
    }

    function print_edit_date($ts=0) {
        // TODO - use fancy JS calendar instead

        $id=rand();
        if ($ts == 0) {
            $disabled = true;
            $date = usergetdate(time());
        } else {
            $disabled = false;
            $date = usergetdate($ts);
        }
        $day = $date['mday'];
        $month = $date['mon'];
        $year = $date['year'];

        echo '<select name="duetime[day]" id="timedueday'.$id.'" >';
        for ($i=1; $i<=31; $i++) {
            $selected = ($i == $day) ? 'selected="selected" ' : '';
            echo '<option value="'.$i.'" '.$selected.'>'.$i.'</option>';
        }
        echo '</select>';
        echo '<select name="duetime[month]" id="timeduemonth'.$id.'" >';
        for ($i=1; $i<=12; $i++) {
            $selected = ($i == $month) ? 'selected="selected" ' : '';
            echo '<option value="'.$i.'" '.$selected.'>'.userdate(gmmktime(12,0,0,$i,15,2000), "%B").'</option>';
        }
        echo '</select>';
        echo '<select name="duetime[year]" id="timedueyear'.$id.'" >';
        $today = usergetdate(time());
        $thisyear = $today['year'];
        for ($i=$thisyear-5; $i<=($thisyear + 10); $i++) {
            $selected = ($i == $year) ? 'selected="selected" ' : '';
            echo '<option value="'.$i.'" '.$selected.'>'.$i.'</option>';
        }
        echo '</select>';
        $checked = $disabled ? 'checked="checked" ' : '';
        echo '<input type="checkbox" name="duetimedisable" '.$checked.' id="timeduedisable'.$id.'" onclick="toggledate'.$id.'()" /><label for="timeduedisable'.$id.'">'.get_string('disable').' </label>'."\n";
        echo '<script type="text/javascript">'."\n";
        echo "function toggledate{$id}() {\n var disable = document.getElementById('timeduedisable{$id}').checked;\n var day = document.getElementById('timedueday{$id}');\n var month = document.getElementById('timeduemonth{$id}');\n var year = document.getElementById('timedueyear{$id}');\n";
        echo "if (disable) { \nday.setAttribute('disabled','disabled');\nmonth.setAttribute('disabled', 'disabled');\nyear.setAttribute('disabled', 'disabled');\n } ";
        echo "else {\nday.removeAttribute('disabled');\nmonth.removeAttribute('disabled');\nyear.removeAttribute('disabled');\n }";
        echo "} toggledate{$id}(); </script>\n";
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

	/**
	* displays items for edition
	*
	*/
    function view_edit_items() {
        global $OUTPUT, $COURSE, $CFG;

        echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');

        $currindent = 0;
        $addatend = true;
        $focusitem = false;
        $hasauto = false;

        $thispage = new moodle_url('/mod/learningtimecheck/edit.php', array('id'=>$this->cm->id, 'sesskey'=>sesskey()));
        if ($this->additemafter) {
            $thispage->param('additemafter', $this->additemafter);
        }
        if ($this->editdates) {
            $thispage->param('editdates', 'on');
        }

        if ($this->learningtimecheck->autopopulate && $this->learningtimecheck->autoupdate) {
            $url = "{$CFG->wwwroot}/mod/learningtimecheck/edit.php?id={$this->cm->id}&amp;sesskey=".sesskey();
            $url .= ($this->additemafter) ? '&amp;additemafter='.$this->additemafter : '';
            $url .= ($this->editdates) ? '&amp;editdates=on' : '';
            echo "<form action='$url' method='POST'>";
            echo learningtimecheck_add_paged_params();
            echo '<input type="submit" name="update_complete_score" value="'.get_string('updatecompletescore', 'learningtimecheck').'" /> ';
            // print_string('completiongradehelp','learningtimecheck');
        }

        if ($this->learningtimecheck->autoupdate && $this->learningtimecheck->autopopulate) {
            if ($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_STUDENT) {
                echo '<p>'.get_string('autoupdatewarning_student', 'learningtimecheck').'</p>';
            } else if ($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_TEACHER) {
                echo '<p class="learningtimecheckwarning">'.get_string('autoupdatewarning_teacher', 'learningtimecheck').'</p>';
            } else {
                echo '<p class="learningtimecheckwarning">'.get_string('autoupdatewarning_both', 'learningtimecheck').'</p>';
            }
        }

        echo '<ol class="learningtimecheck">';
        if ($this->items) {
            $lastitem = count($this->items);
            $lastindent = 0;
            foreach ($this->items as $item) {

                if ($item->itemoptional != learningtimecheck_OPTIONAL_HEADING) echo '<fieldset>';
                while ($item->indent > $currindent) {
                    $currindent++;
                    echo '<ol class="learningtimecheck">';
                }
                while ($item->indent < $currindent) {
                    $currindent--;
                    echo '</ol>';
                }

                $itemname = '"item'.$item->id.'"';
                $thispage->param('itemid',$item->id);

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

                $autoitem = ($this->learningtimecheck->autopopulate) && ($item->moduleid != 0);
                if ($autoitem) {
                    $autoclass = ' itemauto';
                } else {
                    $autoclass = '';
                }
                $hasauto = $hasauto || ($item->moduleid != 0);

                echo '<li>';
                if ($item->itemoptional == learningtimecheck_OPTIONAL_YES) {
                    $title = '"'.get_string('optionalitem','learningtimecheck').'"';
                    echo '<a href="'.$thispage->out(true, array('action'=>'makeheading')).'">';
                    echo '<img src="'.$OUTPUT->pix_url('empty_box','learningtimecheck').'" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = ' class="itemoptional '.$itemcolour.$autoclass.'" ';
                } else if ($item->itemoptional == learningtimecheck_OPTIONAL_HEADING) {
                    if ($item->hidden) {
                        $title = '"'.get_string('headingitem','learningtimecheck').'"';
                        echo '<img src="'.$OUTPUT->pix_url('no_box','learningtimecheck').'" alt='.$title.' title='.$title.' />&nbsp;';
                        $optional = ' class="'.$itemcolour.$autoclass.' itemdisabled"';
                    } else {
                        $title = '"'.get_string('headingitem','learningtimecheck').'"';
                        if (!$autoitem) {
                            echo '<a href="'.$thispage->out(true, array('action'=>'makerequired')).'">';
                        }
                        echo '<img src="'.$OUTPUT->pix_url('no_box','learningtimecheck').'" alt='.$title.' title='.$title.' />';
                        if (!$autoitem) {
                            echo '</a>';
                        }
                        echo '&nbsp;';
                        $optional = ' class="itemheading '.$itemcolour.$autoclass.'" ';
                    }
                } elseif ($item->hidden) {
                    $title = '"'.get_string('requireditem','learningtimecheck').'"';
                    echo '<img src="'.$OUTPUT->pix_url('tick_box','learningtimecheck').'" alt='.$title.' title='.$title.' />&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.' itemdisabled"';
                } else {
                    $title = '"'.get_string('requireditem','learningtimecheck').'"';
                    echo '<a href="'.$thispage->out(true, array('action'=>'makeoptional')).'">';
                    echo '<img src="'.$OUTPUT->pix_url('tick_box','learningtimecheck').'" alt='.$title.' title='.$title.' /></a>&nbsp;';
                    $optional = ' class="'.$itemcolour.$autoclass.'"';
                }

                if (isset($item->editme)) {
                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="post">';
            		echo learningtimecheck_add_paged_params();
                    echo '<input type="text" size="'.learningtimecheck_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.s($item->displaytext).'" id="updateitembox" />';
                    echo '<input type="hidden" name="action" value="updateitem" />';
                    echo html_writer::input_hidden_params($thispage);
                    if ($this->editdates) {
                        $this->print_edit_date($item->duetime);
                    }
                    echo '<input type="submit" name="updateitem" value="'.get_string('updateitem','learningtimecheck').'" />';
                    echo '</form>';

                    $focusitem = 'updateitembox';

                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage, array('sesskey', 'itemid') );
            		echo learningtimecheck_add_paged_params();
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','learningtimecheck').'" />';
                    echo '</form>';

                    $addatend = false;

                } else {
                    echo '<label for='.$itemname.$optional.'>'.s($item->displaytext).'</label>&nbsp;';

                    echo '<a href="'.$thispage->out(true, array('action'=>'nextcolour')).'">';
                    $title = '"'.get_string('changetextcolour','learningtimecheck').'"';
                    echo '<img src="'.$OUTPUT->pix_url($nexticon,'learningtimecheck').'" alt='.$title.' title='.$title.' /></a>';

                    if (!$autoitem) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'edititem')).'">';
                        $title = '"'.get_string('edititem','learningtimecheck').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/edit').'"  alt='.$title.' title='.$title.' /></a>&nbsp;';
                    }

                    if (!$autoitem && $item->indent > 0) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'unindentitem')).'">';
                        $title = '"'.get_string('unindentitem','learningtimecheck').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/left').'" alt='.$title.' title='.$title.'  /></a>';
                    }

                    if (!$autoitem && ($item->indent < learningtimecheck_MAX_INDENT) && (($lastindent+1) > $currindent)) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'indentitem')).'">';
                        $title = '"'.get_string('indentitem','learningtimecheck').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/right').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo '&nbsp;';

                    // TODO more complex checks to take into account indentation
                    if (!$autoitem && $item->position > 1) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'moveitemup')).'">';
                        $title = '"'.get_string('moveitemup','learningtimecheck').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/up').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    if (!$autoitem && $item->position < $lastitem) {
                        echo '<a href="'.$thispage->out(true, array('action'=>'moveitemdown')).'">';
                        $title = '"'.get_string('moveitemdown','learningtimecheck').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/down').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    echo "<input type=\"hidden\" name=\"items[]\" value=\"$item->id\" />\n";

                    if ($autoitem) {
                        if ($item->hidden != learningtimecheck_HIDDEN_BYMODULE) {
                            echo '&nbsp;<a href="'.$thispage->out(true, array('action'=>'deleteitem')).'">';
                            if ($item->hidden == learningtimecheck_HIDDEN_MANUAL) {
                                $title = '"'.get_string('show').'"';
                                echo '<img src="'.$OUTPUT->pix_url('/t/show').'" alt='.$title.' title='.$title.' /></a>';
                            } else {
                                $title = '"'.get_string('hide').'"';
                                echo '<img src="'.$OUTPUT->pix_url('/t/hide').'" alt='.$title.' title='.$title.' /></a>';
                            }
                        }
                    } else {
                        echo '&nbsp;<a href="'.$thispage->out(true, array('action'=>'deleteitem')).'">';
                        $title = '"'.get_string('deleteitem','learningtimecheck').'"';
                        echo '<img src="'.$OUTPUT->pix_url('/t/delete').'" alt='.$title.' title='.$title.' /></a>';
                    }

                    if ($this->learningtimecheck->usetimecounterpart && !($item->itemoptional == learningtimecheck_OPTIONAL_HEADING)){
                    	echo '<br/>'. get_string('credittime', 'learningtimecheck');
                    	echo html_writer::select(learningtimecheck_get_credit_times(), "credittime[$item->id]", @$item->credittime);
                    	$checked = (@$item->enablecredit) ? ' checked="checked" ' : '' ;
                    	echo '&nbsp;'."<input type=\"checkbox\" name=\"enablecredit[$item->id]\" value=\"1\" $checked /> ".get_string('enablecredit', 'learningtimecheck');
                    	
						echo '<br/>';
						print_string('isdeclarative', 'learningtimecheck');                    	
                    	$isdeclarativeoptions = array('0' => get_string('no'),
                    		'1' => get_string('students'),
                    		'2' => get_string('teachers'),
                    		'3' => get_string('both', 'learningtimecheck'));
                    	echo html_writer::select($isdeclarativeoptions, "isdeclarative[$item->id]", @$item->isdeclarative);
                    	
                    }

					if ($item->itemoptional != learningtimecheck_OPTIONAL_HEADING) {
	                	echo '<br/>'. get_string('teachercredittime', 'learningtimecheck');
	                	echo html_writer::select(learningtimecheck_get_credit_times(), "teachercredittime[$item->id]", @$item->teachercredittime);
	                }

                    echo '&nbsp;&nbsp;&nbsp;<a href="'.$thispage->out(true, array('action'=>'startadditem')).'">';
                    $title = '"'.get_string('additemhere','learningtimecheck').'"';
                    echo '<img src="'.$OUTPUT->pix_url('add','learningtimecheck').'" alt='.$title.' title='.$title.' /></a>';
                    if ($item->duetime) {
                        if ($item->duetime > time()) {
                            echo '<span class="learningtimecheck-itemdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                        } else {
                            echo '<span class="learningtimecheck-itemoverdue"> '.userdate($item->duetime, get_string('strftimedate')).'</span>';
                        }
                    }

                }

                $thispage->remove_params(array('itemid'));

                if ($this->additemafter == $item->id) {
                    $addatend = false;
                    echo '<li>';
                    echo '<form style="display:inline;" action="'.$thispage->out_omit_querystring().'" method="post">';
                    echo html_writer::input_hidden_params($thispage);
            		echo learningtimecheck_add_paged_params();
                    echo '<input type="hidden" name="action" value="additem" />';
                    echo '<input type="hidden" name="position" value="'.($item->position+1).'" />';
                    echo '<input type="hidden" name="indent" value="'.$item->indent.'" />';
                    echo '<img src="'.$OUTPUT->pix_url('tick_box','learningtimecheck').'" /> ';
                    echo '<input type="text" size="'.learningtimecheck_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
                    if ($this->editdates) {
                        $this->print_edit_date();
                    }
                    echo '<input type="submit" name="additem" value="'.get_string('additem','learningtimecheck').'" />';
                    echo '</form>';

                    echo '<form style="display:inline" action="'.$thispage->out_omit_querystring().'" method="get">';
                    echo html_writer::input_hidden_params($thispage, array('sesskey','additemafter'));
            		echo learningtimecheck_add_paged_params();
                    echo '<input type="submit" name="canceledititem" value="'.get_string('canceledititem','learningtimecheck').'" />';
                    echo '</form>';
                    echo '</li>';

                    if (!$focusitem) {
                        $focusitem = 'additembox';
                    }


                    $lastindent = $currindent;
                }

                echo '</li>';
            }
        }

        $thispage->remove_params(array('itemid'));

        if ($addatend) {
            echo '<li>';
            echo '<form action="'.$thispage->out_omit_querystring().'" method="post">';
            echo html_writer::input_hidden_params($thispage);
            echo '<input type="hidden" name="action" value="additem" />';
            echo learningtimecheck_add_paged_params();
            echo '<input type="hidden" name="indent" value="'.$currindent.'" />';
            echo '<input type="text" size="'.learningtimecheck_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
            if ($this->editdates) {
                $this->print_edit_date();
            }
            echo '<input type="submit" name="additem" value="'.get_string('additem','learningtimecheck').'" />';
            echo '</form>';
            echo '</li>';
            if (!$focusitem) {
                $focusitem = 'additembox';
            }
        }
        echo '</ol>';
        while ($currindent) {
            $currindent--;
            echo '</ol>';
        }

        echo '<form action="'.$thispage->out_omit_querystring().'" method="get">';
        echo html_writer::input_hidden_params($thispage, array('sesskey','editdates'));
        echo learningtimecheck_add_paged_params();
        if (!$this->editdates) {
            echo '<input type="hidden" name="editdates" value="on" />';
            echo '<input type="submit" value="'.get_string('editdatesstart','learningtimecheck').'" />';
        } else {
            echo '<input type="submit" value="'.get_string('editdatesstop','learningtimecheck').'" />';
        }
        if (!$this->learningtimecheck->autopopulate && $hasauto) {
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="submit" value="'.get_string('removeauto', 'learningtimecheck').'" name="removeauto" />';
        }
        echo '</form>';

        if ($focusitem) {
            echo '<script type="text/javascript">document.getElementById("'.$focusitem.'").focus();</script>';
        }

        echo $OUTPUT->box_end();
    }

    function view_report() {
        global $DB, $OUTPUT;

        $reportsettings = $this->get_report_settings();

        $editchecks = $this->caneditother() && optional_param('editchecks', false, PARAM_BOOL);

        $page = optional_param('page', 0, PARAM_INT);
        $perpage = optional_param('perpage', 30, PARAM_INT);

        $thisurl = new moodle_url('/mod/learningtimecheck/report.php', array('id'=>$this->cm->id, 'sesskey'=>sesskey()) );
        if ($editchecks) { $thisurl->param('editchecks','on'); }

        if ($this->learningtimecheck->autoupdate && $this->learningtimecheck->autopopulate) {
            if ($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_TEACHER) {
                echo '<p class="learningtimecheckwarning">'.get_string('autoupdatewarning_teacher', 'learningtimecheck').'</p>';
            } else if ($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_BOTH) {
                echo '<p class="learningtimecheckwarning">'.get_string('autoupdatewarning_both', 'learningtimecheck').'</p>';
            }
        }

        groups_print_activity_menu($this->cm, $thisurl);
        $activegroup = groups_get_activity_group($this->cm, true);

        echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$thisurl->out_omit_querystring().'" method="get" />';
        echo html_writer::input_hidden_params($thisurl, array('action'));
        if ($reportsettings->showoptional) {
            echo '<input type="hidden" name="action" value="hideoptional" />';
            echo '<input type="submit" name="submit" value="'.get_string('optionalhide','learningtimecheck').'" />';
        } else {
            echo '<input type="hidden" name="action" value="showoptional" />';
            echo '<input type="submit" name="submit" value="'.get_string('optionalshow','learningtimecheck').'" />';
        }
        echo '</form>';

        echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$thisurl->out_omit_querystring().'" method="get" />';
        echo html_writer::input_hidden_params($thisurl);
        if ($reportsettings->showprogressbars) {
            $editchecks = false;
            echo '<input type="hidden" name="action" value="hideprogressbars" />';
            echo '<input type="submit" name="submit" value="'.get_string('showfulldetails','learningtimecheck').'" />';
        } else {
            echo '<input type="hidden" name="action" value="showprogressbars" />';
            echo '<input type="submit" name="submit" value="'.get_string('showprogressbars','learningtimecheck').'" />';
        }
        echo '</form>';

        if ($editchecks) {
            echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$thisurl->out_omit_querystring().'" method="post" />';
            echo html_writer::input_hidden_params($thisurl);
            echo '<input type="hidden" name="action" value="updateallchecks"/>';
            echo '<input type="submit" name="submit" value="'.get_string('savechecks','learningtimecheck').'" />';
        } else if (!$reportsettings->showprogressbars && $this->caneditother() && $this->learningtimecheck->teacheredit != learningtimecheck_MARKING_STUDENT) {
            echo '&nbsp;&nbsp;<form style="display: inline;" action="'.$thisurl->out_omit_querystring().'" method="get" />';
            echo html_writer::input_hidden_params($thisurl);
            echo '<input type="hidden" name="editchecks" value="on" />';
            echo '<input type="submit" name="submit" value="'.get_string('editchecks','learningtimecheck').'" />';
            echo '</form>';
        }

        echo '<br style="clear:both"/>';

        switch ($reportsettings->sortby) {
        case 'firstdesc':
            $orderby = 'u.firstname DESC';
            break;

        case 'lastasc':
            $orderby = 'u.lastname';
            break;

        case 'lastdesc':
            $orderby = 'u.lastname DESC';
            break;

        default:
            $orderby = 'u.firstname';
            break;
        }

        $ausers = false;
        if ($users = get_users_by_capability($this->context, 'mod/learningtimecheck:updateown', 'u.id', $orderby, '', '', $activegroup, '', false)) {
            $users = array_keys($users);
            if ($this->only_view_mentee_reports()) {
                // Filter to only show reports for users who this user mentors (ie they have been assigned to them in a context)
                $users = $this->filter_mentee_users($users);
            }
        }
        if ($users && !empty($users)) {
            if (count($users) < $page*$perpage) {
                $page = 0;
            }
            echo $OUTPUT->paging_bar(count($users), $page, $perpage, new moodle_url($thisurl, array('perpage'=>$perpage)));
            $users = array_slice($users, $page*$perpage, $perpage);

            list($usql, $uparams) = $DB->get_in_or_equal($users);
            $ausers = $DB->get_records_sql('SELECT u.id, u.firstname, u.lastname FROM {user} u WHERE u.id '.$usql.' ORDER BY '.$orderby, $uparams);
        }

        if ($reportsettings->showprogressbars) {
            if ($ausers) {
                // Show just progress bars
                if ($reportsettings->showoptional) {
                    $itemstocount = array();
                    foreach ($this->items as $item) {
                        if (!$item->hidden) {
                            if (($item->itemoptional == learningtimecheck_OPTIONAL_YES) || ($item->itemoptional == learningtimecheck_OPTIONAL_NO)) {
                                $itemstocount[] = $item->id;
                            }
                        }
                    }
                } else {
                    $itemstocount = array();
                    foreach ($this->items as $item) {
                        if (!$item->hidden) {
                            if ($item->itemoptional == learningtimecheck_OPTIONAL_NO) {
                                $itemstocount[] = $item->id;
                            }
                        }
                    }
                }
                $totalitems = count($itemstocount);

                $sql = '';
                if ($totalitems) {
                    list($isql, $iparams) = $DB->get_in_or_equal($itemstocount, SQL_PARAMS_NAMED);
                    if ($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_STUDENT) {
                        $sql = 'usertimestamp > 0 AND item '.$isql.' AND userid = :user ';
                    } else {
                        $sql = 'teachermark = '.learningtimecheck_TEACHERMARK_YES.' AND item '.$isql.' AND userid = :user ';
                    }
                }
                echo '<div>';
                foreach ($ausers as $auser) {
                    if ($totalitems) {
                        $iparams['user'] = $auser->id;
                        $tickeditems = $DB->count_records_select('learningtimecheck_check', $sql, $iparams);
                        $percentcomplete = ($tickeditems * 100) / $totalitems;
                    } else {
                        $percentcomplete = 0;
                        $tickeditems = 0;
                    }

                    if ($this->caneditother()) {
                        $vslink = ' <a href="'.$thisurl->out(true, array('studentid'=>$auser->id) ).'" ';
                        $vslink .= 'alt="'.get_string('viewsinglereport','learningtimecheck').'" title="'.get_string('viewsinglereport','learningtimecheck').'">';
                        $vslink .= '<img src="'.$OUTPUT->pix_url('/t/preview').'" /></a>';
                    } else {
                        $vslink = '';
                    }
                    $userurl = new moodle_url('/user/view.php', array('id'=>$auser->id, 'course'=>$this->course->id) );
                    $userlink = '<a href="'.$userurl.'">'.fullname($auser).'</a>';
                    echo '<div style="float: left; width: 30%; text-align: right; margin-right: 8px; ">'.$userlink.$vslink.'</div>';

                    echo '<div class="learningtimecheck_progress_outer">';
                    echo '<div class="learningtimecheck_progress_inner" style="width:'.$percentcomplete.'%; background-image: url('.$OUTPUT->pix_url('progress','learningtimecheck').');" >&nbsp;</div>';
                    echo '</div>';
                    echo '<div style="float:left; width: 3em;">&nbsp;'.sprintf('%0d%%',$percentcomplete).'</div>';
                    echo '<div style="float:left;">&nbsp;('.$tickeditems.'/'.$totalitems.')</div>';
                    echo '<br style="clear:both;" />';
                }
                echo '</div>';
            }

        } else {

            // Show full table
            $firstlink = 'firstasc';
            $lastlink = 'lastasc';
            $firstarrow = '';
            $lastarrow = '';
            if ($reportsettings->sortby == 'firstasc') {
                $firstlink = 'firstdesc';
                $firstarrow = '<img src="'.$OUTPUT->pix_url('/t/down').'" alt="'.get_string('asc').'" />';
            } else if ($reportsettings->sortby == 'lastasc') {
                $lastlink = 'lastdesc';
                $lastarrow = '<img src="'.$OUTPUT->pix_url('/t/down').'" alt="'.get_string('asc').'" />';
            } else if ($reportsettings->sortby == 'firstdesc') {
                $firstarrow = '<img src="'.$OUTPUT->pix_url('/t/up').'" alt="'.get_string('desc').'" />';
            } else if ($reportsettings->sortby == 'lastdesc') {
                $lastarrow = '<img src="'.$OUTPUT->pix_url('/t/up').'" alt="'.get_string('desc').'" />';
            }
            $firstlink = new moodle_url($thisurl, array('sortby' => $firstlink));
            $lastlink = new moodle_url($thisurl, array('sortby' => $lastlink));
            $nameheading = ' <a href="'.$firstlink.'" >'.get_string('firstname').'</a> '.$firstarrow;
            $nameheading .= ' / <a href="'.$lastlink.'" >'.get_string('lastname').'</a> '.$lastarrow;

            $table = new stdClass;
            $table->head = array($nameheading);
            $table->level = array(-1);
            $table->size = array('100px');
            $table->skip = array(false);
            foreach ($this->items as $item) {
                if ($item->hidden) {
                    continue;
                }

                $table->head[] = s($item->displaytext);
                $table->level[] = ($item->indent < 3) ? $item->indent : 2;
                $table->size[] = '80px';
                $table->skip[] = (!$reportsettings->showoptional) && ($item->itemoptional == learningtimecheck_OPTIONAL_YES);
            }

            $table->data = array();
            if ($ausers) {
                foreach ($ausers as $auser) {
                    $row = array();

                    $vslink = ' <a href="'.$thisurl->out(true, array('studentid'=>$auser->id) ).'" ';
                    $vslink .= 'alt="'.get_string('viewsinglereport','learningtimecheck').'" title="'.get_string('viewsinglereport','learningtimecheck').'">';
                    $vslink .= '<img src="'.$OUTPUT->pix_url('/t/preview').'" /></a>';
                    $userurl = new moodle_url('/user/view.php', array('id'=>$auser->id, 'course'=>$this->course->id) );
                    $userlink = '<a href="'.$userurl.'">'.fullname($auser).'</a>';

                    $row[] = $userlink.$vslink;

                    $sql = 'SELECT i.id, i.itemoptional, i.hidden, c.usertimestamp, c.teachermark FROM {learningtimecheck_item} i LEFT JOIN {learningtimecheck_check} c ';
                    $sql .= 'ON (i.id = c.item AND c.userid = ? ) WHERE i.learningtimecheck = ? AND i.userid=0 ORDER BY i.position';
                    $checks = $DB->get_records_sql($sql, array($auser->id, $this->learningtimecheck->id) );

                    foreach ($checks as $check) {
                        if ($check->hidden) {
                            continue;
                        }

                        if ($check->itemoptional == learningtimecheck_OPTIONAL_HEADING) {
                            $row[] = array(false, false, true, 0, 0);
                        } else {
                            if ($check->usertimestamp > 0) {
                                $row[] = array($check->teachermark,true,false, $auser->id, $check->id);
                            } else {
                                $row[] = array($check->teachermark,false,false, $auser->id, $check->id);
                            }
                        }
                    }

                    $table->data[] = $row;

                    if ($editchecks) {
                        echo '<input type="hidden" name="userids[]" value="'.$auser->id.'" />';
                    }
                }
            }

            echo '<div style="overflow:auto">';
            $this->print_report_table($table, $editchecks);
            echo '</div>';

            if ($editchecks) {
                echo '<input type="submit" name="submit" value="'.get_string('savechecks','learningtimecheck').'" />';
                echo '</form>';
            }
        }
    }

    function coursecalibrationreport($course) {
        global $CFG, $DB, $OUTPUT;

        if (!$this->canviewcoursecalibrationreport()) {
            redirect($CFG->wwwroot.'/mod/learningtimecheck/view.php?id='.$this->cm->id);
        }

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->learningtimecheck->name));

        $this->view_tabs('calibrationreport');

        echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');
        echo $OUTPUT->heading(get_string('coursecalibrationreport', 'learningtimecheck'));
        echo $OUTPUT->box_end();
        
        $this->view_coursecalibrationreport();
        
        echo $OUTPUT->footer($course);
    }

	/**
	* prints the coursecalibration report information
	*
	*/    
    function view_coursecalibrationreport(){
    	global $COURSE, $DB;
    	
    	$alllearningtimechecks = $DB->get_records('learningtimecheck', array('course' => $COURSE->id));
    	
    	$totaltime = 0;
    	$totalestimated = 0;
    	$totalteachercreditable = 0;

		if ($alllearningtimechecks){
			
			$credittimestr = get_string('credittime', 'learningtimecheck');
			$creditedstr = '';
			$teachercredittimestr = get_string('teachercredittime', 'learningtimecheck');
			
	    	foreach($alllearningtimechecks as $chkl){
	    		$items = $DB->get_records_select('learningtimecheck_item', " learningtimecheck = ? AND (credittime > 0 or teachercredittime > 0) ", array($chkl->id));
	    		if ($items){
		    		echo '<br/><table width="100%">';
		    		echo '<tr><td width="40%"></td><td width="20%"><b>'.$credittimestr.'</b></td><td width="20%"><b>'.$creditedstr.'</b></td><td width="20%"><b>'.$teachercredittimestr.'</b></td></tr>';
		    		echo '<tr><td colspan="3"><b>'.$chkl->name.'</b></td></tr>';	    		
		    		foreach($items as $item){
		    			$totaltime += $item->credittime;
		    			$totalestimated += ($item->enablecredit) ? 0 : $item->credittime;
		    			$totalteachercreditable += $item->teachercredittime;
		    			$credited = ($item->enablecredit) ? get_string('credit', 'learningtimecheck') : get_string('estimated', 'learningtimecheck');
		    			echo '<tr><td width="40%">'.$item->displaytext.'</td><td width="20%">'.$item->credittime.'</td><td width="20%">'.$credited.'</td><td width="20%">'.(0 + $item->teachercredittime).'</td></tr>';
		    		}
		    		echo '</table>';
		    	}
	    	}

			echo '<p><b>'.get_string('totalcoursetime', 'learningtimecheck').': </b>'.$totaltime.' '.get_string('minutes').'<br/>';
			echo '<b>'.get_string('totalestimatedtime', 'learningtimecheck').': </b>'.$totalestimated.' '.get_string('minutes').'</p>';
			echo '<b>'.get_string('totalteacherestimatedtime', 'learningtimecheck').': </b>'.$totalteachercreditable.' '.get_string('minutes').'</p>';

	    } else {
	    	echo get_string('nolearningtimecheckincourse', 'learningtimecheck');
	    }
        	
    }

    function tutorboard($course) {
        global $CFG, $DB, $OUTPUT;

        if (!$this->canviewtutorboard()) {
            redirect($CFG->wwwroot.'/mod/learningtimecheck/view.php?id='.$this->cm->id);
        }

        $this->view_header();

        echo $OUTPUT->heading(format_string($this->learningtimecheck->name));

        $this->view_tabs('coursecalibrationreport');

        echo $OUTPUT->box_start('generalbox boxwidthwide boxaligncenter');
        echo $OUTPUT->heading(get_string('tutorboard', 'learningtimecheck'));
        echo $OUTPUT->box_end();
        
        $this->view_tutorboard();
        
        echo $OUTPUT->footer($course);
    }

	/**
	* prints the tutoring times (expenses)
	*
	*/
    function view_tutorboard() {
    	global $USER, $CFG, $COURSE, $DB;
    	
    	$context = context_course::instance($COURSE->id);
    	
    	if (groups_get_course_groupmode($COURSE)){
    		$targetusers = array();
	    	if ($groups = groups_get_all_groups($COURSE->id, $USER->id)){
		    	foreach($groups as $g){
	        		$targetusers[$g->id] = groups_get_members($groupid);
		    	}
			}
	    } else {
    		$targetusers[0] = get_users_by_capability($context, 'moodle/course:view', 'u.id, firstname, lastname, email, institution', 'lastname');
	    }

	    $sql = "
	    	SELECT
	    		CONCAT(chck.userid, '_',chl.id) as markid,
	    		chck.userid as userid,
	    		chl.id as learningtimecheckid,
	    		chl.name,
	    		SUM(chi.teachercredittime) as expected,
	    		SUM(chck.teacherdeclaredtime) as realexpense,	    		
	    		u.lastname,
	    		u.firstname
	    	FROM
	    		{learningtimecheck_check} chck,
	    		{learningtimecheck_item} chi,
	    		{learningtimecheck} chl,
	    		{user} u
	    	WHERE
	    		chck.item = chi.id AND
	    		chi.learningtimecheck = chl.id AND
	    		chl.course = ? AND
	    		chck.teacherid = ? AND
	    		chck.userid = u.id
	    	GROUP BY
	    		chck.userid, chl.id
	    	ORDER BY
	    		u.lastname,u.firstname ASC
	    ";
	    $tutoredusers = array();
	    if ($tutoredtimes = $DB->get_records_sql($sql, array($COURSE->id, $USER->id))){
	    	foreach($tutoredtimes as $tt){
	    		$tutoredusers[$tt->userid][$tt->learningtimecheckid] = $tt;
	    		$tutoredusersfull[$tt->userid]->teachercredittime = 0 + @$tutoredusersfull[$tt->userid]->teachercredittime + $tt->expected;
	    		$tutoredusersfull[$tt->userid]->teacherdeclaredtime = 0 + @$tutoredusersfull[$tt->userid]->teacherdeclaredtime + $tt->realexpense;
	    		$tutoredusersfull[$tt->userid]->firstname = $tt->firstname;
	    		$tutoredusersfull[$tt->userid]->lastname = $tt->lastname;
	    	}
	    }

		$table = new html_table();
		$table->head = array('<b>'.get_string('lastname').' '.get_string('firstname').'</b>', '<b>'.get_string('realtutored', 'learningtimecheck').'</b>', '<b>'.get_string('expectedtutored', 'learningtimecheck').'</b>');
		$table->align = array('left', 'center', 'center');
		$table->size = array('60%', '20%', '20%');
		$table->width = '100%';
		
		$fullcourseexpected = 0;
		$fullcourseexpense = 0;
		$declareddisp = '';
		foreach($tutoredusers as $uid => $tu){
			$credittime = $tutoredusersfull[$uid]->teachercredittime;
			$declaredtime = $tutoredusersfull[$uid]->teacherdeclaredtime;
			$declareddisp = ($credittime > $declaredtime) ? '<span class="positive">'.$declaredtime.' mn</span>' : '<span class="negative">'.$declaredtime.' mn</span>' ;
			$table->data[] = array(fullname($tutoredusersfull[$uid]), $declareddisp, $credittime.' mn');
			$fullcourseexpected += $credittime;
			$fullcourseexpense += $declaredtime;
		}
		$fullexpensedisp = ($fullcourseexpected > $fullcourseexpense) ? '<span class="positive"><b>'.$fullcourseexpense.' mn</b></span>' : '<span class="negative"><b>'.$fullcourseexpense.' mn</b></span>' ;
		$table->data[] = array('<b>'.get_string('totalcourse', 'learningtimecheck').'</b>', $declareddisp, '<b>'.$fullcourseexpected.' mn</b>');
		
		echo html_writer::table($table);
    }

    function print_report_table($table, $editchecks) {
        global $OUTPUT;

        $output = '';

        $output .= '<table summary="'.get_string('reporttablesummary','learningtimecheck').'"';
        $output .= ' cellpadding="5" cellspacing="1" class="generaltable boxaligncenter learningtimecheckreport">';

        $showteachermark = !($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_STUDENT);
        $showstudentmark = !($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_TEACHER);
        $teachermarklocked = $this->learningtimecheck->lockteachermarks && !has_capability('mod/learningtimecheck:updatelocked', $this->context);

        // Sort out the heading row
        $output .= '<tr>';
        $keys = array_keys($table->head);
        $lastkey = end($keys);
        foreach ($table->head as $key => $heading) {
            if ($table->skip[$key]) {
                continue;
            }
            $size = $table->size[$key];
            $levelclass = ' head'.$table->level[$key];
            if ($key == $lastkey) {
                $levelclass .= ' lastcol';
            }
            $output .= '<th style="vertical-align:top; align: center; width:'.$size.'" class="header c'.$key.$levelclass.'" scope="col">';
            $output .= $heading.'</th>';
        }
        $output .= '</tr>';

        // Output the data
        $tickimg = '<img src="'.$OUTPUT->pix_url('/i/tick_green_big').'" alt="'.get_string('itemcomplete','learningtimecheck').'" />';
        $teacherimg = array(learningtimecheck_TEACHERMARK_UNDECIDED => '<img src="'.$OUTPUT->pix_url('empty_box','learningtimecheck').'" alt="'.get_string('teachermarkundecided','learningtimecheck').'" />',
                            learningtimecheck_TEACHERMARK_YES => '<img src="'.$OUTPUT->pix_url('tick_box','learningtimecheck').'" alt="'.get_string('teachermarkyes','learningtimecheck').'" />',
                            learningtimecheck_TEACHERMARK_NO => '<img src="'.$OUTPUT->pix_url('cross_box','learningtimecheck').'" alt="'.get_string('teachermarkno','learningtimecheck').'" />');
        $oddeven = 1;
        $keys = array_keys($table->data);
        $lastrowkey = end($keys);
        foreach ($table->data as $key => $row) {
            $oddeven = $oddeven ? 0 : 1;
            $class = '';
            if ($key == $lastrowkey) {
                $class = ' lastrow';
            }

            $output .= '<tr class="r'.$oddeven.$class.'">';
            $keys2 = array_keys($row);
            $lastkey = end($keys2);
            foreach ($row as $key => $item) {
                if ($table->skip[$key]) {
                    continue;
                }
                if ($key == 0) {
                    // First item is the name
                    $output .= '<td style=" text-align: left; width: '.$table->size[0].';" class="cell c0">'.$item.'</td>';
                } else {
                    $size = $table->size[$key];
                    $img = '&nbsp;';
                    $cellclass = 'level'.$table->level[$key];
                    list($teachermark, $studentmark, $heading, $userid, $checkid) = $item;
                    if ($heading) {
                        $output .= '<td style=" text-align: center; width: '.$size.';" class="cell c'.$key.' reportheading">&nbsp;</td>';
                    } else {
                        if ($showteachermark) {
                            if ($teachermark == learningtimecheck_TEACHERMARK_YES) {
                                $cellclass .= '-checked';
                                $img = $teacherimg[$teachermark];
                            } else if ($teachermark == learningtimecheck_TEACHERMARK_NO) {
                                $cellclass .= '-unchecked';
                                $img = $teacherimg[$teachermark];
                            } else {
                                $img = $teacherimg[learningtimecheck_TEACHERMARK_UNDECIDED];
                            }

                            if ($editchecks) {
                                $disabled = ($teachermarklocked && $teachermark == learningtimecheck_TEACHERMARK_YES) ? 'disabled="disabled" ' : '';

                                $selu = ($teachermark == learningtimecheck_TEACHERMARK_UNDECIDED) ? 'selected="selected" ' : '';
                                $sely = ($teachermark == learningtimecheck_TEACHERMARK_YES) ? 'selected="selected" ' : '';
                                $seln = ($teachermark == learningtimecheck_TEACHERMARK_NO) ? 'selected="selected" ' : '';

                                $img = '<select name="items_'.$userid.'['.$checkid.']" '.$disabled.'>';
                                $img .= '<option value="'.learningtimecheck_TEACHERMARK_UNDECIDED.'" '.$selu.'></option>';
                                $img .= '<option value="'.learningtimecheck_TEACHERMARK_YES.'" '.$sely.'>'.get_string('yes').'</option>';
                                $img .= '<option value="'.learningtimecheck_TEACHERMARK_NO.'" '.$seln.'>'.get_string('no').'</option>';
                                $img .= '</select>';
                            }
                        }
                        if ($showstudentmark) {
                            if ($studentmark) {
                                if (!$showteachermark) {
                                    $cellclass .= '-checked';
                                }
                                $img .= $tickimg;
                            }
                        }

                        $cellclass .= ' cell c'.$key;

                        if ($key == $lastkey) {
                            $cellclass .= ' lastcol';
                        }

                        $output .= '<td style=" text-align: center; width: '.$size.';" class="'.$cellclass.'">'.$img.'</td>';
                    }
                }
            }
            $output .= '</tr>';
        }

        $output .= '</table>';

        echo $output;
    }
    
    
    function view_own_report(){
    	global $CFG, $USER;
    	
		$this->update_complete_scores();
		
        $table = new stdClass;
        $table->width = '100%';
        foreach ($this->items as $item) {
            if ($item->hidden) {
                continue;
            }
            
            $table->head[] = s($item->displaytext);
            $table->level[] = ($item->indent < 3) ? $item->indent : 2;
            $table->size[] = '*';
            $table->skip[] = (!$this->showoptional) && ($item->itemoptional == learningtimecheck_OPTIONAL_YES);
        }
        
        $sql = "
        	SELECT 
        		i.id, 
        		i.itemoptional, 
        		i.hidden, 
        		c.usertimestamp, 
        		c.teachermark 
        	FROM 
        		{learningtimecheck_item} i 
        	LEFT JOIN 
        		{learningtimecheck_check} c 
	        ON 
	        	(i.id = c.item AND c.userid = ?) 
	        WHERE 
	        	i.learningtimecheck = ? AND 
	        	i.userid = 0 
	        ORDER BY 
	        	i.position
    	";
        $checks = get_records_sql($sql, array($USER->id, $this->learningtimecheck->id));

		$checkstates = array();
		if ($checks){
	        foreach ($checks as $check) {
	            if ($check->hidden) {
	                continue;
	            }
	
	            if ($check->itemoptional == learningtimecheck_OPTIONAL_HEADING) {
	                $checkstates[] = array(false, false, true, $check->id);
	            } else {
	                if ($check->usertimestamp > 0) {
	                    $checkstates[] = array($check->teachermark, true, false, $check->id);
	                } else {
	                    $checkstates[] = array($check->teachermark, false, false, $check->id);
	                }
	            }
	        }
	    } else {
	    	$nochecksstr = get_string('nochecks', 'learningtimecheck');
	    	echo "<div class=\"advice\">$nochecksstr</div>";
	    }
        
        echo '<br/>';

        if ($this->learningtimecheck->autopopulate == learningtimecheck_AUTOPOPULATE_COURSE){
	        $completionliststr = get_string('coursecompletionboard', 'learningtimecheck');
	    } else {
	        $completionliststr = get_string('completionboard', 'learningtimecheck');
	    }
        echo "<div class=\"sideblock\"><div class=\"header\"><h2>$completionliststr</h2></div></div>";
    	echo "<table class=\"learningtimecheckreport\" cellspacing=\"4\"><tr valign=\"top\">";
        $studentimg = array(0 => '<img src="'.$CFG->wwwroot.'/pix/spacer.gif" alt="'.get_string('studentmarkno','learningtimecheck').'" />',
                            1 => '<img src="'.$CFG->wwwroot.'/pix/i/tick_amber_big.gif" alt="'.get_string('studentmarkyes','learningtimecheck').'" />');
		$i = 0;
    	foreach($checkstates as $cs){
			list($teachermark, $studentmark, $heading, $checkid) = $cs;
    		if ($this->items[$checkid]->hidden) continue;
			$itemname = s($this->items[$checkid]->displaytext);
			if (!$heading){
				$class = 'chklst-level'.$this->items[$checkid]->indent;
				if ($teachermark == 1){
					$class = 'chklst-level'.$this->items[$checkid]->indent.'-checked';
				} else if ($teachermark == 2){
					$class = 'chklst-level'.$this->items[$checkid]->indent.'-unchecked';
				} else {
					if ($studentmark){
						$class = 'chklst-level'.$this->items[$checkid]->indent.'-done';
					}
				}
				if ($this->items[$checkid]->moduleid){
					if (@$this->items[$checkid]->modulelink){
						$itemname = "<a href=\"{$this->items[$checkid]->modulelink}\">$itemname</a>";
					}
				}
				echo "<td class=\"$class reportcell\">";
				echo "<div class=\"itemstate\">$itemname ". $studentimg[0 + $studentmark]. '</div>';
				if ($comments = get_records_select('learningtimecheck_comment', " userid = $USER->id AND itemid = $checkid ")){
					echo "<div class=\"comment\">";
					foreach($comments as $comment){
						$commenter = get_record('user', 'id', $comment->commentby, '', '', '', '', 'id,firstname,lastname');
						$commentername = get_string('reportedby', 'learningtimecheck', fullname($commenter));
						echo "<span title=\"$commentername\">$comment->text</span>";
					}
					echo "</div>";
				}
				echo '</td>';
			}
			if ($i % learningtimecheck_MAX_CHK_MODS_PER_ROW == 0 && $i > 0){
				echo '</tr><tr valign=\"top\">';
			}
			$i++;
		}
        echo '</tr></table>';
	}

    function view_footer() {
    	global $CFG, $OUTPUT, $COURSE;

		echo '<br/>';
		echo '<hr width="80%">';
		echo '<br/>';
		echo '<center>';
		if ($COURSE->id != SITEID){
			$params['id'] = $COURSE->id;
			if (learningtimecheck_course_is_page_formatted()){
				if ($pageid = optional_param('page', 0, PARAM_INT)){
					$params['page'] = $pageid;
				}
			}
			echo $OUTPUT->single_button(new moodle_url($CFG->wwwroot.'/course/view.php', $params), get_string('backtocourse', 'learningtimecheck'), 'get');
		} else {
			echo $OUTPUT->single_button($CFG->wwwroot, get_string('backtosite', 'learningtimecheck'));
		}
		echo '</center>';

        echo $OUTPUT->footer();
    }

    function process_view_actions() {
        global $CFG;

        $this->useredit = optional_param('useredit', false, PARAM_BOOL);

        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }

        if (!confirm_sesskey()) {
            error('Invalid sesskey');
        }

        $itemid = optional_param('itemid', 0, PARAM_INT);

        switch($action) {
        case 'updatechecks':
            $newchecks = optional_param_array('items', array(), PARAM_INT);
            $this->updatechecks($newchecks);
            break;

        case 'startadditem':
            $this->additemafter = $itemid;
            break;

        case 'edititem':
            if ($this->useritems && isset($this->useritems[$itemid])) {
                $this->useritems[$itemid]->editme = true;
            }
            break;

        case 'additem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
            $displaytext .= "\n".optional_param('displaytextnote', '', PARAM_TEXT);
            $position = optional_param('position', false, PARAM_INT);
            $this->additem($displaytext, $this->userid, 0, $position);
            $item = $this->get_item_at_position($position);
            if ($item) {
                $this->additemafter = $item->id;
            }
            break;

        case 'deleteitem':
            $this->deleteitem($itemid);
            break;

        case 'updateitem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
            $displaytext .= "\n".optional_param('displaytextnote', '', PARAM_TEXT);
            $this->updateitemtext($itemid, $displaytext);
            break;

        default:
            error('Invalid action - "'.s($action).'"');
        }

        if ($action != 'updatechecks') {
            $this->useredit = true;
        }
    }

    function process_edit_actions() {
        global $CFG;
        $this->editdates = optional_param('editdates', false, PARAM_BOOL);
        $additemafter = optional_param('additemafter', false, PARAM_INT);
        $removeauto = optional_param('removeauto', false, PARAM_TEXT);
        $update_complete_scores = optional_param('update_complete_score', false, PARAM_TEXT);

        if ($removeauto) {
            // Remove any automatically generated items from the list
            // (if no longer using automatic items)
            if (!confirm_sesskey()) {
                error('Invalid sesskey');
            }
            $this->removeauto();
            return;
        }

        if ($update_complete_scores) {
            if (!confirm_sesskey()) {
                error('Invalid sesskey');
            }
            $this->update_complete_scores();
            return;
        }

        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            $this->additemafter = $additemafter;
            return;
        }

        if (!confirm_sesskey()) {
            error('Invalid sesskey');
        }

        $itemid = optional_param('itemid', 0, PARAM_INT);

        switch ($action) {
        case 'additem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
            $indent = optional_param('indent', 0, PARAM_INT);
            $position = optional_param('position', false, PARAM_INT);
            if (optional_param('duetimedisable', false, PARAM_BOOL)) {
                $duetime = false;
            } else {
                $duetime = optional_param('duetime', false, PARAM_INT);
            }
            $this->additem($displaytext, 0, $indent, $position, $duetime);
            if ($position) {
                $additemafter = false;
            }
            break;
        case 'startadditem':
            $additemafter = $itemid;
            break;
        case 'edititem':
            if (isset($this->items[$itemid])) {
                $this->items[$itemid]->editme = true;
            }
            break;
        case 'updateitem':
            $displaytext = optional_param('displaytext', '', PARAM_TEXT);
            if (optional_param('duetimedisable', false, PARAM_BOOL)) {
                $duetime = false;
            } else {
                $duetime = optional_param_array('duetime', false, PARAM_INT);
            }
            $this->updateitemtext($itemid, $displaytext, $duetime);
            break;
        case 'deleteitem':
            if (($this->learningtimecheck->autopopulate) && (isset($this->items[$itemid])) && ($this->items[$itemid]->moduleid)) {
                $this->toggledisableitem($itemid);
            } else {
                $this->deleteitem($itemid);
            }
            break;
        case 'moveitemup':
            $this->moveitemup($itemid);
            break;
        case 'moveitemdown':
            $this->moveitemdown($itemid);
            break;
        case 'indentitem':
            $this->indentitem($itemid);
            break;
        case 'unindentitem':
            $this->unindentitem($itemid);
            break;
        case 'makeoptional':
            $this->makeoptional($itemid, true);
            break;
        case 'makerequired':
            $this->makeoptional($itemid, false);
            break;
        case 'makeheading':
            $this->makeoptional($itemid, true, true);
            break;
        case 'nextcolour':
            $this->nextcolour($itemid);
            break;
        default:
            error('Invalid action - "'.s($action).'"');
        }

        if ($additemafter) {
            $this->additemafter = $additemafter;
        }
    }

    function get_report_settings() {
        global $SESSION;

        if (!isset($SESSION->learningtimecheck_report)) {
            $settings = new stdClass;
            $settings->showcompletiondates = false;
            $settings->showoptional = true;
            $settings->showprogressbars = false;
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

    function process_report_actions() {
        $settings = $this->get_report_settings();

        if ($sortby = optional_param('sortby', false, PARAM_TEXT)) {
            $settings->sortby = $sortby;
            $this->set_report_settings($settings);
        }

        $savenext = optional_param('savenext', false, PARAM_TEXT);
        $viewnext = optional_param('viewnext', false, PARAM_TEXT);
        $action = optional_param('action', false, PARAM_TEXT);
        if (!$action) {
            return;
        }

        if (!confirm_sesskey()) {
            error('Invalid sesskey');
        }

        switch ($action) {
        case 'showprogressbars':
            $settings->showprogressbars = true;
            break;
        case 'hideprogressbars':
            $settings->showprogressbars = false;
            break;
        case 'showoptional':
            $settings->showoptional = true;
            break;
        case 'hideoptional':
            $settings->showoptional = false;
            break;
        case 'updatechecks':
            if ($this->caneditother() && !$viewnext) {
                $this->updateteachermarks();
            }
            break;
        case 'updateallchecks':
            if ($this->caneditother()) {
                $this->updateallteachermarks();
            }
            break;
        case 'toggledates':
            $settings->showcompletiondates = !$settings->showcompletiondates;
            break;
        }

        $this->set_report_settings($settings);

        if ($viewnext || $savenext) {
            $this->getnextuserid();
            $this->get_items();
        }
    }

    function additem($displaytext, $userid=0, $indent=0, $position=false, $duetime=false, $moduleid=0, $optional=learningtimecheck_OPTIONAL_NO, $hidden=learningtimecheck_HIDDEN_NO) {
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
                $this->items[$item->id]->teachermark = learningtimecheck_TEACHERMARK_UNDECIDED;
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

    function updateitemtext($itemid, $displaytext, $duetime=false) {
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
            if ($item->hidden == learningtimecheck_HIDDEN_NO) {
                $item->hidden = learningtimecheck_HIDDEN_MANUAL;
            } else if ($item->hidden == learningtimecheck_HIDDEN_MANUAL) {
                $item->hidden = learningtimecheck_HIDDEN_NO;
            }

            $upditem = new stdClass;
            $upditem->id = $itemid;
            $upditem->hidden = $item->hidden;
            $DB->update_record('learningtimecheck_item', $upditem);

            // If the item is a section heading, then show/hide all items in that section
            if ($item->itemoptional == learningtimecheck_OPTIONAL_HEADING) {
                if ($item->hidden) {
                    foreach ($this->items as $it) {
                        if ($it->position <= $item->position) {
                            continue;
                        }
                        if ($it->itemoptional == learningtimecheck_OPTIONAL_HEADING) {
                            break;
                        }
                        if (!$it->moduleid) {
                            continue;
                        }
                        if ($it->hidden == learningtimecheck_HIDDEN_NO) {
                            $it->hidden = learningtimecheck_HIDDEN_MANUAL;
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
                        if ($it->itemoptional == learningtimecheck_OPTIONAL_HEADING) {
                            break;
                        }
                        if (!$it->moduleid) {
                            continue;
                        }
                        if ($it->hidden == learningtimecheck_HIDDEN_MANUAL) {
                            $it->hidden = learningtimecheck_HIDDEN_NO;
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

    function deleteitem($itemid, $forcedelete=false) {
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

        $DB->delete_records('learningtimecheck_item', array('id' => $itemid) );
        $DB->delete_records('learningtimecheck_check', array('item' => $itemid) );

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

    function indentitemto($itemid, $indent) {
        global $DB;

        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1
            return;
        }

        $position = $this->items[$itemid]->position;
        if ($position == 1) {
            $indent = 0;
        }

        if ($indent < 0) {
            $indent = 0;
        } else if ($indent > learningtimecheck_MAX_INDENT) {
            $indent = learningtimecheck_MAX_INDENT;
        }

        $oldindent = $this->items[$itemid]->indent;
        $adjust = $indent - $oldindent;
        if ($adjust == 0) {
            return;
        }
        $this->items[$itemid]->indent = $indent;
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->indent = $indent;
        $DB->update_record('learningtimecheck_item', $upditem);

        // Update all 'children' of this item to new indent
        foreach ($this->items as $item) {
            if ($item->position > $position) {
                if ($item->indent > $oldindent) {
                    $item->indent += $adjust;
                    $upditem = new stdClass;
                    $upditem->id = $item->id;
                    $upditem->indent = $item->indent;
                    $DB->update_record('learningtimecheck_item', $upditem);
                } else {
                    break;
                }
            }
        }
    }

    function indentitem($itemid) {
        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1
            return;
        }
        $this->indentitemto($itemid, $this->items[$itemid]->indent + 1);
    }

    function unindentitem($itemid) {
        if (!isset($this->items[$itemid])) {
            // Not able to indent useritems, as they are always parent + 1
            return;
        }
        $this->indentitemto($itemid, $this->items[$itemid]->indent - 1);
    }

    function makeoptional($itemid, $optional, $heading=false) {
        global $DB;

        if (!isset($this->items[$itemid])) {
            return;
        }

        if ($heading) {
            $optional = learningtimecheck_OPTIONAL_HEADING;
        } else if ($optional) {
            $optional = learningtimecheck_OPTIONAL_YES;
        } else {
            $optional = learningtimecheck_OPTIONAL_NO;
        }

        if ($this->items[$itemid]->moduleid) {
            $op = $this->items[$itemid]->itemoptional;
            if ($op == learningtimecheck_OPTIONAL_HEADING) {
                return; // Topic headings must stay as headings
            } else if ($this->items[$itemid]->itemoptional == learningtimecheck_OPTIONAL_YES) {
                $optional = learningtimecheck_OPTIONAL_NO; // Module links cannot become headings
            } else {
                $optional = learningtimecheck_OPTIONAL_YES;
            }
        }

        $this->items[$itemid]->itemoptional = $optional;
        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->itemoptional = $optional;
        $DB->update_record('learningtimecheck_item', $upditem);
    }

    function nextcolour($itemid) {
        global $DB;

        if (!isset($this->items[$itemid])) {
            return;
        }

        switch ($this->items[$itemid]->colour) {
        case 'black':
            $nextcolour='red';
            break;
        case 'red':
            $nextcolour='orange';
            break;
        case 'orange':
            $nextcolour='green';
            break;
        case 'green':
            $nextcolour='purple';
            break;
        default:
            $nextcolour='black';
        }

        $upditem = new stdClass;
        $upditem->id = $itemid;
        $upditem->colour = $nextcolour;
        $DB->update_record('learningtimecheck_item', $upditem);
        $this->items[$itemid]->colour = $nextcolour;
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
            // Something has gone wrong, so update nothing
            return;
        }

        add_to_log($this->course->id, 'learningtimecheck', 'update checks', "report.php?id={$this->cm->id}&studentid={$this->userid}", $this->learningtimecheck->name, $this->cm->id);

        $updategrades = false;
        $declaredtimes = optional_param_array('declaredtime', '', PARAM_INT);
        if ($this->items) {
            foreach ($this->items as $item) {

            	// declarative time may concern autoupdated items
                $check = $DB->get_record_select('learningtimecheck_check', 'item = ? AND userid = ? ', array($item->id, $this->userid));
                if ((($item->isdeclarative == learningtimecheck_DECLARATIVE_STUDENTS) || ($item->isdeclarative == learningtimecheck_DECLARATIVE_BOTH)) && $item->checked){
                	if(is_array($declaredtimes)){
	                	if(array_key_exists($item->id, $declaredtimes)){
	                		$check->declaredtime = $declaredtimes[$item->id];
	                		$item->declaredtime = $declaredtimes[$item->id];
	                        $DB->update_record('learningtimecheck_check', $check);
	                	}
	                }
                }

                if (($this->learningtimecheck->autoupdate == learningtimecheck_AUTOUPDATE_YES) && ($item->moduleid)) {
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
                        $check->teachermark = learningtimecheck_TEACHERMARK_UNDECIDED;

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
                        $check->teachermark = learningtimecheck_TEACHERMARK_UNDECIDED;

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
            // Something has gone wrong, so update nothing
            return;
        }

        $updategrades = false;
        if ($this->learningtimecheck->teacheredit != learningtimecheck_MARKING_STUDENT) {
            if (!$student = $DB->get_record('user', array('id' => $this->userid))) {
                error('No such user!');
            }
            $info = $this->learningtimecheck->name.' ('.fullname($student, true).')';
            add_to_log($this->course->id, 'learningtimecheck', 'update checks', "report.php?id={$this->cm->id}&studentid={$this->userid}", $info, $this->cm->id);

            $teachermarklocked = $this->learningtimecheck->lockteachermarks && !has_capability('mod/learningtimecheck:updatelocked', $this->context);
            $teacherdeclaredtimes = optional_param_array('teacherdeclaredtime', '', PARAM_INT);

            foreach ($newchecks as $itemid => $newval) {
                if (isset($this->items[$itemid])) {
                    $item = $this->items[$itemid];

                    if ($teachermarklocked && $item->teachermark == learningtimecheck_TEACHERMARK_YES) {
                        continue; // Does not have permission to update marks that are already 'Yes'
                    }
                    if ($newval != $item->teachermark) {
                        $updategrades = true;

                        $newcheck = new stdClass;
                        $newcheck->teachertimestamp = time();
                        $newcheck->teachermark = $newval;
                        $newcheck->teacherid = $USER->id;

                        $item->teachermark = $newcheck->teachermark;
                        $item->teachertimestamp = $newcheck->teachertimestamp;
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
        $commentsunsorted = $DB->get_records_select('learningtimecheck_comment',"userid = ? AND itemid $isql", array_merge(array($this->userid), $iparams) );
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

        if ($this->learningtimecheck->teacheredit == learningtimecheck_MARKING_STUDENT) {
            // Student only lists do not have teacher marks to update
            return;
        }

        $userids = optional_param_array('userids', array(), PARAM_INT);
        if (!is_array($userids)) {
            // Something has gone wrong, so update nothing
            return;
        }

        $userchecks = array();
        foreach ($userids as $userid) {
            $checkdata = optional_param_array('items_'.$userid, array(), PARAM_INT);
            if (!is_array($checkdata)) {
                continue;
            }
            foreach ($checkdata as $itemid => $val) {
                if ($val != learningtimecheck_TEACHERMARK_NO && $val != learningtimecheck_TEACHERMARK_YES && $val != learningtimecheck_TEACHERMARK_UNDECIDED) {
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
                    if ($val == learningtimecheck_TEACHERMARK_UNDECIDED) {
                        continue; // Do not create an entry for blank marks
                    }

                    // No entry for this item - need to create it
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
                    if ($teachermarklocked && $currentchecks[$itemid]->teachermark == learningtimecheck_TEACHERMARK_YES) {
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
        $newteachercredittimes = optional_param_array('teachercredittime', false, PARAM_INT);
        $newenablecredits = optional_param_array('enablecredit', false, PARAM_INT);
        $newisdeclaratives = optional_param_array('isdeclarative', false, PARAM_INT);
        
        if ((!$newitems || !$newscores || !is_array($newscores)) && (!$newcredittimes) && (!$newenablecredits) && (!$newisdeclaratives) && (!$newteachercredittimes)) {
        	// perf trap
            return;
        }
        
        $changed = false;
        foreach ($newitems as $itemid) {
		    $newscore = 0 + @$newscore[$itemid];
		    $newcredittime = 0 + @$newcredittimes[$itemid];
		    $newteachercredittime = 0 + @$newteachercredittimes[$itemid];
		    $newisdeclarative = 0 + @$newisdeclaratives[$itemid];
			$newenablecredit = 0 + @$newenablecredits[$itemid];

            if (!isset($this->items[$itemid])) {
                continue;
            }
            $item =& $this->items[$itemid];

			/*
			// update anyway, for credittimes
            if (!$item->moduleid) {
                continue;
            }
            */
            
            if ((@$item->complete_score != $newscore) || ($item->credittime != $newcredittime) || ($item->enablecredit != $newenablecredit) || ($item->isdeclarative != $newisdeclarative) || ($item->teachercredittime != $newteachercredittime)) {
                $item->complete_score = $newscore;
                $item->credittime = $newcredittime;
                $item->enablecredit = $newenablecredit;
                $item->isdeclarative = $newisdeclarative;
                $item->teachercredittime = $newteachercredittime;
                $upditem = new stdClass;
                $upditem->id = $item->id;
                $upditem->complete_score = $newscore;
                $upditem->credittime = $newcredittime;
                $upditem->enablecredit = $newenablecredit;
                $upditem->isdeclarative = $newisdeclarative;
                $upditem->teachercredittime = $newteachercredittime;
                $DB->update_record('learningtimecheck_item', $upditem);
                $changed = true;
            }
        }

        if ($changed) {
            $this->update_all_checks_from_completion_scores();
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
        $sql = "SELECT cm.id AS cmid, m.name AS mod_name, i.id AS itemid, cm.completion AS completion
        FROM {modules} m, {course_modules} cm, {learningtimecheck_item} i
        WHERE m.id = cm.module AND cm.id = i.moduleid AND i.moduleid > 0 AND i.learningtimecheck = ? AND i.itemoptional != 2";

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
                            $check->teachermark = learningtimecheck_TEACHERMARK_UNDECIDED;

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
                    $check->teachermark = learningtimecheck_TEACHERMARK_UNDECIDED;

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
        $items = $DB->get_records_select('learningtimecheck_item', 'learningtimecheck = ? AND userid = 0 AND itemoptional = '.learningtimecheck_OPTIONAL_NO.' AND hidden = '.learningtimecheck_HIDDEN_NO.$groupings_sel, array($learningtimecheck->id), '', 'id');
        if (empty($items)) {
            return array(false, false);
        }
        $total = count($items);
        list($isql, $iparams) = $DB->get_in_or_equal(array_keys($items));
        $params = array_merge(array($userid), $iparams);

        $sql = "userid = ? AND item $isql AND ";
        if ($learningtimecheck->teacheredit == learningtimecheck_MARKING_STUDENT) {
            $sql .= 'usertimestamp > 0';
        } else {
            $sql .= 'teachermark = '.learningtimecheck_TEACHERMARK_YES;
        }
        $ticked = $DB->count_records_select('learningtimecheck_check', $sql, $params);

        return array($ticked, $total);
    }

    public static function get_user_groupings($userid, $courseid) {
        global $DB;
        $sql = "
			SELECT 
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

function learningtimecheck_course_is_page_formatted(){
	global $COURSE;
	
	return preg_match('/page/', $COURSE->format);
}


function learningtimecheck_get_credit_times(){
	$minutesstr = get_string('minutes');
	$hourstr = get_string('hour');
	$hoursstr = get_string('hours');
	return array('0' => get_string('disabled', 'learningtimecheck'),
		   '5' => '5 '.$minutesstr,
		   '10' => '10 '.$minutesstr,
		   '15' => '15 '.$minutesstr,
		   '20' => '20 '.$minutesstr,
		   '30' => '30 '.$minutesstr,
		   '40' => '40 '.$minutesstr,
		   '45' => '45 '.$minutesstr,
		   '60' => '1 '.$hourstr,
		   '75' => '1 '.$hourstr.' 15 '.$minutesstr,
		   '80' => '1 '.$hourstr.' 20 '.$minutesstr,
		   '90' => '1 '.$hourstr.' 30 '.$minutesstr,
		   '100' => '1 '.$hourstr.' 40 '.$minutesstr,
		   '105' => '1 '.$hourstr.' 45 '.$minutesstr,
		   '120' => '2 '.$hoursstr,
		   '150' => '2 '.$hoursstr.' 30 '.$minutesstr,
		   '180' => '3 '.$hoursstr,
		   '210' => '3 '.$hoursstr.' 30 '.$minutesstr,
		   '240' => '4 '.$hoursstr,
		   '270' => '4 '.$hoursstr.' 30 '.$minutesstr,
		   '300' => '5 '.$hoursstr
	);
}

function learningtimecheck_add_paged_params(){
	if (learningtimecheck_course_is_page_formatted()){
		if ($pageid = optional_param('page', 0, PARAM_INT)){
			echo "<input type=\"hidden\" name=\"page\" value=\"$pageid\" />";
		}
	}
}

