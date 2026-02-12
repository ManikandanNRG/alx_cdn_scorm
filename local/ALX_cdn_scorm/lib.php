<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Load existing CDN settings into the form when editing a SCORM activity.
 *
 * This is called BEFORE the form is displayed to populate existing values.
 *
 * @param array $defaultvalues
 * @param stdClass $instance
 */
function local_alx_cdn_scorm_coursemodule_data_preprocessing(&$defaultvalues, $instance) {
    global $DB;
    
    error_log('ALX CDN DEBUG: data_preprocessing called');
    
    if (empty($instance)) {
        error_log('ALX CDN DEBUG: No instance provided');
        return;
    }
    
    error_log('ALX CDN DEBUG: Instance ID: ' . $instance->id);
    
    // Load existing CDN record
    $record = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $instance->id));
    
    if ($record) {
        error_log('ALX CDN DEBUG: Found existing record - enabled: ' . $record->enabled . ', url: ' . $record->cdnurl);
        $defaultvalues['alx_cdn_enabled'] = $record->enabled;
        $defaultvalues['alx_cdn_url'] = $record->cdnurl;
    } else {
        error_log('ALX CDN DEBUG: No existing record found for scormid: ' . $instance->id);
    }
}


/**
 * Save CDN settings after the SCORM activity form is submitted.
 *
 * This is called AFTER the core SCORM data is saved.
 *
 * @param stdClass $data Form data
 * @param stdClass $course Course object
 * @return stdClass The modified data object
 */
function local_alx_cdn_scorm_coursemodule_edit_post_actions($data, $course) {
    global $DB;
    
    error_log('ALX CDN DEBUG: edit_post_actions called');
    error_log('ALX CDN DEBUG: Module name: ' . $data->modulename);
    
    // Only process if this is a SCORM activity
    if ($data->modulename !== 'scorm') {
        error_log('ALX CDN DEBUG: Not a SCORM module, skipping');
        return $data;
    }
    
    // Get the SCORM instance ID
    $scormid = $data->instance;
    
    if (empty($scormid)) {
        error_log('ALX CDN DEBUG: No instance ID found');
        return $data;
    }
    
    error_log('ALX CDN DEBUG: SCORM ID: ' . $scormid);
    error_log('ALX CDN DEBUG: CDN Enabled: ' . (isset($data->local_alx_cdn_enable) ? $data->local_alx_cdn_enable : 'NOT SET'));
    error_log('ALX CDN DEBUG: CDN URL: ' . (isset($data->local_alx_cdn_url) ? $data->local_alx_cdn_url : 'NOT SET'));
    
    // Check if a record already exists
    $existing = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $scormid));
    
    $record = new \stdClass();
    $record->scormid = $scormid;
    $record->enabled = isset($data->local_alx_cdn_enable) ? $data->local_alx_cdn_enable : 0;  // FIXED FIELD NAME
    $record->cdnurl = isset($data->local_alx_cdn_url) ? trim($data->local_alx_cdn_url) : '';  // FIXED FIELD NAME
    $record->timecreated = time();
    $record->timemodified = time();
    
    if ($existing) {
        // Update existing record
        $record->id = $existing->id;
        $record->timecreated = $existing->timecreated; // Preserve original creation time
        error_log('ALX CDN DEBUG: Updating existing record ID: ' . $record->id);
        $DB->update_record('local_alx_cdn_scorm', $record);
    } else {
        // Insert new record
        error_log('ALX CDN DEBUG: Inserting new record');
        $newid = $DB->insert_record('local_alx_cdn_scorm', $record);
        error_log('ALX CDN DEBUG: New record ID: ' . $newid);
    }
    
    return $data;
}


/**
 * Inject JS/CSS to replace the standard SCORM launch button with our CDN player link.
 *
 * @return string
 */
