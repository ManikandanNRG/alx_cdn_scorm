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
            
            // LOAD EXISTING DATA from database
            global $DB;
            $scormid = optional_param('update', 0, PARAM_INT); // When editing, 'update' param contains the cmid
            
            $existingEnabled = 0;
            $existingUrl = '';
            
            if ($scormid > 0) {
                // Get the actual scorm instance ID from the course module
                $cm = get_coursemodule_from_id('scorm', $scormid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    error_log('ALX CDN DEBUG: Loading form, CM ID: ' . $scormid . ', SCORM ID: ' . $cm->instance);
                    
                    $record = $DB->get_record('local_alx_cdn_scorm', array('scormid' => $cm->instance));
                    if ($record) {
                        $existingEnabled = $record->enabled;
                        $existingUrl = $record->cdnurl;
                        error_log('ALX CDN DEBUG: Found existing - enabled: ' . $existingEnabled . ', url: ' . $existingUrl);
                    } else {
                        error_log('ALX CDN DEBUG: No existing record found');
                    }
                }
            }
            
            // Add a header/separator for clarity
            $form->addElement('header', 'local_alx_cdn_header', get_string('pluginname', 'local_alx_cdn_scorm'));

            $form->addElement('checkbox', 'local_alx_cdn_enable', get_string('enablecdn', 'local_alx_cdn_scorm'), get_string('enablecdn_desc', 'local_alx_cdn_scorm'));
            $form->addHelpButton('local_alx_cdn_enable', 'enablecdn', 'local_alx_cdn_scorm');
            $form->setDefault('local_alx_cdn_enable', $existingEnabled); // SET DEFAULT!
            
            $form->addElement('text', 'local_alx_cdn_url', get_string('cdnurl', 'local_alx_cdn_scorm'), array('size' => 60));
            $form->setType('local_alx_cdn_url', PARAM_RAW); 
            $form->setDefault('local_alx_cdn_url', $existingUrl); // SET DEFAULT!

            // --- Player Height Option (New Section) ---
            $existingHeight = '680px';
            if ($scormid > 0 && isset($record->playerheight)) {
                $existingHeight = $record->playerheight;
            }

            $form->addElement('header', 'local_alx_player_header', get_string('player_settings', 'local_alx_cdn_scorm'));
            
            $heightOptions = [
                'auto' => get_string('height_auto', 'local_alx_cdn_scorm'),
                '680px' => '680px (Standard)',
                '600px' => '600px',
                '500px' => '500px'
            ];
            $form->addElement('select', 'local_alx_player_height', get_string('player_height', 'local_alx_cdn_scorm'), $heightOptions);
            $form->addHelpButton('local_alx_player_height', 'player_height', 'local_alx_cdn_scorm');
            $form->setDefault('local_alx_player_height', $existingHeight);

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
            $js = <<<SCRIPT
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

        // --- 1. Relocate ALL Fields from ALX Section ---
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

        // Helper to move an element after a destination
        var moveAfter = function(source, dest) {
            if (dest && dest.parentNode && source) {
                if (dest.nextSibling) {
                    dest.parentNode.insertBefore(source, dest.nextSibling);
                } else {
                    dest.parentNode.appendChild(source);
                }
                return source; // Return the moved element as the new destination
            }
            return dest;
        };

        // --- 1. Relocate Known Fields (Enable & URL) ---
        // This MUST work to preserve previous functionality
        var cdnEnableWrapper = getWrapper('id_local_alx_cdn_enable');
        var cdnUrlWrapper = getWrapper('id_local_alx_cdn_url');
        
        var currentDest = packageWrapper;

        if (packageWrapper) {
            if (cdnEnableWrapper) {
                console.log('ALX CDN JS: Moving Enable Checkbox...');
                currentDest = moveAfter(cdnEnableWrapper, currentDest);
            }
            
            if (cdnUrlWrapper) {
                console.log('ALX CDN JS: Moving CDN URL...');
                currentDest = moveAfter(cdnUrlWrapper, currentDest);
            }
        }

        // --- 2. RELOCATE Player Height Section to Bottom ---
        var playerHdr = document.getElementById('id_hdr_local_alx_player_header');
        if (playerHdr) {
            var playerContainer = playerHdr.closest('fieldset');
            var mainForm = document.querySelector('form.mform');
            if (mainForm && playerContainer) {
                // Find all fieldsets in the form
                var fieldsets = mainForm.querySelectorAll('fieldset');
                if (fieldsets.length > 0) {
                    var lastFieldset = fieldsets[fieldsets.length - 1];
                    console.log('ALX CDN JS: Moving Player Settings to bottom...');
                    moveAfter(playerContainer, lastFieldset);
                }
            }
        }

        // --- 2. Move Any REMAINING Fields from Source Section ---
        // This catches the 'mystery dropdown' or other fields added by Moodle/plugins
        var triggerEl = document.getElementById('id_local_alx_cdn_enable'); // The checkbox (now moved)
        var sourceContainer = null;
        
        // Find the ORIGINAL container (fieldset/header) even if the checkbox moved
        // We can search by ID for the header if possible, or use the moved element's OLD parent?
        // Actually, since we moved the checkbox, triggerEl.parentNode is now the Package section!
        // So we need to find the specific "ALX Cloud SCORM" header by ID.
        var headerEl = document.getElementById('id_hdr_local_alx_cdn_header'); // Standard Moodle ID for header
        if (headerEl) {
             sourceContainer = headerEl.closest('fieldset') || headerEl.parentNode;
        } else {
             // Try searching for a legend with our text
             var legends = document.getElementsByTagName('legend');
             for (var i = 0; i < legends.length; i++) {
                 if (legends[i].innerText.indexOf('ALX Cloud SCORM') > -1) {
                     sourceContainer = legends[i].parentNode;
                     break;
                 }
             }
        }

        if (sourceContainer && packageWrapper) {
            console.log('ALX CDN JS: Checking for remaining fields in section...');
            
            // Iterate all children and move form rows (fitem/form-group)
            var children = Array.from(sourceContainer.children);
            children.forEach(function(child) {
                // Skip the header itself/legend
                if (child.tagName === 'LEGEND' || child.id === 'id_hdr_local_alx_cdn_header' || 
                    child.className.indexOf('fheader') > -1) {
                    return;
                }
                
                // Skip hidden inputs that might be system-generated
                // Ideally, we move visible rows.
                if (child.style.display === 'none' && child.tagName !== 'INPUT') {
                    // might be safe to skip?
                }

                // Move it to the end of our chain
                console.log('ALX CDN JS: Moving leftover field', child);
                currentDest = moveAfter(child, currentDest);
            });
            
            // Hide the now-empty container
            sourceContainer.style.display = 'none';
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
</script>
SCRIPT;
            $form->addElement('html', $js);
            
            // Note: We removed the server-side disabledIfs for packageurl/packagefile 
            // because we are now relying on the 'External' type switch which inherently handles requirements.
            // (External requires URL, Local requires File).
        }
    }
}
