<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Export endpoint for ps_moduleshop plugin.
 *
 * Allows site admins or valid token holders to export data in JSON format.
 *
 * @package    local_ps_moduleshop
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use local_ps_moduleshop\ps_moduleshop;
require_once(__DIR__ . '/../../config.php');

/*
 * Testen z.B. mit
 * http --form POST https://.../local/ps_moduleshop/export.php token={TOKEN}
 */

 $PAGE->set_url('/local/ps_moduleshop/export.php');
 $token = get_config('local_ps_moduleshop', 'token');
if (!isset($_POST['token']) || (isset($_POST['token']) && $_POST['token'] !== $token)) {
    require_login(0, false);
}
if (
     $token !== '' && isset($_POST['token']) && $_POST['token'] === $token || is_siteadmin()
) {
     $export = new ps_moduleshop();
     header('Content-Type: application/json');
     echo(json_encode($export->get_export()));
} else {
     header('HTTP/1.0 403 Forbidden');
     die('Forbidden');
}
