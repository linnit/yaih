<?php

namespace YAIH;

require_once 'vendor/autoload.php';

require 'controller/Controller.php';

$controller = new \YAIH\Controller\Controller();
$controller->invoke();

