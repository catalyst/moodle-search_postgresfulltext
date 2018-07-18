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
 * Strings for component 'search_postgresfulltext'.
 *
 * @package   search_postgresfulltext
 * @copyright 2017 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Postgres Full Text';
$string['searchinfo'] = 'Search queries';
$string['searchinfo_help'] = 'Enter the search query.';
$string['fileindexsettings'] = 'File indexing settings';
$string['fileindexing'] = 'Enable file indexing';
$string['fileindexing_help'] = 'Enable indexing of files if an Apache Tika server is available.';
$string['maxindexfilekb'] = 'Maximum file size to index (kB)';
$string['maxindexfilekb_help'] = 'Files larger than this number of kilobytes will not be included in search indexing. If set to zero, files of any size will be indexed.';
$string['tikaurl'] = 'Tika URL';
$string['tikaurl_help'] = 'URL to the Apache Tika Server, including the port number e.g. http://localhost:9998';
$string['postgreswarning'] = 'It has been detected that you are not using PostgreSQL - this plugin does not work on other database types.';
$string['privacy:metadata:index'] = 'Indexed contents';
$string['privacy:metadata:index:docid'] = 'Document id (unique)';
$string['privacy:metadata:index:itemid'] = 'Item identifier (in search area scope)';
$string['privacy:metadata:index:title'] = 'Title';
$string['privacy:metadata:index:content'] = 'Contents';
$string['privacy:metadata:index:contextid'] = 'Document context id';
$string['privacy:metadata:index:areaid'] = 'Search area id';
$string['privacy:metadata:index:type'] = 'Document type';
$string['privacy:metadata:index:courseid'] = 'Course id';
$string['privacy:metadata:index:owneruserid'] = 'Document owner user id';
$string['privacy:metadata:index:modified'] = 'Last modification time';
$string['privacy:metadata:index:userid'] = 'Document user id';
$string['privacy:metadata:index:description1'] = 'Extra description field';
$string['privacy:metadata:index:description2'] = 'Extra description field';
$string['privacy:metadata:index:groupid'] = 'Group id';