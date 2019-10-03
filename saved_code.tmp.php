

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

        echo '<!-- mod_learningtimecheck::output/view_items -->';
        echo $this->output->box_start('generalbox boxwidthwide boxaligncenter learningtimecheckbox');

        echo html_writer::tag('div', '&nbsp;', array('id' => 'learningtimecheckspinner'));

        $comments = $this->instance->learningtimecheck->teachercomments;
        $editcomments = false;
        $params = array('view' => 'view', 'id' => $this->instance->cm->id);
        $thispage = new moodle_url('/mod/learningtimecheck/view.php', $params);
        $context = context_module::instance($this->instance->cm->id);

        $teachermarklocked = false;
        $showcompletiondates = false;

        if ($viewother) {
            // I'm teacher an viewing the item list of another user (student).
            if ($comments) {
                $editcomments = optional_param('editcomments', false, PARAM_BOOL);
            }
            $params = array('view' => 'view', 'id' => $this->instance->cm->id, 'studentid' => $this->instance->userid);
            $thispage = new moodle_url('/mod/learningtimecheck/view.php', $params);

            if (!$student = $DB->get_record('user', array('id' => $this->instance->userid) )) {
                print_error('errornosuchuser', 'learningtimecheck');
            }

            $info = format_text($this->instance->learningtimecheck->name).' ('.fullname($student, true).')';

            echo '<h2>'.get_string('learningtimecheckfor', 'learningtimecheck').' '.fullname($student, true).'</h2>';

            // Command block.

            echo '<div class="ltc-useredit-commands">';
            if (!$editcomments) {
                echo $this->print_edit_comments_button($thispage);
            }
            echo $this->print_export_user_details_pdf_button($thispage, $COURSE->id, $this->instance->userid);
            echo $this->print_next_user_button($thispage, $COURSE->id, $this->instance->userid);
            echo '</div>';

            // Preparing intro.

            $teachermarklocked = $this->instance->learningtimecheck->lockteachermarks &&
                    !has_capability('mod/learningtimecheck:updatelocked', $this->instance->context);

            $reportsettings = learningtimecheck_class::get_report_settings();
            $showcompletiondates = $reportsettings->showcompletiondates;

            $strteacherdate = get_string('teacherdate', 'mod_learningtimecheck');
            $struserdate = get_string('userdate', 'mod_learningtimecheck');
            $strteachername = get_string('teacherid', 'mod_learningtimecheck');

            if ($showcompletiondates) {
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
            $intro = file_rewrite_pluginfile_urls($intro, 'pluginfile.php', $this->instance->context->id,
                                                  'mod_learningtimecheck', 'intro', null);
            $opts = array('trusted' => $CFG->enabletrusttext);

            echo $this->output->box(format_text($intro, $this->instance->learningtimecheck->introformat, $opts), 'ltc-box');
        }

        // Progressbar if relevant.

        $showteachermark = false;
        $showcheckbox = false;
        $isteacher = has_capability('mod/learningtimecheck:updateother', $context);
        if ($viewother || $userreport) {
            echo $this->progressbar();
            $showteachermark = ($this->instance->learningtimecheck->teacheredit == LTC_MARKING_TEACHER) ||
                    ($this->instance->learningtimecheck->teacheredit == LTC_MARKING_BOTH) ||
                    ($this->instance->learningtimecheck->teacheredit == LTC_MARKING_EITHER);
            $showcheckbox = (($this->instance->learningtimecheck->teacheredit == LTC_MARKING_BOTH && $viewother)) ||
                    (($this->instance->learningtimecheck->teacheredit == LTC_MARKING_EITHER && $viewother));
            $teachermarklocked = $teachermarklocked && $showteachermark; // Make sure this is OFF, if not showing teacher marks.
        }

        $overrideauto = ($this->instance->learningtimecheck->autoupdate != LTC_AUTOUPDATE_YES);
        $checkgroupings = $this->instance->learningtimecheck->autopopulate && ($this->instance->groupings !== false);

        $canupdateown = $this->instance->canupdateown();
        $updateform = ($showcheckbox && $canupdateown && !$viewother && $userreport) ||
                       ($isteacher && ($showteachermark || $editcomments));
        $updateform = true;

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

                echo '<form action="'.$thispage->out_omit_querystring().'" method="post">';
                $realview = optional_param('view', '', PARAM_TEXT);
                echo '<input type="hidden" name="view" value="'.$realview.'">';
                echo '<input type="hidden" name="id" value="'.$thispage->get_param('id').'">';
                echo '<input type="hidden" name="studentid" value="'.$this->instance->userid.'" >';
                echo learningtimecheck_add_paged_params();
                echo '<input type="hidden" name="what" value="'.($isteacher ? 'teacherupdatechecks' : 'updatechecks').'" />';
                echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
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

            if ($teachermarklocked) {
                echo '<p style="ltc-warning">'.get_string('lockteachermarkswarning', 'learningtimecheck').'</p>';
            }

            /*
             *
             * start producing item list
             *
             */

            echo '<ol class="ltc" id="ltc-outer">';

            $allitems = array_values($this->instance->items);
            $gotomodulestr = get_string('gotomodule', 'learningtimecheck');

            for ($i = 0; $i < count($allitems); $i++) {

                $item = $allitems[$i];
                $nextitem = @$allitems[$i + 1];

                $isheading = $item->itemoptional == LTC_OPTIONAL_HEADING;
                $nextisheading = $nextitem && ($nextitem->itemoptional == LTC_OPTIONAL_HEADING);

                if ($isheading && $nextisheading) {
                    // Do not print empty sections.
                    continue;
                }

                $itemstr = '';

                if ($item->hidden) {
                    continue;
                }

                if ($checkgroupings && !empty($item->grouping)) {
                    if (!in_array($item->grouping, $this->instance->groupings)) {
                        // Current user is not a member of this item's grouping, so skip.
                        continue;
                    }
                }

                $itemname = '"item'.$item->id.'"';
                $checked = (($updateform || $viewother || $userreport) && @$item->checked) ? ' checked="checked" ' : '';
                if ($viewother || $userreport) {
                    $checked .= ' disabled="disabled" ';
                } else if (!$overrideauto && $item->moduleid) {
                    $checked .= ' disabled="disabled" ';
                }

                if ($isheading) {
                    $optional = ' class="itemheading" ';
                    $spacerimg = $this->output->image_url('check_spacer', 'learningtimecheck');
                } else if ($item->itemoptional == LTC_OPTIONAL_YES) {
                    $optional = ' class="itemoptional" ';
                    $checkclass = ' itemoptional';
                } else {
                    $optional = '';
                    $checkclass = '';
                }

                $itemclass = ($isheading) ? 'heading' : '';
                $itemstr .= '<li class="ltc-item '.$itemclass.'">';

                $itemstr .= '<div class="ltc-item-desc">';

                /*
                 * Print main line.
                 */

                if (!$isheading) {
                    if ($showcheckbox) {
                        $itemstr .= '<input class="ltc-item'.$checkclass.'"
                                            type="checkbox"
                                            name="items[]"
                                            id='.$itemname.$checked.'
                                            value="'.$item->id.'" />';
                    } else {
                        $mandatorypix = ($item->itemoptional == LTC_OPTIONAL_YES) ? 'optional' : 'mandatory';
                        $checkedpix = ($item->checked) ? 'marked' : 'unmarked';
                        $pixname = 'item_'.$checkedpix.'_'.$mandatorypix;
                        $pixurl = $this->output->image_url($pixname, 'mod_learningtimecheck');
                        $itemstr .= '&nbsp;<img src="'.$pixurl.'" class="ltc-item-pix" />';
                    }
                }

                if ($item->moduleid) {
                    if ($mod = @$modinfo->cms[$item->moduleid]) {
                        $attrs = array('src' => $mod->get_icon_url(),
                                       'class' => 'ltc-icon-medium activityicon',
                                       'alt' => $mod->modfullname,
                                       'title' => $mod->modfullname);
                        $itemstr .= '&nbsp;'.html_writer::empty_tag('img', $attrs);
                    }
                }

                $itemstr .= '&nbsp;<label for='.$itemname.$optional.'>'.s($item->displaytext).'</label>';

                if (isset($item->modulelink)) {
                    $alt = get_string('linktomodule','learningtimecheck');
                    $pix = $this->output->pix_icon('follow_link', $alt, 'learningtimecheck');
                    $itemstr .= '&nbsp;<a href="'.$item->modulelink.'" title"'.$gotomodulestr.'">'.$pix.'</a>';
                }

                if (!empty($item->credittime)) {
                    $creditstr = get_string('itemcredittime', 'learningtimecheck', $item->credittime);
                    $itemstr .= ' <div class="ltc-credittime">'.$creditstr.'</div>';
                }

                if (!empty($item->declaredtime) && (@$item->isdeclarative > 0) && !$isheading) {
                    $declaredstr = get_string('itemdeclaredtime', 'learningtimecheck', $item->declaredtime);
                    $itemstr .= ' <div class="ltc-declaredtime">'.$declaredstr.'</div>';
                }

                $itemstr .= '</div>';

                $collectitemstr = '<div class="ltc-data-collect">';
                $collectformhaselements = false;

                /*
                 * Print item forms.
                 */

                if ($showteachermark) {
                    if (!$isheading) {
                        $collectformhaselements = true;
                        $collectitemstr .= '<div class="ltc-data-collect-element teachermarks">';
                        if ($viewother) {
                            // I am a teacher that is marking a student.
                            $collectitemstr .= get_string('teachermark', 'learningtimecheck');
                            $cond = $teachermarklocked && $item->teachermark == LTC_TEACHERMARK_YES;
                            $disabledarr = ($cond) ? array('disabled' => 'disabled') : array();
                            $opts = learningtimecheck_get_teacher_mark_options();
                            $collectitemstr .= html_writer::select($opts, "items[$item->id]", $item->teachermark,'', $disabledarr);
                        } else {
                            // I am a student.
                            list($imgsrc, $titletext) = $this->instance->get_teachermark($item->id);
                            $collectitemstr .= '<img src="'.$imgsrc.'" alt="'.$titletext.'" title="'.$titletext.'" />';
                        }
                        $collectitemstr .= '</div>';
                    }
                }

                if (@$item->isdeclarative > 0 && !$isheading) {

                    // Teacher side.
                    if ($isteacher && ($item->isdeclarative > LTC_DECLARATIVE_STUDENTS)) {

                        if ($USER->id != $this->instance->userid) {
                            // We are evaluating other.
                            $collectformhaselements = true;
                            $declaredtimedisabled = ($item->teachermark == LTC_TEACHERMARK_UNDECIDED);
                            $collectitemstr .= '<div class="ltc-data-collect-element">';
                            $collectitemstr .= get_string('teachertimetodeclareperuser', 'learningtimecheck');
                            $opts = learningtimecheck_get_credit_times('thin');
                            $attrs = array('onchange' => 'learningtimecheck_updatechecks_show()',
                                           'id' => 'declaredtime'.$item->id,
                                           'class' => 'teachertimedeclarator');
                            $name = "teacherdeclaredtimeperuser[$item->id]";
                            $collectitemstr .= html_writer::select($opts, $name, $item->teacherdeclaredtime, '', $attrs);
                            $collectitemstr .= '</div>';

                            if (@$item->declaredtime) {
                                $collectitemstr .= ' '.get_string('studenthasdeclared', 'learningtimecheck', $item->declaredtime);
                            }
                        } else {
                            // We are declaring item level time.
                            $collectformhaselements = true;
                            $collectitemstr .= '<div class="ltc-data-collect-element">';
                            $collectitemstr .= get_string('teachertimetodeclare', 'learningtimecheck');
                            $opts = learningtimecheck_get_credit_times('thin');
                            $attrs = array('onchange' => 'learningtimecheck_updatechecks_show()',
                                           'id' => 'declaredtime'.$item->id,
                                           'class' => 'teachertimedeclarator',
                                           'autocomplete' => 'off');
                            $name = "teacherdeclaredtime[$item->id]";
                            $collectitemstr .= html_writer::select($opts, $name, $item->teacherdeclaredtime, '', $attrs);
                            $collectitemstr .= '</div>';
                        }
                    }

                    if (($USER->id == $this->instance->userid) &&
                            (($item->isdeclarative == LTC_DECLARATIVE_STUDENTS) ||
                                    ($item->isdeclarative == LTC_DECLARATIVE_BOTH))) {
                        if ($isteacher) {
                            // Nothing for teachers here.
                        } else {
                            // Students are declaring their student time.
                            $collectformhaselements = true;
                            $declaredtimedisabled = (!$item->checked);
                            $collectitemstr .= '<div class="ltc-data-collect-element">';
                            $collectitemstr .= get_string('timetodeclare', 'learningtimecheck');
                            $opts = learningtimecheck_get_credit_times();
                            $name = "declaredtime[{$item->id}]";
                            $attrs = array('onchange' => 'learningtimecheck_updatechecks_show()',
                                           'id' => 'declaredtime'.$item->id,
                                           'class' => 'sudenttimedeclarator',
                                           'autocomplete' => 'off');
                            if (!empty($this->instance->learningtimecheck->lockstudentinput)) {
                                $attrs['disabled'] = 'disabled';
                            }
                            $collectitemstr .= html_writer::select($opts, $name, $item->declaredtime, '', $attrs);
                            $collectitemstr .= '</div>';
                        }
                    }
                }

                $collectitemstr .= '</div>';

                if ($collectformhaselements) {
                    $itemstr .= $collectitemstr;
                }

                if ($addown) {
                    $params = array('itemid' => $item->id, 'sesskey' => sesskey(), 'what' => 'startadditem');
                    $itemstr .= '&nbsp;<a href="'.$thispage->out(true, $params).'">';
                    $title = '"'.get_string('additemalt', 'learningtimecheck').'"';
                    $itemstr .= $this->output->pix_icon('add', $title, 'learningtimecheck').'</a>';
                }

                if ($showcompletiondates) {
                    if ($isheading) {
                        if ($showteachermark &&
                                $item->teachermark != LTC_TEACHERMARK_UNDECIDED &&
                                        $item->teachertimestamp) {
                            if ($item->teachername) {
                                $itemstr .= '<span class="itemteachername" title="'.$strteachername.'">'.$item->teachername.'</span>';
                            }
                            $span = userdate($item->teachertimestamp, get_string('strftimedatetimeshort'));
                            $itemstr .= '<span class="itemteacherdate" title="'.$strteacherdate.'">'.$span.'</span>';
                        }
                        if ($showcheckbox && $item->checked && $item->usertimestamp) {
                            $span = userdate($item->usertimestamp, get_string('strftimedatetimeshort'));
                            $itemstr .= '<span class="itemuserdate" title="'.$struserdate.'">'.$span.'</span>';
                        }
                    }
                }

                $foundcomment = false;
                if ($comments) {
                    if (array_key_exists($item->id, $comments)) {
                        $comment =  $comments[$item->id];
                        $foundcomment = true;
                        $itemstr .= ' <span class="teachercomment">&nbsp;';
                        if ($comment->commentby) {
                            $params = array('id' => $comment->commentby, 'course' => $this->instance->course->id);
                            $userurl = new moodle_url('/user/view.php', $params);
                            $itemstr .= '<a href="'.$userurl.'">'.fullname($commentusers[$comment->commentby]).'</a>: ';
                        }
                        if ($editcomments) {
                            $outid = '';
                            if (!$focusitem) {
                                $focusitem = 'firstcomment';
                                $outid = ' id="firstcomment" ';
                            }
                            $itemstr .= '<input type="text"
                                                name="teachercomment['.$item->id.']"
                                                value="'.s($comment->text).'"
                                                '.$outid.'/>';
                        } else {
                            $itemstr .= s($comment->text);
                        }
                        $itemstr .= '&nbsp;</span>';
                    }
                }
                if (!$foundcomment && $editcomments) {
                    $itemstr .= '&nbsp;<input type="text" name="teachercomment['.$item->id.']" />';
                }

                $itemstr .= '</li>';

                // Output any user-added items.
                if ($this->instance->useritems) {
                    $useritem = current($this->instance->useritems);

                    if ($useritem && ($useritem->position == $item->position)) {
                        $thisitemurl = clone $thispage;
                        $thisitemurl->param('what', 'updateitem');
                        $thisitemurl->param('sesskey', sesskey());

                        $itemstr .= '<ol class="ltc">';
                        while ($useritem && ($useritem->position == $item->position)) {
                            $itemname = '"item'.$useritem->id.'"';
                            $checked = ($updateform && $useritem->checked) ? ' checked="checked" ' : '';
                            if (isset($useritem->editme)) {
                                $itemtext = explode("\n", $useritem->displaytext, 2);
                                $itemtext[] = '';
                                $text = $itemtext[0];
                                $note = $itemtext[1];
                                $thisitemurl->param('itemid', $useritem->id);

                                $itemstr .= '<li>';
                                $itemstr .= '<div style="float: left;">';
                                if ($showcheckbox) {
                                    $itemstr .= '<input class="ltc-item itemoptional"
                                                        type="checkbox"
                                                        name="items[]"
                                                        id='.$itemname.$checked.'
                                                        disabled="disabled"
                                                        value="'.$useritem->id.'" />';
                                }
                                $itemstr .= '<form style="display:inline" action="'.$thisitemurl->out_omit_querystring().'" method="post">';
                                $itemstr .= html_writer::input_hidden_params($thisitemurl);
                                $itemstr .= learningtimecheck_add_paged_params();
                                $itemstr .= '<input type="text"
                                                    size="'.LTC_TEXT_INPUT_WIDTH.'"
                                                    name="displaytext"
                                                    value="'.s($text).'"
                                                    id="updateitembox" />';
                                $itemstr .= '<input type="submit"
                                                    name="updateitem"
                                                    value="'.get_string('updateitem', 'learningtimecheck').'" />';
                                $itemstr .= '<br />';
                                $itemstr .= '<textarea name="displaytextnote" rows="3" cols="25">'.s($note).'</textarea>';
                                $itemstr .= '</form>';
                                $itemstr .= '</div>';

                                $itemstr .= $this->cancel_item_form($thispage);
                                $itemstr .= '<br style="clear: both;" />';
                                $itemstr .= '</li>';

                                $focusitem = 'updateitembox';
                            } else {
                                $itemstr .= '<li>';
                                if ($showcheckbox) {
                                    $itemstr .= '<input class="ltc-checkitem itemoptional"
                                                        type="checkbox"
                                                        name="items[]"
                                                        id='.$itemname.$checked.'
                                                        value="'.$useritem->id.'" />';
                                }
                                $splittext = explode("\n",s($useritem->displaytext),2);
                                $splittext[] = '';
                                $text = $splittext[0];
                                $note = str_replace("\n",'<br />',$splittext[1]);
                                $itemstr .= '<label class="useritem" for='.$itemname.'>'.$text.'</label>';

                                if ($addown) {
                                    $baseurl = $thispage.'&amp;itemid='.$useritem->id.'&amp;sesskey='.sesskey().'&amp;action=';
                                    $itemstr .= '&nbsp;<a href="'.$baseurl.'edititem">';
                                    $title = '"'.get_string('edititem','learningtimecheck').'"';
                                    $itemstr .= $this->output->pix_icon('/t/edit', $title).'</a>';

                                    $itemstr .= '&nbsp;<a href="'.$baseurl.'deleteitem" class="deleteicon">';
                                    $title = '"'.get_string('deleteitem','learningtimecheck').'"';
                                    $itemstr .= $this->output->pix_icon('remove', $title, 'learningtimecheck').'</a>';
                                }
                                if ($note != '') {
                                    $itemstr .= '<div class="note">'.$note.'</div>';
                                }

                                $itemstr .= '</li>';
                            }
                            $useritem = next($this->instance->useritems);
                        }
                        $itemstr .= '</ol>';
                    }
                }

                if ($addown && ($item->id == $this->instance->additemafter)) {

                    echo $this->add_item_form($thispage, $item, $showcheckbox);
                    echo $this->cancel_item_form($thispage);
                    $itemstr .= '<br style="clear: both;" />';
                    $itemstr .= '</li></ol>';

                    if (!$focusitem) {
                        $focusitem = 'additembox';
                    }
                }

                echo $itemstr;
            }
            echo '</ol>';

            $savechecksstr = get_string('savechecks', 'learningtimecheck');

            if ($updateform) {
                if ($viewother) {
                    echo '&nbsp;<input type="submit" name="viewprev" value="'.get_string('previous').'" />';
                    echo '&nbsp;<input type="submit" name="save" value="'.$savechecksstr.'" />';
                    echo '&nbsp;<input type="submit" name="savenext" value="'.get_string('saveandnext').'" />';
                    echo '&nbsp;<input type="submit" name="viewnext" value="'.get_string('next').'" />';
                } else {
                    echo '<input id="ltc-savechecks" type="submit" name="submit" value="'.$savechecksstr.'" />';
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

        echo $this->output->box_end();
        echo '<!-- /mod_learningtimecheck::output/view_items -->';
    }
