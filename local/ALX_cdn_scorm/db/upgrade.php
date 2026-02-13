<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the local_alx_cdn_scorm plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_alx_cdn_scorm_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026021302) {

        // Define field playerheight to be added to local_alx_cdn_scorm.
        $table = new xmldb_table('local_alx_cdn_scorm');
        $field = new xmldb_field('playerheight', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '680px', 'enabled');

        // Conditionally launch add field playerheight.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Local savepoint reached.
        upgrade_plugin_savepoint(true, 2026021302, 'local', 'alx_cdn_scorm');
    }

    return true;
}
