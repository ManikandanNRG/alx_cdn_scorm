<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

$scormid = required_param('scormid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('scorm', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$scorm = $DB->get_record('scorm', array('id' => $scormid), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

$cdn_record = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $scorm->id));
if (!$cdn_record || !$cdn_record->enabled) {
    redirect($CFG->wwwroot . '/mod/scorm/view.php?id=' . $cm->id);
}

// Check whitelist
$whitelist = get_config('local_alx_cdn_scorm', 'whitelist');
if (!empty($whitelist)) {
    $allowed_domains = array_map('trim', explode(',', $whitelist));
    $parsed_url = parse_url($cdn_record->cdnurl);
    $domain = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    
    $allowed = false;
    foreach ($allowed_domains as $allowed_domain) {
        if ($domain === $allowed_domain || substr($domain, -strlen($allowed_domain) - 1) === '.' . $allowed_domain) {
            $allowed = true;
            break;
        }
    }
    
    if (!$allowed) {
        print_error('error_domain_not_allowed', 'local_alx_cdn_scorm', $CFG->wwwroot . '/course/view.php?id=' . $cm->course, $domain);
    }
}

// Logic to handle imsmanifest.xml
$scorm_url = $cdn_record->cdnurl;
if (stripos($scorm_url, 'imsmanifest.xml') !== false) {
    $base_url = str_ireplace('imsmanifest.xml', '', $scorm_url);
    $curl = new curl();
    $manifest_content = $curl->get($scorm_url);
    
    if ($curl->get_errno() == 0 && !empty($manifest_content)) {
        try {
            $xml = new SimpleXMLElement($manifest_content);
            $xml->registerXPathNamespace('s', 'http://www.imsproject.org/xsd/imscp_rootv1p1p2');
            $xml->registerXPathNamespace('adlcp', 'http://www.adlnet.org/xsd/adlcp_rootv1p2');
            
            $launch_href = '';
            foreach ($xml->resources->resource as $resource) {
                if (isset($resource['href']) && (string)$resource['href'] !== '') {
                    $launch_href = (string)$resource['href'];
                    break;
                }
            }
            if (!empty($launch_href)) {
                 $scorm_url = $base_url . $launch_href;
            } else {
                 debugging('local_alx_cdn_scorm: Could not find a launchable resource in manifest: ' . $scorm_url, DEBUG_DEVELOPER);
            }
        } catch (Exception $e) {
             debugging('local_alx_cdn_scorm: Failed to parse manifest: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    } else {
        debugging('local_alx_cdn_scorm: Failed to fetch manifest: ' . $scorm_url, DEBUG_DEVELOPER);
    }
}

// Identify SCO ID
$scoid = optional_param('scoid', 0, PARAM_INT);
if ($scoid === 0) {
    $scoes = $DB->get_records_select('scorm_scoes', 'scorm = ? AND launch <> ?', array($scorm->id, ''), 'sortorder', 'id', 0, 1);
    if ($scoes) {
        $sco = reset($scoes);
        $scoid = $sco->id;
    }
}

$debugmode = get_config('local_alx_cdn_scorm', 'debugmode');

$PAGE->set_url('/local/alx_cdn_scorm/player.php', array('scormid' => $scormid, 'cmid' => $cmid));
$PAGE->set_title($scorm->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse'); // Use 'incourse' layout to show header and navigation
$PAGE->set_context($context);

// Instead of AMD, we'll use inline JavaScript for the bridge
$bridge_params = [
    'scormid' => $scorm->id,
    'scoid' => $scoid,
    'cmid' => $cm->id,
    'attempt' => scorm_get_last_attempt($scorm->id, $USER->id),
    'debug' => (bool)$debugmode,
    'wwwroot' => $CFG->wwwroot,
    'sesskey' => sesskey()
];

echo $OUTPUT->header();

// Use proxy.php to fetch and inject API into SCORM content
$proxy_url = $CFG->wwwroot . '/local/alx_cdn_scorm/proxy.php?scormid=' . $scormid . 
             '&cmid=' . $cmid . '&scoid=' . $scoid . '&url=' . urlencode($scorm_url);

$template_data = [
    'scorm_name' => $scorm->name,
    'iframe_src' => $proxy_url,
    'width' => '100%',
    'height' => '800px',
    'exit_url' => $CFG->wwwroot . '/mod/scorm/view.php?id=' . $cm->id,
    // Bridge parameters for inline JavaScript
    'bridge_scormid' => $bridge_params['scormid'],
    'bridge_scoid' => $bridge_params['scoid'],
    'bridge_cmid' => $bridge_params['cmid'],
    'bridge_attempt' => $bridge_params['attempt'],
    'bridge_debug' => $bridge_params['debug'] ? 'true' : 'false',
    'bridge_wwwroot' => $bridge_params['wwwroot'],
    'bridge_sesskey' => $bridge_params['sesskey']
];

echo $OUTPUT->render_from_template('local_alx_cdn_scorm/player_embed', $template_data);
echo $OUTPUT->footer();
