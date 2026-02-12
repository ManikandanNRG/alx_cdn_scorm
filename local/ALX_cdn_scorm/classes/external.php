<?php
namespace local_alx_cdn_scorm;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

class external extends \external_api {

    public static function get_user_tracks_parameters() {
        return new \external_function_parameters(
            array(
                'scormid' => new \external_value(PARAM_INT, 'SCORM activity ID'),
                'scoid'   => new \external_value(PARAM_INT, 'SCO ID'),
                'attempt' => new \external_value(PARAM_INT, 'Attempt number')
            )
        );
    }

    public static function get_user_tracks($scormid, $scoid, $attempt) {
        global $USER, $DB;

        $params = self::validate_parameters(self::get_user_tracks_parameters(), array(
            'scormid' => $scormid,
            'scoid'   => $scoid,
            'attempt' => $attempt
        ));

        $scorm = $DB->get_record('scorm', array('id' => $params['scormid']), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('scorm', $scorm->id);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        $tracks = scorm_get_tracks($params['scoid'], $USER->id, $params['attempt']);

        $result_tracks = array();
        if ($tracks) {
            foreach ($tracks as $element => $value) {
                $result_tracks[] = array(
                    'element' => $element,
                    'value'   => (string)$value
                );
            }
        }

        return array('tracks' => $result_tracks);
    }

    public static function get_user_tracks_returns() {
        return new \external_single_structure(
            array(
                'tracks' => new \external_multiple_structure(
                    new \external_single_structure(
                        array(
                            'element' => new \external_value(PARAM_RAW, 'CMI Element'),
                            'value'   => new \external_value(PARAM_RAW, 'CMI Value')
                        )
                    )
                )
            )
        );
    }

    public static function save_tracks_parameters() {
        return new \external_function_parameters(
            array(
                'scormid' => new \external_value(PARAM_INT, 'SCORM activity ID'),
                'scoid'   => new \external_value(PARAM_INT, 'SCO ID'),
                'attempt' => new \external_value(PARAM_INT, 'Attempt number'),
                'tracks'  => new \external_multiple_structure(
                    new \external_single_structure(
                        array(
                            'element' => new \external_value(PARAM_RAW, 'CMI Element'),
                            'value'   => new \external_value(PARAM_RAW, 'CMI Value')
                        )
                    )
                )
            )
        );
    }

    public static function save_tracks($scormid, $scoid, $attempt, $tracks) {
        global $USER, $DB;

        $params = self::validate_parameters(self::save_tracks_parameters(), array(
            'scormid' => $scormid,
            'scoid'   => $scoid,
            'attempt' => $attempt,
            'tracks'  => $tracks
        ));

        $scorm = $DB->get_record('scorm', array('id' => $params['scormid']), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('scorm', $scorm->id);
        $context = \context_module::instance($cm->id);
        
        self::validate_context($context);
        
        
        $saved_count = 0;
        $failed_count = 0;
        $errors = [];
        
        foreach ($params['tracks'] as $track) {
            $element = $track['element'];
            $value = $track['value'];
            
            // ✅ CRITICAL FIX: Prevent completion status downgrade
            // Once a SCORM is completed/passed, it should never go back to incomplete
            // This matches native Moodle SCORM behavior
            if ($element == 'cmi.core.lesson_status' || $element == 'cmi.completion_status') {
                // Check if there's an existing status
                $existing = $DB->get_record('scorm_scoes_track', array(
                    'userid' => $USER->id,
                    'scormid' => $scorm->id,
                    'scoid' => $params['scoid'],
                    'attempt' => $params['attempt'],
                    'element' => $element
                ));
                
                if ($existing) {
                    $existing_status = $existing->value;
                    
                    // Define completion states (in order of precedence)
                    $completed_states = array('completed', 'passed');
                    $incomplete_states = array('incomplete', 'not attempted', 'unknown', 'browsed');
                    
                    // If existing status is completed/passed, don't allow downgrade to incomplete
                    if (in_array($existing_status, $completed_states) && in_array($value, $incomplete_states)) {
                        debugging("local_alx_cdn_scorm: BLOCKED status downgrade from '$existing_status' to '$value' - keeping existing status", DEBUG_DEVELOPER);
                        // Skip this track - don't save the downgrade
                        continue;
                    }
                    
                    debugging("local_alx_cdn_scorm: Status change allowed: '$existing_status' -> '$value'", DEBUG_DEVELOPER);
                }
            }
            
            // Also check success_status (SCORM 2004)
            if ($element == 'cmi.success_status') {
                $existing = $DB->get_record('scorm_scoes_track', array(
                    'userid' => $USER->id,
                    'scormid' => $scorm->id,
                    'scoid' => $params['scoid'],
                    'attempt' => $params['attempt'],
                    'element' => $element
                ));
                
                if ($existing && $existing->value == 'passed' && $value == 'failed') {
                    debugging("local_alx_cdn_scorm: BLOCKED success_status downgrade from 'passed' to 'failed'", DEBUG_DEVELOPER);
                    continue;
                }
            }
            
            try {
                scorm_insert_track($USER->id, $scorm->id, $params['scoid'], $params['attempt'], $element, $value);
                $saved_count++;
                
                // Verify the data was actually inserted
                $check = $DB->get_record('scorm_scoes_track', array(
                    'userid' => $USER->id,
                    'scormid' => $scorm->id,
                    'scoid' => $params['scoid'],
                    'attempt' => $params['attempt'],
                    'element' => $element
                ));
                
                if ($check) {
                    debugging("local_alx_cdn_scorm: VERIFIED - Saved $element = $value (id: {$check->id})", DEBUG_DEVELOPER);
                } else {
                    debugging("local_alx_cdn_scorm: WARNING - Insert succeeded but record not found for $element", DEBUG_DEVELOPER);
                    $errors[] = "Insert succeeded but record not found for $element";
                }
            } catch (\Exception $e) {
                $failed_count++;
                $error_msg = "Failed to save track $element: " . $e->getMessage();
                $errors[] = $error_msg;
                debugging("local_alx_cdn_scorm: " . $error_msg, DEBUG_DEVELOPER);
            }
        }
        
        debugging("local_alx_cdn_scorm: Save complete - Saved: $saved_count, Failed: $failed_count", DEBUG_DEVELOPER);

        return array(
            'success' => true,
            'saved' => $saved_count,
            'failed' => $failed_count,
            'errors' => $errors
        );
    }

    public static function save_tracks_returns() {
        return new \external_single_structure(
            array(
                'success' => new \external_value(PARAM_BOOL, 'True if successful'),
                'saved' => new \external_value(PARAM_INT, 'Number of tracks saved'),
                'failed' => new \external_value(PARAM_INT, 'Number of tracks that failed'),
                'errors' => new \external_multiple_structure(
                    new \external_value(PARAM_TEXT, 'Error message'),
                    'Error messages', VALUE_OPTIONAL
                )
            )
        );
    }
}
