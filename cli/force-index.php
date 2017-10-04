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
 * Replace the existing search engine without downtime
 *
 * @package    search_postgresfulltext
 * @copyright  2017 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');      // CLI only functions.

list($options, $unrecognized) = cli_get_params(array('help' => false, 'switch' => false));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "Index site using postgres even if another engine is currently used.

Options:
-h, --help            Print out this help
-s, --switch          Become the active search engine after re-indexing

Example:
\$ sudo -u www-data /usr/bin/php search/engine/postgresfulltext/cli/force-index.php --switch
";

    echo $help;
    die;
}

$CFG->searchengine = 'postgresfulltext';

if (!$searchengine = \core_search\manager::search_engine_instance()) {
    cli_error(get_string('engineserverstatus', 'search'));
}
if (!$searchengine->is_installed()) {
    cli_error('enginenotinstalled', 'search', $CFG->searchengine);
}
$serverstatus = $searchengine->is_server_ready();
if ($serverstatus !== true) {
    cli_error($serverstatus);
}

$globalsearch = \core_search\manager::instance();


echo "Running full index of site\n";
echo "==========================\n";
$globalsearch->index(true);

if (!empty($options['switch'])) {
    set_config('searchengine', 'postgresfulltext');
    echo "Setting Postgres Full-Text search to the search engine.\n";
}