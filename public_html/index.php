<?php

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    file_put_contents('cabecera.json', json_encode($_SERVER));
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: token, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    header('HTTP/1.1 200 OK');
    die();
}
header('Access-Control-Allow-Origin: *');
use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
