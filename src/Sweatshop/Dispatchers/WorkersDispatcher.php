<?php

namespace Sweatshop\Dispatchers;

use Monolog\Logger;
use Pimple\Container;
use Sweatshop\Queue\Processes\ProcessGroup;
use Sweatshop\Queue\Processes\ProcessWrapper;
use Sweatshop\Sweatshop;

class WorkersDispatcher
{
    protected $_di;
    protected $_childPIDs = [];
    protected $_processes = [];
    protected $_processGroups = [];

    public function __construct(Sweatshop $sweatshop)
    {
        $this->setDependencies($sweatshop->getDependencies());
    }

    public function registerWorker($queue_class, $topics = [], $worker = null, $options = [])
    {
        if (!is_array($topics)) {
            $topics = [$topics];
        }

        $processGroup = new ProcessGroup($this->_di['sweatshop'], $queue_class, $worker, $topics, $options);
        array_push($this->_processGroups, $processGroup);

        /*
        $processArr = array(
            'queue' 	=> $queue_class,
            'topics'	=> $topics,
            'worker'	=> $worker,
            'options' 	=> array_merge($this->_defaultOptions,$options)
        );
        array_push($this->_processes, $processArr);
        */
    }

    public function runWorkers()
    {
        declare(ticks=1);

        // @var $processGroup ProcessGroup
        foreach ($this->_processGroups as $processGroup) {
            $processGroup->syncProcesses();
        }

        pcntl_signal(SIGINT, [$this, 'signal_handlers'], false);
        pcntl_signal(SIGTERM, [$this, 'signal_handlers'], false);

        while (($pid = pcntl_wait($status)) != -1) {
            foreach ($this->_processGroups as $processGroup) {
                $processGroup->notifyDeadProcess($pid, $status);
            }
        }
    }

    public function signal_handlers($signo)
    {
        $this->getLogger()->debug(sprintf('Sweatshop got signal %d', $signo));
        foreach ($this->_processGroups as $processGroup) {
            $processGroup->killAll();
        }

        exit;
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
        $this->_di['logger'] = $logger;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->_di['logger'];
    }

    protected function forkAndRun(ProcessWrapper $processWrapper)
    {
        $pid = $processWrapper->fork();
        if (0 == $pid) {
            // I'm the child!
            // Run the workers
            $processWrapper->runWorkers();

            // Basically if we're here, this means that the processes terminated!

            exit(1);
        }
        // We're the parent process!
        // Keep the process wrapper with PID
        $this->_childPIDs[$pid] = $processWrapper;
    }
}
