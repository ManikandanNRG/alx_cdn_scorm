<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ALX_cdn_scorm';
$plugin->version   = 2026021100; // YYYYMMDDXX
$plugin->requires  = 2020061500; // Requires Moodle 3.9+ (adjust as needed)
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = 'v0.1.0-alpha';
$plugin->dependencies = [
    'mod_scorm' => 2020061500, // Depends on SCORM module
];
