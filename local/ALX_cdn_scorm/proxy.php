<?php
/**
 * SCORM Content Proxy
 * 
 * Fetches the SCORM HTML from CDN and injects the SCORM API script
 * into it, then serves it from Moodle's domain to bypass cross-origin restrictions.
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

$scormid = required_param('scormid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$url = required_param('url', PARAM_RAW);
$scoid = optional_param('scoid', 0, PARAM_INT);

// Security checks
$cm = get_coursemodule_from_id('scorm', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$scorm = $DB->get_record('scorm', array('id' => $scormid), '*', MUST_EXIST);

require_login($course, true, $cm);

$cdn_record = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $scorm->id));
if (!$cdn_record || !$cdn_record->enabled) {
    die('CDN not enabled for this SCORM');
}

// Validate URL is from the CDN record
if (strpos($url, $cdn_record->cdnurl) === false) {
    // The URL should be related to the CDN URL
    $cdn_base = dirname($cdn_record->cdnurl);
    if (strpos($url, $cdn_base) !== 0) {
        die('Invalid URL');
    }
}

// Fetch the content from CDN
$curl = new curl();
$content = $curl->get($url);

if ($curl->get_errno() != 0 || empty($content)) {
    die('Failed to fetch content from CDN: ' . $curl->error);
}

// Parse the URL to get the base URL for relative resources
// We manually handle the path to ensure forward slashes and correct directory stripping
$parsed_url = parse_url($url);
$path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
$path_parts = explode('/', $path);
array_pop($path_parts); // Remove filename
$base_path = implode('/', $path_parts) . '/';

// Ensure we have a valid scheme and host
$scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] : 'https';
$host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
$base_url = $scheme . '://' . $host . $base_path;

// Get attempt number from URL (validated by player.php)
$attempt = optional_param('attempt', 1, PARAM_INT);

$debugmode = get_config('local_alx_cdn_scorm', 'debugmode');

// Create the API injection script with Service Worker registration
$sw_url = $CFG->wwwroot . '/local/alx_cdn_scorm/sw.js';
$cdn_base_json = json_encode($base_url);
$moodle_base_json = json_encode($CFG->wwwroot);

$api_script = <<<SCRIPT
<script>
// Register Service Worker to intercept resource requests
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('{$sw_url}')
        .then(function(registration) {
            console.log('ALX CDN: Service Worker registered');
            
            // Send CDN base URL to service worker
            if (registration.active) {
                registration.active.postMessage({
                    type: 'INIT',
                    cdnBaseUrl: {$cdn_base_json},
                    moodleBaseUrl: {$moodle_base_json}
                });
            }
            
            // Also send to installing worker
            if (registration.installing) {
                registration.installing.postMessage({
                    type: 'INIT',
                    cdnBaseUrl: {$cdn_base_json},
                    moodleBaseUrl: {$moodle_base_json}
                });
            }
        })
        .catch(function(error) {
            console.error('ALX CDN: Service Worker registration failed:', error);
        });
}
</script>

<!-- Force desktop viewport and layout -->
<meta name="viewport" content="width=1024, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
<style>
    /* Force desktop layout for SCORM content */
    html, body {
        min-width: 1024px !important;
        width: 100% !important;
        overflow-x: auto !important;
    }
    body {
        margin: 0 !important;
        padding: 0 !important;
    }
</style>
<script>
console.log('ALX CDN: Injecting SCORM API into content window...');

// SCORM API Implementation
window.API_1484_11 = {
    Initialize: function(param) {
        console.log('ALX CDN: Initialize called');
        window.parent.postMessage(JSON.stringify({
            source: 'scorm_content',
            type: 'LMSInitialize',
            param: param
        }), '*');
        return 'true';
    },
    
    Terminate: function(param) {
        console.log('ALX CDN: Terminate called');
        window.parent.postMessage(JSON.stringify({
            source: 'scorm_content',
            type: 'LMSFinish',
            param: param
        }), '*');
        return 'true';
    },
    
    GetValue: function(element) {
        console.log('ALX CDN: GetValue -', element);
        // Synchronous call using a hack - store result in a global
        if (!window.ALX_SCORM_DATA) window.ALX_SCORM_DATA = {};
        
        window.parent.postMessage(JSON.stringify({
            source: 'scorm_content',
            type: 'LMSGetValue',
            element: element
        }), '*');
        
        return window.ALX_SCORM_DATA[element] || '';
    },
    
    SetValue: function(element, value) {
        console.log('ALX CDN: SetValue -', element, '=', value);
        if (!window.ALX_SCORM_DATA) window.ALX_SCORM_DATA = {};
        window.ALX_SCORM_DATA[element] = value;
        
        window.parent.postMessage(JSON.stringify({
            source: 'scorm_content',
            type: 'LMSSetValue',
            element: element,
            value: value
        }), '*');
        return 'true';
    },
    
    Commit: function(param) {
        console.log('ALX CDN: Commit called');
        window.parent.postMessage(JSON.stringify({
            source: 'scorm_content',
            type: 'LMSCommit',
            param: param
        }), '*');
        return 'true';
    },
    
    GetLastError: function() { return '0'; },
    GetErrorString: function(errorCode) { return 'No error'; },
    GetDiagnostic: function(errorCode) { return 'No diagnostic'; }
};

