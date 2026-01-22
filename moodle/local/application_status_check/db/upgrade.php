<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_application_status_check_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Migrate table name from old tool plugin to local plugin.
    if ($oldversion < 2026012200) {
        $oldtable = new xmldb_table('tool_applicationcheck_apps');
        $newtablename = 'local_application_status_check_apps';

        // Rename old table if it exists and new one does not.
        if ($dbman->table_exists($oldtable) && !$dbman->table_exists(new xmldb_table($newtablename))) {
            $dbman->rename_table($oldtable, $newtablename);
        }

        // Upgrade savepoint.
        upgrade_plugin_savepoint(true, 2026012200, 'local', 'application_status_check');
    }

    return true;
}
