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
 * This file defines the main learningtimecheck configuration form
 * It uses the standard core Moodle (>1.8) formslib. For
 * more info about them, please visit:
 *
 * http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * The form must provide support for, at least these fields:
 *   - name: text element of 64cc max
 *
 * Also, it's usual to use these fields:
 *   - intro: one htmlarea element to describe the activity
 *            (will be showed in the list of activities of
 *             newmodule type (index.php) and in the header
 *             of the learningtimecheck main page (view.php).
 *   - introformat: The format used to write the contents
 *             of the intro field. It automatically defaults
 *             to HTML when the htmleditor is used and can be
 *             manually selected if the htmleditor is not used
 *             (standard formats are: MOODLE, HTML, PLAIN, MARKDOWN)
 *             See lib/weblib.php Constants and the format_text()
 *             function for more info
 */

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_learningtimecheck_mod_form extends moodleform_mod {

    function definition() {

        global $COURSE, $CFG;
        $mform =& $this->_form;

//-------------------------------------------------------------------------------
    /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

    /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('modulename', 'learningtimecheck'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->add_intro_editor(true, get_string('learningtimecheckintro', 'learningtimecheck'));

//-------------------------------------------------------------------------------

        $mform->addElement('header', 'learningtimechecksettings', get_string('learningtimechecksettings', 'learningtimecheck'));

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));
        $mform->addElement('select', 'useritemsallowed', get_string('useritemsallowed', 'learningtimecheck'), $ynoptions);

        $teditoptions = array(  learningtimecheck_MARKING_STUDENT => get_string('teachernoteditcheck','learningtimecheck'),
                                learningtimecheck_MARKING_TEACHER => get_string('teacheroverwritecheck', 'learningtimecheck'),
                                learningtimecheck_MARKING_BOTH => get_string('teacheralongsidecheck', 'learningtimecheck'));
        $mform->addElement('select', 'teacheredit', get_string('teacheredit', 'learningtimecheck'), $teditoptions);

        $mform->addElement('select', 'duedatesoncalendar', get_string('duedatesoncalendar', 'learningtimecheck'), $ynoptions);
        $mform->setDefault('duedatesoncalendar', 0);

        // These settings are all disabled, as they are not currently implemented

        /*
        $themes = array('default' => 'default');
        $mform->addElement('select', 'theme', get_string('theme', 'learningtimecheck'), $themes);
        */

        $mform->addElement('select', 'teachercomments', get_string('teachercomments', 'learningtimecheck'), $ynoptions);
        $mform->setDefault('teachercomments', 1);
        $mform->setAdvanced('teachercomments');

        $mform->addElement('text', 'maxgrade', get_string('maximumgrade'), array('size'=>'10'));
        $mform->setDefault('maxgrade', 100);
        $mform->setAdvanced('maxgrade');

        $emailrecipients = array(   learningtimecheck_EMAIL_NO => get_string('no'),
                                    learningtimecheck_EMAIL_STUDENT => get_string('teachernoteditcheck', 'learningtimecheck'),
                                    learningtimecheck_EMAIL_TEACHER => get_string('teacheroverwritecheck', 'learningtimecheck'),
                                    learningtimecheck_EMAIL_BOTH => get_string('teacheralongsidecheck', 'learningtimecheck'));
        $mform->addElement('select', 'emailoncomplete', get_string('emailoncomplete', 'learningtimecheck'), $emailrecipients);
        $mform->setDefault('emailoncomplete', 0);
        $mform->addHelpButton('emailoncomplete', 'emailoncomplete', 'learningtimecheck');

		if (!learningtimecheck_course_is_page_formatted()){
        $autopopulateoptions = array (learningtimecheck_AUTOPOPULATE_NO => get_string('no'),
                                      learningtimecheck_AUTOPOPULATE_SECTION => get_string('importfromsection','learningtimecheck'),
                                      learningtimecheck_AUTOPOPULATE_COURSE => get_string('importfromcourse', 'learningtimecheck'));
		} else {
        $autopopulateoptions = array (learningtimecheck_AUTOPOPULATE_NO => get_string('no'),
                                      learningtimecheck_AUTOPOPULATE_CURRENT_PAGE => get_string('importfrompage','learningtimecheck'),
                                      learningtimecheck_AUTOPOPULATE_CURRENT_PAGE_AND_SUBS => get_string('importfrompageandsubs','learningtimecheck'),
                                      learningtimecheck_AUTOPOPULATE_CURRENT_TOP_PAGE => get_string('importfromsection','learningtimecheck'),
                                      learningtimecheck_AUTOPOPULATE_COURSE => get_string('importfromcourse', 'learningtimecheck'));
		}

        $mform->addElement('select', 'autopopulate', get_string('autopopulate', 'learningtimecheck'), $autopopulateoptions);
        $mform->setDefault('autopopulate', 0);
        $mform->addHelpButton('autopopulate', 'autopopulate', 'learningtimecheck');

        $autoupdate_options = array( learningtimecheck_AUTOUPDATE_NO => get_string('no'),
                                     learningtimecheck_AUTOUPDATE_YES => get_string('yesnooverride', 'learningtimecheck'),
                                     learningtimecheck_AUTOUPDATE_YES_OVERRIDE => get_string('yesoverride', 'learningtimecheck'));
        $mform->addElement('select', 'autoupdate', get_string('autoupdate', 'learningtimecheck'), $autoupdate_options);
        $mform->setDefault('autoupdate', 1);
        $mform->disabledIf('autoupdate', 'autopopulate', 'eq', 0);
        $mform->addHelpButton('autoupdate', 'autoupdate', 'learningtimecheck');
        $mform->addElement('static', 'autoupdatenote', '', get_string('autoupdatenote', 'learningtimecheck'));

        $mform->addElement('selectyesno', 'lockteachermarks', get_string('lockteachermarks', 'learningtimecheck'));
        $mform->setDefault('lockteachermarks', 0);
        $mform->setAdvanced('lockteachermarks');
        $mform->addHelpButton('lockteachermarks', 'lockteachermarks', 'learningtimecheck');

        $mform->addElement('select', 'usetimecounterpart', get_string('usetimecounterpart', 'learningtimecheck'), $ynoptions);
        $mform->setDefault('usetimecounterpart', 0);

//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();

//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();

    }

    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $default_values['completionpercentenabled']=
            !empty($default_values['completionpercent']) ? 1 : 0;
        if (empty($default_values['completionpercent'])) {
            $default_values['completionpercent']=100;
        }
    }

    function add_completion_rules() {
        $mform =& $this->_form;

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionpercentenabled', '', get_string('completionpercent','learningtimecheck'));
        $group[] =& $mform->createElement('text', 'completionpercent', '', array('size'=>3));
        $mform->setType('completionpercent',PARAM_INT);
        $mform->addGroup($group, 'completionpercentgroup', get_string('completionpercentgroup','learningtimecheck'), array(' '), false);
        $mform->disabledIf('completionpercent','completionpercentenabled','notchecked');

        return array('completionpercentgroup');
    }

    function completion_rule_enabled($data) {
        return (!empty($data['completionpercentenabled']) && $data['completionpercent']!=0);
    }

    function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        // Turn off completion settings if the checkboxes aren't ticked
        $autocompletion = !empty($data->completion) && $data->completion==COMPLETION_TRACKING_AUTOMATIC;
        if (empty($data->completionpercentenabled) || !$autocompletion) {
            $data->completionpercent = 0;
        }
        return $data;
    }

}
