<?php

namespace Sweatshop;

use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Pimple\Container;
use Sweatshop\Dispatchers\MessageDispatcher;
use Sweatshop\Dispatchers\WorkersDispatcher;
use Sweatshop\Message\Message;
use Sweatshop\Queue\Queue;

class Sweatshop
{
    protected $_di;

    /**
     * @var MessageDispatcher
     */
    protected $_messageDispatcher;

    /**
     * @var WorkersDispatcher
     */
    protected $_workersDispatcher;

    public function __construct()
    {
        $di = new Container();
        $di['logger'] = (function ($di) {
            $logger = new Logger('Sweatshop');
            $logger->pushHandler(new NullHandler());

            return $logger;
        });
        $di['config'] = (function ($di) {
            return [];
        });
        $di['sweatshop'] = $this;

        $this->setDependencies($di);
        $this->_messageDispatcher = new MessageDispatcher($this);
        $this->_workersDispatcher = new WorkersDispatcher($this);
    }

    public function pushMessage(Message $message)
    {
        return $this->_messageDispatcher->pushMessage($message);
    }

    public function pushMessageQuick($topic, $params = [])
    {
        $message = new Message($topic, $params);

        return $this->pushMessage($message);
    }

    public function addQueue($queue, $options = [])
    {
        $queue_class = Queue::toClassName($queue);
        $queueObj = new $queue_class($this, $options);
        $this->_messageDispatcher->addQueue($queueObj);
    }

    public function registerWorker($queue, $topic = '', $worker = null, $options = [])
    {
        $queue_class = Queue::toClassName($queue);
        $this->_workersDispatcher->registerWorker($queue_class, $topic, $worker, $options);
    }

    public function runWorkers()
    {
        $this->getLogger()->info('Sweatshop: Launching workers');
        $this->_workersDispatcher->runWorkers();
    }

    public function configureMessagesDispatcher($config)
    {
        $this->_messageDispatcher->configure($config);
    }

    public function setDependencies(Container $di)
    {
        $this->_di = $di;
    }

    public function getDependencies()
    {
        return $this->_di;
    }

    public function setLogger(Logger $logger)
    {
        unset($this->_di['logger']);
        $this->_di['logger'] = $logger;
    }

    public function getLogger()
    {
        return $this->_di['logger'];
    }

    public function setConfig($config)
    {
        unset($this->_di['config']);
        $this->_di['config'] = $config;
    }

    public function getConfig()
    {
        return $this->_di['config'];
    }
}
