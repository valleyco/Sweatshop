<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Sweatshop\Sweatshop;

require_once __DIR__.'/../../vendor/autoload.php';

require_once 'EchoWorker.php';

$sweatshop = new Sweatshop();
$logger = new Logger('website');
$logger->pushHandler(new StreamHandler('php://stdout'));
$sweatshop->setLogger($logger);

$sweatshop->addQueue('rabbitmq', ['host' => 'rabbitmq']);

$results = $sweatshop->pushMessageQuick(
    'topic:test',
    [
        'host' => 'rabbitmq',
        'value' => 3,
    ]
);
$results = $sweatshop->pushMessageQuick(
    'topic:test2',
    [
        'host' => 'rabbitmq',
        'value' => 5,
    ]
);

print_r($results);
