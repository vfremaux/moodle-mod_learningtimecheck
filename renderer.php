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
 * Moodle renderer used to display special elements of the learningtimecheck module
 *
 * @package   mod_Learningtimecheck
 * @category  mod
 * @copyright 2014 Valery Fremaux
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/learningtimecheck/rulefilterlib.php');
require_once($CFG->dirroot.'/report/learningtimecheck/lib.php');

define('LTC_MAX_CHK_MODS_PER_ROW', 4);

class mod_learningtimecheck_renderer extends plugin_renderer_base {

    protected $output;

    public $instance;
    public $groupid;
    public $groupingid;

    public function __construct() {
        global $OUTPUT;

        $this->output = $OUTPUT;

        $this->groupid = 0;
        $this->groupingid = 0;
        $this->instance = null;
    }

    public function set_instance($learningtimecheck) {
        $this->instance = $learningtimecheck;
    }

    public function view_tabs($currenttab) {
        global $COURSE, $USER;

        if (!$this->instance) {
            throw new CodingException('Misuse of an uninitialized renderer. Please review the code');
        }

        $tabs = array();
        $row = array();
        $inactive = array();
        $activated = array();

        $params = array();
        $params['id'] = $this->instance->cm->id;
        if ($COURSE->format == 'page') {
            if ($pageid = optional_param('page', 0, PARAM_INT)) {
                $params['page'] = $pageid;
            }
        }

        // View or preview.
        if ($this->instance->canpreview()) {
            $params['view'] = 'preview';
            $taburl = new moodle_url('/mod/learningtimecheck/view.php', $params);
            $tabs[0][] = new tabobject('preview', $taburl, get_string('preview', 'learningtimecheck'));
        } else {
            $params['view'] = 'view';
            $taburl = new moodle_url('/mod/learningtimecheck/view.php', $params);
            $tabs[0][] = new tabobject('view', $taburl, get_string('view', 'learningtimecheck'));
        }

        if ($this->instance->canviewreports()) {
            // Progress report.
            $params['view'] = 'report';
            $taburl = new moodle_url('/mod/learningtimecheck/view.php', $params);
            $tabs[0][] = new tabobject('report', $taburl, get_string('report', 'learningtimecheck'));
        }

        if ($this->instance->canedit()) {
            unset($params['view']);
            $taburl = new moodle_url('/mod/learningtimecheck/edit.php', $params);
            $tabs[0][] = new tabobject('edit', $taburl, get_string('editchecks', 'learningtimecheck'));
        }

        $canviewcc = $this->instance->canviewcoursecalibrationreport();
        $canviewtb = $this->instance->canviewtutorboard();
        $canviewr = $this->instance->canviewreports();

        if ($canviewcc || $canviewtb || $canviewr) {
            if (learningtimecheck_supports_feature('time/tutor')) {
                if ($canviewcc) {
                    $params = array('id' => $this->instance->cm->id);
                    $taburl = new moodle_url('/mod/learningtimecheck/pro/coursecalibrationreport.php', $params);
                    $tabs[0][] = new tabobject('reports', $taburl, get_string('allreports', 'learningtimecheck'));
                } else if ($canviewtb) {
                    $params = array('id' => $this->instance->cm->id);
                    $taburl = new moodle_url('/mod/learningtimecheck/pro/coursetutorboard.php', $params);
                    $label = get_string('tutorboard', 'learningtimecheck');
                    $tabs[0][] = new tabobject('tutorboard', $taburl, $label);
                } else {
                    if ($canviewr) {
                        $globalparams = array('id' => $COURSE->id);
                    } else {
                        $globalparams = array('id' => $COURSE->id,
                                        'itemid' => $USER->id,
                                        'view' => 'user');
                    }
                    $taburl = new moodle_url('/report/learningtimecheck/index.php', $globalparams);
                    $tabs[0][] = new tabobject('globalreports', $taburl, get_string('reports', 'learningtimecheck'));
                }
            }

            if (in_array($currenttab, array('reports', 'tutorboard', 'calibrationreport'))) {

                if (learningtimecheck_supports_feature('time/tutor')) {
                    if ($canviewcc) {
                        unset($params['view']);
                        $params = array('id' => $this->instance->cm->id);
                        $taburl = new moodle_url('/mod/learningtimecheck/pro/coursecalibrationreport.php', $params);
                        $label = get_string('coursecalibrationreport', 'learningtimecheck');
                        $tabs[1][] = new tabobject('calibrationreport', $taburl, $label);
                    }

                    if ($canviewtb) {
                        $params = array('id' => $this->instance->cm->id);
                        $taburl = new moodle_url('/mod/learningtimecheck/pro/coursetutorboard.php', $params);
                        $label = get_string('tutorboard', 'learningtimecheck');
                        $tabs[1][] = new tabobject('tutorboard', $taburl, $label);
                    }
                }

                $coursecontext = context_course::instance($COURSE->id);
                if (has_capability('report/learningtimecheck:view', $coursecontext)) {
                    if ($canviewr) {
                        $globalparams = array('id' => $COURSE->id);
                    } else {
                        $globalparams = array('id' => $COURSE->id,
                                        'itemid' => $USER->id,
                                        'view' => 'user');
                    }
                    $taburl = new moodle_url('/report/learningtimecheck/index.php', $globalparams);
                    $tabs[1][] = new tabobject('globalreports', $taburl, get_string('reports', 'learningtimecheck'));
                }
            }
        }

        if (count($tabs[0]) == 1) {
            // No tabs for students.
            return;
        }

        if ($currenttab == 'reports') {
            $inactive[] = 'reports';
            $activated[] = 'calibrationreport';
        }

        if ($currenttab == 'report') {
            $activated[] = 'report';
        }

        if ($currenttab == 'calibrationreport') {
            $currenttab = 'reports';
            $activated[] = 'calibrationreport';
            $inactive = 'reports';
        }

        if ($currenttab == 'tutorboard') {
            $activated[] = 'tutorboard';
            $currenttab = 'reports';
            $inactive = 'reports';
        }

        if ($currenttab == 'edit') {
            $activated[] = 'edit';

            if (!$this->instance->items) {
                $inactive = array('view', 'report', 'preview');
            }
        }

        if ($currenttab == 'preview') {
            $activated[] = 'preview';
        }

        print_tabs($tabs, $currenttab, $inactive, $activated);
    }

