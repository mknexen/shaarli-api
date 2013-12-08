<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/class/ApiController.php';

$controller = new ApiController();
$controller->route();
