<?php

namespace Sweatshop\Queue;

use Sweatshop\Message\Message;
use Sweatshop\Worker\Worker;

class GearmanExchangeQueue extends GearmanQueue
{
    public const TOPIC_ALL = 'sweatshop/gearman/exchange/*';
    public const TOPIC_ADD_WORKER = 'sweatshop/gearman/exchange/add_worker';

    public static $directTopics = [
        self::TOPIC_ALL,
        self::TOPIC_ADD_WORKER,
    ];

    protected $_topic_exchange = [];

    public function __construct($sweatshop, $options = [])
    {
        parent::__construct($sweatshop, $options);
    }

    public function _doPushMessage(Message $message)
    {
        // Route all messages to the exchange topic
        $res = $this->client()->doBackground(self::TOPIC_ALL, serialize($message));

        return [];
    }

    public function _doRunWorkers()
    {
        $this->_setupExchange();
        if ($this->isCandidateForGracefulKill()) {
            $this->getLogger()->error(sprintf('Queue "%s" is exiting without performing any work. Please check configurations.', get_class($this)));

            return;
        }

        while (!$this->isCandidateForGracefulKill() && $this->worker()->work()) {
            $this->workCycleEnd();
        }
    }

    /**
     * Declate an new topic route.
     */
    public function _callbackAddExchangeTopics(\GearmanJob $job)
    {
        $workloadStr = $job->workload();
        $message = unserialize($workloadStr);
        $params = $message->getParams();
        foreach ($params['topics'] as $from => $to) {
            $this->addExchangeTopic($from, $to);
        }
    }

    public function _callbackDoExchange(\GearmanJob $job)
    {
        $workloadStr = $job->workload();
        $message = unserialize($workloadStr);
        $params = $message->getParams();

        $this->dispatchToExchangeTopics($message);
    }

    protected function _doRegisterWorker($topic, Worker $worker)
    {
    }

    private function addExchangeTopic($from, $to)
    {
        if (empty($this->_topic_exchange[$from])) {
            $this->_topic_exchange[$from] = [];
        }
        if (!in_array($to, $this->_topic_exchange[$from])) {
            $this->getLogger()->debug('Gearman Exchange: Adding exchange topic', [
                'from' => $from,
                'to' => $to,
            ]);
            array_push($this->_topic_exchange[$from], $to);
        }
    }

    private function dispatchToExchangeTopics(Message $message)
    {
        $sourceTopic = $message->getTopic();
        if (!empty($this->_topic_exchange[$sourceTopic])) {
            foreach ($this->_topic_exchange[$sourceTopic] as $new_topic) {
                $this->getLogger()->debug('Gearman Exchange: Routing message to ', [
                    'from' => $sourceTopic,
                    'to' => $new_topic,
                ]);
                $res = $this->client()->doBackground($new_topic, serialize($message));
            }
        }
    }

    private function _setupExchange()
    {
        $this->worker()->addFunction(self::TOPIC_ADD_WORKER, [$this, '_callbackAddExchangeTopics']);
        $this->worker()->addFunction(self::TOPIC_ALL, [$this, '_callbackDoExchange']);
    }
}
