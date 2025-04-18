<?php
/*defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
  $settings->add(new admin_setting_configtext('local_ps_moduleshop/token', 'Token', 'Der Token muss bei der Abfrage vom Shopsystem angegeben werden.', ''));
}*/



defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_ps_moduleshop', get_string('pluginname', 'local_ps_moduleshop'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_ps_moduleshop/token',
        'Token',
        'Der Token muss bei der Abfrage vom Shopsystem angegeben werden.',
        ''
    ));
}

?>
