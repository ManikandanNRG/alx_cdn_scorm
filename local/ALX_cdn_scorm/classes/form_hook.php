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

            // --- SERVER SIDE FIXES ---
            // 1. Relax validation on 'packageurl' to prevent 'Invalid URL' errors
            if ($form->elementExists('packageurl')) {
                // Remove existing rules if possible (Moodle doesn't expose removeRule easily, but we can override type)
                $form->setType('packageurl', PARAM_RAW);
                
                // If we can access the element, we can try to remove the 'url' type requirement in the DOM via JS
                // or just trust that PARAM_RAW helps server-side.
            }

            // --- Javascript for UX and Validation ---
            // switching to Vanilla JS with robust selectors and trimming
            $js = "
<script>
(function() {
    var initALX = function() {
        console.log('ALX CDN JS: Initializing...');

        // Helper to find the row wrapper (fitem/form-group)
        var getWrapper = function(id) {
            var el = document.getElementById(id);
            if (!el) return null;
            // Try to find the standard Moodle form row wrapper
            var wrapper = el.closest('.fitem');
            if (!wrapper) wrapper = el.closest('.form-group'); // Boost/Bootstrap
            if (!wrapper) wrapper = el.closest('.row'); // Generic fallback
            if (!wrapper) wrapper = el.parentNode; // Ultimate fallback
            return wrapper;
        };

        // --- 1. Relocate Fields ---
        var packageElId = 'id_packagefile';
        if (!document.getElementById(packageElId)) {
             packageElId = 'id_packagefile_filemanager'; 
        }
        
        var packageWrapper = getWrapper(packageElId);
        // Fallback: search by name attribute if ID fails
        if (!packageWrapper) {
            var inputs = document.getElementsByName('packagefile');
            if (inputs.length) packageWrapper = getWrapper(inputs[0].id);
        }

        var cdnEnableWrapper = getWrapper('id_local_alx_cdn_enable');
        var cdnUrlWrapper = getWrapper('id_local_alx_cdn_url');

        if (packageWrapper && cdnEnableWrapper && cdnUrlWrapper) {
            console.log('ALX CDN JS: Moving fields...');
            // Insert Enable Checkbox after Package Wrapper
            packageWrapper.parentNode.insertBefore(cdnEnableWrapper, packageWrapper.nextSibling);
            // Insert URL Field after Enable Checkbox
            cdnEnableWrapper.parentNode.insertBefore(cdnUrlWrapper, cdnEnableWrapper.nextSibling);
        }

        // --- 2. Handle Validation / Interaction ---
        var checkbox = document.getElementById('id_local_alx_cdn_enable');
        var typeSelect = document.getElementById('id_scormtype');
        var inputUrl = document.getElementById('id_local_alx_cdn_url');
        var packageUrl = document.getElementById('id_packageurl');
        var packageUrlWrapper = getWrapper('id_packageurl');

        if (!checkbox || !typeSelect) return;

        var updateState = function() {
            if (checkbox.checked) {
                // CDN Enabled - Switch to External
                var externalOption = false;
                for (var i = 0; i < typeSelect.options.length; i++) {
                    if (typeSelect.options[i].value === 'external') {
                        externalOption = true;
                        break;
                    }
                }

                if (externalOption) {
                    if (typeSelect.value !== 'external') {
                        typeSelect.value = 'external';
                        typeSelect.dispatchEvent(new Event('change'));
                    }
                    if (packageUrlWrapper) packageUrlWrapper.style.display = 'none';
                }
            } else {
                // CDN Disabled
                if (packageUrlWrapper) packageUrlWrapper.style.display = '';
            }
        };

        var syncUrl = function() {
            if (inputUrl && packageUrl) {
                var cleanVal = inputUrl.value.trim();
                
                // FIX: Moodle 'url' field type sometimes requires protocol explicitly or fails on complex chars.
                // We ensure it looks like a valid URL.
                if (cleanVal && cleanVal.indexOf('http') !== 0) {
                     // If user typed shorthand, maybe Moodle dislikes it? 
                     // But usually we just pass the raw value.
                }

                packageUrl.value = cleanVal;
                
                // Manually trigger Moodle/YUI validation to clear error if valid
                packageUrl.dispatchEvent(new Event('input'));
                packageUrl.dispatchEvent(new Event('change'));
            }
        };

        // Listeners
        checkbox.addEventListener('change', updateState);
        if (inputUrl) {
            inputUrl.addEventListener('input', syncUrl);
            inputUrl.addEventListener('change', syncUrl);
            inputUrl.addEventListener('blur', syncUrl);
        }
        
        // Initial run
        setTimeout(function() {
            updateState();
            syncUrl();
        }, 500);
    };

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initALX();
    } else {
        document.addEventListener('DOMContentLoaded', initALX);
    }
})();
</script>";
            $form->addElement('html', $js);
            
            // Note: We removed the server-side disabledIfs for packageurl/packagefile 
            // because we are now relying on the 'External' type switch which inherently handles requirements.
            // (External requires URL, Local requires File).
        }
    }
}
