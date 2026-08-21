<?php

declare(strict_types=1);

use think\App;

require dirname(__DIR__) . '/vendor/autoload.php';

$http = (new App(dirname(__DIR__)))->http;
$response = $http->run();
$response->send();
$http->end($response);
