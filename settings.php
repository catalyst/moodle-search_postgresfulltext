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

if ($ADMIN->fulltree) {

    if (!during_initial_install()) {


        $settings->add(new admin_setting_heading('search_postgresfulltext_fileindexing',
                new lang_string('fileindexsettings', 'search_postgresfulltext'), ''));

        $settings->add(new admin_setting_configcheckbox('search_postgresfulltext/fileindexing',
                new lang_string('fileindexing', 'search_postgresfulltext'),
                new lang_string('fileindexing_help', 'search_postgresfulltext'), 0));

        $settings->add(new admin_setting_configtext('search_postgresfulltext/tikaurl',
                new lang_string('tikaurl', 'search_postgresfulltext'),
                new lang_string('tikaurl_help', 'search_postgresfulltext'), '', PARAM_URL));

        $settings->add(new admin_setting_configtext('search_postgresfulltext/maxindexfilekb',
                new lang_string('maxindexfilekb', 'search_postgresfulltext'),
                new lang_string('maxindexfilekb_help', 'search_postgresfulltext'), '10000', PARAM_INT));
    }
}
