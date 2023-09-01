<?php

namespace Sweatshop\Queue;

use Sweatshop\Message\Message;
use Sweatshop\Queue\Exceptions\QueueServerUnavailableException;
use Sweatshop\Worker\Worker;

class GearmanQueue extends Queue
{
    protected $_gmclient;
    protected $_gmworker;
    protected $_workersQueue = [];
    protected $_workersStack = [];

    public function __construct($sweatshop, $options = [])
    {
        parent::__construct($sweatshop, $options);
        $this->_options = array_merge([
            'host' => 'localhost',
            'port' => '4730',
        ], $this->_options, $options);
    }

    public function _doRunWorkers()
    {
        $this->_gmclient = null;
        $this->_gmworker = null;

        $this->_doRegisterCallbacks();

        if ($this->isCandidateForGracefulKill()) {
            $this->getLogger()->error(sprintf('Queue "%s" is exiting without performing any work. Please check configurations.', get_class($this)));

            return;
        }

        while (!$this->isCandidateForGracefulKill() && $this->worker()->work()) {
            $this->workCycleEnd();
        }
    }

    public function _executeWorkerBackground(\GearmanJob $job)
    {
        $workloadStr = $job->workload();
        $worker_topic = $job->functionName();
        list($topic, $workerClass) = explode(':', $worker_topic);

        $workload = unserialize($workloadStr);

        $worker = (!empty($this->_workersStack[$worker_topic])) ? $this->_workersStack[$worker_topic] : null;
        if ($worker instanceof Worker) {
            $results = $worker->execute($workload);
        } else {
            $results = [];
            // TODO: Log error
        }

        return serialize($results);
    }

    protected function _doPushMessage(Message $message)
    {
        $results = [];
        $res = $this->client()->doBackground($message->getTopic(), serialize($message));

        return [];
    }

    /* (non-PHPdoc)
     * @see \Sweatshop\Queue\Queue::_doRegisterWorker()
     * Here we're just registering those workers internally.
     * Actual callbacks will be registered later, right before processing work
     */
    protected function _doRegisterWorker($topic, Worker $worker)
    {
        $workerClass = get_class($worker);
        $worker_topic = "{$topic}:{$workerClass}";
        if (empty($this->_workersStack[$workerClass])) {
            $this->_workersStack[$workerClass] = [];
        }
        $this->_workersStack[$worker_topic] = $worker;

        $message = new Message(GearmanExchangeQueue::TOPIC_ADD_WORKER, [
            'topics' => [
                $topic => $worker_topic,
            ],
        ]);
        $this->_doPushMessage($message);
    }

    /**
     * Register the callbacks with Gearman server.
     */
    protected function _doRegisterCallbacks()
    {
        foreach (array_keys($this->_workersStack) as $worker_topic) {
            // Register a function on gearnam for every worker
            $this->worker()->addFunction($worker_topic, [$this, '_executeWorkerBackground']);
        }
    }

    protected function client()
    {
        if (!$this->_gmclient) {
            $this->_gmclient = new \GearmanClient();
            $this->_gmclient->addServer($this->_options['host'], $this->_options['port']);
            $res = @$this->_gmclient->ping('ping');
            if (!$res) {
                $this->getLogger()->error(sprintf('Queue %s Failed to connect to a Gearman server', get_class($this)));

                throw new QueueServerUnavailableException('Unable to connect to a Gearman server');
            }
        }

        return $this->_gmclient;
    }

    /**
     * @return \GearmanWorker
     */
    protected function worker()
    {
        if (!$this->_gmworker) {
            // Ugly way to check if server available
            if (!$this->client()) {
                $this->getLogger()->error(sprintf('Queue %s Failed to connect to a Gearman server', get_class($this)));

                throw new QueueServerUnavailableException('Unable to connect to a Gearman server');
            }
            $this->_gmworker = new \GearmanWorker();
            $this->_gmworker->addServer($this->_options['host'], $this->_options['port']);

            // check if server available
        }

        return $this->_gmworker;
    }
}