    /**
     * print main item list in student view or when the teacher assesses
     * a student list.
     * @param boolean $viewother
     * @param boolean $isuserreport
     */
    public function view_items($viewother = false, $userreport = false) {
        global $DB, $PAGE, $CFG, $USER, $COURSE;

        // Get course fast cache.
        $modinfo = get_fast_modinfo($COURSE);

        $comments = $this->instance->learningtimecheck->teachercomments;
        $params = array('view' => 'view', 'id' => $this->instance->cm->id);
        $thispage = new moodle_url('/mod/learningtimecheck/view.php', $params);
        $context = context_module::instance($this->instance->cm->id);

        $template = new Stdclass;

        $teachermarklocked = false;
        $template->showcompletiondates = false;
        $template->viewother = $viewother;
        $template->cancomment = false;
        $template->canaddown = false; // Further work to retore user own items.

        if ($template->viewother) {
            // I'm teacher an viewing the item list of another user (student).
            if ($comments) {
                $template->cancomment = optional_param('editcomments', false, PARAM_BOOL);
            }
            $params = array('view' => 'view', 'id' => $this->instance->cm->id, 'studentid' => $this->instance->userid);
            $thispage = new moodle_url('/mod/learningtimecheck/view.php', $params);

            if (!$student = $DB->get_record('user', array('id' => $this->instance->userid) )) {
                print_error('errornosuchuser', 'learningtimecheck');
            }

            $template->fullname = fullname($student);
            $template->info = format_text($this->instance->learningtimecheck->name).' ('.fullname($student, true).')';

            // Command block.

            $template->prevbutton = $this->print_prev_user_button($thispage, $COURSE->id, $this->instance->userid);
            if (!$template->cancomment) {
                $template->editcommentsbutton = $this->print_edit_comments_button($thispage);
            }
            $template->userdetailpdfbutton = $this->print_export_user_details_pdf_button($thispage, $COURSE->id, $this->instance->userid);
            $template->nextbutton = $this->print_next_user_button($thispage, $COURSE->id, $this->instance->userid);

            // Preparing intro.

            $template->teachermarklocked = $this->instance->learningtimecheck->lockteachermarks &&
                    !has_capability('mod/learningtimecheck:updatelocked', $this->instance->context);

            $reportsettings = learningtimecheck_class::get_report_settings();
            $template->showcompletiondates = $reportsettings->showcompletiondates;

            $strteacherdate = get_string('teacherdate', 'mod_learningtimecheck');
            $struserdate = get_string('userdate', 'mod_learningtimecheck');
            $strteachername = get_string('teacherid', 'mod_learningtimecheck');

            if ($template->showcompletiondates) {
                $teacherids = array();
                foreach ($this->instance->items as $item) {
                    if ($item->teacherid) {
                        $teacherids[$item->teacherid] = $item->teacherid;
                    }
                }
                $teachers = $DB->get_records_list('user', 'id', $teacherids, '', 'id,'.get_all_user_name_fields(true, ''));
                foreach ($this->instance->items as $item) {
                    if (isset($teachers[$item->teacherid])) {
                        $item->teachername = fullname($teachers[$item->teacherid]);
                    } else {
                        $item->teachername = false;
                    }
                }
            }
        }

        $intro = $this->instance->learningtimecheck->intro;
        if (!empty($intro)) {
            $template->hasintro = true;
            $intro = file_rewrite_pluginfile_urls($intro, 'pluginfile.php', $this->instance->context->id,
                                                  'mod_learningtimecheck', 'intro', null);
            $opts = array('trusted' => $CFG->enabletrusttext);

            $template->intro = format_text($intro, $this->instance->learningtimecheck->introformat, $opts);
        }

        // Progressbar if relevant.

        $template->showteachermark = false;
        $template->showcheckbox = false;
        $template->isteacher = has_capability('mod/learningtimecheck:updateother', $context);
        if ($template->viewother || $userreport) {
            $template->hasprogress = true;
            $template->progressbars = $this->progressbar();
            $template->showteachermark = ($this->instance->learningtimecheck->teacheredit == LTC_MARKING_TEACHER) ||
                    ($this->instance->learningtimecheck->teacheredit == LTC_MARKING_BOTH) ||
                    ($this->instance->learningtimecheck->teacheredit == LTC_MARKING_EITHER);
            $template->showcheckbox = (($this->instance->learningtimecheck->teacheredit == LTC_MARKING_BOTH && $template->viewother)) ||
                    (($this->instance->learningtimecheck->teacheredit == LTC_MARKING_EITHER && $template->viewother));
            $template->teachermarklocked = @$template->teachermarklocked && @$template->showteachermark; // Make sure this is OFF, if not showing teacher marks.
        }

        $overrideauto = ($this->instance->learningtimecheck->autoupdate != LTC_AUTOUPDATE_YES);
        $checkgroupings = $this->instance->learningtimecheck->autopopulate && ($this->instance->groupings !== false);

        $canupdateown = $this->instance->canupdateown();
        $updateform = ($template->showcheckbox && $canupdateown && !$template->viewother && $userreport) ||
                       ($template->isteacher && ($template->showteachermark || $template->cancomment));
        // $updateform = true;

        if (!$this->instance->items) {
            print_string('noitems', 'learningtimecheck');
        } else {

            $focusitem = false;
            $addown = $this->instance->canaddown() && $this->instance->useredit;

            if ($updateform) {
                if ($this->instance->canaddown() && !$viewother) {
                    // DEPRECATED on learningtimecheck at the moment.
                    echo $this->add_own_form($thispage, $addown);
                }

                if (!$viewother) {
                    // Load the Javascript required to send changes back to the server (without clicking 'save').
                    $jsmodule = array(
                        'name' => 'mod_learningtimecheck',
                        'fullpath' => new moodle_url('/mod/learningtimecheck/updatechecks.js')
                    );
                    $updatechecksurl = new moodle_url('/mod/learningtimecheck/updatechecks.php');
                    // Progress bars should only be updated with 'student only' learningtimechecks:
                    $updateprogress = $showteachermark ? 0 : 1;
                    $args = array($updatechecksurl->out(), sesskey(), $this->instance->cm->id, $updateprogress);
                    $PAGE->requires->js_init_call('M.mod_learningtimecheck.init', $args, true, $jsmodule);
                }

                $template->formurl = $thispage->out_omit_querystring();
                $template->realview = optional_param('view', '', PARAM_TEXT);
                $template->id = $thispage->get_param('id');
                $template->userid = $this->instance->userid;
                $template->pagedparams = learningtimecheck_add_paged_params();
                $template->action = ($isteacher ? 'teacherupdatechecks' : 'updatechecks');
                $template->sesskey = sesskey();
            }

            if ($this->instance->useritems) {
                reset($this->instance->useritems);
            }

            if ($comments) {
                list($isql, $iparams) = $DB->get_in_or_equal(array_keys($this->instance->items));
                $params = array_merge(array($this->instance->userid), $iparams);
                $commentsunsorted = $DB->get_records_select('learningtimecheck_comment', "userid = ? AND itemid $isql", $params);
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

            /*
             *
             * start producing item list
             *
             */

            $allitems = array_values($this->instance->items);

            for ($i = 0; $i < count($allitems); $i++) {
                $template->hasitems = true;
                $itemtpl = new StdClass;

                $item = $allitems[$i];
                $nextitem = @$allitems[$i + 1];

                $itemtpl->isheading = $item->itemoptional == LTC_OPTIONAL_HEADING;
                $nextisheading = $nextitem && ($nextitem->itemoptional == LTC_OPTIONAL_HEADING);

                if ($itemtpl->isheading && $nextisheading) {
                    // Do not print empty sections.
                    continue;
                }

                if ($item->hidden) {
                    continue;
                }

                if ($checkgroupings && !empty($item->grouping)) {
                    if (!in_array($item->grouping, $this->instance->groupings)) {
                        // Current user is not a member of this item's grouping, so skip.
                        continue;
                    }
                }

                $itemtpl->itemname = '"item'.$item->id.'"';
                $itemtpl->checked = (($updateform || $template->viewother || $userreport) && @$item->checked) ? ' checked="checked" ' : '';
                if ($template->viewother || $userreport) {
                    $itemtpl->checked .= ' disabled="disabled" ';
                } else if (!$overrideauto && $item->moduleid) {
                    $itemtpl->checked .= ' disabled="disabled" ';
                }

                if ($itemtpl->isheading) {
                    $itemtpl->optional = ' class="itemheading" ';
                    $itemtpl->checkclass = '';
                } else if ($item->itemoptional == LTC_OPTIONAL_YES) {
                    $itemtpl->optional = ' class="itemoptional" ';
                    $itemtpl->checkclass = ' itemoptional';
                } else {
                    $itemtpl->optional = '';
                    $itemtpl->checkclass = '';
                }

                $itemtpl->itemclass = ($itemtpl->isheading) ? 'heading' : '';
                $itemtpl->id = $item->id;

                /*
                 * Print main line.
                 */

                $itemtpl->pixurl = '';
                if (!$itemtpl->isheading) {
                    if (!$template->showcheckbox) {
                        $mandatorypix = ($item->itemoptional == LTC_OPTIONAL_YES) ? 'optional' : 'mandatory';
                        $checkedpix = ($item->checked) ? 'marked' : 'unmarked';
                        $pixname = 'item_'.$checkedpix.'_'.$mandatorypix;
                        $itemtpl->pixurl = $this->output->image_url($pixname, 'mod_learningtimecheck');
                    }
                }

                $itemtpl->hasicon = false;
                $itemtpl->modiconimage = '';
                if ($item->moduleid) {
                    $itemtpl->hasicon = true;
                    if ($mod = @$modinfo->cms[$item->moduleid]) {
                        $attrs = array('src' => $mod->get_icon_url(),
                                       'class' => 'ltc-icon-medium activityicon',
                                       'alt' => $mod->modfullname,
                                       'title' => $mod->modfullname);
                        $itemtpl->modiconimage = '&nbsp;'.html_writer::empty_tag('img', $attrs);
                    }
                }

                $itemtpl->displaytext = s($item->displaytext);

                $itemtpl->haslink = false;
                if (isset($item->modulelink)) {
                    $itemtpl->haslink = true;
                    $alt = get_string('linktomodule', 'learningtimecheck');
                    $itemtpl->modulepix = $this->output->pix_icon('follow_link', $alt, 'learningtimecheck');
                    $itemtpl->modulelink = $item->modulelink;
                }

                $itemtpl->hascredittime = false;
                if (!empty($item->credittime)) {
                    $itemtpl->hascredittime = true;
                    $itemtpl->creditstr = get_string('itemcredittime', 'learningtimecheck', $item->credittime);
                }

                $itemtpl->hasdeclaredtime = false;
                if (!empty($item->declaredtime) && (@$item->isdeclarative > 0) && !$isheading) {
                    $itemtpl->hasdeclaredtime = true;
                    $itemtpl->declaredstr = get_string('itemdeclaredtime', 'learningtimecheck', $item->declaredtime);
                }

                if ($viewother || !$userreport) {
                    $itemtpl->hastscoupling = false;
                    if (!empty($item->enablecredit)) {
                        $itemtpl->hastscoupling = true;
                    }
                }

                /*
                 * Print item forms.
                 */
                $itemtpl->hascollectform = false;
                $itemtpl->teachermarkimgurl = '';
                $itemtpl->titletext = '';

                if ($template->showteachermark) {
                    if (!$template->isheading) {
                        $itemtpl->hascollectform = true;
                        if ($template->viewother) {
                            // I am a teacher that is marking a student.
                            $cond = $teachermarklocked && $item->teachermark == LTC_TEACHERMARK_YES;
                            $disabledarr = ($cond) ? array('disabled' => 'disabled') : array();
                            $opts = learningtimecheck_get_teacher_mark_options();
                            $itemtpl->teachermarkselect = html_writer::select($opts, "items[$item->id]", $item->teachermark, '', $disabledarr);
                        } else {
                            // I am a student.
                            list($imgsrc, $titletext) = $this->instance->get_teachermark($item->id);
                            $itemtpl->teachermarkimgurl = $imgsrc;
                            $itemtpl->titletext = $titletext;
                        }
                    }
                }

                if (@$item->isdeclarative > 0 && !$itemtpl->isheading) {

                    $itemtpl->hascollectform = true;
                    $itemtpl->isdeclarative = $item->isdeclarative;

                    // Teacher side.
                    if ($template->isteacher && ($item->isdeclarative > LTC_DECLARATIVE_STUDENTS)) {
                        $template->teacherdeclaration = true;

                        if ($USER->id != $this->instance->userid) {
                            // We are evaluating other.
                            $collectformhaselements = true;
                            $declaredtimedisabled = ($item->teachermark == LTC_TEACHERMARK_UNDECIDED);
                            $opts = learningtimecheck_get_credit_times('thin');
                            $attrs = array('onchange' => 'learningtimecheck_updatechecks_show()',
                                           'id' => 'declaredtime'.$item->id,
                                           'class' => 'teachertimedeclarator');
                            $name = "teacherdeclaredtimeperuser[$item->id]";
                            $itemtpl->teacherdeclarativeperuserselect = html_writer::select($opts, $name, $item->teacherdeclaredtime, '', $attrs);

                            if (@$item->declaredtime) {
                                $itemtpl->declaredtime = $item->declaredtime;
                            }
                        } else {
                            // We are declaring item level time.
                            $collectformhaselements = true;
                            $opts = learningtimecheck_get_credit_times('thin');
                            $attrs = array('onchange' => 'learningtimecheck_updatechecks_show()',
                                           'id' => 'declaredtime'.$item->id,
                                           'class' => 'teachertimedeclarator',
                                           'autocomplete' => 'off');
                            $name = "teacherdeclaredtime[$item->id]";
                            $itemtpl->teacherdeclarativeselect = html_writer::select($opts, $name, $item->teacherdeclaredtime, '', $attrs);
                        }
                    }

                    if (($USER->id == $this->instance->userid) &&
                            (($item->isdeclarative == LTC_DECLARATIVE_STUDENTS) ||
                                    ($item->isdeclarative == LTC_DECLARATIVE_BOTH))) {
                        if (!$template->isteacher) {
                            $template->studentdeclaration = true;
                            // Students are declaring their student time.
                            $collectformhaselements = true;
                            $declaredtimedisabled = (!$item->checked);
                            $opts = learningtimecheck_get_credit_times();
                            $name = "declaredtime[{$item->id}]";
                            $attrs = array('onchange' => 'learningtimecheck_updatechecks_show()',
                                           'id' => 'declaredtime'.$item->id,
                                           'class' => 'sudenttimedeclarator',
                                           'autocomplete' => 'off');
                            if (!empty($this->instance->learningtimecheck->lockstudentinput)) {
                                $attrs['disabled'] = 'disabled';
                            }
                            $itemtpl->studentdeclarativeselect = html_writer::select($opts, $name, $item->declaredtime, '', $attrs);
                        }
                    }
                }

                if ($template->canaddown) {

                    $params = array('itemid' => $item->id, 'sesskey' => sesskey(), 'what' => 'startadditem');
                    $itemtpl->addownurl = $thispage->out(true, $params);
                    $itemtpl->addowntitle = get_string('additemalt', 'learningtimecheck');
                    $itemtpl->addownicon = $this->output->pix_icon('add', $title, 'learningtimecheck').'</a>';
                }

                if ($template->showcompletiondates) {
                    if ($template->isheading) {
                        if ($template->showteachermark &&
                                $item->teachermark != LTC_TEACHERMARK_UNDECIDED &&
                                        $item->teachertimestamp) {
                            if ($item->teachername) {
                                $itemtpl->teachername = $item->teachername;
                            }
                            $itemtpl->strteacherdate = userdate($item->teachertimestamp, get_string('strftimedatetimeshort'));
                        }
                        if ($template->showcheckbox && $item->checked && $item->usertimestamp) {
                            $itemtpl->struserdate = userdate($item->usertimestamp, get_string('strftimedatetimeshort'));
                        }
                    }
                }

                $itemtpl->usecomments = false;
                $foundcomment = false;
                if ($comments) {
                    $itemtpl->usecomments = true;
                    if (array_key_exists($item->id, $comments)) {
                        $comment = $comments[$item->id];
                        $itemtpl->commenttext = $comment->text;
                        $foundcomment = true;
                        if ($comment->commentby) {
                            $params = array('id' => $comment->commentby, 'course' => $this->instance->course->id);
                            $userurl = new moodle_url('/user/view.php', $params);
                            $itemtpl->commenteruserurl = $userurl;
                            $itemtpl->commenterfullname = fullname($commentusers[$comment->commentby]);
                        }
                    }
                }

                // Output any user-added items.
                $template->hasuseritems = false;
                if ($this->instance->useritems) {
                    $template->hasuseritems = true;
                }
                $template->items[] = $itemtpl;
            }
        }

        return $this->output->render_from_template('mod_learningtimecheck/view_items', $template);
    }

    /**
     * prints the coursecalibration report information
     *
     */
    public function view_coursecalibrationreport() {
        global $COURSE, $DB;

        $alllearningtimechecks = $DB->get_records('learningtimecheck', array('course' => $COURSE->id));

        $totaltime = 0;
        $totalestimated = 0;
        $totalteachercreditable = 0;

        if ($alllearningtimechecks) {

            $credittimestr = get_string('credittime', 'learningtimecheck');
            $creditedstr = get_string('timesource', 'learningtimecheck');
            $teachercredittimestr = get_string('teachercredittime', 'learningtimecheck');
            $teachercredittimeperuserstr = get_string('teachercredittimeperuser', 'learningtimecheck');
            $teachercredittimeforusersstr = get_string('teachercredittimeforusers', 'learningtimecheck');
            $teachercredittimeforitemstr = get_string('teachercredittimeforitem', 'learningtimecheck');

            $coursecontext = context_course::instance($COURSE->id);
            $groupid = groups_get_activity_group($this->instance->cm);
            $enrolled = get_enrolled_users($coursecontext, '', $groupid, 'u.id, u.username');

            $usercount = count($enrolled);

            $hasmany = count($alllearningtimechecks);

            foreach ($alllearningtimechecks as $chkl) {
                if ($hasmany > 1) {
                    echo $this->output->heading($chkl->name, 3);
                }
                $select = '
                    learningtimecheck = ? AND
                    (credittime > 0 OR
                    teachercredittime > 0)
                ';
                $sort = 'learningtimecheck,position';
                $items = $DB->get_records_select('learningtimecheck_item', $select, array($chkl->id), $sort);
                if ($items) {
                    $table = new html_table();
                    $table->head = array('',
                                         $credittimestr,
                                         $creditedstr,
                                         $teachercredittimestr,
                                         $teachercredittimeperuserstr,
                                         $teachercredittimeforusersstr,
                                         $teachercredittimeforitemstr);

                    $table->size = array('40%', '10%', '10%', '10%', '10%', '10%', '10%');
                    $table->align = array('left', 'center', 'center', 'center', 'right', 'center', 'center');
                    $table->width = '95%';
                    $table->attributes['class'] = 'generaltable ltc-coursecalibration-table';

                    foreach ($items as $item) {
                        $totaltime += $item->credittime;
                        $totalestimated += ($item->enablecredit) ? 0 : $item->credittime;
                        $totaltimeforusersnum = 0 + @$item->teachercredittimeperuser * $usercount;
                        $totalteachercreditable += $item->teachercredittime;
                        $totalteachercreditable += $totaltimeforusersnum;
                        $teacheritemtime = learningtimecheck_format_time($item->teachercredittime);
                        $totaltimeforusers = learningtimecheck_format_time($totaltimeforusersnum);
                        $itemtimeperuser = learningtimecheck_format_time(0 + @$item->teachercredittimeperuser). ' x '.$usercount.' = '.$totaltimeforusers;
                        $cond = $item->isdeclarative != LTC_DECLARATIVE_STUDENTS && $item->isdeclarative != LTC_DECLARATIVE_BOTH;
                        $creditsource = ($cond) ? get_string('credit', 'learningtimecheck') : get_string('estimated', 'learningtimecheck');
                        $totaltimeforitem = learningtimecheck_format_time($item->teachercredittime + $totaltimeforusersnum);
                        $row = array($item->displaytext,
                                     $item->credittime,
                                     $creditsource,
                                     $teacheritemtime,
                                     $itemtimeperuser,
                                     $totaltimeforusers,
                                     $totaltimeforitem);
                        $table->data[] = $row;
                    }
                    echo html_writer::table($table);
                }
            }

            echo '<p><b>'.get_string('totalcoursetime', 'learningtimecheck').': </b>'.learningtimecheck_format_time($totaltime).'<br/>';
            echo '<b>'.get_string('totalestimatedtime', 'learningtimecheck').': </b>'.learningtimecheck_format_time($totalestimated).'</p>';
            echo '<b>'.get_string('totalteacherestimatedtime', 'learningtimecheck').': </b>'.learningtimecheck_format_time($totalteachercreditable).'</p>';

        } else {
            echo get_string('nolearningtimecheckincourse', 'learningtimecheck');
        }
    }

    /**
     * prints the tutoring times (expenses)
     *
     */
    public function view_tutorboard() {
        global $USER, $COURSE, $DB;

        $context = context_course::instance($COURSE->id);

        $groups = groups_get_all_groups($COURSE->id);

        $thisurl = new moodle_url('/mod/learningtimecheck/pro/coursetutorboard.php', array('id' => $this->instance->cm->id));

        if (!empty($groups) && $COURSE->groupmode != NOGROUPS) {
            echo $this->group_grouping_menu($thisurl);
        }

        $group = groups_get_activity_groupmode($this->instance->cm);
        $targetusers = get_enrolled_users($context, '', $group, 'u.id, firstname, lastname, email, institution', 'lastname');

        $sql = "
            SELECT
                CONCAT(chck.userid, '_',chl.id) as markid,
                chck.userid as userid,
                chl.id as learningtimecheckid,
                chl.name,
                SUM(chi.teachercredittime) as expected,
                SUM(chck.teacherdeclaredtime) as realexpense,
                ".get_all_user_name_fields(true, 'u')."
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

        $fields = get_all_user_name_fields(false, '');

        if ($tutoredtimes = $DB->get_records_sql($sql, array($COURSE->id, $USER->id))) {
            foreach ($tutoredtimes as $tt) {
                $tutoredusers[$tt->userid][$tt->learningtimecheckid] = $tt;
                $tutoredusersfull[$tt->userid] = new StdClass;
                $tutoredusersfull[$tt->userid]->teachercredittime = 0 + @$tutoredusersfull[$tt->userid]->teachercredittime + $tt->expected;
                $tutoredusersfull[$tt->userid]->teacherdeclaredtime = 0 + @$tutoredusersfull[$tt->userid]->teacherdeclaredtime + $tt->realexpense;
                foreach ($fields as $f) {
                    $tutoredusersfull[$tt->userid]->$f = $tt->$f;
                }
            }
        }

        $table = new html_table();
        $table->head = array('<b>'.get_string('lastname').' '.get_string('firstname').'</b>',
                             '<b>'.get_string('realtutored', 'learningtimecheck').'</b>',
                             '<b>'.get_string('expectedtutored', 'learningtimecheck').'</b>');
        $table->align = array('left', 'center', 'center');
        $table->size = array('60%', '20%', '20%');
        $table->width = '100%';

        $fullcourseexpected = 0;
        $fullcourseexpense = 0;
        $declareddisp = '';
        foreach ($tutoredusers as $uid => $tu) {
            $credittime = $tutoredusersfull[$uid]->teachercredittime;
            $declaredtime = $tutoredusersfull[$uid]->teacherdeclaredtime;
            $positive = '<span class="positive">'.$declaredtime.' mn</span>';
            $negatiestr = '<span class="negative">'.$declaredtime.' mn</span>';
            $declareddisp = ($credittime > $declaredtime) ? $positivestr : $negativestr;
            $table->data[] = array(fullname($tutoredusersfull[$uid]), $declareddisp, $credittime.' mn');
            $fullcourseexpected += $credittime;
            $fullcourseexpense += $declaredtime;
        }
        $positivestr = '<span class="positive"><b>'.$fullcourseexpense.' mn</b></span>';
        $negativestr = '<span class="negative"><b>'.$fullcourseexpense.' mn</b></span>';
        $fullexpensedisp = ($fullcourseexpected > $fullcourseexpense) ? $positivestr : $negativestr;
        $table->data[] = array('<b>'.get_string('totalcourse', 'learningtimecheck').'</b>', $declareddisp, '<b>'.$fullcourseexpected.' mn</b>');

        echo html_writer::table($table);
    }

    /**
     * @see
     * DEPRECATED
     */
    public function view_own_report() {
        global $USER, $DB;

        echo "Deprecated. This function should be not used";
        return;

        if (!$this->instance) {
            throw new CodingException('Misuse of an uninitialized renderer. Please review the code');
        }

        $str = '';

        $this->instance->update_complete_scores();

        $reportsettings = learningtimecheck_class::get_report_settings();

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
        $checks = $DB->get_records_sql($sql, array($USER->id, $this->instance->learningtimecheck->id));

        $checkstates = array();
        if ($checks ) {
            $checksrecs = array_values($checks);
            for ($i = 0; $i < count($checksrecs); $i++) {

                $check = $checksrecs[$i];

                if ($check->hidden) {
                    continue;
                }

                if ($check->itemoptional == LTC_OPTIONAL_HEADING) {
                    if (isset($checksrecs[$i + 1]) && $checksrecs[$i + 1]->itemoptional == LTC_OPTIONAL_HEADING) {
                        continue;
                    }
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
            $str .= '<div class="advice">'.$nochecksstr.'</div>';
        }

        $str .= '<br/>';

        if ($this->instance->learningtimecheck->autopopulate == LTC_AUTOPOPULATE_COURSE) {
            $completionliststr = get_string('coursecompletionboard', 'learningtimecheck');
        } else {
            $completionliststr = get_string('completionboard', 'learningtimecheck');
        }
        $str .= '<div class="sideblock"><div class="header"><h2>'.$completionliststr.'</h2></div></div>';
        $str .= '<table class="ltc-own-report" width="100%"><tr valign="top">';
        $altno = get_string('studentmarkno', 'learningtimecheck');
        $altyes = get_string('studentmarkyes', 'learningtimecheck');
        $studentimg = array(0 => $this->output->pix_icon('spacer', $altno),
                            1 => $this->output->pix_icon('tick_amber_big', $altyes, 'mod_learningtimecheck'));
        $i = 0;
        foreach ($checkstates as $cs) {

            list($teachermark, $studentmark, $heading, $checkid) = $cs;

            if ($this->instance->items[$checkid]->hidden) {
                continue;
            }

            $itemname = s($this->instance->items[$checkid]->displaytext);

            if (!$heading) {
                $class = '';
                if ($teachermark == 1) {
                    $class = 'chklst-checked';
                } else if ($teachermark == 2) {
                    $class = 'chklst-unchecked';
                } else {
                    if ($studentmark) {
                        $class = 'chklst-done';
                    }
                }
                if ($this->instance->items[$checkid]->moduleid) {
                    if (@$this->instance->items[$checkid]->modulelink) {
                        $itemname = "<a href=\"{$this->instance->items[$checkid]->modulelink}\">$itemname</a>";
                    }
                }
                $str .= '<td class="$class reportcell">';
                $str .= '<div class="itemstate">'.$itemname.' '. $studentimg[0 + $studentmark]. '</div>';
                $select = '
                    userid = ? AND
                    itemid = ?
                ';
                if ($comments = $DB->get_records_select('learningtimecheck_comment', $select, array($USER->id, $checkid))) {
                    $str .= '<div class="comment">';
                    foreach ($comments as $comment) {
                        $fields = 'id,'.get_all_user_name_fields(true, '');
                        $commenter = $DB->get_record('user', array('id' => $comment->commentby), $fields);
                        $commentername = get_string('reportedby', 'learningtimecheck', fullname($commenter));
                        $str .= '<span title="'.$commentername.'">'.$comment->text.'</span>';
                    }
                    $str .= '</div>';
                }
                $str .= '</td>';
            } else {
                $str .= '<td class="$class reportcell">';
                $str .= '<h3>';
                $str .= $itemname;
                $str .= '</h3>';
                $str .= '</td>';
            }
            $str .= '</tr><tr valign="top">';
            $i++;
        }
        $str .= '</tr></table>';

        return $str;
    }

    /**
     * View all the assessable activites as colored blocks
     */
    public function view_own_report_blocks($hidelinks = false) {
        global $USER, $DB;

        $str = '';

        $this->instance->update_complete_scores();
        $reportsettings = learningtimecheck_class::get_report_settings();

        $table = new stdClass;
        $table->width = '100%';
        foreach ($this->instance->items as $item) {
            if ($item->hidden) {
                continue;
            }

            $table->head[] = s($item->displaytext);
            $table->size[] = '*';
            $table->skip[] = (!$reportsettings->showoptional) && ($item->itemoptional == LTC_OPTIONAL_YES);
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
        $checks = $DB->get_records_sql($sql, array($USER->id, $this->instance->learningtimecheck->id));

        $checkstates = array();
        if ($checks) {
            foreach ($checks as $check) {
                if ($check->hidden) {
                    continue;
                }

                if ($check->itemoptional == LTC_OPTIONAL_HEADING) {
                    $checkstates[] = array(false, false, true, $check->id, false);
                } else {
                    if ($check->usertimestamp > 0) {
                        $checkstates[] = array($check->teachermark, true, false, $check->id, false);
                    } else {
                        $isopt = $check->itemoptional == LTC_OPTIONAL_YES;
                        $checkstates[] = array($check->teachermark, false, false, $check->id, $isopt);
                    }
                }
            }
        }

        $str .= '<br/>';

        if ($this->instance->learningtimecheck->autopopulate == LTC_AUTOPOPULATE_COURSE) {
            $completionliststr = get_string('coursecompletionboard', 'learningtimecheck');
        } else {
            $completionliststr = get_string('completionboard', 'learningtimecheck');
        }
        $str .= '<div class="sideblock"><div class="header"><h2>'.$completionliststr.'</h2></div></div>';
        $str .= '<table class="ltc-report" cellspacing="4"><tr valign="top">';
        $alt = get_string('studentmarkyes', 'learningtimecheck');
        $pixicon = $this->output->pix_icon('tick_amber_big', $alt, 'mod_learningtimecheck');
        $studentimg = array(0 => $this->output->pix_icon('spacer', get_string('studentmarkno', 'learningtimecheck')),
                            1 => $pixicon);
        $i = 0;
        if (!empty($checkstates)) {
            foreach ($checkstates as $cs) {
                list($teachermark, $studentmark, $heading, $checkid, $optional) = $cs;

                $optionalclass = ($optional) ? 'optional' : '';
                if (!isset($this->instance->items[$checkid])) {
                    continue; // Missing (undeleted ?).
                }
                if ($this->instance->items[$checkid]->hidden) {
                    continue;
                }
                $itemname = s($this->instance->items[$checkid]->displaytext);
                if (!$heading) {
                    $class = 'cell';
                    if ($teachermark == 1) {
                        $class = 'cell-checked';
                    } else if ($teachermark == 2) {
                        $class = 'cell-unchecked';
                    } else if ($studentmark) {
                        $class = 'cell-done';
                    } else {
                        $class = 'cell '.$optionalclass;
                    }
                    if ($this->instance->items[$checkid]->moduleid) {
                        if (@$this->instance->items[$checkid]->modulelink && !$hidelinks) {
                            $itemname = '<a href="'.$this->instance->items[$checkid]->modulelink.'">'.$itemname.'</a>';
                        }
                    }
                    $str .= '<td class="'.$class.' reportcell">';
                    $str .= '<div class="itemstate">'.$itemname.' '. $studentimg[0 + $studentmark]. '</div>';
                    $select = '
                        userid = ? AND
                        itemid = ?
                    ';
                    if ($comments = $DB->get_records_select('learningtimecheck_comment', $select, array($USER->id, $checkid))) {
                        $str .= '<div class="comment">';
                        foreach ($comments as $comment) {
                            $fields = 'id,'.get_all_user_name_fields(true, '');
                            $commenter = $DB->get_record('user', array('id' => $comment->commentby), $fields);
                            $commentername = get_string('reportedby', 'learningtimecheck', fullname($commenter));
                            $str .= '<span title="'.$commentername.'">'.$comment->text.'</span>';
                        }
                        $str .= '</div>';
                    }
                    $str .= '</td>';
                    if ((($i + 1) % LTC_MAX_CHK_MODS_PER_ROW == 0) && ($i > 0)) {
                        $str .= '</tr><tr valign="top">';
                    }
                    $i++;
                }
            }
            $str .= '</tr></table>';
        } else {
            $nochecksstr = get_string('nochecks', 'learningtimecheck');
            $str .= '<div class="ltc-advice">'.$nochecksstr.'</div>';
        }

        return $str;
    }

    /**
     * displays items for edition. Full editing view must rely on database, not on learningtimecheck
     * memory instance 'items' which are filtered for the end user
     */
    public function view_edit_items() {
        global $COURSE, $DB, $USER;

        $context = context_module::instance($this->instance->cm->id);
        $forcetrainingsessions = has_capability('mod/learningtimecheck:forceintrainingsessions', $context, $USER->id, false);
        $config = get_config('learningtimecheck');
        $couplecredittomandatoryoption = $config->couplecredittomandatoryoption;

        // Get course fast cache.
        $modinfo = get_fast_modinfo($COURSE);

        $addatend = true;
        $focusitem = false;
        $hasauto = false;

        $params = array('id' => $this->instance->cm->id, 'sesskey' => sesskey());
        $thispage = new moodle_url('/mod/learningtimecheck/edit.php', $params);

        if ($this->instance->additemafter) {
            $thispage->param('additemafter', $this->instance->additemafter);
        }

        echo '<form action="'.$thispage.'" method="POST">';
        echo '<input type="hidden" name="id" value="'.$this->instance->cm->id.'" />';
        echo '<input type="hidden" name="what" value="update_complete_scores" />';
        echo learningtimecheck_add_paged_params();
        echo $this->autoupdate_advice($this->instance->learningtimecheck);

        /* *****
         * Start producing item list
         ****** */

        $select = '
            (userid = ? OR userid = 0) AND
            learningtimecheck = ?
        ';
        $items = $DB->get_records_select('learningtimecheck_item', $select, array($USER->id, $this->instance->learningtimecheck->id), 'position');
        $itemslist = array_values($items);

        if ($items) {
            $lastitem = count($items);
            $lastindent = 0;

            echo '<table id="ltc-edit" width="100%">';

            echo '<tr>';
            echo '<th>'.get_string('ismandatory', 'learningtimecheck').'</th>';
            echo '<th>';
            if ($this->instance->learningtimecheck->usetimecounterpart) {
                echo $this->timesettings_form($item, true);
            }
            echo '</th>'; // Commands column.
            echo '<th></th>'; // Commands column.
            echo '</tr>';

            $i = 0;
            foreach ($items as $item) {

                $i++;  // Prefetch pointer.

                $item->lastitem = $lastitem;

                if ($item->itemoptional == LTC_OPTIONAL_HEADING) {
                    $nextitem = @$itemslist[$i];
                    if (!$nextitem || ($nextitem->itemoptional == LTC_OPTIONAL_HEADING)) {
                        continue;
                    }
                }

                $itemname = '"item'.$item->id.'"';
                $thispage->param('itemid', $item->id);

                $autoitem = ($this->instance->learningtimecheck->autopopulate) && ($item->moduleid != 0);
                $hasauto = $hasauto || ($item->moduleid != 0);
                $hideclass = $item->hidden ? 'shadow' : '';

                echo '<tr valign="top">';
                echo '<td id="ltc-opt-controls-'.$item->id.'">';
                echo $this->item_optional_controls($item, $autoitem, $optional, $thispage);
                echo '<input type="hidden" name="items[]" value="'.$item->id.'" /> ';
                echo '</td>';

                // Item heading.
                if ($item->itemoptional == LTC_OPTIONAL_HEADING) {
                    echo '<td class="'.$hideclass.' heading">';
                    echo '<h2>'.strip_tags($item->displaytext).'</h2>';
                    echo '</td>';
                } else {
                    echo '<td class="'.$hideclass.'">';
                    echo '<h4>';
                    if ($item->moduleid) {
                        $mod = $modinfo->cms[$item->moduleid];
                        echo html_writer::empty_tag('img', array('src' => $mod->get_icon_url(),
                        'class' => 'iconlarge activityicon', 'alt' => $mod->modfullname, 'title' => $mod->modfullname));
                    }
                    echo ' '.strip_tags($item->displaytext).'</h4>';
                    echo '</td>';
                }

                echo '<td>';
                echo $this->item_edit_commands($thispage, $item, $autoitem);
                echo '</td>';

                echo '</tr>';

                echo '<tr class="'.$hideclass.'" valign="top">';

                echo '<td id="ltc-due-signal-'.$item->id.'">';
                echo $this->item_due_signal($item);
                echo '</td>';

                $declarative = ($item->isdeclarative == LTC_DECLARATIVE_STUDENTS || $item->isdeclarative == LTC_DECLARATIVE_BOTH);
                $isdeclarative = $declarative ? 'isdeclarative' : '';
                echo '<td id="ltc-time-settings-'.$item->id.'" class="ltc-time-settings '.$isdeclarative.'">';
                if ($this->instance->learningtimecheck->usetimecounterpart && !($item->itemoptional == LTC_OPTIONAL_HEADING)) {
                    echo $this->timesettings_form($item, false, $forcetrainingsessions, $couplecredittomandatoryoption);
                }
                echo '</td>';

                echo '<td></td>';
                echo '</tr>';
            }
            echo '</table>';
        }

        echo '<div class="ltc-submitter" style="height:60px;clear:both">';
        echo '<div class="ltc-submitter-element" style="text-align:right;height:40px">';
        echo '<br/><br/><input type="submit" name="update_complete_score" value="'.get_string('saveall', 'learningtimecheck').'" />';
        echo '</div>';
        echo '</div>';

        echo '</form>';

        echo '<form action="'.$thispage->out_omit_querystring().'" method="get" name="editoptions">';
        echo html_writer::input_hidden_params($thispage, array('sesskey', 'editdates'));
        echo learningtimecheck_add_paged_params();
        echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        echo '<fieldset class="ltc-box">';
        echo '<legend>'.get_string('editingoptions', 'learningtimecheck').'</legend>';

        // Remove autopopulated elements.
        if (!$this->instance->learningtimecheck->autopopulate && $hasauto) {
            echo '<div class="ltc-globalsetting-cell">';
            echo '<input type="submit" value="'.get_string('removeauto', 'learningtimecheck').'" name="removeauto" />';
            echo '</div>';
        }

        // Add timecounterparts globals.
        if ($this->instance->learningtimecheck->usetimecounterpart) {
            echo $this->timesettingglobals_form_additions();
        }
        echo '</fieldset>';
        echo '</form>';

    }

    /**
     *
     *
     */
    public function toggle_date_form() {
        global $SESSION;

        // Toggle due data editing.
        echo '<div class="ltc-globalsetting-cell">';
        echo '<form type="hidden" name="editdates" value="on" />';
        if (!empty($SESSION->learningtimecheck_editdates)) {
            echo '<input type="hidden" name="editdates" value="1" />';
            echo '<input type="submit" value="'.get_string('editdatesstart', 'learningtimecheck').'" />';
        } else {
            echo '<input type="hidden" name="editdates" value="0" />';
            echo '<input type="submit" value="'.get_string('editdatesstop', 'learningtimecheck').'" />';
        }
        echo '</form>';
        echo '</div>';
    }

    /**
     * This prints the due time for itmes. In case of offline items, this will be sotred
     * in item record. for any item linked to a course module the due time information if
     * retrieved from the course module itself
     */
    public function item_due_signal($item, $editing = false) {
        global $DB;

        if ($item->moduleid) {
            // If completion is enabled (necessary for autocompletion, then the completion due date is used.
            $completiondate = $DB->get_field('course_modules', 'completionexpected', array('id' => $item->moduleid));
            $frommodulestr = get_string('timeduefromcompletion', 'learningtimecheck');
            if ($completiondate) {
                if ($completiondate > time()) {
                    $span = userdate($completiondate, get_string('strftimedate'));
                    return '<span class="ltc-itemdue" title="'.$frommodulestr.'"> '.$span.'</span>';
                } else {
                    $span = userdate($completiondate, get_string('strftimedate'));
                    return '<span class="ltc-itemoverdue" title="'.$frommodulestr.'"> '.$span.'</span>';
                }
            }
        }
        return '';
    }

    /**
     *
     */
    public function edit_me_form($item, $url) {
        $str = '';

        $str .= '<form style="display:inline" action="'.$url->out_omit_querystring().'" method="post">';
        $str .= learningtimecheck_add_paged_params();
        $str .= '<input type="text" size="'.LTC_TEXT_INPUT_WIDTH.'" name="displaytext" value="'.s($item->displaytext).'" id="updateitembox" />';
        $str .= '<input type="hidden" name="what" value="updateitem" />';
        $str .= html_writer::input_hidden_params($thispage);
        $str .= '<input type="submit" name="updateitem" value="'.get_string('updateitem', 'learningtimecheck').'" />';
        $str .= '</form>';

        return $str;
    }

    /**
     * prints a checkbox that control item optionnality in the pedagogic contract
     * @param object $item
     * @param bool $autoitem
     * @param string $optional a value to feed for caller, with optional class text
     * @param url $thispage : deprecated.
     */
    public function item_optional_controls($item, $autoitem, &$optional, $thispage) {

        $str = '';

        $autoclass = ($autoitem) ? ' itemauto' : '';

        if ($item->itemoptional == LTC_OPTIONAL_HEADING) {
            if ($item->hidden) {
                $str .= '&nbsp;';
                $optional = ' class="'.$autoclass.' itemdisabled"';
            } else {
                $str .= '&nbsp;';
                $optional = ' class="itemheading '.$autoclass.'" ';
            }
        } else if ($item->itemoptional == LTC_OPTIONAL_YES) {
            if ($item->hidden) {
                $title = get_string('optionalitem', 'learningtimecheck');
                $str .= '<input type="checkbox"
                                name="optional[]"
                                value="'.$item->id.'"
                                id="optional'.$item->id.'"
                                title="'.$title.'"
                                alt="'.$title.'"
                                disabled="disabled">';
                $optional = ' class="'.$autoclass.' itemdisabled"';
            } else {
                $title = get_string('optionalitem', 'learningtimecheck');
                $str .= '<input type="checkbox"
                                name="optional[]"
                                value="'.$item->id.'"
                                id="optional'.$item->id.'"
                                title="'.$title.'"
                                alt="'.$title.'">';
                $optional = ' class="'.$autoclass.'"';
            }
        } else {
            if ($item->hidden) {
                $title = get_string('requireditem', 'learningtimecheck');
                $str .= '<input type="checkbox"
                                name="optional[]"
                                value="'.$item->id.'"
                                id="optional'.$item->id.'"
                                checked="checked"
                                title="'.$title.'"
                                alt="'.$title.'"
                                disabled="disabled">&nbsp;';
                $optional = ' class="'.$autoclass.' itemdisabled"';
            } else {
                $title = get_string('requireditem', 'learningtimecheck');
                $str .= '<input type="checkbox"
                                name="optional[]"
                                value="'.$item->id.'"
                                id="optional'.$item->id.'"
                                checked="checked"
                                title="'.$title.'"
                                alt="'.$title.'">&nbsp;';
                $optional = ' class="'.$autoclass.'"';
            }
        }

        return $str;
    }

    public function autoupdate_advice($learningtimecheck) {
        $str = '';

        if ($learningtimecheck->autopopulate && $learningtimecheck->autoupdate) {

            $formsubmit = '<input type="submit"
                                  name="update_complete_score"
                                  value="'.get_string('updatecompletescore', 'learningtimecheck').'" /> ';

            if ($learningtimecheck->teacheredit == LTC_MARKING_STUDENT) {
                $str .= '<p>'.get_string('autoupdatewarning_student', 'learningtimecheck').'</p>';
            } else if ($learningtimecheck->teacheredit == LTC_MARKING_TEACHER) {
                $str .= '<p class="ltc-warning">'.get_string('autoupdatewarning_teacher', 'learningtimecheck').'</p>';
            } else {
                $str .= '<p class="ltc-warning">'.get_string('autoupdatewarning_both', 'learningtimecheck').'</p>';
            }
        }

        return $str;
    }

    /**
     * All reports of users for teachers
     */
    public function view_report($ausers, $totalusers) {
        global $PAGE, $COURSE;

        if (!$this->instance) {
            throw new CodingException('Misuse of an uninitialized renderer. Please review the code');
        }

        $reportsettings = learningtimecheck_class::get_report_settings();

        $editchecks = $this->instance->caneditother() && optional_param('editchecks', false, PARAM_BOOL);

        $page = optional_param('page', 0, PARAM_INT);
        $perpage = optional_param('perpage', 20, PARAM_INT);

        $hpage = optional_param('hpage', 0, PARAM_INT);

        $params = array('id' => $this->instance->cm->id, 'view' => 'report', 'sesskey' => sesskey(), 'hpage' => $hpage, 'page' => $page);
        $thispage = new moodle_url('/mod/learningtimecheck/view.php', $params);

        if ($editchecks) {
            $thispage->param('editchecks', 'on');
        }

        // Event filters.

        echo $this->print_event_filter($thispage);
        $reportrenderer = $PAGE->get_renderer('report_learningtimecheck');
        echo $reportrenderer->options('report', $COURSE->id, 0, 'mod/'.$this->instance->cm->id.'/report');

        // Course report global indicators.
        echo $this->print_global_counters();

        // Advice.

        if ($this->instance->learningtimecheck->autoupdate && $this->instance->learningtimecheck->autopopulate) {
            if ($this->instance->learningtimecheck->teacheredit == LTC_MARKING_TEACHER) {
                echo '<p class="ltc-warning">'.get_string('autoupdatewarning_teacher', 'learningtimecheck').'</p>';
            } else if ($this->instance->learningtimecheck->teacheredit == LTC_MARKING_BOTH) {
                echo '<p class="ltc-warning">'.get_string('autoupdatewarning_both', 'learningtimecheck').'</p>';
            }
        }

        // Group control.

        echo '<table width="100%" cellspacing="10">';
        echo '<tr><td>';
        groups_print_activity_menu($this->instance->cm, $thispage);
        $activegroup = groups_get_activity_group($this->instance->cm, true);
        if ($activegroup) {
            $this->groupid = $activegroup;
        }
        echo '</td><td>';
        echo $this->namefilter($thispage);
        echo '</td></tr>';
        echo '</table>';

        // Report control.

        echo $this->output->box_start('controls');

        if ($reportsettings->showprogressbars) {
            echo $this->optional_hide_button($thispage, $reportsettings);
            echo '&nbsp;';
        }

        $reportrenderer = $PAGE->get_renderer('report_learningtimecheck');

        echo $this->toggle_progressbar_button($thispage, $reportsettings);
        echo '&nbsp;';
        echo $this->print_velocity_button($thispage);
        echo '&nbsp;';
        echo $this->print_export_excel_button($thispage);
        echo '&nbsp;';
        echo $this->print_export_pdf_button($thispage);
        echo '&nbsp;';

        $hpage = optional_param('hpage', 0, PARAM_INT);
        if (!$reportsettings->showprogressbars) {
            $itemcount = count($this->instance->items);
            if ($itemcount > LTC_HPAGE_SIZE) {
                if ($hpage > 0) {
                    echo $this->print_previous_hpage_button($thispage, $hpage);
                    echo '&nbsp;';
                }
                if (($hpage + 1) * LTC_HPAGE_SIZE < $itemcount) {
                    echo $this->print_next_hpage_button($thispage, $hpage, $itemcount);
                    echo '&nbsp;';
                }
            }
        }

        if ($editchecks) {
            echo '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="post" />';
            echo html_writer::input_hidden_params($thispage);
            echo '<input type="hidden" name="what" value="updateallchecks"/>';
            echo '<input type="submit" name="submit" value="'.get_string('savechecks', 'learningtimecheck').'" />';
        } else if (!$reportsettings->showprogressbars && $this->instance->caneditother() && $this->instance->learningtimecheck->teacheredit != LTC_MARKING_STUDENT) {
            echo '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get" />';
            echo html_writer::input_hidden_params($thispage);
            echo '<input type="hidden" name="editchecks" value="on" />';
            echo '<input type="submit" name="submit" value="'.get_string('editchecks', 'learningtimecheck').'" />';
            echo '</form>';
        }

        echo $this->output->box_end();

        if ($totalusers > 200) {
            echo $this->output->notification(get_string('largeuseramountsignal', 'learningtimecheck'), 'warning');
        }

        if (!empty($ausers)) {
            if ($totalusers < $page * $perpage) {
                $page = 0;
            }
            $barurl = new moodle_url($thispage, array('perpage' => $perpage, 'sortby' => @$reportsettings->sortby));
            echo $this->output->paging_bar($totalusers, $page, $perpage, $barurl);
        }

        // Report panels.

        if ($reportsettings->showprogressbars) {

            // This is the progressbar version of the report panel.

            if ($ausers) {
                echo $this->output->box_start('ltc-progressbar');

                $namestr = get_string('name');
                $progressstr = get_string('progressbar', 'learningtimecheck');
                $itemstodostr = get_string('itemstodo', 'learningtimecheck');
                $doneitemsstr = get_string('itemsdone', 'learningtimecheck');
                $donetimestr = get_string('timedone', 'learningtimecheck');
                $leftratiostr = get_string('ratioleft', 'learningtimecheck');
                $timeleftstr = get_string('timeleft', 'learningtimecheck');

                $firstlink = 'firstasc';
                $lastlink = 'lastasc';
                $firstarrow = '';
                $lastarrow = '';
                $asc = get_string('asc');
                $desc = get_string('desc');
                if ($reportsettings->sortby == 'firstasc') {
                    $firstlink = 'firstdesc';
                    $firstarrow = $this->output->pix_icon('/t/down', $asc);
                } else if ($reportsettings->sortby == 'lastasc') {
                    $lastlink = 'lastdesc';
                    $lastarrow = $this->output->pix_icon('/t/down', $asc);
                } else if ($reportsettings->sortby == 'firstdesc') {
                    $firstarrow = $this->output->pix_icon('/t/up', $desc);
                } else if ($reportsettings->sortby == 'lastdesc') {
                    $lastarrow = $this->output->pix_icon('/t/up', $desc);
                }
                $firstlink = new moodle_url($thispage, array('sortby' => $firstlink));
                $lastlink = new moodle_url($thispage, array('sortby' => $lastlink));
                $nameheading = ' <a href="'.$firstlink.'" >'.get_string('firstname').'</a> '.$firstarrow;
                $nameheading .= ' / <a href="'.$lastlink.'" >'.get_string('lastname').'</a> '.$lastarrow;

                $table = new html_table();
                $table->head = array($nameheading, $progressstr, $itemstodostr, $doneitemsstr, $donetimestr, $leftratiostr, $timeleftstr);
                $table->size = array('30%', '30%', '10%', '10%', '10%', '10%');
                $table->align = array('left', 'center', 'center', 'center', 'center', 'center');
                $table->colclasses = array('', '', '', '', 'highlighted', '');

                $countusers = count($ausers);

                $sums['mandatory']['items'] = 0;
                $sums['mandatory']['ticked'] = 0;
                $sums['mandatory']['time'] = 0;
                $sums['mandatory']['tickedtime'] = 0;
                $sums['mandatory']['timeleft'] = 0;

                if ($reportsettings->showoptional) {
                    $sums['optional']['items'] = 0;
                    $sums['optional']['ticked'] = 0;
                    $sums['optional']['time'] = 0;
                    $sums['optional']['tickedtime'] = 0;
                    $sums['optional']['timeleft'] = 0;
                }

                foreach ($ausers as $auser) {
                    // Fetch total items and total time for user.
                    $checkinfo = $this->instance->get_items_for_user($auser, $reportsettings);

                    $sums['mandatory']['items'] += $checkinfo['mandatory']['items'];
                    $sums['mandatory']['ticked'] += $checkinfo['mandatory']['ticked'];
                    $sums['mandatory']['time'] += $checkinfo['mandatory']['time'];
                    $sums['mandatory']['tickedtime'] += $checkinfo['mandatory']['tickedtime'];
                    $sums['mandatory']['timeleft'] += $checkinfo['mandatory']['timeleft'];

                    if ($reportsettings->showoptional) {
                        $sums['optional']['items'] += $checkinfo['optional']['items'];
                        $sums['optional']['ticked'] += $checkinfo['optional']['ticked'];
                        $sums['optional']['time'] += $checkinfo['optional']['time'];
                        $sums['optional']['tickedtime'] += $checkinfo['optional']['tickedtime'];
                        $sums['optional']['timeleft'] += $checkinfo['optional']['timeleft'];
                    }

                    if ($this->instance->caneditother()) {
                        $vslink = ' <a href="'.$thispage->out(true, array('studentid' => $auser->id) ).'" ';
                        $vslink .= 'alt="'.get_string('viewsinglereport', 'learningtimecheck').'" title="'.get_string('viewsinglereport', 'learningtimecheck').'">';
                        $vslink .= fullname($auser).'</a>';
                    } else {
                        $vslink = fullname($auser);
                    }
                    $userurl = new moodle_url('/user/view.php', array('id' => $auser->id, 'course' => $this->instance->course->id) );
                    $userlink = '<a href="'.$userurl.'">'.$this->output->user_picture($auser, array('size' => 25)).'</a>';

                    $row = array();
                    $row[] = $userlink.$vslink;

                    $useroptions = (object) report_learningtimecheck::get_user_options();
                    if ($useroptions->progressbars == PROGRESSBAR_BOTH) {
                        $complete1 = $checkinfo['mandatory']['percentcomplete'] * 100;
                        $complete2 = $checkinfo['mandatory']['percenttimecomplete'] * 100;
                    } else if ($useroptions->progressbars == PROGRESSBAR_ITEMS) {
                        $complete1 = $checkinfo['mandatory']['percentcomplete'] * 100;
                        $complete2 = null;
                    } else {
                        $complete1 = null;
                        $complete2 = $checkinfo['mandatory']['percenttimecomplete'] * 100;
                    }

                    // Get time ratio anyway for displaying the remaining time.
                    $timecomplete = round($checkinfo['mandatory']['percenttimecomplete'] * 100);
                    $timecompleteoptional = round($checkinfo['optional']['percenttimecomplete'] * 100);

                    $row[] = self::progressbar_thin($complete1, $complete2);

                    $totalitems = $checkinfo['mandatory']['items'];
                    if ($reportsettings->showoptional) {
                        $totalitems .= '<span class="ltc-optional"> +'.$checkinfo['optional']['items'].'</span>';
                    }
                    $row[] = $totalitems;

                    $tickeditems = $checkinfo['mandatory']['ticked'];
                    if ($reportsettings->showoptional) {
                        $tickeditems .= '<span class="ltc-optional"> +'.$checkinfo['optional']['ticked'].'</span>';
                    }
                    $row[] = $tickeditems;

                    $tickedtimes = learningtimecheck_format_time($checkinfo['mandatory']['tickedtime']);
                    if ($reportsettings->showoptional) {
                        $tickeditems .= '<span class="ltc-optional"> +'.learningtimecheck_format_time($checkinfo['optional']['tickedtime']).'</span>';
                    }
                    $row[] = $tickedtimes;

                    $timeleftratio = (100 - $timecomplete).' %';
                    if ($reportsettings->showoptional && @$checkinfo['optional']['percenttimeleft']) {
                        $timeleftratio .= '<span class="ltc-optional"> +'.(100 - $timecompleteoptional).' %</span>';
                    }
                    $row[] = $timeleftratio;

                    $timeleft = learningtimecheck_format_time($checkinfo['mandatory']['timeleft']);
                    if ($reportsettings->showoptional && @$checkinfo['optional']['timeleft']) {
                        $timeleft .= '<span class="ltc-optional"> +'.learningtimecheck_format_time($checkinfo['optional']['timeleft']).'</span>';
                    }
                    $row[] = $timeleft;

                    $table->data[] = $row;
                }

                // Make last row with average and sums.

                $row1 = new html_table_row();

                $cell1 = new html_table_cell();
                $cell1->text = '<b>'.get_string('summators', 'learningtimecheck').'</b>';
                $cell1->colspan = 1;
                $cell1->align = 'right';
                $row1->cells[] = $cell1;

                $cell2 = new html_table_cell();
                $averagedonenum = round($sums['mandatory']['ticked'] / $sums['mandatory']['items'] * 100);
                $averagedone = ($sums['mandatory']['items']) ? sprintf('%0d', $averagedonenum).' %' : '0 %';
                if ($reportsettings->showoptional) {
                    $sumoptionals = $sums['optional']['items'];
                    if ($sumoptionals > 0) {
                        $averagedonenumoptional = round($sums['optional']['ticked'] / $sums['optional']['items'] * 100);
                    } else {
                        $averagedonenumoptional = 0;
                    }
                    $averagedone .= ($sums['optional']['items']) ? '<span class="ltc-optional"> +'.sprintf('%0d', $averagedonenumoptional). ' %</span>' : '';
                }
                $cell2->text = $averagedone.' '.get_string('average', 'learningtimecheck');
                $row1->cells[] = $cell2;

                $cell3 = new html_table_cell();
                $sumitems = $sums['mandatory']['items'];
                if ($reportsettings->showoptional && @$sums['optional']['items']) {
                    $sumitems .= '<span class="ltc-optional"> +'.$sums['optional']['items'].'</span>';
                }
                $cell3->text = '<span class="totalizer">'.$sumitems.' '.get_string('totalized', 'learningtimecheck').'</span>';
                $row1->cells[] = $cell3;

                $cell4 = new html_table_cell();
                $sumticked = $sums['mandatory']['ticked'];
                if ($reportsettings->showoptional && @$sums['optional']['ticked']) {
                    $sumticked .= '<span class="ltc-optional"> +'.$sums['optional']['ticked'].'</span>';
                }
                $cell4->text = '<span class="totalizer">'.$sumticked.' '.get_string('totalized', 'learningtimecheck').'</span>';
                $row1->cells[] = $cell4;

                $cell5 = new html_table_cell();
                $sumtickedtime = $sums['mandatory']['tickedtime'];
                if ($reportsettings->showoptional && @$sums['optional']['tickedtime']) {
                    $sumtickedtime .= '<span class="ltc-optional"> +'.$sums['optional']['tickedtime'].'</span>';
                }
                $cell5->text = '<span class="totalizer">'.$sumtickedtime.' '.get_string('totalized', 'learningtimecheck').'</span>';
                $cell5->attributes['class'] = 'ltc-result';
                $row1->cells[] = $cell5;

                $cell6 = new html_table_cell();
                $ratio = 100 - $averagedonenum;
                $percentleft = ($sums['mandatory']['items']) ? sprintf('%0d', $ratio.' %') : '0 %';
                if ($reportsettings->showoptional && @$sums['optional']['items']) {
                    $ratio = 100 - $averagedonenumoptional;
                    $optionalpercentleft = ($sums['optional']['items']) ? sprintf('%0d', $ratio.' %') : '0 %';
                    $percentleft .= '<span class="ltc-optional"> +'.$optionalpercentleft.'</span>';
                }
                $cell6->text = $percentleft;
                $cell6->attributes['class'] = 'ltc-remain-result';
                $row1->cells[] = $cell6;

                $cell7 = new html_table_cell();
                $sumtimeleft = learningtimecheck_format_time($sums['mandatory']['timeleft']);
                if ($reportsettings->showoptional && @$sums['optional']['timeleft']) {
                    $sumtimeleft .= '<span class="ltc-optional"> +'.learningtimecheck_format_time($sums['optional']['timeleft']).'</span>';
                }
                $cell7->text = $sumtimeleft.' '.get_string('totalized', 'learningtimecheck');
                $cell7->attributes['class'] = 'ltc-remain-result';
                $row1->cells[] = $cell7;

                 $table->data[] = $row1;

                echo html_writer::table($table);
                echo $this->output->box_end();
            } else {
                echo $this->output->notification(get_string('nousers', 'learningtimecheck'));
            }

        } else {
            echo $this->print_activity_detailed_list($ausers, $reportsettings, $thispage, $editchecks, true);
        }
    }

    /**
     * Displays full activity table for users with or without teacher controls
     */
    public function print_activity_detailed_list($users, $reportsettings, $thispage, $editchecks, $isteacher = false) {
        global $COURSE, $DB, $OUTPUT;

        $hpage = optional_param('hpage', 0, PARAM_INT);

        $str = '';

        $firstlink = 'firstasc';
        $lastlink = 'lastasc';
        $firstarrow = '';
        $lastarrow = '';

        $asc = get_string('asc');
        $desc = get_string('desc');
        if ($reportsettings->sortby == 'firstasc') {
            $firstlink = 'firstdesc';
            $firstarrow = $this->output->pix_icon('/t/down', $asc);
        } else if ($reportsettings->sortby == 'lastasc') {
            $lastlink = 'lastdesc';
            $lastarrow = $this->output->pix_icon('/t/down', $asc);
        } else if ($reportsettings->sortby == 'firstdesc') {
            $firstarrow = $this->output->pix_icon('/t/up', $desc);
        } else if ($reportsettings->sortby == 'lastdesc') {
            $lastarrow = $this->output->pix_icon('/t/up', $desc);
        }

        $firstlink = new moodle_url($thispage, array('sortby' => $firstlink));
        $lastlink = new moodle_url($thispage, array('sortby' => $lastlink));
        $nameheading = ' <a href="'.$firstlink.'" >'.get_string('firstname').'</a> '.$firstarrow;
        $nameheading .= ' / <a href="'.$lastlink.'" >'.get_string('lastname').'</a> '.$lastarrow;

        $table = new html_table();
        $table->head = array($nameheading);
        $table->level = array(-1);
        $table->size = array('100px');
        $table->skip = array(false);
        $items = $this->instance->get_items_from_db($reportsettings->showoptional, $reportsettings->hideheadings);
        $printableitems = array_slice($items, $hpage * LTC_HPAGE_SIZE, LTC_HPAGE_SIZE, true);

        $modinfo = get_fast_modinfo($COURSE);
        $cms = $modinfo->get_cms();
        $itemmods = array();

        foreach ($printableitems as $item) {
            if ($item->hidden) {
                if (function_exists('debug_trace')) {
                    debug_trace("Hidden item $item->moduleid ");
                }
                continue;
            }

            if ($item->itemoptional != LTC_OPTIONAL_HEADING) {
                $icon = '';
                if (in_array($item->moduleid, array_keys($cms))) {
                    // Try first in module cache.
                    $mod = $modinfo->get_cm($item->moduleid);
                    if (!$mod) {
                        if (function_exists('debug_trace')) {
                            debug_trace("Lost module $item->moduleid before cache rebuild. ");
                        }
                        // Try rebuild.
                        rebuild_course_cache($COURSE->id);
                        $modinfo = get_fast_modinfo($COURSE);
                        $cms = $modinfo->get_cms();
                        $mod = $modinfo->get_cm($item->moduleid);
                    }
                    if (!$mod) {
                        // Lost modules
                        if (function_exists('debug_trace')) {
                            debug_trace("Lost module $item->moduleid after cache rebuild. ");
                        }
                        continue;
                    }
                    $icon = html_writer::empty_tag('img', array('src' => $mod->get_icon_url(),
                    'class' => 'iconlarge activityicon', 'alt' => $mod->modfullname, 'title' => $mod->modfullname));
                    $itemmods[$item->id] = $mod;
                } else {
                    // Try get it in DB.
                    $mod = $DB->get_record('course_modules', array('id' => $item->moduleid));
                    if (!$mod) {
                        // Really lost module
                        if (function_exists('debug_trace')) {
                            debug_trace("Lost module $item->moduleid even in DB");
                        }
                        continue;
                    }
                    if (function_exists('debug_trace')) {
                        debug_trace("Lost module $item->moduleid but in DB. ");
                    }
                }

                $itemurl = new moodle_url('/mod/'.$mod->modname.'/view.php', array('id' => $item->moduleid));
                $table->head[] = '<a href="'.$itemurl.'" data-cmid="'.$mod->id.'">'.$icon.'</a> '.s($item->displaytext);
                $table->size[] = '80px';
            } else {
                assert(1);
            }
            $table->size[] = '80px';
            $table->skip[] = (!$reportsettings->showoptional) && ($item->itemoptional == LTC_OPTIONAL_YES);
        }

        $table->data = [];
        if ($users) {
            foreach ($users as $auser) {
                $row = [];

                $userurl = new moodle_url('/user/view.php', array('id' => $auser->id, 'course' => $this->instance->course->id) );
                $userlink = '<a href="'.$userurl.'">'.fullname($auser).'</a>';

                $vslink = ' <a href="'.$thispage->out(true, array('view' => 'view', 'studentid' => $auser->id) ).'" ';
                $vslink .= 'alt="'.get_string('viewsinglereport', 'learningtimecheck').'" title="'.get_string('viewsinglereport', 'learningtimecheck').'">';
                $vslink .= $this->output->pix_icon('/t/preview', get_string('preview', 'learningtimecheck')).'</a>';

                $row[] = $userlink.$vslink;

                // Get all checks for the user for the items to print on board.
                $checks = $this->instance->get_checks_for_items(array_keys($printableitems), $auser->id);

                foreach ($checks as $check) {
                    if ($check->hidden) {
                        continue;
                    }

                    if ($check->itemoptional == LTC_OPTIONAL_HEADING) {
                        if (!$reportsettings->hideheadings) {
                            // $row[] = array(false, false, true, 0, 0, null);
                            continue;
                        }
                    } else {
                        if ($check->usertimestamp > 0) {
                            // Check->id is the itemid.
                            $row[] = array($check->teachermark, true, false, $auser->id, $check->id, @$ITEMMODS[$check->id]);
                        } else {
                            // Check->id is the itemid.
                            $row[] = array($check->teachermark, false, false, $auser->id, $check->id, @$ITEMMODS[$check->id]);
                        }
                    }
                }

                $table->data[] = $row;

                if ($editchecks) {
                    echo '<input type="hidden" name="userids[]" value="'.$auser->id.'" />';
                }
            }
            echo '<div style="overflow:auto">';
            echo $this->report_table($table, $editchecks);
            echo '</div>';
        } else {
            echo $OUTPUT->notification(get_string('nousers', 'learningtimecheck'));
        }

        if ($editchecks) {
            echo '<input type="submit" name="submit" value="'.get_string('savechecks', 'learningtimecheck').'" />';
            echo '</form>';
        }
    }

    // Accessory sub items.

    public function progressbar($useroptions = null) {

        if (is_null($useroptions)) {
            $useroptions = (object) report_learningtimecheck::get_user_options();
        }

        if (empty($useroptions->elements)) {
            $useroptions->elements = [PROGRESSBAR_MANDATORY, PROGRESSBAR_ALL];
        }

        if (empty($useroptions->progressbars)) {
            $useroptions->progressbars = [PROGRESSBAR_BOTH];
        }

        // Ugly patch wating better resolution
        // FIX
        if (isset($useroptions->elements) && !is_array($useroptions->elements)) {
            $useroptions->elements = [$useroptions->elements];
        }

        // Actually should already be catched sooner.
        if (!$this->instance) {
            throw new CodingException('Misuse of an uninitialized renderer. Please review the code');
        }

        if (empty($this->instance->items)) {
            return;
        }

        $ccs = $this->instance->calculate();

        if ($ccs['allitems'] == 0) {
            return $this->output->notification(get_string('noitems', 'learningtimecheck'));
        }

        $allpercentcomplete = round(($ccs['allcompleteitems'] * 100) / $ccs['allitems']);
        $alltimepercentcomplete = ($ccs['alltime']) ? round(($ccs['allcompletetime'] * 100) / $ccs['alltime']) : 0;
        $requiredcompletepercent = ($ccs['requireditems']) ? round(($ccs['requiredcompleteitems'] * 100) / $ccs['requireditems']) : 0;
        $requiredcompletepercenttime = ($ccs['requiredtime']) ? round(($ccs['requiredcompletetime'] * 100) / $ccs['requiredtime']) : 0;
        $optionalcompletepercent = ($ccs['optionalitems']) ? round(($ccs['optionalcompleteitems'] * 100) / $ccs['optionalitems']) : 0;
        $optionalcompletepercenttime = ($ccs['optionaltime']) ? round(($ccs['optionalcompletetime'] * 100) / $ccs['optionaltime']) : 0;

        $template = new StdClass;
        $template->fadeurl = $this->output->image_url('progress-fade', 'learningtimecheck');

        if ($ccs['requireditems'] > 0 && $ccs['allitems'] > $ccs['requireditems']) {

            if (in_array($useroptions->progressbars, array(PROGRESSBAR_ITEMS, PROGRESSBAR_BOTH)) &&
            in_array(PROGRESSBAR_MANDATORY, $useroptions->elements)) {
                $bartpl = new StdClass;
                $bartpl->percentcomplete = $requiredcompletepercent;
                $bartpl->formattedpercentcomplete = sprintf('%0d', $bartpl->percentcomplete);
                $bartpl->heading = get_string('percentcomplete', 'learningtimecheck');
                $bartpl->progressurl = $this->output->image_url('progress1_big', 'learningtimecheck');
                $template->bars[] = $bartpl;
            }

            if (in_array($useroptions->progressbars, array(PROGRESSBAR_TIME, PROGRESSBAR_BOTH)) &&
            in_array(PROGRESSBAR_MANDATORY, $useroptions->elements)) {
                $bartpl = new StdClass;
                $bartpl->percentcomplete = $requiredcompletepercenttime;
                $bartpl->formattedpercentcomplete = sprintf('%0d', $bartpl->percentcomplete);
                $bartpl->heading = get_string('timepercentcomplete', 'learningtimecheck');
                $bartpl->progressurl = $this->output->image_url('progress2_big', 'learningtimecheck');
                $template->bars[] = $bartpl;
            }
        }

        if (in_array($useroptions->progressbars, array(PROGRESSBAR_ITEMS, PROGRESSBAR_BOTH)) &&
            in_array(PROGRESSBAR_OPTIONAL, $useroptions->elements)) {
            $bartpl = new StdClass;
            $bartpl->percentcomplete = $optionalpercentcomplete;
            $bartpl->formattedpercentcomplete = sprintf('%0d', $bartpl->percentcomplete);
            $bartpl->heading = get_string('optionalpercentcompleteall', 'learningtimecheck');
            $bartpl->progressurl = $this->output->image_url('progress1_big', 'learningtimecheck');
            $template->bars[] = $bartpl;
        }

        if (in_array($useroptions->progressbars, array(PROGRESSBAR_TIME, PROGRESSBAR_BOTH)) &&
            in_array(PROGRESSBAR_OPTIONAL, $useroptions->elements)) {
            $bartpl = new StdClass;
            $bartpl->heading = get_string('optionaltimepercentcompleteall', 'learningtimecheck');
            $bartpl->percentcomplete = $optionaltimepercentcompletetime;
            $bartpl->formattedpercentcomplete = sprintf('%0d', $bartpl->percentcomplete);
            $bartpl->progressurl = $this->output->image_url('progress2_big', 'learningtimecheck');
            $template->bars[] = $bartpl;
        }

        if (in_array($useroptions->progressbars, array(PROGRESSBAR_ITEMS, PROGRESSBAR_BOTH)) &&
            in_array(PROGRESSBAR_ALL, $useroptions->elements)) {
            $bartpl = new StdClass;
            $bartpl->percentcomplete = $allpercentcomplete;
            $bartpl->formattedpercentcomplete = sprintf('%0d', $bartpl->percentcomplete);
            $bartpl->heading = get_string('percentcompleteall', 'learningtimecheck');
            $bartpl->progressurl = $this->output->image_url('progress1_big', 'learningtimecheck');
            $template->bars[] = $bartpl;
        }

        if (in_array($useroptions->progressbars, array(PROGRESSBAR_TIME, PROGRESSBAR_BOTH)) &&
            in_array(PROGRESSBAR_ALL, $useroptions->elements)) {
            $bartpl = new StdClass;
            $bartpl->heading = get_string('timepercentcompleteall', 'learningtimecheck');
            $bartpl->percentcomplete = $alltimepercentcomplete;
            $bartpl->formattedpercentcomplete = sprintf('%0d', $bartpl->percentcomplete);
            $bartpl->progressurl = $this->output->image_url('progress2_big', 'learningtimecheck');
            $template->bars[] = $bartpl;
        }

        return $this->output->render_from_template('mod_learningtimecheck/progressbar', $template);
    }

    public static function progressbar_thin($percentcomplete1, $percentcomplete2) {
        global $OUTPUT;

        $template = new StdClass;

        $class = '';
        if (!is_null($percentcomplete1) && !is_null($percentcomplete2)) {
            $template->class = ' both';
        }

        if (!is_null($percentcomplete1)) {
            $template->percentcomplete1 = new StdClass;
            $template->percentcomplete1->value = round($percentcomplete1);
            $template->pixurl = $OUTPUT->image_url('progress1', 'learningtimecheck');
        }

        if (!is_null($percentcomplete2)) {
            $template->percentcomplete2 = new StdClass;
            $template->percentcomplete2->value = round($percentcomplete2);
            $template->percentcomplete2->pixurl = $OUTPUT->image_url('progress2', 'learningtimecheck');
        }

        return $OUTPUT->render_from_template('mod_learningtimecheck/progressthin', $template);
    }

    /**
     * Prints the part of the editing form that refers to learning times.
     * @param objectref $item
     * @param bool $titles
     * @param bool $canforcetrainingsessions if set, will add the checkbox allowing transfer to trainingsession reports
     */
    public function timesettings_form(&$item, $titles = 0, $usercanforcetrainingsessions = false, $couplecredittomandatoryoption = false) {
        global $CFG;

        $config = get_config('learningtimecheck');

        $str = '';

        if ($titles) {

            $str .= '<table class="ltc-timesettings generaltable" width="100%">';
            $str .= '<tr><td class="cell c0 header" width="26%">';
            $str .= get_string('credittime', 'learningtimecheck');
            $str .= '</td>';
            $str .= '<td class="cell c1 header" width="24%">';
            $str .= get_string('isdeclarative', 'learningtimecheck');
            $str .= '</td>';
            $str .= '<td class="cell c2 header" width="25%">';
            $str .= get_string('teachercredittime', 'learningtimecheck');
            $str .= '</td>';
            $str .= '<td class="cell c3 header" width="25%">';
            $str .= get_string('teachercredittimeperuser', 'learningtimecheck');
            $str .= '</td>';
            $str .= '</tr>';
            $str .= '</table>';

            return $str;
        }

        $str .= '<table class="ltc-timesettings generaltable">';

        // Student credit time.
        $str .= '<td class="cell c0" width="26%">';
        $attributes = array('id' => 'creditselect'.$item->id);
        if ($couplecredittomandatoryoption) {
            $attributes['onchange'] = 'set_mandatory(\'creditselect'.$item->id.'\', \'optional'.$item->id.'\')';
        }

        $str .= html_writer::select(learningtimecheck_get_credit_times(), "credittime[$item->id]", @$item->credittime, array('' => 'choosedots'), $attributes);

        // Credit time can be forced to report in training sessions time report.
        if (is_dir($CFG->dirroot.'/report/trainingsessions')) {
            if ($usercanforcetrainingsessions || $config->allowoverrideusestats) {
                // Disable this option if training sessions report is not installed.
                $checked = (@$item->enablecredit) ? ' checked="checked" ' : '';
                $str .= '<br/><input type="checkbox" name="enablecredit['.$item->id.']" value="1" '.$checked.' /> ';
                $str .= '<span <lass="smalltext">'.get_string('enablecredit', 'learningtimecheck').'</span>';
            }
        }

        $str .= '</td>';

        // Student and teacher declaration mode.
        $str .= '<td class="cell c1" width="24%">';
        $isdeclarativeoptions = array('0' => get_string('no'),
            '1' => get_string('students'),
            '2' => get_string('teachers'),
            '3' => get_string('both', 'learningtimecheck'));
        $attrs = array('onchange' => 'checktimecreditlist(\''.$item->id.'\', this)');
        $str .= html_writer::select($isdeclarativeoptions, "isdeclarative[$item->id]", @$item->isdeclarative, array('' => 'choosedots'), $attrs);
        $str .= '</td>';

        // teacher credittime item scope.
        $str .= '<td class="cell c2" width="25%">';
        $str .= html_writer::select(learningtimecheck_get_credit_times('coarse'), "teachercredittime[$item->id]", 0 + @$item->teachercredittime);
        $str .= '</td>';

        // teacher credittime item/student scope.
        $str .= '<td class="cell c3 last" width="25%">';
        $str .= html_writer::select(learningtimecheck_get_credit_times('thin'), "teachercredittimeperuser[$item->id]", 0 + @$item->teachercredittimeperuser);
        $str .= '</td>';
        $str .= '</tr></table>';

        return $str;
    }

    public function timesettingglobals_form_additions() {
        global $CFG;

        $str = '';

        $str .= '<div class="ltc-globalsetting-cell">';
        $str .= get_string('credittime', 'learningtimecheck');
        $str .= '<br/>';
        $str .= html_writer::select(learningtimecheck_get_credit_times(), "credittimeglobal", @$item->credittime);
        $str .= '<br/><input type="submit" value="'.get_string('applytoall', 'learningtimecheck').'" name="applycredittimetoall" />';
        $str .= '</div>';

        if (is_dir($CFG->dirroot.'/report/trainingsessions')) {
            $str .= '<div class="ltc-globalsetting-cell">';
            $str .= '&nbsp;'."<input type=\"checkbox\" name=\"enablecreditglobal\" value=\"1\" /> ".get_string('enablecredit', 'learningtimecheck');
            $str .= '<input type="submit" value="'.get_string('applytoall', 'learningtimecheck').'" name="applyenablecredittoall" />';
            $str .= '</div>';
        }

        // Student and teacher declaration mode.
        $str .= '<div class="ltc-globalsetting-cell">';
        $str .= get_string('isdeclarative', 'learningtimecheck');
        $isdeclarativeoptions = array('0' => get_string('no'),
            '1' => get_string('students'),
            '2' => get_string('teachers'),
            '3' => get_string('both', 'learningtimecheck'));
        $str .= '<br/>';
        $str .= html_writer::select($isdeclarativeoptions, "isdeclarativeglobal", 1);
        $str .= '<input type="submit" value="'.get_string('applytoall', 'learningtimecheck').'" name="applyisdeclarativetoall" />';
        $str .= '</div>';

        // Teacher credittime item scope.
        $str .= '<div class="ltc-globalsetting-cell">';
        $str .= get_string('teachercredittime', 'learningtimecheck');
        $str .= '<br/>';
        $str .= html_writer::select(learningtimecheck_get_credit_times('coarse'), "teachercredittimeglobal", 0);
        $str .= '<input type="submit" value="'.get_string('applytoall', 'learningtimecheck').'" name="applyteachercredittimetoall" />';
        $str .= '</div>';

        // Teacher credittime item/student scope.
        $str .= '<div class="ltc-globalsetting-cell">';
        $str .= get_string('teachercredittimeperuser', 'learningtimecheck');
        $str .= '<br/>';
        $str .= html_writer::select(learningtimecheck_get_credit_times('thin'), "teachercredittimeperuserglobal", 0);
        $str .= '<input type="submit" value="'.get_string('applytoall', 'learningtimecheck').'" name="applyteachercredittimeperusertoall" />';
        $str .= '</div>';

        return $str;
    }

    /**
     * Provides editing commands for each item.
     */
    public function item_edit_commands(&$thispage, &$item, $autoitem) {

        $str = '';

        if ($autoitem) {
            if ($item->hidden != LTC_HIDDEN_BYMODULE) {
                // Here user still has control over hidden status.
                if ($item->hidden == LTC_HIDDEN_MANUAL) {
                    $title = '"'.get_string('itemenable', 'learningtimecheck').'"';
                    $img = $this->output->pix_icon('/t/show', $title);
                    $str .= '&nbsp;<a href="'.$thispage->out(true, array('what' => 'showitem')).'">'.$img.'</a>';
                } else {
                    $title = '"'.get_string('itemdisable', 'learningtimecheck').'"';
                    $img = $this->output->pix_icon('/t/hide', $title);
                    $str .= '&nbsp;<a href="'.$thispage->out(true, array('what' => 'hideitem')).'">'.$img.'</a>';
                }
            } else {
                $title = '"'.get_string('hiddenbymodule', 'learningtimecheck').'"';
                $str .= '&nbsp;'.$this->output->pix_icon('hiddenbymodule', $title, 'learningtimecheck').'</a>';
            }
        } else {
            assert(1);
        }

        return $str;
    }

    public function add_item_form($thispage, &$item, $showcheckbox) {

        $str = '';

        $additemstr = get_string('additem', 'learningtimecheck');
        $canceledititemstr = get_string('canceledititem', 'learningtimecheck');

        $thisitemurl = clone $thispage;
        $thisitemurl->param('what', 'additem');
        $thisitemurl->param('position', $item->position);
        $thisitemurl->param('sesskey', sesskey());

        $str .= '<div style="float: left;">';
        $str .= '<form action="'.$thispage->out_omit_querystring().'" method="post">';
        $str .= html_writer::input_hidden_params($thisitemurl);
        $str .= learningtimecheck_add_paged_params();

        if ($showcheckbox) {
            $str .= '<input type="checkbox" disabled="disabled" />';
        }

        $str .= '<input type="text" size="'.LTC_TEXT_INPUT_WIDTH.'" name="displaytext" value="" id="additembox" />';
        $str .= '<input type="submit" name="additem" value="'.$additemstr.'" />';
        $cancelstr = get_string('canceledititem', 'learningtimecheck');
        $str .= '<input type="button" name="canceledititem" value="'.$cancelstr.'" onclick="cancel_add_item_form(\''.$addafteritem->id.'\')" />';
        $str .= '<br />';
        $str .= '<textarea name="displaytextnote" rows="3" cols="25"></textarea>';
        $str .= '</form>';
        $str .= '</div>';

        return $str;
    }

    /**
     * this form is used when adding an extra non automatic item
     * when edting the learningtimecheck list. Used by teachers
     * @param string $thispage
     * @param int $addafteritem
     */
    public function edit_item_form($thispage, $addafteritem) {

        $template = new StdClass;

        $template->formurl = $thispage->out_omit_querystring();
        $template->hiddenparams = html_writer::input_hidden_params($thispage, array('sesskey'));
        $template->pageparams = learningtimecheck_add_paged_params();

        $template->position = $addafteritem->position + 1;
        $template->indent = $addafteritem->indent;

        $template->size = LTC_TEXT_INPUT_WIDTH;
        $template->id = $addafteritem->id;

        return $this->output->render_from_template('mod_learningtimecheck/edit_item_form', $template);
    }

    public function print_view_all_button($thispage) {

        $str = '';

        $str .= '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
        $str .= html_writer::input_hidden_params($thispage, array('studentid'));
        $str .= '<input type="submit" name="viewall" value="'.get_string('viewall', 'learningtimecheck').'" />';
        $str .= '</form>';

        return $str;
    }

    public function print_edit_comments_button($thispage) {

        $str = '';

        $str .= '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
        $str .= html_writer::input_hidden_params($thispage);
        $str .= '<input type="hidden" name="editcomments" value="on" />';
        $str .= ' <input type="submit" name="viewall" value="'.get_string('addcomments', 'learningtimecheck').'" />';
        $str .= '</form>';

        return $str;
    }

    /**
     * Deprecated. Not more date handling
     */
    public function print_toggle_dates_button($thispage) {

        $str = '';

        $str .= '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
        $str .= html_writer::input_hidden_params($thispage);
        $str .= '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        $str .= '<input type="hidden" name="what" value="toggledates" />';
        $str .= ' <input type="submit" name="toggledates" value="'.get_string('toggledates', 'learningtimecheck').'" />';
        $str .= '</form>';

        return $str;
    }

    public function print_velocity_button($thispage) {

        $str = '';

        $velocityurl = new moodle_url('/mod/learningtimecheck/learningvelocities.php');
        $str .= '<form style="display: inline;" action="'.$velocityurl.'" method="get">';
        $str .= html_writer::input_hidden_params($thispage);
        $str .= '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        $str .= ' <input type="submit" name="gotovelocities" value="'.get_string('learningvelocities', 'learningtimecheck').'" />';
        $str .= '</form>';

        return $str;
    }

    public function print_export_excel_button($thispage, $userid = 0) {
        global $COURSE;

        $str = '';

        $formurl = new moodle_url('/report/learningtimecheck/export.php');
        $str .= '<form style="display: inline;" action="'.$formurl.'" method="get" target="_blanck">';
        $str .= '<input type="hidden" name="id" value="'.$COURSE->id.'" />';
        $str .= '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        $str .= '<input type="hidden" name="exporttype" value="course" />';
        $str .= '<input type="hidden" name="output" value="xls" />';
        $str .= '<input type="hidden" name="detail" value="0" />';
        $str .= '<input type="hidden" name="itemid" value="'.$COURSE->id.'" />';
        $str .= ' <input type="submit" name="exportexcel" value="'.get_string('exportexcel', 'learningtimecheck').'" />';
        $str .= '</form>';
        return $str;
    }

    public function print_export_user_details_pdf_button($thispage, $courseid, $userid = 0) {
        $str = '';

        $formurl = new moodle_url('/report/learningtimecheck/export.php');
        $str .= '<form style="display: inline;" action="'.$formurl.'" method="get" target="_blank" >';
        $str .= '<input type="hidden" name="exporttype" value="userdetail" />';
        $str .= '<input type="hidden" name="itemid" value="'.$userid.'" />';
        $str .= '<input type="hidden" name="output" value="pdf" />';
        $str .= '<input type="hidden" name="detail" value="0" />';
        $str .= '<input type="hidden" name="id" value="'.$courseid.'" />';
        $str .= ' <input type="submit" name="toggledates" value="'.get_string('exportpdf', 'learningtimecheck').'" />';
        $str .= '</form>';
        return $str;
    }

    public function print_export_pdf_button($thispage, $userid = 0) {
        global $COURSE;

        $template = new StdClass;

        $template->formurl = new moodle_url('/report/learningtimecheck/export.php');
        $template->courseid = $COURSE->id;
        $template->sesskey = sesskey();

        if (!empty($this->userid)) {
            $template->userid = $this->userid;
        }
        if (!empty($this->groupid)) {
            $template->groupid = $this->groupid;
            $template->groupingid = $this->groupingid;
        }

        return $this->output->render_from_template('mod_learningtimecheck/export_pdf_button', $template);
    }

    public function optional_hide_button($thispage, $reportsettings) {
        $str = '';

        $str .= '&nbsp;&nbsp;<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get" />';
        $str .= html_writer::input_hidden_params($thispage, array('what'));
        if ($reportsettings->showoptional) {
            $str .= '<input type="hidden" name="what" value="hideoptional" />';
            $str .= '<input type="submit" name="submit" value="'.get_string('optionalhide', 'learningtimecheck').'" />';
        } else {
            $str .= '<input type="hidden" name="what" value="showoptional" />';
            $str .= '<input type="submit" name="submit" value="'.get_string('optionalshow', 'learningtimecheck').'" />';
        }
        $str .= '</form>';

        return $str;
    }

    public function collapse_headers_button($thispage, $reportsettings) {
        $str = '';

        $str .= '&nbsp;&nbsp;<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get" />';
        $str .= html_writer::input_hidden_params($thispage, array('what'));
        if (!$reportsettings->hideheadings) {
            $str .= '<input type="hidden" name="what" value="collapseheaders" />';
            $str .= '<input type="submit" name="submit" value="'.get_string('collapseheaders', 'learningtimecheck').'" />';
        } else {
            $str .= '<input type="hidden" name="what" value="expandheaders" />';
            $str .= '<input type="submit" name="submit" value="'.get_string('expandheaders', 'learningtimecheck').'" />';
        }
        $str .= '</form>';

        return $str;
    }

    public function toggle_progressbar_button($thispage, $reportsettings) {
        $str = '';

        $str .= '<form style="display: inline;" action="'.$thispage->out_omit_querystring().'" method="get" />';
        $str .= html_writer::input_hidden_params($thispage);
        if ($reportsettings->showprogressbars) {
            $editchecks = false;
            $str .= '<input type="hidden" name="what" value="hideprogressbars" />';
            $str .= '<input type="submit" name="submit" value="'.get_string('showfulldetails', 'learningtimecheck').'" />';
        } else {
            $str .= '<input type="hidden" name="what" value="showprogressbars" />';
            $str .= '<input type="submit" name="submit" value="'.get_string('showprogressbars', 'learningtimecheck').'" />';
        }
        $str .= '</form>';

        return $str;
    }

    public function print_previous_hpage_button($thispage, $hpage) {

        $filterlastname = optional_param('filterlastname', '', PARAM_TEXT);
        $filterfirstname = optional_param('filterfirstname', '', PARAM_TEXT);
        $page = optional_param('page', '', PARAM_TEXT);

        $str = '';
        $actionurl = new moodle_url('/mod/learningtimecheck/view.php');
        $str .= '<form style="display: inline;" action="'.$actionurl.'" method="get">';
        $thispage->remove_params('hpage', 'page');
        $str .= html_writer::input_hidden_params($thispage);
        $disabled = ($hpage == 0) ? 'disabled="disabled" class="shadow" ' : '';
        $str .= '<input type="hidden" name="hpage" value="'.($hpage - 1).'"/>';
        $str .= '<input type="hidden" name="page" value="'.$page.'"/>';
        $str .= '<input type="hidden" name="filterlastname" value="'.$filterlastname.'"/>';
        $str .= '<input type="hidden" name="filterfirstname" value="'.$filterfirstname.'"/>';
        $str .= ' <input type="submit" name="previouspage" value="&lt;"  '.$disabled.' />';
        $str .= '</form>';

        return $str;
    }

    public function print_next_hpage_button($thispage, $hpage, $itemcount) {

        $str = '';

        $filterlastname = optional_param('filterlastname', '', PARAM_TEXT);
        $filterfirstname = optional_param('filterfirstname', '', PARAM_TEXT);
        $page = optional_param('page', '', PARAM_TEXT);

        $actionurl = new moodle_url('/mod/learningtimecheck/view.php');
        $str .= '<form style="display: inline;" action="'.$actionurl.'" method="get">';
        $str .= html_writer::input_hidden_params($thispage);
        $thispage->remove_params('hpage', 'page');
        $str .= '<input type="hidden" name="hpage" value="'.($hpage + 1).'" />';
        $str .= '<input type="hidden" name="page" value="'.$page.'"/>';
        $str .= '<input type="hidden" name="filterlastname" value="'.$filterlastname.'"/>';
        $str .= '<input type="hidden" name="filterfirstname" value="'.$filterfirstname.'"/>';
        $disabled = (($hpage + 1) * LTC_HPAGE_SIZE >= $itemcount) ? 'disabled="disabled" class="shadow" ' : '';
        $str .= ' <input type="submit" name="nextpage" value="&gt;" '.$disabled.' />';
        $str .= '</form>';

        return $str;
    }


    public function print_prev_user_button($thispage, $courseid, $userid = 0) {

        $template = new StdClass;

        $template->formurl = new moodle_url('/mod/learningtimecheck/view.php');
        $template->hiddenparams = html_writer::input_hidden_params($thispage);
        $template->sesskey = sesskey();

        return $this->output->render_from_template('mod_learningtimecheck/prev_user_button', $template);
    }

    public function print_next_user_button($thispage, $courseid, $userid = 0) {

        $template = new StdClass;

        $template->formurl = new moodle_url('/mod/learningtimecheck/view.php');
        $template->hiddenparams = html_writer::input_hidden_params($thispage);
        $template->sesskey = sesskey();

        return $this->output->render_from_template('mod_learningtimecheck/next_user_button', $template);
    }

    /**
     * The event filter allows filtering the output considering some events
     * and date where they occur
     * this deals with filter rules.
     * @param object $thispage brings some extra params for generating url
     * @param string $url if not set, will return to main learningtimecheck view. If set, diverts return to
     * an alternate URL.
     */
    public function print_event_filter($thispage, $url = null, $component = 'mod', $itemid = 0) {
        global $SESSION;

        $ruleops = learningtimecheck_class::get_ruleop_options();

        $template = new StdClass;
        $template->actionurl = (!is_null($url)) ? $url : new moodle_url('/mod/learningtimecheck/view.php');

        $filtererror = optional_param('filtererror', '', PARAM_CLEANHTML);
        if ($filtererror) {
            $template->filtererror = get_string($filtererror, 'learningtimecheck');
            $template->haserrors = true;
        }

        $template->hiddenparams = html_writer::input_hidden_params($thispage);

        if (!empty($SESSION->learningtimecheck->filterrules)) {
            foreach ($SESSION->learningtimecheck->filterrules as $filterrule) {
                $filterruletpl = new StdClass;

                if (!empty($filterrule->logop)) {
                    $filterruletpl->haslogop = true;
                    $filterruletpl->logopstr = get_string($filterrule->logop, 'learningtimecheck');
                }

                $filterruletpl->rulestr = get_string($filterrule->rule, 'learningtimecheck');

                $filterruletpl->ruleop = $ruleops[$filterrule->ruleop];

                $filterruletpl->ruledate = $filterrule->datetime;

                // Makes url more contextual from where it is called.
                $deleteurl = clone($thispage);
                $params = array('what' => 'deleterule', 'ruleid' => $filterrule->id);
                $filterruletpl->deleteurl = $deleteurl->params($params);
            }
            $template->filterrules[] = $filterruletpl;
        }

        // Will ajax load a new rule form.
        $template->id = $thispage->get_param('id');
        $template->component = $component;
        $template->view = $thispage->get_param('view');
        $template->itemid = $itemid;

        return $this->output->render_from_template('mod_learningtimecheck/event_filter', $template);
    }

    /**
     * Used by ajax call to add a new rule form
     */
    public function filter_rule_form($url, $view = 'course') {
        global $SESSION;

        $template = new Stdclass;

        $logopoptions = learningtimecheck_class::get_logop_options();
        $ruleoptions = learningtimecheck_class::get_rule_options($view);
        $ruleopoptions = learningtimecheck_class::get_ruleop_options();

        $template->newruleurl = $url->out_omit_querystring();
        $template->hiddenparams = html_writer::input_hidden_params($url);

        $template->isfirst = true;
        if (!empty($SESSION->learningtimecheck->filterrules)) {
            $template->isfirst = false;
            // Do not set any preoperator for the first rule.
            $template->logopselect = html_writer::select($logopoptions, 'logop');
        }

        $template->ruleselect = html_writer::select($ruleoptions, 'rule');
        $template->ruleopselect = html_writer::select($ruleopoptions, 'ruleop');

        return $this->output->render_from_template('mod_learningtimecheck/filter_rule_form', $template);
    }

    public function print_global_counters() {
        global $DB, $COURSE;

        $sql = "
            SELECT
                COUNT(*)
            FROM
                {learningtimecheck} ltc,
                {course_modules} cm,
                {modules} m
            WHERE
                ltc.id = cm.instance AND
                cm.module = m.id AND
                m.name = 'learningtimecheck' AND
                cm.deletioninprogress = 0 AND
                cm.course = ?
        ";
        $courseinstances = $DB->count_records_sql($sql, [$COURSE->id]);

        // Collect all items of the whole course.
        $totalitems = (object)learningtimecheck_count_total_items($this->instance->learningtimecheck->course, 0, true);

        /*
         * This local time will take into account item current user cannot see because of some hiding rules
         * Local only considers current LTC items
         */
        $localtime = 0;
        $localoptionaltime = 0;
        $localcount = 0;
        foreach ($this->instance->items as $item) {
            if ($item->hidden) {
                continue;
            }
            $localcount++;
            switch ($item->itemoptional) {
                case LTC_OPTIONAL_HEADING:
                    break;
                case LTC_OPTIONAL_NO:
                    $localtime += @$item->credittime;
                    break;
                case LTC_OPTIONAL_YES:
                    $localoptionaltime += @$item->credittime;
                    break;
            }
        }

        $ratio = ($totalitems->time) ? floor($localtime / $totalitems->time * 100) : 0;
        $optionalratio = ($totalitems->optionaltime) ? floor($localoptionaltime / $totalitems->optionaltime * 100) : 0;
        $mandatoryratio = 100 - $optionalratio;

        $template = new StdClass;
        $template->hasseveralinstances = false;
        if ($courseinstances > 1) {
            $template->hasseveralinstances = true;
        }

        if ($localoptionaltime) {
            $template->haslocaloptionaltime = true;
            $template->localmandatorytime = learningtimecheck_format_time($localtime);
            $template->localoptionaltime = learningtimecheck_format_time($localoptionaltime);
        } else {
            $template->haslocaloptionaltime = false;
            $template->localtime = learningtimecheck_format_time($localtime);
        }

        if ($totalitems->optionaltime) {
            $template->hastotaloptionaltime = true;
            $template->totalmandatorytime = learningtimecheck_format_time($totalitems->time);
            $template->totaloptionaltime = learningtimecheck_format_time($totalitems->optionaltime);
        } else {
            $template->hastotaloptionaltime = false;
            $template->totaltime = learningtimecheck_format_time($totalitems->time);
        }

        if ($localoptionaltime) {
            $template->haslocaloptionaltime = true;
            $template->mandatoryratio = $ratio;
            $template->optionalratio = $optionalratio;
        } else {
            $template->haslocaloptionaltime = false;
            $template->ratio = $ratio;
        }

        if ($totalitems->count) {
            $template->hasmandatoryitems = true;
            $template->mandatorycount = $totalitems->count;
        }
        if ($totalitems->optionalcount) {
            $template->hasoptionalitems = true;
            $template->optionalcount = $totalitems->optionalcount;
        }

        return $this->output->render_from_template('mod_learningtimecheck/global_counters', $template);
    }

    public function group_grouping_menu($rooturl) {
        global $COURSE;

        $str = '';
        $this->groupid = groups_get_course_group($COURSE, true);
        $str .= $this->grouping_menu($rooturl);
        $str .= $this->group_menu($rooturl);

        return $str;
    }

    public function group_menu($rooturl) {
        global $COURSE;

        $strgroup = get_string('group', 'group');

        $groups = groups_get_all_groups($COURSE->id, 0, 0 + @$this->grouping);

        $options = array();
        $options[0] = get_string('all');
        foreach ($groups as $group) {
            $options[$group->id] = strip_tags(format_string($group->name));
        }
        $popupurl = new moodle_url($rooturl.'&grouping='.$this->groupingid);
        $select = new single_select($popupurl, 'group', $options, $this->groupid, array());
        $select->label = $strgroup;
        $select->formid = 'selectgroup';
        return $this->output->render($select);
    }

    public function grouping_menu($rooturl) {
        global $COURSE;

        $strgrouping = get_string('grouping', 'group');

        $this->grouping = optional_param('grouping', 0, PARAM_INT);
        $groupings = groups_get_all_groupings($COURSE->id);

        $options = array();
        $options[0] = get_string('all');
        foreach ($groupings as $grouping) {
            $options[$grouping->id] = strip_tags(format_string($grouping->name));
        }
        $popupurl = new moodle_url($rooturl.'&group='.$this->groupid);
        $select = new single_select($popupurl, 'grouping', $options, $this->grouping, array());
        $select->label = $strgrouping;
        $select->formid = 'selectgrouping';
        return $this->output->render($select);
    }

    public function add_own_form($thispage, $addafteritem) {

        $str = '';

        if (empty($addafteritem)) {
            return('');
        }

        $str .= '<form style="display:inline;" action="'.$thispage->out_omit_querystring().'" method="get">';
        $str .= html_writer::input_hidden_params($thispage);
        $str .= '<input type="hidden" name="useredit" value="on" />';
        $str .= '<input type="submit" name="submit" value="'.get_string('addownitems', 'learningtimecheck').'" />';
        $jshandler = 'cancel_add_item_form(\''.$addafteritem->id.'\')';
        $label = get_string('canceledititem', 'learningtimecheck');
        $str .= '<input type="button" name="canceledititem" value="'.$label.'" onclick="'.$jshandler.'" />';
        $str .= '</form>';

        return $str;
    }

    /**
     * Renders the check grid table as master report table
     * @param arrayref &$table the check table to render
     * @param bool $editchecks if true, the teacher is viewing and can edit the counterchecks
     */
    public function report_table($table, $editchecks) {

        $template = new StdClass;

        $showteachermark = !($this->instance->learningtimecheck->teacheredit == LTC_MARKING_STUDENT);
        $showstudentmark = !($this->instance->learningtimecheck->teacheredit == LTC_MARKING_TEACHER);
        $teachermarklocked = $this->instance->learningtimecheck->lockteachermarks &&
                !has_capability('mod/learningtimecheck:updatelocked', $this->instance->context);

        // Sort out the heading row.
        $template->headers = $this->table_head_row($table);

        // Output the data.
        $studenttickimg = $this->output->pix_icon('tick_green_big', get_string('itemcomplete', 'learningtimecheck'), 'mod_learningtimecheck');
        $teacherimgs = $this->teachermark_pixs();

        $template->showteachermark = $showteachermark;
        $template->showstudentmark = $showstudentmark;
        $template->teachermarklocked = $teachermarklocked;

        $oddeven = 1;
        $keys = array_keys($table->data);
        $lastrowkey = end($keys);
        $template->rows = [];

        foreach ($table->data as $key => $row) {
            $oddeven = $oddeven ? 0 : 1;
            $rowtpl = new StdClass;
            $rowtpl->oddeven = $oddeven;

            if ($key == $lastrowkey) {
                $rowtpl->class = ' lastrow';
            }

            $keys2 = array_keys($row);
            $lastkey = end($keys2);

            // Prints cell content for each item.
            foreach ($row as $key => $item) {
                if (!empty($table->skip[$key]) || !array_key_exists($key, $table->size)) {
                    continue;
                }

                $itemtpl = new StdClass;
                $itemtpl->size = $table->size[$key];

                if ($key == 0) {
                    // First item is the name.
                    $itemtpl->item = $item;
                    $itemtpl->isfirst = true;
                    $rowtpl->items[] = $itemtpl;
                    continue;
                } else {
                    $itemtpl->isfirst = false;
                    // This is a check cell.
                    list($teachermark, $studentmark, $heading, $userid, $checkid, $modinfo) = $item;
                    $itemtpl->cellclass = ($heading) ? 'reportheading cell c'.$key : ' cell c'.$key;
                    $itemtpl->itemid = $checkid;
                    if (!empty($modinfo)) {
                        $itemtpl->cmid = $modinfo->id;
                    }

                    if ($heading) {
                        $itemtpl->isheading = true;
                    } else {
                        $itemtpl->ischeck = true;
                        // We have some checks to print.
                        if ($showstudentmark) {
                            if ($studentmark) {
                                $itemtpl->checkedstate = '-checked';
                                $itemtpl->studenttickimg = $studenttickimg;
                            } else {
                                $itemtpl->checkedstate = '-unchecked';
                                $itemtpl->studenttickimg = '&nbsp;';
                            }
                        }

                        if ($showteachermark) {
                            if ($teachermark == LTC_TEACHERMARK_YES) {
                                $itemtpl->teachercellclass = ' teachercheck-checked';
                                $itemtpl->teachertickimg = $teacherimgs[$teachermark];
                            } else if ($teachermark == LTC_TEACHERMARK_NO) {
                                $itemtpl->teachercellclass = ' teachercheck-unchecked';
                                $itemtpl->teachertickimg = $teacherimgs[$teachermark];
                            } else {
                                $itemtpl->teachercellclass = '';
                                $itemtpl->teachertickimg = $teacherimgs[LTC_TEACHERMARK_UNDECIDED];
                            }

                            if ($editchecks) {
                                $itemtpl->editdisabled = ($teachermarklocked && $teachermark == LTC_TEACHERMARK_YES) ? 'disabled="disabled" ' : '';

                                $itemtpl->selu = ($teachermark == LTC_TEACHERMARK_UNDECIDED) ? 'selected="selected" ' : '';
                                $itemtpl->sely = ($teachermark == LTC_TEACHERMARK_YES) ? 'selected="selected" ' : '';
                                $itemtpl->seln = ($teachermark == LTC_TEACHERMARK_NO) ? 'selected="selected" ' : '';

                                $itemtpl->userid = $userid;
                                $itemtpl->checkid = $checkid;
                            }
                        }

                        $itemtpl->cmcomplement = $this->cell_cm_complement($item);

                        if ($key == $lastkey) {
                            $itemtpl->cellclass .= ' lastcol';
                        }
                    }
                    $rowtpl->items[] = $itemtpl;
                }
            }
            $template->rows[] = $rowtpl;
        }

        return $this->output->render_from_template('mod_learningtimecheck/report_table', $template);
    }

    /**
     * Compute some complements depending on cmid
     * @param array $item
     */
    public function cell_cm_complement($item) {
        global $DB;

        $str = '';

        if ($modinfo = $item[5]) {
            if ($modinfo->modname == 'assign') {
                $userlastname = $DB->get_field('user', 'lastname', array('id' => $item[3]));
                $userfirstletter = substr($userlastname, 0, 1);
                $params = array('action' => 'grading', 'id' => $modinfo->id, 'thide' => 'email', 'tilast' => $userfirstletter);
                $assigncheckurl = new moodle_url('/mod/assign/view.php', $params);
                $pix = $this->output->pix_icon('t/hide');
                $str = '<a href="'.$assigncheckurl.'&thide=picture&thide=grade&thide=userid" target="_blank">'.$pix.'</a>';
            }
        }

        return $str;
    }

    /**
     * Renders the the check grid upper row with mod names
     * @param html_table $table the check table
     */
    public function table_head_row(html_table $table) {

        $keys = array_keys($table->head);
        $lastkey = end($keys);

        $headers = [];

        foreach ($table->head as $key => $heading) {
            if (!empty($table->skip[$key])) {
                continue;
            }
            $headertpl = new StdClass;
            $headertpl->size = $table->size[$key];
            $headertpl->levelclass = 'ltc-header';
            if ($key == $lastkey) {
                $headertpl->levelclass .= ' lastcol';
            }
            $headertpl->heading = $heading;
            $headers[] = $headertpl;
        }

        return $headers;
    }

    /**
     * Get suitable pixs for each teacher state.
     */
    public function teachermark_pixs() {

        $alt = get_string('teachermarkundecided', 'learningtimecheck');
        $piximg = $this->output->pix_icon('empty_box', $alt, 'learningtimecheck');
        $pixarray[LTC_TEACHERMARK_UNDECIDED] = $piximg;

        $alt = get_string('teachermarkyes', 'learningtimecheck');
        $piximg = $this->output->pix_icon('tick_green_big', $alt, 'learningtimecheck');
        $pixarray[LTC_TEACHERMARK_YES] = $piximg;

        $alt = get_string('teachermarkno', 'learningtimecheck');
        $piximg = $this->output->pix_icon('wrong_red_big', $alt, 'learningtimecheck');
        $pixarray[LTC_TEACHERMARK_NO] = $piximg;

        return $pixarray;
    }

    /**
     * Renders a name filter for filtering on first or last name.
     * @param moodle_url ref &$thispageurl the current url of the page with all quiery string params.
     */
    public function namefilter(&$thispageurl) {

        $localthispageurl = clone($thispageurl);
        $localthispageurl->params(['page' => 0]);
        $template = new Stdclass;

        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $template->firstnamefilter = optional_param('filterfirstname', false, PARAM_TEXT);

        for ($i = 0; $i < strlen($letters); $i++) {
            $lettertpl = new StdClass;
            $lettertpl->letter = $letters[$i];
            if ($template->firstnamefilter == $lettertpl->letter) {
                $lettertpl->current = true;
            } else {
                $lettertpl->thisurl = $localthispageurl.'&filterfirstname='.$lettertpl->letter;
                $lettertpl->current = false;
            }
            $template->fnletters[] = $lettertpl;
        }
        $template->allfnurl = $localthispageurl.'&filterfirstname=';

        $template->lastnamefilter = optional_param('filterlastname', false, PARAM_TEXT);

        for ($i = 0; $i < strlen($letters); $i++) {
            $lettertpl = new StdClass;
            $lettertpl->letter = $letters[$i];
            if ($template->lastnamefilter == $lettertpl->letter) {
                $lettertpl->current = true;
            } else {
                $lettertpl->thisurl = $localthispageurl.'&filterlastname='.$lettertpl->letter;
                $lettertpl->current = false;
            }
            $template->lnletters[] = $lettertpl;
        }
        $template->alllnurl = $localthispageurl.'&filterlastname=';

        $params = array();
        if ($template->firstnamefilter) {
            $params['filterfirstname'] = $template->firstnamefilter;
        }
        if ($template->lastnamefilter) {
            $params['filterlastname'] = $template->lastnamefilter;
        }
        $thispageurl->params($params);

        return $this->output->render_from_template('mod_learningtimecheck/namefilter', $template);
    }

    /**
     * Renders a JQPlot graph showing compared progress of students in a given population.
     * @param arrayref &$users
     * @param string $scale
     * @param int $timeorigin
     */
    public function learning_curves(&$users, $scale = 'days', $timeorigin) {
        global $USER;

        $scalediv = ($scale == 'days') ? DAYSECS : HOURSECS;

        // Precalculate curves.
        $series = array();
        $k = 0;
        $maxtix = 0;
        foreach ($users as $user) {
            $data[$k][] = array(0, 0);
            // $checksum may depend on user due to group/gourping effect.
            $checksums = $this->instance->get_items_for_user($USER);

            $series[] = array('color' => $this->random_color());
            $checks = $this->instance->get_checks($user->id, null, 'c.usertimestamp ASC');

            $sumtime = 0;
            $userfirst = true;
            $yvalue = 0;
            foreach ($checks as $c) {
                if ($c->usertimestamp == 0) {
                    continue;
                }
                if ($c->itemoptional == LTC_OPTIONAL_YES) {
                    continue;
                }
                if ($c->itemoptional == LTC_OPTIONAL_HEADING) {
                    continue;
                }

                $tix = sprintf('%0.2f', ($c->usertimestamp - $timeorigin) / $scalediv);

                $maxtix = ($maxtix < $tix) ? $tix : $maxtix;
                $sumtime += $c->credittime;
                if ($checksums['mandatory']['time']) {
                    $yvalue = round($sumtime / $checksums['mandatory']['time'] * 100);
                } else {
                    $yvalue = 0;
                }
                if ($userfirst == true && $tix != 0) {
                    $userfirst = false;
                    $data[$k][] = array($tix, 0);
                }
                $data[$k][] = array($tix, $yvalue);
            }

            if ($yvalue) {
                $labels[] = fullname($user).' ('.$yvalue.'%)';
            } else {
                $labels[] = fullname($user);
            }

            $k++;
        }

        $htmlid = 'user-velocity';

        $jqplot = array(
            'title' => array(
                'text' => get_string('uservelocity', 'learningtimecheck'),
                'fontSize' => '1.3em',
                'color' => '#000000',
                ),
            'legend' => array(
                'renderer' => '$.jqplot.EnhancedLegendRenderer',
                'rendererOptions' => array(
                    'numberColumns' => 2,
                    'seriesToggle' => true,
                ),
                'show' => true,
                'location' => 'e',
                'placement' => 'outsideGrid',
                'showSwatches' => true,
                'marginLeft' => '10px',
                'border' => '1px solid #808080',
                'labels' => $labels,
            ),
            'highlighter' => array(
                'show' => true,
                'sizeAdjust' => 7.5,
             ),
            'cursor' => array(
                'show' => false,
                'zoom' => true,
                'dblClickReset' => true,
                'showCursorLegend' => true,
            ),
            'axesDefaults' => array('labelRenderer' => '$.jqplot.CanvasAxisLabelRenderer'),
            'axes' => array(
                'xaxis' => array(
                    'label' => get_string($scale, 'learningtimecheck'),
                    'tickOptions' => array('formatString' => '%1d'),
                    'renderer' => '$.jqplot.LinearAxisRenderer',
                    'pad' => 1.2,
                    'min' => 0,
                    'max' => $maxtix + 1,
                    ),
                'yaxis' => array(
                    'autoscale' => true,
                    'tickOptions' => array('formatString' => '%2d'),
                    'rendererOptions' => array('forceTickAt0' => true),
                    'label' => get_string('progress', 'learningtimecheck'),
                    'labelRenderer' => '$.jqplot.CanvasAxisLabelRenderer',
                    'labelOptions' => array('angle' => 90),
                    'min' => 0,
                    'max' => 100,
                    'pad' => 1.2
                    )
                ),
            'series' => $series,
        );

        return local_vflibs_jqplot_print_graph($htmlid, $jqplot, $data, 1080, 500, '', true, null);
    }

    private function random_color_part() {
        return str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT);
    }

    private function random_color() {
        return '#'.$this->random_color_part() . $this->random_color_part() . $this->random_color_part();
    }
}