// SCORM 1.2 API
window.API = {
    LMSInitialize: window.API_1484_11.Initialize,
    LMSFinish: window.API_1484_11.Terminate,
    LMSGetValue: window.API_1484_11.GetValue,
    LMSSetValue: window.API_1484_11.SetValue,
    LMSCommit: window.API_1484_11.Commit,
    LMSGetLastError: window.API_1484_11.GetLastError,
    LMSGetErrorString: window.API_1484_11.GetErrorString,
    LMSGetDiagnostic: window.API_1484_11.GetDiagnostic
};

// Listen for responses from parent
window.addEventListener('message', function(event) {
    try {
        var data = JSON.parse(event.data);
        if (data.source === 'scorm_bridge' && data.type === 'LMSGetValue') {
            if (!window.ALX_SCORM_DATA) window.ALX_SCORM_DATA = {};
            window.ALX_SCORM_DATA[data.element] = data.result;
        }
    } catch (e) {}
});

console.log('ALX CDN: API ready - window.API and window.API_1484_11 available');

// DEBUG overlay
var debugMode = {$debugmode};
if (debugMode) {
    var debugOverlay = document.createElement('div');
    debugOverlay.style.cssText = 'position:fixed;top:0;left:0;background:rgba(0,0,0,0.8);color:#0f0;padding:10px;z-index:99999;font-family:monospace;font-size:12px;pointer-events:none;max-width:100%;word-wrap:break-word;';
    debugOverlay.innerHTML = '<strong>ALX SCORM Proxy Debug</strong><br>' +
        'Fetched URL: {$url}<br>' +
        'Base URL: {$base_url}<br>' +
        'API Status: Injected';
    document.body.appendChild(debugOverlay);
    
    // Check for 404s on resources
    window.addEventListener('error', function(e) {
        if(e.target && (e.target.tagName === 'IMG' || e.target.tagName === 'SCRIPT' || e.target.tagName === 'LINK')) {
             console.error('ALX CDN Resource Error:', e.target.src || e.target.href);
             var errDiv = document.createElement('div');
             errDiv.style.color = 'red';
             errDiv.innerText = 'Failed: ' + (e.target.src || e.target.href);
             debugOverlay.appendChild(errDiv);
        }
    }, true);
}
</script>
SCRIPT;

// ---------------------------------------------------------
// REWRITE RELATIVE URLS
// Base tag often fails with dynamic script loading/AJAX. 
// We must rewrite paths in the content itself.
// ---------------------------------------------------------

$base_url_path = $base_url; // e.g. https://scorm.machi.cloud/IR/

// Regex to find relative src/href/data-src, ignoring absolute URLs (http/https/data:) and anchors (#)
// Matches: src="style.css" -> src="https://cdn.../style.css"
$pattern = '/\b(src|href|data-url|data-src)=["\'](?!(http|https|data:|#|\/))([^"\']+)["\']/i';

$content = preg_replace_callback($pattern, function($matches) use ($base_url_path) {
    // $matches[1] is the attribute (src, href)
    // $matches[2] is the check for http/data (unused in replacement)
    // $matches[3] is the relative path (e.g. "lib/main.js")
    
    // Check if it already has the base path (rare but possible)
    if (strpos($matches[3], $base_url_path) === 0) {
        return $matches[0];
    }
    
    return $matches[1] . '="' . $base_url_path . $matches[3] . '"';
}, $content);

// Also look for "url('...')" in inline CSS
$content = preg_replace_callback('/url\([\'"]?(?!(http|data:|\/))([^\'"\)]+)[\'"]?\)/i', function($matches) use ($base_url_path) {
    return "url('" . $base_url_path . $matches[2] . "')";
}, $content);

// ---------------------------------------------------------
// INJECT SCORM API
// ---------------------------------------------------------

// Inject the script right after <head>
// We keep the base tag as a fallback for anything the regex missed
$api_script_block = $base_tag . "\n" . $api_script;

$content = preg_replace(
    '/(<head[^>]*>)/i',
    '$1' . "\n" . $api_script_block,
    $content,
    1
);

// If no head tag found, inject at the beginning of body
if (strpos($content, $api_script) === false) {
    $content = preg_replace(
        '/(<body[^>]*>)/i',
        $api_script_block . "\n" . '$1',
        $content,
        1
    );
}

// Serve the modified content
header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: SAMEORIGIN');
echo $content;
