<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ALX Cloud SCORM (CDN)';
$string['enablecdn'] = 'Enable CDN Delivery';
$string['enablecdn_desc'] = 'If enabled, this SCORM package will be served from the configured CDN URL.';
$string['cdnurl'] = 'CDN URL';
$string['cdnurl_help'] = 'Enter the full URL to the SCORM entry point (e.g., https://cdn.example.com/course/index.html) or imsmanifest.xml.';
$string['err_url_empty'] = 'The CDN URL cannot be empty.';
$string['err_url_https_only'] = 'The CDN URL must start with https:// for security.';
$string['err_url_manifest'] = 'The URL should usually point to imsmanifest.xml or the launch file.';
$string['settings'] = 'Settings';
$string['whitelist'] = 'Allowed CDN Domains';
$string['whitelist_desc'] = 'Comma-separated list of allowed domains for CDN URLs. Leave empty to allow any (not recommended). Example: cdn.example.com, s3.amazonaws.com';
$string['timeout'] = 'Request Timeout';
$string['timeout_desc'] = 'Timeout in seconds for external requests (if any).';
$string['debugmode'] = 'Debug Mode';
$string['debugmode_desc'] = 'Enable verbose logging in the browser console.';
$string['error_domain_not_allowed'] = 'The CDN domain ($a) is not allowed by the administrator.';
$string['player_settings'] = 'ALX Player Dimensions';
$string['player_height'] = 'Player Height';
$string['player_height_help'] = 'Select how the player height should be calculated. Auto will fit the browser window.';
$string['height_auto'] = 'Fit to Window (Auto)';
