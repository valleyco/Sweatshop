<?php

use Monolog\Handler\StreamHandler;

use Monolog\Logger;

use Sweatshop\Sweatshop;

require_once __DIR__.'/../../vendor/autoload.php';
require_once 'EchoWorker.php';

$sweatshop = new Sweatshop();
$logger = new Logger('website');
$logger->pushHandler(new StreamHandler("php://stdout"));
$sweatshop->setLogger($logger);

$sweatshop->addQueue('rabbitmq',[]);


$results = $sweatshop->pushMessageQuick('topic:test',array(
	'value' => 3		
));
$results = $sweatshop->pushMessageQuick('topic:test2',array(
		'value' => 5
));

print_r($results);