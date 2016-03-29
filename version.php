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
 * Code fragment to define the version of learningtimecheck
 * This fragment is called by moodle_needs_upgrading() and /admin/index.php
 *
 * @author  Davo Smith <moodle@davosmith.co.uk>
 * @package mod/learningtimecheck
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2015100800;  // The current module version (Date: YYYYMMDDXX)
$plugin->requires = 2015111000;
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '3.0.0 (Build: 2015100800)';
$plugin->component = 'mod_learningtimecheck';
$plugin->dependencies = array('report_learningtimecheck' => '2015042302', 'local_vflibs' => '2015101800');