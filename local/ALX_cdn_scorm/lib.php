<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Extend the course module navigation to add our settings if needed.
 *
 * @param navigation_node $navref
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function local_ALX_cdn_scorm_extend_settings_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    // This hook allows us to add settings to the SCORM activity administration navigation.
    // We might not need this if we only hook into the form, but keeping it as a placeholder.
}

/**
 * Hook into the SCORM module form definition.
 *
 * @param moodleform $formwrapper The form wrapper
 * @param MoodleQuickForm $mform The actual form
 */
function local_ALX_cdn_scorm_coursemodule_standard_elements($formwrapper, $mform) {
    global $CFG;
    
    // Check if we are editing a SCORM activity
    if ($formwrapper->get_coursemodule()->modname !== 'scorm') {
        return;
    }

    // We need to inject the "CDN URL" option. 
    // This hook (coursemodule_standard_elements) runs at the end of the form.
    // To inject into the specific 'package' section, we might need a more specific hook 
    // or use JavaScript to move the element if the PHP form API is too restrictive.
    // However, Moodle 3.9+ supports 'standard_coursemodule_elements' which is generic.
    // A better approach for specific module form modification is core_coursemodule_edit_post_actions
    // or intercepting the form definition if a specific hook exists. 
    //
    // UNFORTUNATELY, mod_scorm doesn't have a specific "add_package_type" hook.
    // We will use the `coursemodule_edit_post_actions` hook to manipulate the form 
    // or purely rely on the `local_ALX_cdn_scorm_extend_form` pattern if supported by local plugins.
    
    // For now, we will define the class that handles the detailed form manipulation.
    require_once($CFG->dirroot . '/local/ALX_cdn_scorm/classes/form_hook.php');
    \local_ALX_cdn_scorm\form_hook::extend_scorm_form($mform);
}
