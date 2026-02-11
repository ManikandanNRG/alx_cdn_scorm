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

        // Check if we are interacting with the SCORM module form
        $classname = get_class($mform);
        if (strpos($classname, 'mod_scorm_mod_form') === false) {
            return;
        }

        // We need the internal MoodleQuickForm object which is protected as $_form.
        // We use Reflection to access it properly.
        $form = $mform;
        if (method_exists($mform, 'get_form')) {
             $form = $mform->get_form();
        } else {
             try {
                $ref = new \ReflectionClass($mform);
                if ($ref->hasProperty('_form')) {
                    $prop = $ref->getProperty('_form');
                    $prop->setAccessible(true);
                    $form = $prop->getValue($mform);
                }
             } catch (\Exception $e) {
                // Should not happen, but fallback
             }
        }
        
        // Final sanity check: does $form have addElement?
        if (!is_object($form) || !method_exists($form, 'addElement')) {
            return;
        }

        // Now use $form to add elements
        if ($form->elementExists('scormtype')) {
            
            // Add a header/separator for clarity
            $form->addElement('header', 'local_alx_cdn_header', get_string('pluginname', 'local_alx_cdn_scorm'));

            $form->addElement('checkbox', 'local_alx_cdn_enable', get_string('enablecdn', 'local_alx_cdn_scorm'), get_string('enablecdn_desc', 'local_alx_cdn_scorm'));
            $form->addHelpButton('local_alx_cdn_enable', 'enablecdn', 'local_alx_cdn_scorm');
            
            $form->addElement('text', 'local_alx_cdn_url', get_string('cdnurl', 'local_alx_cdn_scorm'), array('size' => 60));
            $form->setType('local_alx_cdn_url', PARAM_RAW); 
            $form->hideIf('local_alx_cdn_url', 'local_alx_cdn_enable', 'notchecked');
        }
    }
}
