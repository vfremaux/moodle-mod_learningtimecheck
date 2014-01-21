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

$module->version  = 2014011800;  // The current module version (Date: YYYYMMDDXX)
$module->cron     = 60;          // Period for cron to check this module (secs)
$module->maturity = MATURITY_STABLE;
$module->release  = '2.4 (Build: 2014011800)';
$module->requires = 2012062501;
$module->component = 'mod_learningtimecheck';