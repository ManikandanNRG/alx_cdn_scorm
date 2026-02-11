<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_alx_cdn_scorm', get_string('pluginname', 'local_alx_cdn_scorm'));

    $ADMIN->add('localplugins', $settings);

    // Whitelist setting
    $settings->add(new admin_setting_configtext(
        'local_alx_cdn_scorm/whitelist',
        get_string('whitelist', 'local_alx_cdn_scorm'),
        get_string('whitelist_desc', 'local_alx_cdn_scorm'),
        '',
        PARAM_RAW
    ));

    // Debug mode
    $settings->add(new admin_setting_configcheckbox(
        'local_alx_cdn_scorm/debugmode',
        get_string('debugmode', 'local_alx_cdn_scorm'),
        get_string('debugmode_desc', 'local_alx_cdn_scorm'),
        0
    ));
}
