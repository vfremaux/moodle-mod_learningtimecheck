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
 * @author      Valery Fremaux (valery.fremaux@gmail.com)
 * @copyright   2015 onwards Valery Fremaux (valery.fremaux@gmail.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

<<<<<<< HEAD
$plugin->version  = 2023063000;  // The current module version (Date: YYYYMMDDXX).
$plugin->requires = 2022112801;
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '4.1.0 (Build: 2023063000)';
=======
$plugin->version  = 2019040600;  // The current module version (Date: YYYYMMDDXX).
$plugin->requires = 2019111200;
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '3.8.0 (Build: 2019040600)';
>>>>>>> b46bc5a448b7a6dc2f38e2b4e62ae9b2933a11e3
$plugin->component = 'mod_learningtimecheck';
$plugin->supported = [401, 402];
$plugin->dependencies = array('report_learningtimecheck' => '2015042302', 'local_vflibs' => '2015101800');

// Non Moodle attributes.
<<<<<<< HEAD
$plugin->codeincrement = '4.1.0012';
=======
$plugin->codeincrement = '3.8.0009';
>>>>>>> b46bc5a448b7a6dc2f38e2b4e62ae9b2933a11e3
$plugin->privacy = "dualrelease";