<?php

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/lib.php');

/*
 * Testen z.B. mit
 * http --form POST https://.../local/ps_moduleshop/export.php token={TOKEN}
 */

$PAGE->set_url('/local/ps_moduleshop/export.php');

$token = get_config('local_ps_moduleshop', 'token');

/*
  Zugriff nur für Site Admins oder mit Token
*/
if (is_siteadmin() ||
    $token !== '' && isset($_POST['token']) && $_POST['token'] === $token) {
    $obj = new ps_moduleshop();
    header('Content-Type: application/json');
    echo(json_encode($obj->get_export()));
} else {
    header('HTTP/1.0 403 Forbidden');
    die('Forbidden');
}

?>
