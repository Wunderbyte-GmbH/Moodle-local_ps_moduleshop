<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
use local\ps_moduleshop\ps_moduleshop;

require_login();

$context = context_system::instance();
require_capability('moodle/course:create', $context);

$PAGE->set_url(new moodle_url('/local/ps_moduleshop/export.php'));
$PAGE->set_context($context);
$exporter = new ps_moduleshop();

header('Content-Type: application/json');
echo json_encode($exporter->get_export());
exit;
