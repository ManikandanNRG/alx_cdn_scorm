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

// Get additional parameters (matching default player)
$scoid = optional_param('scoid', 0, PARAM_INT);
$mode = optional_param('mode', 'normal', PARAM_ALPHA);
$newattempt = optional_param('newattempt', 'off', PARAM_ALPHA);
$currentorg = optional_param('currentorg', '', PARAM_RAW);
$displaymode = optional_param('display', '', PARAM_ALPHA);

// Validate currentorg against database
if (!empty($currentorg)) {
    if (!$DB->record_exists('scorm_scoes', array('scorm' => $scorm->id, 'identifier' => $currentorg))) {
        $currentorg = '';
    }
}

// Get last attempt
$attempt = scorm_get_last_attempt($scorm->id, $USER->id);

// Check mode and validate attempt (this handles resume/restart logic)
scorm_check_mode($scorm, $newattempt, $attempt, $USER->id, $mode);

// Check if activity is hidden
if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $context)) {
    echo $OUTPUT->header();
    notice(get_string("activityiscurrentlyhidden"));
    echo $OUTPUT->footer();
    die;
}

// Check SCORM availability (dates, attempts, etc.)
require_once($CFG->libdir . '/completionlib.php');
list($available, $warnings) = scorm_get_availability_status($scorm);
if (!$available) {
    $reason = current(array_keys($warnings));
    echo $OUTPUT->header();
    echo $OUTPUT->box(get_string($reason, "scorm", $warnings[$reason]), "generalbox boxaligncenter");
    echo $OUTPUT->footer();
    die;
}

// Validate and get SCO
if (!empty($scoid)) {
    $scoid = scorm_check_launchable_sco($scorm, $scoid);
} else {
    // Get first launchable SCO
    $scoes = $DB->get_records_select('scorm_scoes', 'scorm = ? AND launch <> ?', array($scorm->id, ''), 'sortorder', 'id', 0, 1);
    if ($scoes) {
        $sco = reset($scoes);
        $scoid = $sco->id;
    }
}

// Load SCORM version library
$scorm->version = strtolower(clean_param($scorm->version, PARAM_SAFEDIR));
if (!file_exists($CFG->dirroot.'/mod/scorm/datamodels/'.$scorm->version.'lib.php')) {
    $scorm->version = 'scorm_12';
}
require_once($CFG->dirroot.'/mod/scorm/datamodels/'.$scorm->version.'lib.php');

// Get TOC and validate attempt limits
$result = scorm_get_toc($USER, $scorm, $cm->id, TOCJSLINK, $currentorg, $scoid, $mode, $attempt, true, true);
$sco = $result->sco;

// Check if max attempts exceeded
if ($scorm->lastattemptlock == 1 && $result->attemptleft == 0) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('exceededmaxattempts', 'scorm'));
    echo $OUTPUT->footer();
    exit;
}

// Set up session variables (required by some SCORM packages)
$SESSION->scorm = new stdClass();
$SESSION->scorm->scoid = $sco->id;
$SESSION->scorm->scormstatus = 'Not Initialized';
$SESSION->scorm->scormmode = $mode;
$SESSION->scorm->attempt = $attempt;

// Mark module as viewed for completion tracking
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$debugmode = get_config('local_alx_cdn_scorm', 'debugmode');

// Get course context for page title
$coursecontext = context_course::instance($course->id);

// Set page layout based on display mode (matches default SCORM player)
if ($displaymode == 'popup') {
    $PAGE->set_pagelayout('embedded');
} else {
    $shortname = format_string($course->shortname, true, array('context' => $coursecontext));
    $pagetitle = strip_tags("$shortname: ".format_string($scorm->name));
    $PAGE->set_title($pagetitle);
    $PAGE->set_heading($course->fullname);
}

$PAGE->set_url('/local/alx_cdn_scorm/player.php', array('scormid' => $scormid, 'cmid' => $cmid));
$PAGE->set_context($context);

// Generate exit URL (matches default SCORM player logic)
$exiturl = "";
if (empty($scorm->popup) || $displaymode == 'popup') {
    if ($course->format == 'singleactivity' && $scorm->skipview == SCORM_SKIPVIEW_ALWAYS
        && !has_capability('mod/scorm:viewreport', $context)) {
        // Redirect students back to site home to avoid redirect loop
        $exiturl = $CFG->wwwroot;
    } else {
        // Redirect back to the correct section if one section per page is being used
        $exiturl = course_get_url($course, $cm->sectionnum)->out();
    }
}

