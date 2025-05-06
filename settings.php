<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package    local_ps_moduleshop
 * @author     David Ala
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$componentname = 'local_ps_moduleshop';

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ps_moduleshop', get_string('pluginname', 'local_ps_moduleshop'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_ps_moduleshop/token',
        get_string('token', 'local_ps_moduleshop'),
        get_string('tokendesc', 'local_ps_moduleshop'),
        '',
        PARAM_ALPHANUMEXT,
    ));
}
