<?php
namespace local_application_status_check\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');

class status_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        // We use auto-detect flow; `schemes` array is not used.

        $mform->addElement('text', 'email', get_string('email', 'local_application_status_check'));
        $mform->setType('email', PARAM_TEXT);
        $mform->addRule('email', get_string('required'), 'required');


        // Ensure form submissions include the 'open' flag so the landing page isn't shown.
        $mform->addElement('hidden', 'open', 1);
        $mform->setType('open', PARAM_INT);
        // Include sesskey to avoid Moodle session-related redirects.
        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_RAW);

        // Initial step: don't ask for a course name. Provide a button to auto-detect courses
        // based on the provided email and dob.
        $mform->addElement('html', '<div class="form-group"><p>' . get_string('autodetectcourseinfo', 'local_application_status_check') . '</p></div>');
        
        // Add CAPTCHA for security
        global $CFG, $SESSION;
        
        // Initialize session flag if not exists
        if (!isset($SESSION->application_status_captcha_verified)) {
            $SESSION->application_status_captcha_verified = false;
        }
        
        // Only show CAPTCHA if not already verified in this session
        if (!empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey)) {
            if (!$SESSION->application_status_captcha_verified) {
                // CAPTCHA not yet verified - show it
                $mform->addElement('html', '<div class="captcha-wrapper" style="margin: 1rem 0;" id="captcha-wrapper-div">');
                $mform->addElement('recaptcha', 'recaptcha_element', '');
                $mform->addElement('html', '</div>');
                
                // Add JavaScript to disable submit buttons until CAPTCHA is checked
                $mform->addElement('html', '
                <script>
                (function() {
                    var captchaVerified = false;
                    
                    function setButtonsEnabled(enabled) {
                        var buttons = document.querySelectorAll("input[name=\"getscheme\"], button[name=\"checkstatus\"]");
                        buttons.forEach(function(btn) {
                            btn.disabled = !enabled;
                            btn.style.opacity = enabled ? "1" : "0.5";
                            btn.style.cursor = enabled ? "pointer" : "not-allowed";
                        });
                    }

                    function captchaCompleted() {
                        var response = document.querySelector("textarea[name=\"g-recaptcha-response\"]");
                        return response && response.value && response.value.trim().length > 0;
                    }

                    function applyState() {
                        if (captchaCompleted() && !captchaVerified) {
                            captchaVerified = true;
                            setButtonsEnabled(true);
                        }
                    }

                    // Ensure buttons are disabled after DOM is ready
                    if (document.readyState === "loading") {
                        document.addEventListener("DOMContentLoaded", function() {
                            setButtonsEnabled(false);
                        });
                    } else {
                        setButtonsEnabled(false);
                    }

                    // Poll for CAPTCHA completion - enable once and keep enabled
                    var intervalId = setInterval(function() {
                        applyState();
                        if (captchaVerified) {
                            clearInterval(intervalId);
                        }
                    }, 300);

                    // Also check on any change
                    document.addEventListener("change", applyState);
                    
                    // If form is reloaded (after submission), check if CAPTCHA token still exists
                    window.addEventListener("load", function() {
                        if (captchaCompleted()) {
                            captchaVerified = true;
                            setButtonsEnabled(true);
                        }
                    });
                })();
                </script>
                ');
            } else {
                // CAPTCHA already verified - show success message and enable buttons
                $mform->addElement('html', '<div class="captcha-success-message" style="margin: 1rem 0; padding: 0.75rem 1rem; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; color: #155724;"><span style="font-size: 1.2em;">✓</span> <strong>CAPTCHA Verified</strong></div>');
                
                // Add JavaScript to ensure buttons are enabled
                $mform->addElement('html', '
                <script>
                (function() {
                    function setButtonsEnabled(enabled) {
                        var buttons = document.querySelectorAll("input[name=\"getscheme\"], button[name=\"checkstatus\"]");
                        buttons.forEach(function(btn) {
                            btn.disabled = !enabled;
                            btn.style.opacity = enabled ? "1" : "0.5";
                            btn.style.cursor = enabled ? "pointer" : "not-allowed";
                        });
                    }
                    
                    // Always enable buttons since CAPTCHA is already verified
                    setButtonsEnabled(true);
                    
                    // Re-enable on page load
                    window.addEventListener("load", function() {
                        setButtonsEnabled(true);
                    });
                })();
                </script>
                ');
            }
        }
        
        $mform->addElement('submit', 'getscheme', get_string('getscheme', 'local_application_status_check'));

        // If detected course(s) are provided via customdata, render each as a row
        // with the course fullname and a right-aligned check button that submits
        // the course id as the button value (name="checkstatus", value="{id}").
        $detected = $this->_customdata['detectedcourses'] ?? $this->_customdata['detectedcourse'] ?? null;
        if (!empty($detected)) {
            // Normalize to array of courses
            $courses = [];
            if (is_array($detected) && array_key_exists('id', $detected)) {
                $courses[] = $detected;
            } else if (is_array($detected)) {
                $courses = $detected;
            }

            $html = '<div class="application-courses">';
            foreach ($courses as $c) {
                $label = format_string($c['label'] ?? ($c['fullname'] ?? ''));
                $id = (int)($c['id'] ?? 0);
                // Each button will submit the parent form with name 'checkstatus' and value set to course id.
                $html .= '<div class="detected-course-row" style="display:flex;align-items:center;justify-content:space-between">';
                $html .= '<div class="detected-course">' . $label . '</div>';
                $html .= '<button type="submit" name="checkstatus" value="' . $id . '" class="btn btn-primary check-status-btn">' . get_string('checkstatus', 'local_application_status_check') . '</button>';
                $html .= '</div>';
            }
            $html .= '</div>';

            $mform->addElement('html', $html);
        }
    }

    public function validation($data, $files) {
        global $CFG, $SESSION;
        $errors = parent::validation($data, $files);
        
        // Initialize session flag for CAPTCHA verification if not exists
        if (!isset($SESSION->application_status_captcha_verified)) {
            $SESSION->application_status_captcha_verified = false;
        }
        
        // Validate reCAPTCHA if configured - ONLY on initial submission (Get Scheme)
        // Skip validation if:
        // 1. CAPTCHA was already verified in this session, OR
        // 2. Check Status button was clicked (not Get Scheme)
        $checkstatus_pressed = !empty($this->_form->_submitValues['checkstatus']) || 
                               !empty($this->_form->_submitValues['checkstatus.x']);
        
        $captcha_already_verified = !empty($SESSION->application_status_captcha_verified);
        
        if (!$checkstatus_pressed && !$captcha_already_verified && 
            !empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey)) {
            $recaptchaelement = $this->_form->getElement('recaptcha_element');
            $response = $this->_form->_submitValues['g-recaptcha-response'] ?? '';
            $response = trim($response);
            if ($response === '') {
                $errors['recaptcha_element'] = get_string('missingreqreason', 'error');
            } else if ($recaptchaelement) {
                $verify = $recaptchaelement->verify($response);
                if ($verify !== true) {
                    $errors['recaptcha_element'] = get_string('incorrectpleasetryagain', 'auth');
                } else {
                    // Mark CAPTCHA as verified in session
                    $SESSION->application_status_captcha_verified = true;
                }
            }
        } else if (!$checkstatus_pressed && $captcha_already_verified) {
            // CAPTCHA already verified, mark it again to ensure session persists
            $SESSION->application_status_captcha_verified = true;
        }
        
        // Validate that email field contains either valid email or numeric mobile number
        if (!empty($data['email'])) {
            $input = trim($data['email']);
            $is_email = filter_var($input, FILTER_VALIDATE_EMAIL);
            $is_mobile = preg_match('/^[0-9]{10}$/', $input);
            if (!$is_email && !$is_mobile) {
                $errors['email'] = get_string('invalidemailormobile', 'local_application_status_check');
            }
        }
        if (!empty($data['courseid'])) {
            if (!is_numeric($data['courseid'])) {
                $errors['courseid'] = get_string('invaliddata', 'error');
            }
        } else if (!empty($data['course'])) {
            // Accept any text; matching happens server-side.
        }
        return $errors;
    }
}