function local_alx_cdn_scorm_before_footer() {
    global $PAGE, $DB, $CFG, $OUTPUT;

    // Only run on SCORM view page
    if ($PAGE->pagetype === 'mod-scorm-view') {
        $cm = $PAGE->cm;
        if (!$cm) return '';

        $scormid = $cm->instance;
        $record = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $scormid));

        if ($record && $record->enabled) {
            $playerUrl = $CFG->wwwroot . '/local/alx_cdn_scorm/player.php?scormid=' . $scormid . '&cmid=' . $cm->id;
            
            // Create a direct link styled as a button (not a form)
            // This ensures we go exactly where we want without form submission issues
            $customBtnHtml = '
                <div id="alx-cdn-launch-wrapper" style="margin: 20px 0; text-align: center;">
                    <a href="' . $playerUrl . '" 
                       class="btn btn-primary btn-lg"
                       style="background-color: #d9534f; border-color: #d43f3a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-size: 16px; font-weight: bold;">
                        Enter
                    </a>
                </div>
            ';

            // 1. CSS to hide the original form/button
            // We target forms pointing to the standard player
            $css = "
                <style>
                    /* Hide standard launch forms */
                    form[action*='mod/scorm/player.php'] {
                        display: none !important;
                    }
                    /* Hide standard launch links */
                    a[href*='mod/scorm/player.php'] {
                        display: none !important;
                    }
                </style>
            ";

            // 2. JS to move our button to the right place (top of general box usually)
            // vanilla JS, no dependencies
            $js = "
                <script>
                (function() {
                    var placeButton = function() {
                        var wrapper = document.getElementById('alx-cdn-launch-wrapper');
                        var originalForm = document.querySelector(\"form[action*='mod/scorm/player.php']\");
                        
                        if (wrapper && originalForm) {
                            // Insert our button before the (now hidden) original form
                            originalForm.parentNode.insertBefore(wrapper, originalForm);
                        } else if (wrapper) {
                            // Fallback: try to find the main content region
                            var region = document.querySelector('[role=\"main\"]') || document.getElementById('region-main');
                            if (region) {
                                region.appendChild(wrapper);
                            }
                        }
                    };
                    
                    // Run immediately and on load
                    placeButton();
                    document.addEventListener('DOMContentLoaded', placeButton);
                })();
                </script>
            ";

            return $css . $customBtnHtml . $js;
        }
    }

    return '';
}

/**
 * Add CDN elements to SCORM form.
 *
 * @param \mod_scorm_mod_form $mform
 */
function local_alx_cdn_scorm_coursemodule_standard_elements($mform, $course) {
    global $CFG;
    require_once($CFG->dirroot . '/local/alx_cdn_scorm/classes/form_hook.php');
    \local_alx_cdn_scorm\form_hook::extend_scorm_form($mform);
}

/**
 * Add link to plugin settings in Site Administration.
 *
 * @param \part_of_admin_tree $settingsnav
 * @param \context $context
 */
function local_alx_cdn_scorm_extend_settings_navigation($settingsnav, $context) {
    // Placeholder for future use
}

/**
 * Redirect to CDN player if enabled.
 * This intercepts ALL SCORM player launches (including when students click activity name).
 */
function local_alx_cdn_scorm_before_http_headers() {
    global $PAGE, $DB, $CFG;
    
    // Only intercept the default SCORM player page
    if ($PAGE->pagetype === 'mod-scorm-player') {
        // Get SCORM ID from URL parameters
        $scormid = optional_param('a', 0, PARAM_INT);
        $cmid = optional_param('cm', 0, PARAM_INT);
        
        if ($scormid > 0) {
            // Check if CDN is enabled for this SCORM
            $record = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $scormid));
            
            if ($record && $record->enabled) {
                // Get CM ID if not provided
                if ($cmid == 0) {
                    $cm = get_coursemodule_from_instance('scorm', $scormid);
                    $cmid = $cm ? $cm->id : 0;
                }
                
                // Redirect to our custom CDN player
                $redirectUrl = $CFG->wwwroot . '/local/alx_cdn_scorm/player.php?scormid=' . $scormid . '&cmid=' . $cmid;
                redirect($redirectUrl);
            }
        }
    }
}
