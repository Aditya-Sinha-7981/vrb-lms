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
 * Admin page link for local_vrbcontent - no configurable settings yet.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Management page (Book/Quiz import wizards) - under Site administration > Reports,
// matching local_vrbcert's placement. Access is gated by the
// 'local/vrbcontent:import' capability on the page itself.
$ADMIN->add('reports', new admin_externalpage(
    'local_vrbcontent_import',
    get_string('adminpageheading', 'local_vrbcontent'),
    new moodle_url('/local/vrbcontent/index.php'),
    'local/vrbcontent:import'
));
