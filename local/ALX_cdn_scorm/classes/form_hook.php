<?php
namespace local_alx_cdn_scorm;

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
        if ($mform->elementExists('scormtype')) {
            $scormtype = $mform->getElement('scormtype');
            
            // Add a header/separator for clarity
            $mform->addElement('header', 'local_alx_cdn_header', get_string('pluginname', 'local_alx_cdn_scorm'));

            $mform->addElement('checkbox', 'local_alx_cdn_enable', get_string('enablecdn', 'local_alx_cdn_scorm'), get_string('enablecdn_desc', 'local_alx_cdn_scorm'));
            $mform->addHelpButton('local_alx_cdn_enable', 'enablecdn', 'local_alx_cdn_scorm');
            
            $mform->addElement('text', 'local_alx_cdn_url', get_string('cdnurl', 'local_alx_cdn_scorm'), array('size' => 60));
            $mform->setType('local_alx_cdn_url', PARAM_RAW); 
            $mform->hideIf('local_alx_cdn_url', 'local_alx_cdn_enable', 'notchecked');
        }
    }
}
