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

$plugin->version  = 2025011400;  // The current module version (Date: YYYYMMDDXX).
$plugin->requires = 2022112801;
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '4.5.0 (Build: 2024031802)';
$plugin->component = 'mod_learningtimecheck';
$plugin->supported = [401, 405];
$plugin->dependencies = array('report_learningtimecheck' => '2015042302', 'local_vflibs' => '2015101800');

// Non Moodle attributes.
$plugin->codeincrement = '4.5.0013';
$plugin->privacy = "dualrelease";