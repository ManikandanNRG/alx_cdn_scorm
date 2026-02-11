<?php
defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname'   => '\core\event\course_module_created',
        'callback'    => '\local_ALX_cdn_scorm\observer::course_module_created',
    ),
    array(
        'eventname'   => '\core\event\course_module_updated',
        'callback'    => '\local_ALX_cdn_scorm\observer::course_module_updated',
    ),
    array(
        'eventname'   => '\core\event\course_module_deleted',
        'callback'    => '\local_ALX_cdn_scorm\observer::course_module_deleted',
    ),
);
