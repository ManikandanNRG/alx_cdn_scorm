<?php
namespace local_ALX_cdn_scorm;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class form_hook {
    /**
     * Extend the SCORM activity form.
     *
     * @param \MoodleQuickForm $mform
     */
    public static function extend_scorm_form($mform) {
        global $CFG;

        // Ensure we are in the SCORM module form
        // This check might need to be robust against different form names if Moodle changes them.
        // But typically, the form name is maintained.
        
        // Add "CDN URL" option to the 'scormtype' select element if it exists.
        if ($mform->elementExists('scormtype')) {
            $scormtype = $mform->getElement('scormtype');
            $options = $scormtype->getOptions();
            // We'll use a custom constant or string for our type. 
            // Since we can't easily add a new SCORM_TYPE constant to core, we might need a workaround.
            // A common workaround is to use 'external' type but with a specific agreed-upon value or 
            // just use a text field that overrides the package file if set.
            
            // OPTION A: Add a new option to the existing select.
            // Risk: The backend verifications in mod_scorm might reject unknown types.
            
            // OPTION B: Add a checkbox "Use CDN URL" that toggles visibility of a new URL field.
            // This is safer.
            
            $mform->addElement('checkbox', 'local_ALX_cdn_enable', get_string('enablecdn', 'local_ALX_cdn_scorm'), get_string('enablecdn_desc', 'local_ALX_cdn_scorm'));
            $mform->addHelpButton('local_ALX_cdn_enable', 'enablecdn', 'local_ALX_cdn_scorm');
            
            $mform->addElement('text', 'local_ALX_cdn_url', get_string('cdnurl', 'local_ALX_cdn_scorm'), array('size' => 60));
            $mform->setType('local_ALX_cdn_url', PARAM_RAW); // We validates this manually
            $mform->hideIf('local_ALX_cdn_url', 'local_ALX_cdn_enable', 'notchecked');
            
            // Insert these elements after the 'packagefile' or 'scormtype' element.
            // Moodle forms allow insertBefore/insertAfter depending on the API version, 
            // but often we just append or use `insertElementBefore`.
            
            // For now, let's just add them. The order might be at the bottom of the section.
        }
    }
}
