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
 * @package     mod_learningtimecheck
 * @category    mod
 * @author      David Smith <moodle@davosmith.co.uk> as checklist
 * @author      Valery Fremaux
 * @license     http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
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

    public function definition() {
        global $COURSE;

        $mform = $this->_form;

        $config = get_config('learningtimecheck');

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('modulename', 'learningtimecheck'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('learningtimecheckintro', 'learningtimecheck'));

        $mform->addElement('header', 'learningtimechecksettings', get_string('learningtimechecksettings', 'learningtimecheck'));

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $teditoptions = array(LTC_MARKING_STUDENT => get_string('teachernoteditcheck', 'learningtimecheck'),
                              LTC_MARKING_TEACHER => get_string('teacheroverwritecheck', 'learningtimecheck'),
                              LTC_MARKING_BOTH => get_string('teacheralongsidecheck', 'learningtimecheck'),
                              LTC_MARKING_EITHER => get_string('eithercheck', 'learningtimecheck'),
                              );
        $mform->addElement('select', 'teacheredit', get_string('marktypes', 'learningtimecheck'), $teditoptions);

        // These settings are all disabled, as they are not currently implemented.

        $mform->addElement('select', 'teachercomments', get_string('teachercomments', 'learningtimecheck'), $ynoptions);
        $mform->setDefault('teachercomments', 1);
        $mform->setAdvanced('teachercomments');

        $emailrecipients = array(LTC_EMAIL_NO => get_string('no'),
                                 LTC_EMAIL_STUDENT => get_string('teachernoteditcheck', 'learningtimecheck'),
                                 LTC_EMAIL_TEACHER => get_string('teacheroverwritecheck', 'learningtimecheck'),
                                 LTC_EMAIL_BOTH => get_string('teacheralongsidecheck', 'learningtimecheck'));

        $mform->addElement('select', 'emailoncomplete', get_string('emailoncomplete', 'learningtimecheck'), $emailrecipients);
        $mform->setDefault('emailoncomplete', 0);
        $mform->addHelpButton('emailoncomplete', 'emailoncomplete', 'learningtimecheck');

        if (!($COURSE->format == 'page')) {
            $autopopulateoptions = array (LTC_AUTOPOPULATE_NO => get_string('no'),
                                          LTC_AUTOPOPULATE_SECTION => get_string('importfromsection','learningtimecheck'),
                                          LTC_AUTOPOPULATE_COURSE => get_string('importfromcourse', 'learningtimecheck'));
        } else {
            $autopopulateoptions = array (LTC_AUTOPOPULATE_NO => get_string('no'),
                                          LTC_AUTOPOPULATE_CURRENT_PAGE => get_string('importfrompage','learningtimecheck'),
                                          LTC_AUTOPOPULATE_CURRENT_PAGE_AND_SUBS => get_string('importfrompageandsubs','learningtimecheck'),
                                          LTC_AUTOPOPULATE_CURRENT_TOP_PAGE => get_string('importfromtoppage','learningtimecheck'),
                                          LTC_AUTOPOPULATE_COURSE => get_string('importfromcourse', 'learningtimecheck'));
        }

        $mform->addElement('select', 'autopopulate', get_string('autopopulate', 'learningtimecheck'), $autopopulateoptions);
        $mform->setDefault('autopopulate', 0 + @$config->initialautocapture);
        $mform->addHelpButton('autopopulate', 'autopopulate', 'learningtimecheck');

        $mform->addElement('selectyesno', 'lockteachermarks', get_string('lockteachermarks', 'learningtimecheck'));
        $mform->setDefault('lockteachermarks', 0);
        $mform->setAdvanced('lockteachermarks');
        $mform->addHelpButton('lockteachermarks', 'lockteachermarks', 'learningtimecheck');

        $mform->addElement('select', 'usetimecounterpart', get_string('usetimecounterpart', 'learningtimecheck'), $ynoptions);
        $mform->setDefault('usetimecounterpart', 0 + @$config->initialcredittimeon);

        $mform->addElement('date_time_selector', 'lastcompiledtime', get_string('lastcompiledtime', 'learningtimecheck'));
        $mform->setAdvanced('lastcompiledtime');

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

    }

    public function add_completion_rules() {
        $mform = $this->_form;

        $group = array();
        $label = get_string('completionpercent', 'learningtimecheck');
        $group[] = $mform->createElement('advcheckbox', 'cplpercentenabled', '', $label);
        $group[] = $mform->createElement('text', 'completionpercent', '', array('size' => 3));
        $mform->setType('completionpercent', PARAM_INT);
        $label = get_string('completionpercentgroup', 'learningtimecheck');
        $mform->addGroup($group, 'completionpercentgroup', $label, array(' '), false);
        $mform->disabledIf('completionpercent', 'cplpercentenabled', 'notchecked');
        $mform->setDefault('completionpercent', 100);

        $group = array();
        $label = get_string('completionmandatory', 'learningtimecheck');
        $group[] = $mform->createElement('advcheckbox', 'cplmandatoryenabled', '', $label);
        $group[] = $mform->createElement('text', 'completionmandatory', '', array('size' => 3));
        $mform->setType('completionmandatory', PARAM_INT);
        $label = get_string('completionmandatorygroup', 'learningtimecheck');
        $mform->addGroup($group, 'completionmandatorygroup', $label, array(' '), false);
        $mform->disabledIf('completionmandatory', 'cplmandatoryenabled', 'notchecked');
        $mform->setDefault('completionmandatory', 100);

        return array('completionpercentgroup', 'completionmandatorygroup');
    }

    public function completion_rule_enabled($data) {
        $cond = !empty($data['cplpercentenabled']) && $data['completionpercent'] != 0;
        $condmandatory = !empty($data['cplmandatoryenabled']) && $data['completionmandatory'] != 0;
        return ($cond || $condmandatory);
    }

}
