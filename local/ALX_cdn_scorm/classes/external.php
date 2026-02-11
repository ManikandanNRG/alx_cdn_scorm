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
        
        foreach ($params['tracks'] as $track) {
            $element = $track['element'];
            $value = $track['value'];
            
            try {
                scorm_insert_track($USER->id, $scorm->id, $params['scoid'], $params['attempt'], $element, $value);
            } catch (\Exception $e) {
                debugging("local_alx_cdn_scorm: Failed to save track $element: " . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return array('success' => true);
    }

    public static function save_tracks_returns() {
        return new \external_single_structure(
            array(
                'success' => new \external_value(PARAM_BOOL, 'True if successful')
            )
        );
    }
}
