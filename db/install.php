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
 * Post installation and migration code.
 *
 * @package    search_postgresfulltext
 * @copyright  2007 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create Postgres specific column types and indexes
 *
 * @return void
 */
function xmldb_search_postgresfulltext_install() {
    global $DB;

    $DB->execute("ALTER TABLE {search_postgresfulltext}
                  ADD COLUMN fulltextindex tsvector");

    $DB->execute("CREATE INDEX {search_postgresfulltext_index}
                  ON {search_postgresfulltext} USING GIN (fulltextindex)");

    $DB->execute("ALTER TABLE {search_postgresfulltext_file}
                  ADD COLUMN fulltextindex tsvector");

    $DB->execute("CREATE INDEX {search_postgresfulltext_file_index}
                 ON {search_postgresfulltext_file} USING GIN (fulltextindex)");

}
