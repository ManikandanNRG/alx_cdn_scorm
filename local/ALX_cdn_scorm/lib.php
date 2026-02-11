<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add link to plugin settings in Site Administration.
 *
 * @param \part_of_admin_tree $settingsnav
 * @param \context $context
 */
function local_alx_cdn_scorm_extend_settings_navigation($settingsnav, $context) {
    // Placeholder
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
 * Inject JS to redirect SCORM launch button if CDN is enabled.
 *
 * @return string
 */
function local_alx_cdn_scorm_before_footer() {
    global $PAGE, $DB, $CFG;

    // Only run on SCORM view page
    if ($PAGE->pagetype === 'mod-scorm-view') {
        $cm = $PAGE->cm;
        if (!$cm) {
            return '';
        }

        // Check if CDN is enabled for this SCORM
        // Note: $PAGE->activityrecord might not be set yet, use $cm->instance
        $scormid = $cm->instance;
        $record = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $scormid));

        if ($record && $record->enabled) {
            // CDN is enabled. We need to hijack the launch button.
            // But we don't want to serve SCORM files locally.
            // The button usually goes to mod/scorm/player.php
            // We want it to go to local/alx_cdn_scorm/player.php
            
            $url = $CFG->wwwroot . '/local/alx_cdn_scorm/player.php?scormid=' . $scormid . '&cmid=' . $cm->id;
            
            // Inject JS to change the form action or button href
            // Standard Moodle SCORM view has a form with action='.../player.php'
            // Or a button.
            
            $script = "
<script>
require(['jquery'], function($) {
    $(document).ready(function() {
        // Find the form that posts to player.php
        var form = $('form[action*=\"mod/scorm/player.php\"]');
        if (form.length) {
            // Change action
            form.attr('action', '" . $url . "');
            // Remove 'scoid' hidden input if we want to rely on player.php logic, 
            // but keeping it might be fine if player.php handles it.
        }
        
        // Also check if there's a direct link (sometimes used in different themes)
        var link = $('a[href*=\"mod/scorm/player.php\"]');
        if (link.length) {
            link.attr('href', '" . $url . "');
        }
    });
});
</script>";
            return $script;
        }
    }

    return '';
}
