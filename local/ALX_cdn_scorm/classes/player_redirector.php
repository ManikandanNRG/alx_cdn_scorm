<?php
namespace local_alx_cdn_scorm;

defined('MOODLE_INTERNAL') || die();

class player_redirector {
    /**
     * Get redirect URL if CDN is enabled.
     *
     * @param stdClass $scorm
     * @param stdClass $cm
     * @return string|null
     */
    public static function get_redirect_url($scorm, $cm) {
        global $DB, $CFG;
        
        $record = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $scorm->id));
        if ($record && $record->enabled) {
            return $CFG->wwwroot . '/local/alx_cdn_scorm/player.php?scormid=' . $scorm->id . '&cmid=' . $cm->id;
        }
        
        return null; // No redirect
    }
}
