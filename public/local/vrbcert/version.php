<?php
// This file is part of Moodle - http://moodle.org/
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
 * Plugin version and requirements.
 *
 * @package    local_vrbcert
 * @copyright  2026 VRB LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_vrbcert';
$plugin->version = 2026101800;
$plugin->requires = 2025100600;
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.3.0';
$plugin->dependencies = [
    // Top-performer certificates consume the leaderboard as-is; completion certificates use its course-progress API.
    'local_vrblms' => 2026101800,
];
