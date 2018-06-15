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
 * Posgres fulltext search engine settings.
 *
 * @package    search_postgresfulltext
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * XMLDB upgrade
 *
 * @param integer $oldversion Version of previous release
 * @return void
 */
function xmldb_search_postgresfulltext_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2018040400) {

        // Define field groupid to be added to search_postgresfulltext.
        $table = new xmldb_table('search_postgresfulltext');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'description2');

        // Conditionally launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('groupid', XMLDB_INDEX_NOTUNIQUE, array('groupid'));

        // Conditionally launch add index groupid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Postgresfulltext savepoint reached.
        upgrade_plugin_savepoint(true, 2018040400, 'search', 'postgresfulltext');
    }

    return true;

}