<?php

require_once __DIR__.'/../../vendor/autoload.php';

use Sweatshop\Message\Message;
use Sweatshop\Queue\InternalQueue;
use Sweatshop\Sweatshop;
use Sweatshop\Worker\Worker;

// Define the worker class
// here or somewhere else...
class EchoWorker extends Worker
{
    public function work(Message $message)
    {
        $params = $message->getParams();

        return $params['value'];
    }
}

// Setup Sweatshop, Queue and Worker
$sweatshop = new Sweatshop();
$queue = new InternalQueue($sweatshop);
$worker = new EchoWorker($sweatshop);
$queue->registerWorker('topic:test:echo', $worker);
$sweatshop->addQueue($queue);

// Create a new Message
$message = new Message('topic:test:echo', [
    'value' => 3,
]);

// Invoke Workers for the message
$results = $sweatshop->pushMessage($message);
print_r($results);

/*
Expected Result:

Array
(
    [0] => 3
)
 */
