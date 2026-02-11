<?php
namespace local_alx_cdn_scorm;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Handle course module creation.
     *
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        self::process_scorm_settings($event);
    }

    /**
     * Handle course module update.
     *
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event) {
        self::process_scorm_settings($event);
    }
    
    /**
     * Handle course module deletion.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        global $DB;
        $data = $event->get_data();
        if ($data['objecttable'] === 'scorm') {
             $DB->delete_records('local_alx_cdn_scorm', array('scormid' => $data['objectid']));
        }
    }

    /**
     * Common logic to save settings.
     *
     * @param \core\event\base $event
     */
    protected static function process_scorm_settings($event) {
        global $DB;
        
        $data = $event->get_data();
        
        // We only care about SCORM modules.
        if ($data['objecttable'] !== 'scorm') {
            return;
        }

        // Check if our fields are in the POST data
        // Note: Field names must match form_hook (lowercase)
        $cdn_enabled = optional_param('local_alx_cdn_enable', 0, PARAM_BOOL);
        $cdn_url = optional_param('local_alx_cdn_url', '', PARAM_RAW);
        
        // SCORM ID
        $scormid = $data['objectid'];
        
        // Check if record exists
        $existing = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $scormid));
        
        $record = new \stdClass();
        $record->scormid = $scormid;
        $record->cdnurl = $cdn_url;
        $record->enabled = $cdn_enabled ? 1 : 0;
        $record->timemodified = time();

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_alx_cdn_scorm', $record);
        } else {
            if ($cdn_enabled || !empty($cdn_url)) {
                $record->timecreated = time();
                $DB->insert_record('local_alx_cdn_scorm', $record);
            }
        }
    }
}
