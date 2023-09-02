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

$sweatshop->addQueue(
    'GearmanExchange',
    [
        'host' => 'gearman',
    ]
);

$results = $sweatshop->pushMessageQuick(
    'topic:test',
    [
        'host' => 'gearman',
        'value' => 3,
    ]
);
$results = $sweatshop->pushMessageQuick(
    'topic:test2',
    [
        'host' => 'gearman',
        'value' => 5,
    ]
);

print_r($results);
