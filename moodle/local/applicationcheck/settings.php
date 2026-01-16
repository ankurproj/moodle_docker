<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_externalpage(
        'tool_applicationcheck',
        get_string('pluginname', 'tool_applicationcheck'),
        new moodle_url('/admin/tool/applicationcheck/index.php'),
        'moodle/site:config'
    ));
}
