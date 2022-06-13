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

/**
 * Check if site using is using postgres.
 *
 * @param environment_results $result object to update, if relevant.
 * @return environment_results|null updated results or null.
 */
function search_postgresfulltext_check_database(environment_results $result) {
    global $CFG, $DB;

    if ($CFG->dbtype !== 'pgsql') {
        $result->setInfo('You must be using PostgreSQL.');
        $result->setStatus(false);
        return $result;
    }

    // To use websearch function, we need PG 11 or higher.
    $neededversion = "11";

    $currentvendor = $DB->get_dbvendor();
    $dbinfo = $DB->get_server_info();
    $currentversion = normalize_version($dbinfo['version']);

    if (version_compare($currentversion, $neededversion, '>=')) {
        $result->setStatus(true);
    } else {
        $result->setStatus(false);
    }
    $result->setCurrentVersion($currentversion);
    $result->setNeededVersion($neededversion);
    $result->setInfo($currentvendor . ' (' . $dbinfo['description'] . ')');
    return $result;
}
