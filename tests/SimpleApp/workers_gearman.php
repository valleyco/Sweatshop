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

$sweatshop->addQueue('GearmanExchange', ['host' => 'gearman']);
$sweatshop->registerWorker('GearmanExchange', '', null, ['host' => 'gearman']);
$sweatshop->registerWorker(
    'gearman',
    'topic:test',
    'BackgroundPrintWorker',
    [
        'host' => 'gearman',
    ]
);
$sweatshop->registerWorker('gearman', ['topic:test', 'topic:test2'], 'BackgroundLoggerWorker', [
    'host' => 'gearman',
    'max_work_cycles' => 3,
    'min_processes' => 1,
]);

$sweatshop->runWorkers();
