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
 * Functions used during install to check environment.
 *
 * @package    search_postgresfulltext
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Check if site using is using postgres.
 *
 * @param environment_results $result object to update, if relevant.
 * @return environment_results|null updated results or null.
 */
function search_postgresfulltext_check_database(environment_results $result) {
    global $CFG;
    if ($CFG->dbtype !== 'pgsql') {
        $result->setInfo('You must be using PostgreSQL.');
        $result->setStatus(false);
        return $result;
    }
    return null;
}
