<?php
namespace local_ALX_cdn_scorm;

defined('MOODLE_INTERNAL') || die();

class player_redirector {
    /**
     * Check if the current SCORM activity is a CDN one and if we should redirect.
     * 
     * @param stdClass $scorm The scorm object
     * @param stdClass $cm The course module object
     * @return string|null The redirect URL or null if not applicable.
     */
    public static function get_redirect_url($scorm, $cm) {
        // We need to check if our custom field 'local_ALX_cdn_enable' is set.
        // However, standard Moodle doesn't automatically add extra fields to the $scorm object 
        // unless we added them to the 'mdl_scorm' table (which we didn't, we just added them to the form).
        // If we want to persist them, we should have added them to the 'mdl_scorm' table via upgrade.php, 
        // OR stored them in 'mdl_config_plugins' if global, 
        // OR stored them in 'mdl_course_modules' config (using $cm->id).
        
        // WAIT: In the form hook, we added elements to the form. 
        // If we didn't add the columns to the database, the data is LOST upon save!
        // We strictly need to modify the database or use a separate table.
        // Given the constraints and "local plugin" nature, using `mdl_config_plugins` is for global settings.
        // For instance settings, we should use `mdl_course_modules` "config" field (if available and supported by mod_scorm)
        // OR create a local table `mdl_local_ALX_cdn_scorm` linking scormid -> cdn_url.
        
        // CORRECTION: Phase 1 missed the database schema update.
        // I need to add an `install.xml` and `upgrade.php` to create a table valid for this plugin.
        // Table: mdl_local_alx_cdn_scorm (id, scormid, cdnurl, enabled, timecreated, timemodified)
        
        // For now, let's assume we have this table. I will add the db/install.xml task.
        
        global $DB, $CFG;
        
        $record = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $scorm->id));
        if ($record && $record->enabled) {
            return $CFG->wwwroot . '/local/ALX_cdn_scorm/player.php?scormid=' . $scorm->id . '&cmid=' . $cm->id;
        }
        
        return null;
    }
}
