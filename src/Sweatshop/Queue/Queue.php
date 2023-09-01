<?php

namespace Sweatshop\Queue;

use Monolog\Logger;
use Pimple\Container;
use Sweatshop\Interfaces\MessageableInterface;
use Sweatshop\Message\Message;
use Sweatshop\Sweatshop;
use Sweatshop\Worker\Worker;

abstract class Queue implements MessageableInterface
{
    protected $_config;
    protected $_di;
    protected $_options = [];
    private $_workers = [];
    private $_logger;

    public function __construct(Sweatshop $sweatshop, $options = [])
    {
        $this->setDependencies($sweatshop->getDependencies());
        $this->_options = array_merge($this->_options, [
            'max_work_cycles' => -1,
            'max_process_memory' => -1,
        ], $options);
    }

    public function __destruct()
    {
        // $this->getLogger()->debug(sprintf('Queue "%s": tearing down',get_class($this)));
    }

    public static function toClassName($queueName)
    {
        if (class_exists($queueName)) {
            return $queueName;
        }
        $newname = 'Sweatshop\\Queue\\'.ucfirst($queueName).'Queue';
        if (class_exists($newname)) {
            return $newname;
        }

        throw new \InvalidArgumentException('Unable to find queue: '.$queueName);
    }

    public function setDependencies(Container $di)
    {
        $this->_di = $di;
        $this->_logger = $di['logger'];
    }

    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Push message to the Queue.
     */
    public function pushMessage(Message $message)
    {
        $this->getLogger()->debug(sprintf('Queue "%s" Pushing message id "%s"', get_class($this), $message->getId()));

        try {
            return $this->_doPushMessage($message);
        } catch (\Exception $e) {
            $this->getLogger()->error(sprintf('Unable to push message into queue "%s". Message was: %s', get_class($this), $e->getMessage()));

            return [];
        }
    }

    /**
     * Register worker for a topic.
     *
     * @param string $topic
     */
    public function registerWorker($topic, Worker $worker)
    {
        $this->getLogger()->info(sprintf('Queue "%s" Registering new worker "%s" on topic "%s"', get_class($this), get_class($worker), $topic));

        array_push($this->_workers, $worker);

        try {
            return $this->_doRegisterWorker($topic, $worker);
        } catch (\Exception $e) {
            $this->getLogger()->error(sprintf('Unable to register worker on queue "%s". Message was: %s', get_class($this), $e->getMessage()));
        }
    }

    public function runWorkers()
    {
        try {
            $this->getLogger()->debug(sprintf('Queue "%s" Launching workers', get_class($this)));

            return $this->_doRunWorkers();
        } catch (\Exception $e) {
            $this->getLogger()->error(sprintf('Unable to run workers on queue "%s". Message was: %s', get_class($this), $e->getMessage()));
        }
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    abstract protected function _doPushMessage(Message $message);

    abstract protected function _doRegisterWorker($topic, Worker $worker);

    abstract protected function _doRunWorkers();

    protected function workCycleStart()
    {
    }

    protected function workCycleEnd()
    {
        if ($this->_options['max_work_cycles'] > 0) {
            --$this->_options['max_work_cycles'];
        }
        $this->getLogger()->debug(sprintf('Queue "%s" work cycle tick', get_class($this)));
        $this->getLogger()->debug(sprintf('Queue "%s" memory: %.2f', get_class($this), memory_get_usage(true)));
    }

    protected function isCandidateForGracefulKill()
    {
        if (0 === $this->_options['max_work_cycles']) {
            $this->getLogger()->debug(sprintf('Queue "%s" maxed out its allowed work cycles', get_class($this)));

            return true;
        }
        if ($this->_options['max_process_memory'] > 0 && memory_get_usage(true) >= $this->_options['max_process_memory']) {
            $this->getLogger()->debug(sprintf('Queue "%s" maxed out its allowed memory usage', get_class($this)), ['memory' => memory_get_usage(true)]);

            return true;
        }

        return false;
    }
}
