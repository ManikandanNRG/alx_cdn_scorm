<?php
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_alx_cdn_scorm_get_user_tracks' => array(
        'classname'   => 'local_alx_cdn_scorm\external',
        'methodname'  => 'get_user_tracks',
        'classpath'   => 'local/alx_cdn_scorm/classes/external.php',
        'description' => 'Retrieves SCORM tracking data for the CDN bridge',
        'type'        => 'read',
        'ajax'        => true,
    ),
    'local_alx_cdn_scorm_save_tracks' => array(
        'classname'   => 'local_alx_cdn_scorm\external',
        'methodname'  => 'save_tracks',
        'classpath'   => 'local/alx_cdn_scorm/classes/external.php',
        'description' => 'Saves SCORM tracking data from the CDN bridge',
        'type'        => 'write',
        'ajax'        => true,
    ),
);
