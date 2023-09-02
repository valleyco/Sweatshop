<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Sweatshop\Sweatshop;

require_once __DIR__.'/../../vendor/autoload.php';

$sweatshop = new Sweatshop();
$logger = new Logger('website');
$logger->pushHandler(new StreamHandler('php://stdout'));
$sweatshop->setLogger($logger);

require_once 'BackgroundPrintWorker.php';

require_once 'BackgroundLoggerWorker.php';

$sweatshop->registerWorker(
    'rabbitmq',
    'topic:test',
    'BackgroundPrintWorker',
    [
        'host' => 'rabbitmq',
        'process_title' => 'Sweatshop:test-printer',
        'max_work_cycles' => 4,
    ]
);
$sweatshop->registerWorker(
    'rabbitmq',
    'topic:test2',
    'BackgroundPrintWorker',
    [
        'host' => 'rabbitmq',
        'process_title' => 'Sweatshop:test-printer',
        'max_work_cycles' => 4,
    ]
);
$sweatshop->registerWorker(
    'rabbitmq',
    ['topic:test', 'topic:test2'],
    'BackgroundLoggerWorker',
    [
        'host' => 'rabbitmq',
        'min_processes' => 2,
        'process_title' => 'Sweatshop:test-logger']
);

$sweatshop->runWorkers();
