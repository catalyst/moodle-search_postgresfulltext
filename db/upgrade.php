<?php
// This file keeps track of upgrades to
// the assignment module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

defined('MOODLE_INTERNAL') || die();

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

}