<?php
namespace local_ALX_cdn_scorm;

defined('MOODLE_INTERNAL') || die();

class url_validator {
    /**
     * Validate the CDN URL.
     *
     * @param string $url
     * @return bool|string True if valid, error string otherwise.
     */
    public static function validate($url) {
        if (empty($url)) {
            return get_string('err_url_empty', 'local_ALX_cdn_scorm');
        }

        // Must be HTTPS
        if (strpos($url, 'https://') !== 0) {
            return get_string('err_url_https_only', 'local_ALX_cdn_scorm');
        }

        // Must end in imsmanifest.xml (standard SCORM) or be a direct launch link?
        // The spec implies we are replacing the package file, so pointing to imsmanifest.xml is standard.
        // However, some CDNs might serve the player directly.
        // We'll stick to the spec: "Enter external SCORM URL" -> usually imsmanifest.xml
        
        if (strpos($url, 'imsmanifest.xml') === false) {
             return get_string('err_url_manifest', 'local_ALX_cdn_scorm');
        }

        // Check reachability (optional, might slow down form save).
        // We can do a quick HEAD request.
        
        return true;
    }
}
