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
 * Capability definitions for the learningtimecheck module
 */

$capabilities = array(
    // Check if user is able to add a learningtimecheck module (M2.3+ only)
    'mod/learningtimecheck:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/course:manageactivities'
    ),

    // Ability to view and update own learningtimecheck.
    'mod/learningtimecheck:updateown' => array(
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'student' => CAP_ALLOW
        )
    ),

    // Ability to alter the marks on another person's learningtimecheck.
    'mod/learningtimecheck:updateother' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Ability to preview a learningtimecheck (to check it is OK). Usually denotes a teacher.
    'mod/learningtimecheck:preview' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Ability to check up on the progress of all users through
    // their learningtimechecks
    'mod/learningtimecheck:viewreports' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

      // Ability to watch the course calibration report
      // summating all learningtimechecks in the course
      'mod/learningtimecheck:viewcoursecalibrationreport' => array(
          'riskbitmask' => RISK_PERSONAL,
          'captype' => 'read',
          'contextlevel' => CONTEXT_COURSE,
          'archetypes' => array(
              'editingteacher' => CAP_ALLOW,
              'manager' => CAP_ALLOW
          )
      ),

      // Ability to watch the tutor board report
      // summating all learningtimechecks in the course
      'mod/learningtimecheck:viewtutorboard' => array(
          'riskbitmask' => RISK_PERSONAL,
          'captype' => 'read',
          'contextlevel' => CONTEXT_COURSE,
          'archetypes' => array(
              'editingteacher' => CAP_ALLOW,
              'manager' => CAP_ALLOW
          )
      ),
    // Ability to view reports related to their 'mentees' only
    'mod/learningtimecheck:viewmenteereports' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array()  // Not assigned by default
    ),

    // Ability to create and manage learningtimechecks
    'mod/learningtimecheck:edit' => array(
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Will receive emails when learningtimechecks complete (if learningtimecheck is set to do so)
    'mod/learningtimecheck:emailoncomplete' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW
        )
    ),

    // Can update teacher learningtimecheck marks even if locked
    'mod/learningtimecheck:updatelocked' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    )
);
