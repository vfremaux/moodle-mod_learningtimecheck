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
 * Version details.
 *
 * @package     mod_learningtimecheck
 * @category    mod
 * @author      Davo Smith <moodle@davosmith.co.uk>
 * @author      Valery Fremaux (valery.fremaux@gmail.com)
 * @copyright   2015 onwards Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2017081001;  // The current module version (Date: YYYYMMDDXX).
<<<<<<< HEAD
<<<<<<< HEAD
$plugin->requires = 2015050500;
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '3.3.0 (Build: 2017081001)';
=======
$plugin->requires = 2017110800;
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '3.4.0 (Build: 2017081001)';
>>>>>>> MOODLE_34_STABLE
=======
$plugin->requires = 2018042700;
$plugin->maturity = MATURITY_BETA;
$plugin->release  = '3.5.0 (Build: 2017081001)';
>>>>>>> MOODLE_35_STABLE
$plugin->component = 'mod_learningtimecheck';
$plugin->dependencies = array('report_learningtimecheck' => '2015042302', 'local_vflibs' => '2015101800');

// Non Moodle attributes.
<<<<<<< HEAD
<<<<<<< HEAD
<<<<<<< HEAD
$plugin->codeincrement = '3.3.0007';
=======
$plugin->codeincrement = '3.4.0007';
>>>>>>> MOODLE_34_STABLE
=======
$plugin->codeincrement = '3.4.0008';
$plugin->privacy = "dualrelease";
>>>>>>> MOODLE_34_STABLE
=======
$plugin->codeincrement = '3.5.0008';
$plugin->privacy = "dualrelease";
>>>>>>> MOODLE_35_STABLE