echo $OUTPUT->header();

// Exit button should ONLY be displayed when NOT in popup mode (matches default player line 211)
if ($displaymode !== 'popup') {
    $renderer = $PAGE->get_renderer('mod_scorm');
    echo $renderer->generate_exitbar($exiturl);
    
    // ✅ Display mode text (Review mode, Browse mode, Normal mode) - matches default player line 224
    if ($mode !== 'normal') {
        echo html_writer::div(get_string("{$mode}mode", 'scorm'), 'scorm-left h3', ['id' => 'scormmode']);
    }
}

// Check if we need to open a popup window (matches default player lines 247-263)
if ($result->prerequisites) {
    if ($scorm->popup != 0 && $displaymode !== 'popup') {
        // This is "New window" mode - open JavaScript popup
        // Clean the name for the window as IE is fussy
        $name = preg_replace("/[^A-Za-z0-9]/", "", $scorm->name);
        if (!$name) {
            $name = 'DefaultPlayerWindow';
        }
        $name = 'scorm_'.$name;
        
        echo html_writer::script('', $CFG->wwwroot.'/mod/scorm/player.js');
        $url = new moodle_url($PAGE->url, array('scoid' => $sco->id, 'display' => 'popup', 'mode' => $mode));
        echo html_writer::script(
            js_writer::function_call('scorm_openpopup', Array($url->out(false),
                                                       $name, $scorm->options,
                                                       $scorm->width, $scorm->height)));
        
        // Show message that popup was launched
        $linkcourse = html_writer::link($CFG->wwwroot.'/course/view.php?id='.
                        $scorm->course, get_string('finishscormlinkname', 'scorm'));
        echo $OUTPUT->box(get_string('finishscorm', 'scorm', $linkcourse), 'generalbox', 'altfinishlink');
    } else {
        // This is "Current window" or "Popup" mode - embed the SCORM
        $proxy_url = $CFG->wwwroot . '/local/alx_cdn_scorm/proxy.php?scormid=' . $scormid . 
                     '&cmid=' . $cmid . '&scoid=' . $sco->id . '&attempt=' . $attempt . '&url=' . urlencode($scorm_url);
        
        $bridge_params = [
            'scormid' => $scorm->id,
            'scoid' => $sco->id,
            'cmid' => $cm->id,
            'attempt' => $attempt,
            'mode' => $mode,
            'debug' => (bool)$debugmode,
            'wwwroot' => $CFG->wwwroot,
            'sesskey' => sesskey()
        ];
        
        // Load saved tracking data (for resume functionality)
        $userdata = scorm_get_tracks($sco->id, $USER->id, $attempt);
        $scorm_data_json = '{}';
        if ($userdata) {
            // Convert to JSON for JavaScript
            $scorm_data_json = json_encode($userdata, JSON_HEX_APOS | JSON_HEX_QUOT);
        }
        
        $template_data = [
            'scorm_name' => $scorm->name,
            'iframe_src' => $proxy_url,
            'width' => '100%',
            'height' => '800px',
            'show_exit_button' => false,  // Exit button already shown above
            'scorm_data_json' => $scorm_data_json,  // Pre-loaded tracking data
            // Bridge parameters for inline JavaScript
            'bridge_scormid' => $bridge_params['scormid'],
            'bridge_scoid' => $bridge_params['scoid'],
            'bridge_cmid' => $bridge_params['cmid'],
            'bridge_attempt' => $bridge_params['attempt'],
            'bridge_debug' => $bridge_params['debug'] ? 'true' : 'false',
            'bridge_wwwroot' => $bridge_params['wwwroot'],
            'bridge_sesskey' => $bridge_params['sesskey'],
            // Additional parameters for missing SCORM features
            'mode' => $mode,
            'currentorg' => $currentorg,
            'scormauto' => $scorm->auto,
            'masteryoverride' => $scorm->masteryoverride,
            'hidetoc' => $scorm->hidetoc
        ];
        
        echo $OUTPUT->render_from_template('local_alx_cdn_scorm/player_embed', $template_data);
    }
} else {
    echo $OUTPUT->box(get_string('noprerequisites', 'scorm'));
}

echo $OUTPUT->footer();

// Set the start time of this SCO (matches default player line 302)
scorm_insert_track($USER->id, $scorm->id, $scoid, $attempt, 'x.start.time', time());
