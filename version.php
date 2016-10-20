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
 * @package    mod_learningtimecheck
 * @category   mod
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @copyright  2014 onwards Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2016090700;  // The current module version (Date: YYYYMMDDXX).
$plugin->requires = 2014110400;
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '2.8.0 (Build: 2016090700)';
$plugin->component = 'mod_learningtimecheck';
$plugin->dependencies = array('report_learningtimecheck' => '2015071300', 'local_vflibs' => '2015053000');

// Non Moodle attributes
$plugin->codeincrement = '2.8.0002';