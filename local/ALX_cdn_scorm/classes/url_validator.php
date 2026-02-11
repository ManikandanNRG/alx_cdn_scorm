<?php
namespace local_alx_cdn_scorm;

defined('MOODLE_INTERNAL') || die();

class url_validator {
    public static function validate($url) {
        if (empty($url)) {
            return get_string('err_url_empty', 'local_alx_cdn_scorm');
        }

        if (strpos($url, 'https://') !== 0) {
            return get_string('err_url_https_only', 'local_alx_cdn_scorm');
        }

        // Less strict validation since we handle imsmanifest.xml now
        return true;
    }
}
