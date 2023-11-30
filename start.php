<?php
require dirname(__FILE__).'/vendor/autoload.php';

$server = new \statshow\StatShow('0.0.0.0', 8081);
$server->start();